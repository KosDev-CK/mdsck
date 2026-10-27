<?php

namespace Modules\GestionTI\Support\Compras;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\GestionTI\Mail\SolicitudProveedorEncargadoMail;
use Modules\GestionTI\Models\SolicitudProveedor;
use Throwable;

/**
 * Manda a los ENCARGADOS de cada lugar de entrega una copia de una Solicitud
 * a Proveedores con lo que deben recibir en su sitio (solo las líneas de sus
 * sitios), un correo por cada lugar de entrega con el PDF solo de ese lugar:
 * un encargado de varios sitios recibe un correo por sitio, no uno con todo junto. Se usa al enviar la solicitud al proveedor y
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

        /** @var array<int, array{user: \App\Models\User, lineas: Collection}> $envios Un envío por encargado Y por lugar de entrega. */
        $envios = [];
        $sinEncargados = [];

        foreach ($solicitud->lineas->groupBy('lugar_entrega_id') as $lineas) {
            $lugar = $lineas->first()->lugarEntrega;
            $encargados = ($lugar?->usuarios ?? collect())->filter(fn ($u) => $u->is_active && ! empty($u->email));

            if ($encargados->isEmpty()) {
                $sinEncargados[] = $lugar?->nombre ?? 'Sin lugar de entrega';

                continue;
            }

            foreach ($encargados as $usuario) {
                $envios[] = ['user' => $usuario, 'lineas' => $lineas->sortBy('id')->values()];
            }
        }

        $enviados = 0;
        $fallidos = [];

        foreach ($envios as $envio) {
            try {
                Mail::to($envio['user']->email)->send(new SolicitudProveedorEncargadoMail($solicitud, new EloquentCollection($envio['lineas']->all())));
                $enviados++;
            } catch (Throwable $e) {
                Log::warning('GestionTI: no se pudo avisar a un encargado de entrega', [
                    'solicitud' => $solicitud->folio,
                    'usuario' => $envio['user']->id,
                    'error' => $e->getMessage(),
                ]);
                $fallidos[] = $envio['user']->name;
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
            $partes[] = "Se enviaron {$resultado['enviados']} correo(s) a encargados de entrega (uno por lugar).";
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
