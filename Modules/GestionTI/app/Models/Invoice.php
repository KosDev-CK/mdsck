<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Facturación (sección 7.9 del spec original) — SIN Orden de Compra. Ver
 * docs/gestionti-progreso.md, Fase 3 etapa 6, para el recorte de alcance
 * explícito (decisión del usuario, no un gap): `PurchaseOrder` no se
 * construye en este módulo, vive en el ERP externo. Esta pantalla es
 * exclusivamente para registrar manualmente las facturas que el proveedor
 * entrega junto con la mercancía/remisión — nada de integración real con
 * el ERP todavía.
 *
 * "Invoice" -> "invoices" es una pluralización regular en inglés, no
 * requiere `$table` explícito (verificado antes de escribir la migración,
 * a diferencia del riesgo ya documentado repetidamente en este módulo para
 * nombres en español).
 */
class Invoice extends Model
{
    public const MONEDAS = ['MXN', 'USD'];

    public const ESTATUS_RECIBIDA = 'recibida';

    public const ESTATUS_REGISTRADA = 'registrada';

    public const ESTATUS_AUTORIZADA = 'autorizada';

    public const ESTATUS_PAGADA = 'pagada';

    protected $fillable = [
        'folio_factura',
        'vendor_id',
        'fecha_recepcion',
        'monto_total',
        'moneda',
        'estatus',
        'fecha_autorizacion',
        'fecha_pago',
        'partida_presupuestal',
        'ejercicio_fiscal',
        'diferencia_a_revisar',
    ];

    protected $casts = [
        'fecha_recepcion' => 'date',
        'fecha_autorizacion' => 'date',
        'fecha_pago' => 'date',
        'monto_total' => 'decimal:2',
        'diferencia_a_revisar' => 'boolean',
    ];

    public function vendor()
    {
        return $this->belongsTo(Proveedor::class, 'vendor_id');
    }

    /**
     * M:N — el spec lo describe explícitamente como M:N, no 1:N (ver
     * migración `invoice_recepciones`). Sin columnas pivote extra, solo las
     * 2 FKs.
     */
    public function recepciones()
    {
        return $this->belongsToMany(Recepcion::class, 'invoice_recepciones');
    }

    /**
     * El spec NO nombra una columna FK directa para el adjunto de Invoice
     * (a diferencia de `AssetAssignment.documento_responsiva_id`/
     * `Recepcion.documento_remision_id`, que sí son columnas explícitas) —
     * mismo patrón de llave genérica que `SolicitudSicBorrador::documentoAdjunto()`.
     */
    public function documentoAdjunto(): ?DocumentoDigitalizado
    {
        return DocumentoDigitalizado::where('entidad_relacionada', class_basename(self::class))
            ->where('entidad_id', $this->id)
            ->latest('fecha_subida')
            ->first();
    }
}
