<?php

namespace Modules\GestionTI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class StockMinimo extends Model
{
    protected $table = 'stocks_minimos';

    protected $fillable = ['tipo_equipo_id', 'ubicacion_id', 'cantidad_minima', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function tipoEquipo()
    {
        return $this->belongsTo(TipoEquipo::class);
    }

    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class);
    }

    /**
     * Cálculo de "en breach" (stock libre por debajo del mínimo) — extraído
     * aquí para que tanto `Livewire\Inventarios\Stock::alertasMinimos()` como
     * `Console\Commands\RevisarAvisosProgramadosCommand` (aviso
     * `STOCK_BAJO_MINIMO`, Fase 4) lo reutilicen sin duplicar la regla.
     * Stock libre = solo `en_stock` — NO cuenta `reservado` ni `asignado`
     * (spec 7.11, línea 127).
     *
     * @return Collection<int, array{minimo: StockMinimo, stock_actual: int}>
     */
    public static function enBreach(): Collection
    {
        return static::where('activo', true)
            ->with(['tipoEquipo', 'ubicacion'])
            ->get()
            ->map(function (StockMinimo $minimo) {
                $stockActual = Asset::where('tipo_equipo_id', $minimo->tipo_equipo_id)
                    ->where('ubicacion_actual_id', $minimo->ubicacion_id)
                    ->whereHas('estatus', fn ($q) => $q->where('codigo', 'en_stock'))
                    ->count();

                return [
                    'minimo' => $minimo,
                    'stock_actual' => $stockActual,
                ];
            })
            ->filter(fn (array $item) => $item['stock_actual'] < $item['minimo']->cantidad_minima)
            ->values();
    }
}
