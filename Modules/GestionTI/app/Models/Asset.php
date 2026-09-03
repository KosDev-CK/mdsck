<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Asset extends Model
{
    protected $fillable = [
        'codigo', 'tipo_equipo_id', 'marca_id', 'modelo_id',
        'numero_serie', 'service_tag', 'especificaciones', 'costo_adquisicion',
        'origen_tipo', 'recepcion_linea_id', 'motivo_alta_manual',
        'dado_de_alta_por_id', 'vendor_id', 'fecha_alta_stock',
        'fecha_inicio_garantia', 'fecha_fin_garantia', 'ubicacion_actual_id',
        'sic_reservada_id', 'proyecto_presupuesto_id', 'estatus_id',
        'propiedad_id', 'invoice_id', 'nota_adquisicion_original',
    ];

    protected $casts = [
        'especificaciones' => 'array',
        'costo_adquisicion' => 'decimal:2',
    ];

    /**
     * Slugs curados para los tipos canónicos conocidos (mismos 3 valores que
     * `ImportarHistoricoCommand::TIPO_SLUG_OVERRIDES` antes de esta
     * extracción) — cualquier otro `TipoEquipo->nombre` cae al slug genérico
     * derivado del nombre. Vive aquí (no en el comando) porque tanto la
     * migración histórica como la Recepción de Proveedor (Fase 3, etapa 3)
     * generan `codigo` de Asset y ambos deben usar exactamente la misma
     * secuencia para no colisionar.
     */
    public const TIPO_SLUG_OVERRIDES = [
        'Laptop' => 'LAPTOP',
        'PC de Escritorio' => 'DESKTOP',
        'Monitor' => 'MONITOR',
    ];

    /**
     * Última secuencia generada por slug, en memoria de proceso — evita
     * repetir el `max(codigo)` contra BD en cada llamada dentro de una misma
     * corrida (ej. las ~3799 filas de `ImportarHistoricoCommand`). No
     * persiste entre requests/procesos reales (cada request PHP-FPM es su
     * propio proceso, este proyecto no usa Octane) — el único riesgo es
     * contaminación entre tests dentro del mismo proceso de PHPUnit, por eso
     * tanto el comando como la pantalla de Recepción llaman
     * `resetCodigoSequenceCache()` al inicio de cada corrida/transacción.
     *
     * @var array<string, int>
     */
    private static array $codigoSequenceCache = [];

    /**
     * Genera el siguiente `codigo` secuencial (`KOS-<SLUG>-######`) para el
     * tipo de equipo dado. Extraído de `ImportarHistoricoCommand` (Fase 2)
     * para que ese comando y la pantalla de Recepción de Proveedor (Fase 3)
     * compartan la misma fuente de verdad y nunca generen un `codigo`
     * duplicado entre sí.
     */
    public static function generateCodigo(TipoEquipo $tipo): string
    {
        $slug = static::slugForTipoEquipo($tipo);

        if (! array_key_exists($slug, static::$codigoSequenceCache)) {
            $maxExisting = static::query()
                ->where('codigo', 'like', "KOS-{$slug}-%")
                ->pluck('codigo')
                ->map(fn ($codigo) => (int) substr($codigo, (int) strrpos($codigo, '-') + 1))
                ->max();

            static::$codigoSequenceCache[$slug] = $maxExisting ?? 0;
        }

        static::$codigoSequenceCache[$slug]++;

        return sprintf('KOS-%s-%06d', $slug, static::$codigoSequenceCache[$slug]);
    }

    /**
     * Limpia la caché de secuencias en memoria — llamar al inicio de
     * cualquier corrida/transacción que vaya a generar `codigo`s nuevos
     * (`ImportarHistoricoCommand::handle()`, `Recepciones::save()`) para
     * garantizar que la secuencia siempre arranque de un `max(codigo)` fresco
     * contra BD, sin importar qué haya generado otra llamada anterior en el
     * mismo proceso PHP (relevante sobre todo en tests, donde varios métodos
     * corren en el mismo proceso de PHPUnit).
     */
    public static function resetCodigoSequenceCache(): void
    {
        static::$codigoSequenceCache = [];
    }

    public static function slugForTipoEquipo(TipoEquipo $tipo): string
    {
        return self::TIPO_SLUG_OVERRIDES[$tipo->nombre] ?? self::genericSlug($tipo->nombre);
    }

    private static function genericSlug(string $nombre): string
    {
        $slug = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($nombre)));

        return $slug !== '' ? $slug : 'GENERICO';
    }

    public function tipoEquipo()
    {
        return $this->belongsTo(TipoEquipo::class);
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    public function modelo()
    {
        return $this->belongsTo(Modelo::class);
    }

    public function dadoDeAltaPor()
    {
        return $this->belongsTo(Validador::class, 'dado_de_alta_por_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Proveedor::class, 'vendor_id');
    }

    public function ubicacionActual()
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_actual_id');
    }

    public function estatus()
    {
        return $this->belongsTo(EstatusActivo::class, 'estatus_id');
    }

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class);
    }

    public function recepcionLinea()
    {
        return $this->belongsTo(RecepcionLinea::class, 'recepcion_linea_id');
    }

    public function sicReservada()
    {
        return $this->belongsTo(SolicitudSicBorrador::class, 'sic_reservada_id');
    }

    public function proyectoPresupuesto()
    {
        return $this->belongsTo(ProyectoPresupuesto::class, 'proyecto_presupuesto_id');
    }

    /**
     * Trazabilidad futura — sin lógica adicional en esta etapa (Fase 3,
     * etapa 4, Asignación de Activo).
     */
    public function assignments()
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /**
     * Se llena en `Facturas::save()` (Fase 3 etapa 6) — sustituye al gancho
     * "cuando la factura generó la OC" del spec original, ya que no hay
     * `PurchaseOrder` en este módulo (ver docs/gestionti-progreso.md).
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Historial de movimientos de stock (Fase 3, etapa 7 — solo la pantalla
     * de Stock inserta filas aquí, y solo `tipo = 'traslado'` por ahora).
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Bitácora de reasignaciones manuales de SIC reservada (Fase 3, etapa
     * 7 — "excepciones" del spec, distinta del `AuditLog` transversal).
     */
    public function sicReservationLogs()
    {
        return $this->hasMany(AssetSicReservationLog::class);
    }

    /**
     * Historial de mantenimientos preventivos/correctivos (Fase 3, etapa 9).
     */
    public function mantenimientos()
    {
        return $this->hasMany(Mantenimiento::class);
    }
}
