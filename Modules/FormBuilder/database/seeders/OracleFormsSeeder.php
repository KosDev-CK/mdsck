<?php

namespace Modules\FormBuilder\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\FormBuilder\Models\Form;
use Modules\FormBuilder\Models\FormField;

/**
 * Fase 5, punto 3 del prompt original de GestionTI ("Módulo de
 * Formularios"), reconceptualizado: estas 4 plantillas ya NO son "el
 * solicitante llena la SIC" — son formularios de captura de datos
 * independientes (procesos administrativos de Oracle EBS + datos de apoyo
 * para que el técnico arme una SIC), 100% dentro de FormBuilder, sin ninguna
 * integración con Modules\GestionTI. Ver docs/gestionti-progreso.md (Fase 5)
 * para el detalle completo de la decisión y las asunciones marcadas.
 *
 * Idempotente: cada Form se localiza por `slug` (updateOrCreate) y cada
 * FormField por el par (form_id, field_key) — correr este seeder varias
 * veces actualiza los datos existentes en vez de duplicarlos.
 */
class OracleFormsSeeder extends Seeder
{
    private ?int $createdBy = null;

    public function run(): void
    {
        $this->createdBy = User::where('email', config('mds.admin_email'))->value('id');

        $this->seedDatosParaSic();
        $this->seedAltaUsuariosOracle();
        $this->seedSolicitudResponsabilidadesOracle();
        $this->seedSolicitudFlujoAprobacionOracle();
    }

    /**
     * "Datos para SIC" — campos planos de apoyo para que el técnico arme una
     * Solicitud Interna de Compra (en Oracle EBS o en la pantalla local de
     * GestionTI), sin automatizar ningún cruce con ese módulo. Sin PDF propio
     * (usa el fallback genérico field:value de FormBuilder si se descarga).
     */
    private function seedDatosParaSic(): void
    {
        $form = $this->upsertForm(
            name: 'Datos para SIC',
            slug: 'datos-para-sic',
            description: 'Datos de apoyo para que el técnico capture una Solicitud Interna de Compra (Oracle EBS o pantalla local).',
            pdfTemplate: null,
        );

        $fields = [
            ['short_text', 'Nombre completo de quién solicita', 'nombre_solicitante'],
            ['short_text', 'Nombre completo de la persona a quién se le va a asignar el bien o servicio', 'nombre_persona_asignada'],
            ['long_text', 'Descripción de lo solicitado', 'descripcion_solicitado'],
            ['long_text', 'Justificación y/o Observaciones', 'justificacion_observaciones'],
            ['short_text', 'Área o unidad operativa', 'area_unidad_operativa'],
            ['short_text', 'PEC', 'pec'],
            ['short_text', 'Nombre del Director de Área', 'director_area'],
            ['short_text', 'Nombre del Director Ejecutivo', 'director_ejecutivo'],
            ['short_text', 'Localidad de entrega', 'localidad_entrega'],
        ];

        foreach ($fields as $order => [$type, $label, $key]) {
            $this->upsertField($form, null, $type, $label, $key, $order);
        }
    }

