<?php

namespace Modules\FormBuilder\Tests\Feature\Seeders;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\FormBuilder\Database\Seeders\OracleFormsSeeder;
use Modules\FormBuilder\Livewire\Public\FillTicketForm;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\FormField;
use Modules\FormBuilder\Models\TicketFormLink;
use Tests\TestCase;

/**
 * Fase 5, punto 3 (reconceptualizado) — las 4 plantillas de captura de datos
 * para procesos administrativos de Oracle EBS + datos de apoyo para SIC. Ver
 * Modules\FormBuilder\Database\Seeders\OracleFormsSeeder y
 * docs/gestionti-progreso.md (Fase 5) para el detalle completo.
 */
class OracleFormsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_four_forms_with_their_fields(): void
    {
        (new OracleFormsSeeder)->run();

        $this->assertSame(4, Form::count());

        // --- 1. Datos para SIC ---
        $datosSic = Form::where('slug', 'datos-para-sic')->firstOrFail();
        $this->assertSame('published', $datosSic->status);
        $this->assertNull($datosSic->pdf_template);
        $this->assertSame(9, $datosSic->fields()->count());
        $this->assertTrue($datosSic->fields->every(fn (FormField $f) => $f->is_required));

        // --- 2. Alta Usuarios Oracle ---
        $altaUsuarios = Form::where('slug', 'alta-usuarios-oracle')->firstOrFail();
        $this->assertNull($altaUsuarios->pdf_template);
        $this->assertSame(1, $altaUsuarios->fields()->count());

        $repeater = $altaUsuarios->fields->first();
        $this->assertSame('repeater', $repeater->type);
        $this->assertSame(31, $repeater->children()->count());

        $sexo = $repeater->children->firstWhere('field_key', 'sexo');
        $this->assertSame('single_choice', $sexo->type);
        $this->assertCount(2, $sexo->options);

        $estadoCivil = $repeater->children->firstWhere('field_key', 'estado_civil');
        $this->assertCount(5, $estadoCivil->options);

        $tipoTelefono = $repeater->children->firstWhere('field_key', 'tipo_telefono');
        $this->assertSame('single_choice', $tipoTelefono->type);
        $this->assertCount(3, $tipoTelefono->options);
        $this->assertSame(
            ['oficina', 'celular', 'casa'],
            collect($tipoTelefono->options)->pluck('value')->all()
        );

        $this->assertTrue(
            $repeater->children->every(fn (FormField $c) => in_array($c->type, FormField::REPEATER_CHILD_TYPES, true))
        );

        // --- 3. Solicitud Responsabilidades Oracle ---
        $responsabilidades = Form::where('slug', 'solicitud-responsabilidades-oracle')->firstOrFail();
        $this->assertSame('oracle_responsabilidades', $responsabilidades->pdf_template);
        $this->assertSame(9, $responsabilidades->fields()->count());

        $responsabilidadesRepeater = $responsabilidades->fields->firstWhere('type', 'repeater');
        $this->assertNotNull($responsabilidadesRepeater);
        $this->assertSame(1, $responsabilidadesRepeater->children()->count());
        $this->assertSame('Responsabilidad', $responsabilidadesRepeater->children->first()->label);

        $esModificacion = $responsabilidades->fields->firstWhere('field_key', 'es_modificacion_usuario_existente');
        $this->assertSame(
            ['si', 'no'],
            collect($esModificacion->options)->pluck('value')->all()
        );

        // --- 4. Solicitud de Flujo de Aprobación Oracle ---
        $flujoAprobacion = Form::where('slug', 'solicitud-flujo-aprobacion-oracle')->firstOrFail();
        $this->assertSame('oracle_flujo_aprobacion', $flujoAprobacion->pdf_template);
        $this->assertSame(18, $flujoAprobacion->fields()->count());
        $this->assertSame(3, $flujoAprobacion->fields->where('type', 'label')->count());
        $this->assertTrue($flujoAprobacion->fields->where('type', 'label')->every(fn (FormField $f) => ! $f->is_required));

        // Regresión real encontrada en revisión manual: un `label` declarado
        // como arrow function (`fn`) capturaba el contador de `order` por
        // valor en vez de por referencia, así que los 3 encabezados de
        // sección siempre quedaban con `order = 0` sin correr el contador
        // real — las secciones aparecían todas al principio del formulario
        // en vez de intercaladas antes de sus campos. Verifica el orden
        // exacto, no solo los conteos.
        $ordenEsperado = [
            'Datos del Solicitante', 'Nombre Completo', 'Número de Empleado', 'Empleadora',
            'Área o Departamento', 'Puesto', 'Localidad', 'Teléfono', 'Correo Electrónico (Contacto)',
            'Jefe Inmediato', 'Director Operativo', 'Datos de Aprobaciones', 'Circuito de Aprobación',
            'Autorizadores', 'Autorizador 1', 'Autorizador 2', 'Autorizador 3', 'Autorizador 4',
        ];
        $this->assertSame(
            $ordenEsperado,
            $flujoAprobacion->fields()->orderBy('order')->pluck('label')->all()
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        (new OracleFormsSeeder)->run();
        (new OracleFormsSeeder)->run();

        $this->assertSame(4, Form::count());
        $this->assertSame(9, Form::where('slug', 'datos-para-sic')->firstOrFail()->fields()->count());

        $altaUsuarios = Form::where('slug', 'alta-usuarios-oracle')->firstOrFail();
        $this->assertSame(1, $altaUsuarios->fields()->count());
        $this->assertSame(31, $altaUsuarios->fields->first()->children()->count());

        $responsabilidades = Form::where('slug', 'solicitud-responsabilidades-oracle')->firstOrFail();
        $this->assertSame(9, $responsabilidades->fields()->count());

        $flujoAprobacion = Form::where('slug', 'solicitud-flujo-aprobacion-oracle')->firstOrFail();
        $this->assertSame(18, $flujoAprobacion->fields()->count());

        $this->assertSame(
            9 + (1 + 31) + (9 + 1) + 18,
            FormField::count(),
            'Correr el seeder 2 veces no debe duplicar ningún FormField.'
        );
    }

    /**
     * dompdf writes its content streams FlateDecode-compressed — a plain
     * substring search on the raw PDF bytes never finds captured text.
     * Inflate every `stream ... endstream` block (ignoring any that aren't
     * valid zlib, e.g. the embedded font program objects) and search the
     * decoded text there instead. Good enough to confirm a captured value
     * made it into the rendered PDF without a full PDF-parsing dependency.
     */
    protected function assertPdfContainsText(string $pdf, string $needle): void
    {
        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $matches);

        $found = false;

        foreach ($matches[1] as $rawStream) {
            $decoded = @zlib_decode($rawStream);

            if ($decoded !== false && str_contains($decoded, $needle)) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "El PDF no contiene el texto esperado: {$needle}");
    }

    protected function createPendingLink(Form $form): array
    {
        [$rawToken, $hash] = TicketFormLink::generateToken();

        $link = TicketFormLink::create([
            'form_id' => $form->id,
            'ticket_number' => 'INC-ORACLE-1',
            'recipient_email' => 'destinatario@example.com',
            'token_hash' => $hash,
            'expires_at' => now()->addDay(),
        ]);

        return [$rawToken, $link];
    }

    public function test_oracle_responsabilidades_pdf_export_contains_captured_data(): void
    {
        (new OracleFormsSeeder)->run();
        $form = Form::where('slug', 'solicitud-responsabilidades-oracle')->firstOrFail();
        [$rawToken] = $this->createPendingLink($form);

        $component = Livewire::test(FillTicketForm::class, ['token' => $rawToken])
            ->set('confirmedEmail', 'destinatario@example.com')
            ->call('verifyEmail')
            ->assertSet('verified', true);

        $component
            ->set('answers.nombre', 'Juan')
            ->set('answers.apellidos', 'Perez')
            ->set('answers.correo_corporativo', 'juan.perez@example.com')
            ->set('answers.es_modificacion_usuario_existente', 'si')
            ->set('answers.tipo_modificacion', 'responsabilidad')
            ->set('answers.alta_baja_cambio', 'alta')
            ->set('answers.empresa', 'Kosmos')
            ->set('answers.area', 'TI')
            ->set('repeaterDrafts.responsabilidades.responsabilidad', 'MOL_RE_P03_JEFE_DE_REMISIONES')
            ->call('saveRepeaterRow', 'responsabilidades')
            ->call('submit')
            ->assertSet('status', 'used');

        $component->call('exportPdf');

        $pdfContent = base64_decode(data_get($component->effects, 'download.content'));

        $this->assertNotEmpty($pdfContent);
        $this->assertPdfContainsText($pdfContent, 'MOL_RE_P03_JEFE_DE_REMISIONES');
    }

    public function test_oracle_flujo_aprobacion_pdf_export_contains_captured_data(): void
    {
        (new OracleFormsSeeder)->run();
        $form = Form::where('slug', 'solicitud-flujo-aprobacion-oracle')->firstOrFail();
        [$rawToken] = $this->createPendingLink($form);

        $component = Livewire::test(FillTicketForm::class, ['token' => $rawToken])
            ->set('confirmedEmail', 'destinatario@example.com')
            ->call('verifyEmail')
            ->assertSet('verified', true);

        $component
            ->set('answers.nombre_completo', 'Juan Perez Test')
            ->set('answers.numero_empleado', 'EMP0001')
            ->set('answers.empleadora', 'Kosmos')
            ->set('answers.area_departamento', 'TI')
            ->set('answers.puesto', 'Analista')
            ->set('answers.localidad', 'CDMX')
            ->set('answers.telefono', '5555555555')
            ->set('answers.correo_contacto', 'juan.perez@example.com')
            ->set('answers.jefe_inmediato', 'Maria Lopez')
            ->set('answers.director_operativo', 'Carlos Ruiz')
            ->set('answers.circuito_aprobacion', 'Comprobacion de gastos')
            ->set('answers.autorizador_1', 'AUTORIZADOR UNO')
            ->set('answers.autorizador_2', 'Autorizador Dos')
            ->set('answers.autorizador_3', 'Autorizador Tres')
            ->set('answers.autorizador_4', 'Autorizador Cuatro')
            ->call('submit')
            ->assertSet('status', 'used');

        $component->call('exportPdf');

        $pdfContent = base64_decode(data_get($component->effects, 'download.content'));

        $this->assertNotEmpty($pdfContent);
        $this->assertPdfContainsText($pdfContent, 'AUTORIZADOR UNO');
    }
}
