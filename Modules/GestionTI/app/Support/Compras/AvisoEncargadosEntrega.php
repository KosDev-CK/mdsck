<?php

namespace Modules\GestionTI\Support\Compras;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\GestionTI\Mail\SolicitudProveedorEncargadoMail;
use Modules\GestionTI\Models\SolicitudProveedor;
use Throwable;

/**
 * Manda a los ENCARGADOS de cada lugar de entrega una copia de una Solicitud
 * a Proveedores con lo que deben recibir en su sitio (solo las líneas de sus
 * sitios). Un encargado de varios sitios de la misma solicitud recibe UN solo
 * correo con todas sus líneas. Se usa al enviar la solicitud al proveedor y
 * desde la acción "Avisar a encargados" (que no le escribe al proveedor).
 */
class AvisoEncargadosEntrega
{
    /**
     * @return array{enviados: int, sin_encargados: array<int, string>, fallidos: array<int, string>}
     */
    public function enviar(SolicitudProveedor $solicitud): array
    {
        $solicitud->load([
            'vendor', 'creadoPor',
            'lineas.articulo', 'lineas.sic', 'lineas.ebsRequisition.lines', 'lineas.lugarEntrega.usuarios',
        ]);

        /** @var array<int, array{user: \App\Models\User, lineas: Collection}> $porUsuario */
        $porUsuario = [];
        $sinEncargados = [];

        foreach ($solicitud->lineas->groupBy('lugar_entrega_id') as $lineas) {
            $lugar = $lineas->first()->lugarEntrega;
            $encargados = ($lugar?->usuarios ?? collect())->filter(fn ($u) => $u->is_active && ! empty($u->email));

            if ($encargados->isEmpty()) {
                $sinEncargados[] = $lugar?->nombre ?? 'Sin lugar de entrega';

                continue;
            }

            foreach ($encargados as $usuario) {
                $porUsuario[$usuario->id]['user'] = $usuario;
                $porUsuario[$usuario->id]['lineas'] = ($porUsuario[$usuario->id]['lineas'] ?? collect())->merge($lineas);
            }
        }

        $enviados = 0;
        $fallidos = [];

        foreach ($porUsuario as $datos) {
            $lineas = $solicitud->lineas->whereIn('id', $datos['lineas']->pluck('id'))->sortBy('id')->values();

            try {
                Mail::to($datos['user']->email)->send(new SolicitudProveedorEncargadoMail($solicitud, $lineas));
                $enviados++;
            } catch (Throwable $e) {
                Log::warning('GestionTI: no se pudo avisar a un encargado de entrega', [
                    'solicitud' => $solicitud->folio,
                    'usuario' => $datos['user']->id,
                    'error' => $e->getMessage(),
                ]);
                $fallidos[] = $datos['user']->name;
            }
        }

        if ($enviados > 0) {
            $solicitud->forceFill(['encargados_avisados_at' => now()])->save();
        }

        return ['enviados' => $enviados, 'sin_encargados' => $sinEncargados, 'fallidos' => $fallidos];
    }

    /** Texto corto con el resultado, para el mensaje de la pantalla. */
    public static function resumen(array $resultado): string
    {
        $partes = [];

        if ($resultado['enviados'] > 0) {
            $partes[] = "Copia enviada a {$resultado['enviados']} encargado(s) de entrega.";
        }

        if (! empty($resultado['sin_encargados'])) {
            $partes[] = 'Sin encargados en: '.implode(', ', $resultado['sin_encargados']).'.';
        }

        if (! empty($resultado['fallidos'])) {
            $partes[] = 'No se pudo enviar a: '.implode(', ', $resultado['fallidos']).'.';
        }

        return implode(' ', $partes);
    }
}