    /**
     * "Alta Usuarios Oracle" — un solo campo `repeater` (una fila por
     * empleado a dar de alta), sin PDF propio (confirmado por el usuario).
     *
     * ASUNCIÓN A REVISAR: las opciones de "Tipo de teléfono" (Oficina/
     * Celular/Casa) no vienen confirmadas por el usuario — la lista original
     * del Excel se perdió (la validación de datos apuntaba a un rango de
     * celdas ya borrado). Ajustar aquí si el usuario confirma otra lista.
     */
    private function seedAltaUsuariosOracle(): void
    {
        $form = $this->upsertForm(
            name: 'Alta Usuarios Oracle',
            slug: 'alta-usuarios-oracle',
            description: 'Captura de datos para el alta de uno o más usuarios en Oracle EBS.',
            pdfTemplate: null,
        );

        $repeater = $this->upsertField(
            $form, null, 'repeater', 'Empleados a dar de alta', 'empleados_alta', 0,
        );

        $sexo = [
            ['value' => 'masculino', 'label' => 'Masculino'],
            ['value' => 'femenino', 'label' => 'Femenino'],
        ];

        $estadoCivil = [
            ['value' => 'soltero', 'label' => 'Soltero(a)'],
            ['value' => 'casado', 'label' => 'Casado(a)'],
            ['value' => 'divorciado', 'label' => 'Divorciado(a)'],
            ['value' => 'viudo', 'label' => 'Viudo(a)'],
            ['value' => 'union_libre', 'label' => 'Unión libre'],
        ];

        // Asunción sin confirmar por el usuario — ver docblock del método.
        $tipoTelefono = [
            ['value' => 'oficina', 'label' => 'Oficina'],
            ['value' => 'celular', 'label' => 'Celular'],
            ['value' => 'casa', 'label' => 'Casa'],
        ];

        $children = [
            ['short_text', 'Apellido Paterno', 'apellido_paterno', null],
            ['short_text', 'Apellido Materno', 'apellido_materno', null],
            ['short_text', 'Nombre(s)', 'nombres', null],
            ['single_choice', 'Sexo', 'sexo', $sexo],
            ['short_text', 'CURP', 'curp', null],
            ['date', 'Fecha de Nacimiento', 'fecha_nacimiento', null],
            ['short_text', 'Ciudad de Nacimiento', 'ciudad_nacimiento', null],
            ['short_text', 'Región de Nacimiento', 'region_nacimiento', null],
            ['short_text', 'País de Nacimiento', 'pais_nacimiento', null],
            ['single_choice', 'Estado Civil', 'estado_civil', $estadoCivil],
            ['short_text', 'Nacionalidad', 'nacionalidad', null],
            ['email', 'Correo', 'correo', null],
            ['short_text', 'RFC', 'rfc', null],
            ['short_text', 'ID Seguridad Social', 'id_seguridad_social', null],
            ['short_text', 'Calle', 'calle', null],
            ['short_text', 'Colonia', 'colonia', null],
            ['short_text', 'Delegación', 'delegacion', null],
            ['short_text', 'CP', 'cp', null],
            ['short_text', 'Ciudad', 'ciudad', null],
            ['short_text', 'Estado', 'estado', null],
            ['short_text', 'País', 'pais', null],
            ['single_choice', 'Tipo de teléfono', 'tipo_telefono', $tipoTelefono],
            ['short_text', 'Teléfono', 'telefono', null],
            ['short_text', 'Libro Contable', 'libro_contable', null],
            ['short_text', 'Cuenta de Gastos por defecto', 'cuenta_gastos_default', null],
            ['short_text', 'Puesto', 'puesto', null],
            ['short_text', 'Almacén', 'almacen', null],
            ['short_text', 'Área', 'area', null],
            ['short_text', 'IP Impresora Laser', 'ip_impresora_laser', null],
            ['short_text', 'Marca', 'marca', null],
            ['short_text', 'IP Impresora Zebra', 'ip_impresora_zebra', null],
        ];

        foreach ($children as $order => [$type, $label, $key, $options]) {
            $this->upsertField($form, $repeater, $type, $label, $key, $order, options: $options);
        }
    }

    /**
     * "Solicitud Responsabilidades Oracle" — con PDF propio
     * (`oracle_responsabilidades`, confirmado por el usuario).
     */
    private function seedSolicitudResponsabilidadesOracle(): void
    {
        $form = $this->upsertForm(
            name: 'Solicitud Responsabilidades Oracle',
            slug: 'solicitud-responsabilidades-oracle',
            description: 'Alta, baja o cambio de responsabilidades/accesos de un usuario en Oracle EBS.',
            pdfTemplate: 'oracle_responsabilidades',
        );

        $siNo = [
            ['value' => 'si', 'label' => 'Sí'],
            ['value' => 'no', 'label' => 'No'],
        ];

        $tipoModificacion = [
            ['value' => 'reseteo_password', 'label' => 'Reseteo de Password'],
            ['value' => 'responsabilidad', 'label' => 'Responsabilidad'],
            ['value' => 'cambio_de_area', 'label' => 'Cambio de área'],
        ];

        $altaBajaCambio = [
            ['value' => 'alta', 'label' => 'Alta'],
            ['value' => 'baja', 'label' => 'Baja'],
            ['value' => 'cambio', 'label' => 'Cambio'],
        ];

        $fields = [
            ['short_text', 'Nombre', 'nombre', null],
            ['short_text', 'Apellidos', 'apellidos', null],
            ['email', 'Correo Electrónico Corporativo', 'correo_corporativo', null],
            ['single_choice', '¿Es modificación de un usuario ya existente?', 'es_modificacion_usuario_existente', $siNo],
            ['single_choice', 'Tipo de modificación', 'tipo_modificacion', $tipoModificacion],
            ['single_choice', 'Alta / Baja / Cambio', 'alta_baja_cambio', $altaBajaCambio],
            ['short_text', 'Empresa a la que pertenece el usuario', 'empresa', null],
            ['short_text', 'Área', 'area', null],
        ];

        $order = 0;
        foreach ($fields as [$type, $label, $key, $options]) {
            $this->upsertField($form, null, $type, $label, $key, $order++, options: $options);
        }

        $repeater = $this->upsertField(
            $form, null, 'repeater',
            'Funcionalidad a realizar en el sistema / Responsabilidades requeridas',
            'responsabilidades', $order,
        );

        $this->upsertField($form, $repeater, 'short_text', 'Responsabilidad', 'responsabilidad', 0);
    }

