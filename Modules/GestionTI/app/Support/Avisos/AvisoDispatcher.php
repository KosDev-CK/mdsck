<?php

namespace Modules\GestionTI\Support\Avisos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Modules\GestionTI\Models\AvisoEnviado;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\TipoAviso;
use Modules\GestionTI\Models\TipoAvisoDestinatario;
use Modules\GestionTI\Models\Validador;
use Modules\GestionTI\Notifications\AvisoNotification;
use Throwable;

/**
 * Resolutor + despachador de "Configuración de Avisos" (sección 7.15 / 4 del
 * spec original). Ver docs/gestionti-progreso.md para el diseño completo.
 *
 * Ninguna persona-catálogo de este módulo (`Empleado`, `Validador`) tiene una
 * relación directa con `App\Models\User` — limitación de diseño ya
 * documentada varias veces en el progreso del módulo. Para que un aviso
 * realmente le llegue a alguien hay que resolverlo a un `User` real:
 * - `Empleado` -> coincidencia de `correo` contra `users.email`.
 * - `Validador` -> `user_id` (manual, agregado en esta misma entrega).
 * - Rol fijo -> Spatie (`User::role(...)`).
 * Si no se puede resolver, se omite EN SILENCIO — no truena ni levanta
 * excepción, simplemente esa regla no le avisa a nadie.
 */
class AvisoDispatcher
{
    /**
     * Mapa canal de la Notification (`via()`) -> canal persistido en
     * `avisos_enviados`. `broadcast` no cuenta como canal propio — es solo
     * el mecanismo de push en vivo sobre la misma notificación `database`.
     */
    private const CANAL_MAP = [
        'mail' => AvisoEnviado::CANAL_CORREO,
        'database' => AvisoEnviado::CANAL_IN_APP,
    ];

    public function disparar(
        string $eventoDisparador,
        Model $entidad,
        ?Empleado $solicitante = null,
        ?Empleado $responsable = null,
        array $variables = []
    ): void {
        $tipoAviso = TipoAviso::where('evento_disparador', $eventoDisparador)
            ->where('activo', true)
            ->first();

        if (! $tipoAviso) {
            return;
        }

        $usuarios = $this->resolverDestinatarios($tipoAviso, $solicitante, $responsable);

        if ($usuarios->isEmpty()) {
            return;
        }

        $mensaje = $this->renderizarPlantilla($tipoAviso->plantilla_mensaje, $variables);

        foreach ($usuarios as $usuario) {
            $this->enviarAUsuario($tipoAviso, $usuario, $mensaje, $entidad);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function resolverDestinatarios(TipoAviso $tipoAviso, ?Empleado $solicitante, ?Empleado $responsable): Collection
    {
        $usuarios = collect();

        foreach ($tipoAviso->destinatarios as $destinatario) {
            $usuarios = $usuarios->merge(match ($destinatario->tipo_destinatario) {
                TipoAvisoDestinatario::TIPO_ROL_FIJO => $this->resolverPorRol($destinatario),
                TipoAvisoDestinatario::TIPO_VALIDADOR_ESPECIFICO => $this->resolverPorValidador($destinatario),
                TipoAvisoDestinatario::TIPO_DINAMICO_SOLICITANTE => $this->resolverPorEmpleado($solicitante),
                TipoAvisoDestinatario::TIPO_DINAMICO_RESPONSABLE => $this->resolverPorEmpleado($responsable),
                default => collect(),
            });
        }

        return $usuarios->unique('id')->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolverPorRol(TipoAvisoDestinatario $destinatario): Collection
    {
        if (! $destinatario->rol_nombre) {
            return collect();
        }

        return User::role($destinatario->rol_nombre)->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolverPorValidador(TipoAvisoDestinatario $destinatario): Collection
    {
        $validador = $destinatario->validador_id ? Validador::find($destinatario->validador_id) : null;

        if (! $validador || ! $validador->user_id) {
            return collect();
        }

        return collect([$validador->user])->filter();
    }

    /**
     * `Empleado` no tiene relación real con `App\Models\User` — se resuelve
     * por coincidencia de correo. Si no hay cuenta con ese email, se omite
     * en silencio (no hay forma de avisarle por este sistema).
     *
     * @return Collection<int, User>
     */
    private function resolverPorEmpleado(?Empleado $empleado): Collection
    {
        if (! $empleado || ! $empleado->correo) {
            return collect();
        }

        $usuario = User::where('email', $empleado->correo)->first();

        return $usuario ? collect([$usuario]) : collect();
    }

    private function renderizarPlantilla(string $plantilla, array $variables): string
    {
        $buscar = [];
        $reemplazar = [];

        foreach ($variables as $clave => $valor) {
            $buscar[] = '{{'.$clave.'}}';
            $reemplazar[] = (string) $valor;
        }

        return str_replace($buscar, $reemplazar, $plantilla);
    }

    private function enviarAUsuario(TipoAviso $tipoAviso, User $usuario, string $mensaje, Model $entidad): void
    {
        $entidadRelacionada = class_basename($entidad);
        $entidadId = (int) $entidad->getKey();

        $notification = new AvisoNotification($tipoAviso, $mensaje, $entidadRelacionada, $entidadId);
        $canales = $notification->via($usuario);

        try {
            Notification::send($usuario, $notification);
            $estatus = AvisoEnviado::ESTATUS_ENVIADO;
        } catch (Throwable $e) {
            Log::error('GestionTI: fallo al enviar aviso', [
                'tipo_aviso' => $tipoAviso->codigo,
                'usuario_id' => $usuario->id,
                'error' => $e->getMessage(),
            ]);
            $estatus = AvisoEnviado::ESTATUS_FALLIDO;
        }

        foreach ($canales as $canal) {
            if (! array_key_exists($canal, self::CANAL_MAP)) {
                continue;
            }

            $canalPersistido = self::CANAL_MAP[$canal];

            AvisoEnviado::create([
                'tipo_aviso_id' => $tipoAviso->id,
                'entidad_relacionada' => $entidadRelacionada,
                'entidad_id' => $entidadId,
                'destinatario_user_id' => $usuario->id,
                'canal' => $canalPersistido,
                'fecha_envio' => now(),
                'estatus_envio' => $estatus,
                'leido' => $canalPersistido === AvisoEnviado::CANAL_IN_APP ? false : null,
            ]);
        }
    }
}
