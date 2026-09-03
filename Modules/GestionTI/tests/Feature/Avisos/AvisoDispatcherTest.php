<?php

namespace Modules\GestionTI\Tests\Feature\Avisos;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\GestionTI\Models\AvisoEnviado;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Marca;
use Modules\GestionTI\Models\TipoAviso;
use Modules\GestionTI\Models\Validador;
use Modules\GestionTI\Notifications\AvisoNotification;
use Modules\GestionTI\Support\Avisos\AvisoDispatcher;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AvisoDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private function entidad(): Marca
    {
        return Marca::create(['nombre' => 'Entidad de prueba', 'activo' => true]);
    }

    private function empleado(string $nombre, ?string $correo): Empleado
    {
        return Empleado::create([
            'numero_empleado' => 'EMP-'.Str::random(8),
            'nombre' => $nombre,
            'correo' => $correo,
            'activo' => true,
        ]);
    }

    private function tipoAviso(string $evento, string $plantilla = 'Mensaje de prueba', bool $activo = true): TipoAviso
    {
        return TipoAviso::create([
            'codigo' => $evento,
            'descripcion' => 'Aviso de prueba',
            'entidad_relacionada' => 'Marca',
            'evento_disparador' => $evento,
            'plantilla_mensaje' => $plantilla,
            'activo' => $activo,
        ]);
    }

    public function test_resolves_rol_fijo_destinatario(): void
    {
        Notification::fake();

        $tipoAviso = $this->tipoAviso('EVENTO_ROL');
        $tipoAviso->destinatarios()->create([
            'tipo_destinatario' => 'rol_fijo',
            'rol_nombre' => 'Compras',
        ]);

        $role = Role::findOrCreate('Compras', 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        app(AvisoDispatcher::class)->disparar('EVENTO_ROL', $this->entidad());

        Notification::assertSentTo($user, AvisoNotification::class);
        $this->assertSame(2, AvisoEnviado::where('destinatario_user_id', $user->id)->count());
    }

    public function test_resolves_validador_especifico_destinatario(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $validador = Validador::create(['nombre' => 'Juan Pérez', 'activo' => true, 'user_id' => $user->id]);

        $tipoAviso = $this->tipoAviso('EVENTO_VALIDADOR');
        $tipoAviso->destinatarios()->create([
            'tipo_destinatario' => 'validador_especifico',
            'validador_id' => $validador->id,
        ]);

        app(AvisoDispatcher::class)->disparar('EVENTO_VALIDADOR', $this->entidad());

        Notification::assertSentTo($user, AvisoNotification::class);
    }

    public function test_resolves_dinamico_solicitante_destinatario(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'solicitante@example.com']);
        $empleado = $this->empleado('Solicitante', 'solicitante@example.com');

        $tipoAviso = $this->tipoAviso('EVENTO_SOLICITANTE');
        $tipoAviso->destinatarios()->create(['tipo_destinatario' => 'dinamico_solicitante']);

        app(AvisoDispatcher::class)->disparar('EVENTO_SOLICITANTE', $this->entidad(), solicitante: $empleado);

        Notification::assertSentTo($user, AvisoNotification::class);
    }

    public function test_resolves_dinamico_responsable_destinatario(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'responsable@example.com']);
        $empleado = $this->empleado('Responsable', 'responsable@example.com');

        $tipoAviso = $this->tipoAviso('EVENTO_RESPONSABLE');
        $tipoAviso->destinatarios()->create(['tipo_destinatario' => 'dinamico_responsable']);

        app(AvisoDispatcher::class)->disparar('EVENTO_RESPONSABLE', $this->entidad(), responsable: $empleado);

        Notification::assertSentTo($user, AvisoNotification::class);
    }

    public function test_dinamico_solicitante_without_matching_user_does_not_throw_and_creates_nothing(): void
    {
        Notification::fake();

        $empleado = $this->empleado('Sin cuenta', 'sin-cuenta@example.com');

        $tipoAviso = $this->tipoAviso('EVENTO_SIN_CUENTA');
        $tipoAviso->destinatarios()->create(['tipo_destinatario' => 'dinamico_solicitante']);

        app(AvisoDispatcher::class)->disparar('EVENTO_SIN_CUENTA', $this->entidad(), solicitante: $empleado);

        Notification::assertNothingSent();
        $this->assertSame(0, AvisoEnviado::count());
    }

    public function test_validador_without_user_id_does_not_throw_and_creates_nothing(): void
    {
        Notification::fake();

        $validador = Validador::create(['nombre' => 'Sin cuenta', 'activo' => true]);

        $tipoAviso = $this->tipoAviso('EVENTO_VALIDADOR_SIN_USER');
        $tipoAviso->destinatarios()->create([
            'tipo_destinatario' => 'validador_especifico',
            'validador_id' => $validador->id,
        ]);

        app(AvisoDispatcher::class)->disparar('EVENTO_VALIDADOR_SIN_USER', $this->entidad());

        Notification::assertNothingSent();
        $this->assertSame(0, AvisoEnviado::count());
    }

    public function test_deduplicates_a_user_who_qualifies_by_two_rules(): void
    {
        Notification::fake();

        $role = Role::findOrCreate('Compras', 'web');
        $user = User::factory()->create(['email' => 'doble@example.com']);
        $user->assignRole($role);
        $empleado = $this->empleado('Doble regla', 'doble@example.com');

        $tipoAviso = $this->tipoAviso('EVENTO_DOBLE');
        $tipoAviso->destinatarios()->create(['tipo_destinatario' => 'rol_fijo', 'rol_nombre' => 'Compras']);
        $tipoAviso->destinatarios()->create(['tipo_destinatario' => 'dinamico_solicitante']);

        app(AvisoDispatcher::class)->disparar('EVENTO_DOBLE', $this->entidad(), solicitante: $empleado);

        Notification::assertSentToTimes($user, AvisoNotification::class, 1);
        $this->assertSame(2, AvisoEnviado::where('destinatario_user_id', $user->id)->count());
    }

    public function test_inactive_tipo_aviso_does_not_dispatch_anything(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $role = Role::findOrCreate('Compras', 'web');
        $user->assignRole($role);

        $tipoAviso = $this->tipoAviso('EVENTO_INACTIVO', activo: false);
        $tipoAviso->destinatarios()->create(['tipo_destinatario' => 'rol_fijo', 'rol_nombre' => 'Compras']);

        app(AvisoDispatcher::class)->disparar('EVENTO_INACTIVO', $this->entidad());

        Notification::assertNothingSent();
        $this->assertSame(0, AvisoEnviado::count());
    }

    public function test_unknown_evento_does_not_dispatch_anything(): void
    {
        Notification::fake();

        app(AvisoDispatcher::class)->disparar('EVENTO_QUE_NO_EXISTE', $this->entidad());

        Notification::assertNothingSent();
        $this->assertSame(0, AvisoEnviado::count());
    }

    public function test_template_substitutes_variables(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'plantilla@example.com']);
        $empleado = $this->empleado('Plantilla', 'plantilla@example.com');

        $tipoAviso = $this->tipoAviso('EVENTO_PLANTILLA', 'Hola {{nombre}}, tu folio es {{folio}}.');
        $tipoAviso->destinatarios()->create(['tipo_destinatario' => 'dinamico_solicitante']);

        app(AvisoDispatcher::class)->disparar('EVENTO_PLANTILLA', $this->entidad(), solicitante: $empleado, variables: [
            'nombre' => 'Plantilla',
            'folio' => 'SIC-1',
        ]);

        Notification::assertSentTo($user, AvisoNotification::class, function (AvisoNotification $notification) use ($user) {
            return $notification->toArray($user)['message'] === 'Hola Plantilla, tu folio es SIC-1.';
        });
    }
}