    /**
     * "Solicitud de Flujo de Aprobación Oracle" — con PDF propio
     * (`oracle_flujo_aprobacion`). Campos `label` como encabezado de sección,
     * mismo patrón ya usado por FormBuilder para separar secciones.
     */
    private function seedSolicitudFlujoAprobacionOracle(): void
    {
        $form = $this->upsertForm(
            name: 'Solicitud de Flujo de Aprobación Oracle',
            slug: 'solicitud-flujo-aprobacion-oracle',
            description: 'Alta de un flujo de aprobación (circuito de autorizadores) en Oracle EBS.',
            pdfTemplate: 'oracle_flujo_aprobacion',
        );

        $order = 0;

        // OJO: debe ser una closure normal con `use (&$order)`, no una arrow
        // function (`fn`) — las arrow functions capturan `$order` POR VALOR
        // al momento de declararse, así que su `$order++` nunca habría tocado
        // el contador real compartido con `$field()` (bug real encontrado al
        // revisar el resultado: los 3 `label` quedaban con `order = 0` cada
        // vez, sin correrle el contador a los campos que le seguían).
        $label = function (string $label, string $key) use ($form, &$order) {
            $this->upsertField($form, null, 'label', $label, $key, $order++, required: false);
        };
        $field = function (string $type, string $label, string $key, ?string $helpText = null) use ($form, &$order) {
            $this->upsertField($form, null, $type, $label, $key, $order++, helpText: $helpText);
        };

        $label('Datos del Solicitante', 'datos_solicitante_label');
        $field('short_text', 'Nombre Completo', 'nombre_completo');
        $field('short_text', 'Número de Empleado', 'numero_empleado');
        $field('short_text', 'Empleadora', 'empleadora');
        $field('short_text', 'Área o Departamento', 'area_departamento');
        $field('short_text', 'Puesto', 'puesto');
        $field('short_text', 'Localidad', 'localidad');
        $field('short_text', 'Teléfono', 'telefono');
        $field('email', 'Correo Electrónico (Contacto)', 'correo_contacto');
        $field('short_text', 'Jefe Inmediato', 'jefe_inmediato');
        $field('short_text', 'Director Operativo', 'director_operativo');

        $label('Datos de Aprobaciones', 'datos_aprobaciones_label');
        $field('long_text', 'Circuito de Aprobación', 'circuito_aprobacion', 'Ejemplo: comprobación de gastos, uniformes, papelería');

        $label('Autorizadores', 'autorizadores_label');
        $field('short_text', 'Autorizador 1', 'autorizador_1');
        $field('short_text', 'Autorizador 2', 'autorizador_2');
        $field('short_text', 'Autorizador 3', 'autorizador_3');
        $field('short_text', 'Autorizador 4', 'autorizador_4');
    }

    private function upsertForm(string $name, string $slug, ?string $description, ?string $pdfTemplate): Form
    {
        return Form::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => $description,
                'status' => 'published',
                'pdf_template' => $pdfTemplate,
                'created_by' => $this->createdBy,
            ]
        );
    }

    private function upsertField(
        Form $form,
        ?FormField $parent,
        string $type,
        string $label,
        string $fieldKey,
        int $order,
        bool $required = true,
        ?array $options = null,
        ?string $helpText = null,
    ): FormField {
        return FormField::updateOrCreate(
            ['form_id' => $form->id, 'field_key' => $fieldKey],
            [
                'parent_field_id' => $parent?->id,
                'type' => $type,
                'label' => $label,
                'help_text' => $helpText,
                'options' => $options,
                'is_required' => $required,
                'order' => $order,
            ]
        );
    }
}
