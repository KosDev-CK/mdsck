<?php

namespace Modules\GestionTI\Console\Commands;

use Illuminate\Console\Command;
use Modules\GestionTI\Models\AvisoEnviado;
use Modules\GestionTI\Models\Mantenimiento;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\StockMinimo;
use Modules\GestionTI\Models\TipoAviso;
use Modules\GestionTI\Support\Avisos\AvisoDispatcher;

/**
 * Cubre los 3 disparadores de "Configuración de Avisos" que no ocurren
 * dentro de una acción de usuario (a diferencia de `SIC_AUTORIZADA` /
 * `SIC_RECHAZADA` / `PRESUPUESTO_LISTO_PARA_AUTORIZAR` / `PROYECTO_AUTORIZADO`,
 * disparados inline desde `SolicitudesSic`/`PresupuestoProyectos\Show`):
 * `MANTENIMIENTO_PROXIMO_VENCER`, `MANTENIMIENTO_VENCIDO`, `STOCK_BAJO_MINIMO`
 * y `PRESUPUESTO_COSTO_PENDIENTE`. Ver docs/gestionti-progreso.md.
 *
 * Registrado en `GestionTIServiceProvider::configureSchedules()` (diario) —
 * en producción requiere el cron estándar de Laravel
 * (`* * * * * php artisan schedule:run`).
 */
class RevisarAvisosProgramadosCommand extends Command
{
    protected $signature = 'gestionti:revisar-avisos-programados';

    protected $description = 'Dispara los avisos programados de GestionTI: mantenimientos próximos a vencer/vencidos, stock bajo mínimo y costos de presupuesto pendientes.';

    public function handle(AvisoDispatcher $dispatcher): int
    {
        $this->revisarMantenimientosProximosAVencer($dispatcher);
        $this->revisarMantenimientosVencidos($dispatcher);
        $this->revisarStockBajoMinimo($dispatcher);
        $this->revisarPresupuestoCostoPendiente($dispatcher);

        return self::SUCCESS;
    }

    /**
     * Deduplicación "alguna vez" (no diaria) — un mismo mantenimiento no debe
     * volver a generar el aviso de "próximo a vencer" cada día que corre el
     * comando mientras siga en ese rango.
     */
    private function yaSeAvisoAlgunaVez(string $evento, string $entidadRelacionada, int $entidadId): bool
    {
        $tipoAvisoId = TipoAviso::where('evento_disparador', $evento)->value('id');

        if (! $tipoAvisoId) {
            return false;
        }

        return AvisoEnviado::where('tipo_aviso_id', $tipoAvisoId)
            ->where('entidad_relacionada', $entidadRelacionada)
            ->where('entidad_id', $entidadId)
            ->exists();
    }

    /**
     * Deduplicación diaria — permite re-avisar si la condición de breach
     * sigue vigente al día siguiente (a diferencia de Mantenimiento).
     */
    private function yaSeAvisoHoy(string $evento, string $entidadRelacionada, int $entidadId): bool
    {
        $tipoAvisoId = TipoAviso::where('evento_disparador', $evento)->value('id');

        if (! $tipoAvisoId) {
            return false;
        }

        return AvisoEnviado::where('tipo_aviso_id', $tipoAvisoId)
            ->where('entidad_relacionada', $entidadRelacionada)
            ->where('entidad_id', $entidadId)
            ->whereDate('fecha_envio', today())
            ->exists();
    }

    private function mantenimientosEnEstatusVigente()
    {
        return Mantenimiento::whereIn('estatus', [
            Mantenimiento::ESTATUS_PROGRAMADO,
            Mantenimiento::ESTATUS_REPROGRAMADO,
        ])->with('asset');
    }

