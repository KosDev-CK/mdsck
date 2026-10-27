<?php

namespace Modules\GestionTI\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Support\Pdf\SolicitudProveedorPdf;

/**
 * Copia de una Solicitud a Proveedores para un ENCARGADO de lugar de entrega:
 * le dice qué debe recibir en UN sitio — solo las líneas de ese lugar (se
 * manda un correo por lugar, no uno con todo junto). Adjunta el PDF con solo la
 * hoja de ese lugar (la numeración de líneas L{folio}-n es la de la solicitud
 * completa, la misma que se usa al recibir).
 * Se envía desde `Support\Compras\AvisoEncargadosEntrega`.
 */
class SolicitudProveedorEncargadoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \Modules\GestionTI\Models\SolicitudProveedorLinea>  $lineas  Solo las líneas de los sitios de este encargado.
     */
    public function __construct(public SolicitudProveedor $solicitud, public Collection $lineas)
    {
    }

    public function build(): self
    {
        // Número de línea dentro de la solicitud completa: el mismo del PDF y
        // de los códigos de barras que se escanean en Recepción.
        $ordinales = $this->solicitud->lineas->sortBy('id')->values()
            ->mapWithKeys(fn ($linea, $i) => [$linea->id => $i + 1]);

        $sitios = $this->lineas->map(fn ($l) => $l->lugarEntrega?->nombre)->filter()->unique()->values();

        return $this->subject("Por recibir en {$sitios->implode(', ')} — solicitud {$this->solicitud->folio}")
            ->markdown('gestionti::mail.solicitud-encargado', [
                'solicitud' => $this->solicitud,
                'lineas' => $this->lineas,
                'ordinales' => $ordinales,
                'sitios' => $sitios,
            ]);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => SolicitudProveedorPdf::generar($this->solicitud, $this->lineas->pluck('lugar_entrega_id')->unique()->values()->all())->output(),
                "solicitud-proveedor-{$this->solicitud->folio}.pdf"
            )->withMime('application/pdf'),
        ];
    }
}
