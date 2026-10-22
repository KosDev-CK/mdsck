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
 * le dice qué se solicitó y qué debe recibir en su(s) sitio(s) — solo las
 * líneas que le corresponden. Adjunta el mismo PDF completo de la solicitud
 * (la numeración de líneas L{folio}-n es la misma que se usa al recibir).
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

        return $this->subject("Por recibir — solicitud {$this->solicitud->folio}")
            ->markdown('gestionti::mail.solicitud-encargado', [
                'solicitud' => $this->solicitud,
                'lineas' => $this->lineas,
                'ordinales' => $ordinales,
                'sitios' => $this->lineas->map(fn ($l) => $l->lugarEntrega?->nombre)->filter()->unique()->values(),
            ]);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => SolicitudProveedorPdf::generar($this->solicitud)->output(),
                "solicitud-proveedor-{$this->solicitud->folio}.pdf"
            )->withMime('application/pdf'),
        ];
    }
}