    private function revisarMantenimientosProximosAVencer(AvisoDispatcher $dispatcher): void
    {
        $mantenimientos = $this->mantenimientosEnEstatusVigente()
            ->whereBetween('fecha_programada', [today()->toDateString(), today()->addDays(7)->toDateString()])
            ->get();

        foreach ($mantenimientos as $mantenimiento) {
            if ($this->yaSeAvisoAlgunaVez(TipoAviso::EVENTO_MANTENIMIENTO_PROXIMO_VENCER, 'Mantenimiento', $mantenimiento->id)) {
                continue;
            }

            $dispatcher->disparar(TipoAviso::EVENTO_MANTENIMIENTO_PROXIMO_VENCER, $mantenimiento, variables: [
                'tipo' => $mantenimiento->tipo,
                'activo' => $mantenimiento->asset?->codigo ?? "Activo #{$mantenimiento->asset_id}",
                'fecha_programada' => optional($mantenimiento->fecha_programada)->format('d/m/Y'),
            ]);
        }
    }

    private function revisarMantenimientosVencidos(AvisoDispatcher $dispatcher): void
    {
        $mantenimientos = $this->mantenimientosEnEstatusVigente()
            ->where('fecha_programada', '<', today()->toDateString())
            ->get();

        foreach ($mantenimientos as $mantenimiento) {
            if ($this->yaSeAvisoAlgunaVez(TipoAviso::EVENTO_MANTENIMIENTO_VENCIDO, 'Mantenimiento', $mantenimiento->id)) {
                continue;
            }

            $dispatcher->disparar(TipoAviso::EVENTO_MANTENIMIENTO_VENCIDO, $mantenimiento, variables: [
                'tipo' => $mantenimiento->tipo,
                'activo' => $mantenimiento->asset?->codigo ?? "Activo #{$mantenimiento->asset_id}",
                'fecha_programada' => optional($mantenimiento->fecha_programada)->format('d/m/Y'),
            ]);
        }
    }

    /**
     * Reutiliza `StockMinimo::enBreach()` — el mismo cálculo que
     * `Livewire\Inventarios\Stock::alertasMinimos()` — en vez de duplicarlo.
     */
    private function revisarStockBajoMinimo(AvisoDispatcher $dispatcher): void
    {
        foreach (StockMinimo::enBreach() as $item) {
            $minimo = $item['minimo'];

            if ($this->yaSeAvisoHoy(TipoAviso::EVENTO_STOCK_BAJO_MINIMO, 'StockMinimo', $minimo->id)) {
                continue;
            }

            $dispatcher->disparar(TipoAviso::EVENTO_STOCK_BAJO_MINIMO, $minimo, variables: [
                'tipo_equipo' => $minimo->tipoEquipo?->nombre ?? 'Sin tipo',
                'ubicacion' => $minimo->ubicacion?->nombre ?? 'Sin ubicación',
                'stock_actual' => $item['stock_actual'],
                'cantidad_minima' => $minimo->cantidad_minima,
            ]);
        }
    }

    private function revisarPresupuestoCostoPendiente(AvisoDispatcher $dispatcher): void
    {
        $articulos = ProyectoPresupuestoArticulo::where('estatus_captura', ProyectoPresupuestoArticulo::ESTATUS_CAPTURA_PENDIENTE)
            ->whereHas('proyecto', fn ($q) => $q->where('fecha_limite_captura', '<=', today()->addDays(3)->toDateString()))
            ->with(['proyecto', 'responsableCosto'])
            ->get();

        foreach ($articulos as $articulo) {
            if ($this->yaSeAvisoHoy(TipoAviso::EVENTO_PRESUPUESTO_COSTO_PENDIENTE, 'ProyectoPresupuestoArticulo', $articulo->id)) {
                continue;
            }

            $dispatcher->disparar(
                TipoAviso::EVENTO_PRESUPUESTO_COSTO_PENDIENTE,
                $articulo,
                responsable: $articulo->responsableCosto,
                variables: [
                    'descripcion' => $articulo->descripcion,
                    'proyecto' => $articulo->proyecto?->nombre_proyecto,
                ]
            );
        }
    }
}
