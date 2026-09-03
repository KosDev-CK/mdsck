<?php

namespace Modules\GestionTI\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionTI\Models\Asset;
use Modules\GestionTI\Models\Empleado;
use Modules\GestionTI\Models\Invoice;
use Modules\GestionTI\Models\Mantenimiento;
use Modules\GestionTI\Models\ProyectoPresupuestoArticulo;
use Modules\GestionTI\Models\ProyectoPresupuestoAutorizacion;
use Modules\GestionTI\Models\SolicitudProveedor;
use Modules\GestionTI\Models\SolicitudSicBorrador;
use Modules\GestionTI\Models\StockMinimo;

/**
 * Dashboard del módulo (sección 7.1 del spec original) — NO es el dashboard
 * genérico del core (`App\Livewire\Dashboard`, `/dashboard`, compartido por
 * todos los proyectos clonados de la plantilla, sin nada de negocio). Este
 * vive exclusivamente en GestionTI, 100% lectura/agregación, sin formularios
 * ni acciones — cada tarjeta enlaza a la pantalla real que la resuelve
 * (mismo criterio que ya usa `BusquedaGlobal`).
 *
 * Gating por permiso real, igual que `BusquedaGlobal`: cada sección/tarjeta
 * solo se calcula (no solo se oculta en la vista) si el usuario tiene el
 * permiso de la pantalla que esa tarjeta representa — un usuario de un
 * perfil que no ve Facturación no dispara esa query.
 *
 * Ver docs/gestionti-progreso.md (Fase 4, etapa 6) para las decisiones de
 * diseño documentadas: qué permiso se eligió para cada tarjeta, por qué
 * "Mantenimientos próximos" vive fuera del bloque de "Mis pendientes", y
 * qué pasa cuando el usuario autenticado no tiene un `Empleado` vinculado.
 */
#[Layout('layouts.app')]
class Dashboard extends Component
{
    /**
     * Ventana de "próximo a vencer" para Mantenimiento — mismo criterio
     * exacto que `RevisarAvisosProgramadosCommand::revisarMantenimientosProximosAVencer()`
     * (evento `MANTENIMIENTO_PROXIMO_VENCER`): estatus vigente
     * (`programado`/`reprogramado`) con `fecha_programada` entre hoy y
     * hoy+7 (ambos límites incluidos). No se extrajo a un método reusable en
     * el comando (queda privado ahí), así que se repite aquí el mismo
     * criterio — con una diferencia deliberada de implementación: se usa
     * `whereDate()` en vez de `whereBetween()` con strings `Y-m-d`.
     * Encontrado al escribir el test de esta pantalla: el cast `date` de
     * Eloquent persiste `fecha_programada` con hora `00:00:00` incluida
     * (comportamiento real de Laravel al escribir, no solo un artefacto de
     * SQLite) — en una columna `DATE` real de MySQL eso se trunca
     * silenciosamente al guardar, pero en SQLite (motor de la suite de
     * tests) se conserva tal cual, y como texto `"...09-09 00:00:00"` es
     * lexicográficamente MAYOR que el límite superior `"...09-09"` de un
     * `whereBetween` con strings, excluyendo por error el límite exacto
     * `hoy+7`. `whereDate()` compara solo la parte de fecha en ambos
     * motores y evita el problema. No se tocó `RevisarAvisosProgramadosCommand`
     * (fuera de alcance de esta entrega) — su test existente nunca ejercitó
     * el límite exacto de 7 días, por eso no lo había encontrado antes.
     */
    private function mantenimientosProximosCount(): int
    {
        return Mantenimiento::whereIn('estatus', [
            Mantenimiento::ESTATUS_PROGRAMADO,
            Mantenimiento::ESTATUS_REPROGRAMADO,
        ])
            ->whereDate('fecha_programada', '>=', today())
            ->whereDate('fecha_programada', '<=', today()->addDays(7))
            ->count();
    }

    /**
     * Conteo de Asset agrupado por estatus.codigo — join simple en vez de
     * agregar una relación `EstatusActivo::assets()` nueva solo para esto
     * (el modelo hoy no la tiene, y esta pantalla es de solo lectura).
     *
     * @return Collection<int, object{codigo: string, nombre: string, cantidad: int}>
     */
    private function activosPorEstatus(): Collection
    {
        return Asset::query()
            ->join('estatus_activo', 'estatus_activo.id', '=', 'assets.estatus_id')
            ->groupBy('estatus_activo.codigo', 'estatus_activo.nombre')
            ->orderBy('estatus_activo.nombre')
            ->get(['estatus_activo.codigo as codigo', 'estatus_activo.nombre as nombre', DB::raw('count(*) as cantidad')]);
    }

    /**
     * Top 8 (descendente) de Asset en stock (`estatus.codigo = en_stock`)
     * agrupado por tipo de equipo.
     *
     * @return Collection<int, object{tipo: string, cantidad: int}>
     */
    private function stockPorTipo(): Collection
    {
        return Asset::query()
            ->join('tipos_equipo', 'tipos_equipo.id', '=', 'assets.tipo_equipo_id')
            ->join('estatus_activo', 'estatus_activo.id', '=', 'assets.estatus_id')
            ->where('estatus_activo.codigo', 'en_stock')
            ->groupBy('tipos_equipo.nombre')
            ->orderByDesc(DB::raw('count(*)'))
            ->limit(8)
            ->get(['tipos_equipo.nombre as tipo', DB::raw('count(*) as cantidad')]);
    }

    /**
     * Mismo criterio exacto que `PresupuestoProyectos\Show::esNivelAccionable()`
     * (privado ahí, atado a `$this->proyectoPresupuesto` — no es trivial
     * reutilizarlo tal cual desde una clase distinta, así que se repite la
     * misma lógica): un nivel de autorización solo es accionable si sigue
     * `pendiente` Y ningún nivel anterior (menor, del mismo proyecto) sigue
     * sin `aprobado`.
     */
    private function esNivelAccionable(ProyectoPresupuestoAutorizacion $autorizacion): bool
    {
        if ($autorizacion->estatus !== ProyectoPresupuestoAutorizacion::ESTATUS_PENDIENTE) {
            return false;
        }

        return ! ProyectoPresupuestoAutorizacion::where('proyecto_id', $autorizacion->proyecto_id)
            ->where('nivel', '<', $autorizacion->nivel)
            ->where('estatus', '!=', ProyectoPresupuestoAutorizacion::ESTATUS_APROBADO)
            ->exists();
    }

    /**
     * @return Collection<int, ProyectoPresupuestoAutorizacion>
     */
    private function autorizacionesAccionables(Empleado $empleado): Collection
    {
        return ProyectoPresupuestoAutorizacion::where('aprobador_id', $empleado->id)
            ->where('estatus', ProyectoPresupuestoAutorizacion::ESTATUS_PENDIENTE)
            ->get()
            ->filter(fn (ProyectoPresupuestoAutorizacion $autorizacion) => $this->esNivelAccionable($autorizacion))
            ->values();
    }

    public function render()
    {
        $user = auth()->user();

        $activosPorEstatus = null;
        $stockPorTipo = null;
        $stockBajoMinimo = null;

        if ($user->can('screens.gestionti-stock.manage')) {
            $activosPorEstatus = $this->activosPorEstatus();
            $stockPorTipo = $this->stockPorTipo();
            $stockBajoMinimo = StockMinimo::enBreach();
        }

        $sicsEnCaptura = $user->can('screens.gestionti-solicitudes-sic.manage')
            ? SolicitudSicBorrador::whereIn('estatus', [
                SolicitudSicBorrador::ESTATUS_CAPTURADO,
                SolicitudSicBorrador::ESTATUS_SIC_CREADA,
            ])->count()
            : null;

        $solicitudesProveedorPendientes = $user->can('screens.gestionti-solicitudes-proveedor.manage')
            ? SolicitudProveedor::whereIn('estatus', [
                SolicitudProveedor::ESTATUS_SOLICITADA,
                SolicitudProveedor::ESTATUS_PARCIALMENTE_RECIBIDA,
            ])->count()
            : null;

        $facturasPendientes = null;
        $facturasDiferencia = null;

        if ($user->can('screens.gestionti-facturas.manage')) {
            $facturasPendientes = Invoice::where('estatus', '!=', Invoice::ESTATUS_PAGADA)->count();
            $facturasDiferencia = Invoice::where('diferencia_a_revisar', true)->count();
        }

        // "Mantenimientos próximos" no se filtra por el Empleado del usuario
        // (Mantenimiento no tiene un "responsable asignado" fijo, es
        // operativo general) — se muestra como tarjeta de la Sección 1
        // (métricas globales), gateada solo por el permiso de la pantalla,
        // sin importar si el usuario tiene Empleado vinculado.
        $mantenimientosProximos = $user->can('screens.gestionti-mantenimientos.manage')
            ? $this->mantenimientosProximosCount()
            : null;

        // "Mis pendientes": resuelve el Empleado del usuario actual con el
        // mismo criterio exacto ya usado en AvisoDispatcher::resolverPorEmpleado()
        // (coincidencia de correo). Sin Empleado vinculado, la sección
        // completa se omite (mensaje, no tarjetas en cero).
        $empleado = Empleado::where('correo', $user->email)->first();
        $misPendientes = null;

        if ($empleado) {
            $misPendientes = [
                'costos_pendientes' => null,
                'autorizaciones_accionables' => null,
                // Notificaciones sin leer no depende de ningún permiso de
                // pantalla del módulo (es un dato propio del usuario, vía el
                // mecanismo nativo ya usado por App\Livewire\Notifications\Bell)
                // — se muestra siempre que el panel personalizado se renderiza.
                'notificaciones_sin_leer' => $user->unreadNotifications()->count(),
            ];

            if ($user->can('screens.gestionti-presupuestos-proyecto.manage')) {
                $misPendientes['costos_pendientes'] = ProyectoPresupuestoArticulo::where('responsable_costo_id', $empleado->id)
                    ->where('estatus_captura', ProyectoPresupuestoArticulo::ESTATUS_CAPTURA_PENDIENTE)
                    ->count();

                $misPendientes['autorizaciones_accionables'] = $this->autorizacionesAccionables($empleado)->count();
            }
        }

        return view('gestionti::livewire.dashboard', [
            'activosPorEstatus' => $activosPorEstatus,
            'stockPorTipo' => $stockPorTipo,
            'stockBajoMinimo' => $stockBajoMinimo,
            'sicsEnCaptura' => $sicsEnCaptura,
            'solicitudesProveedorPendientes' => $solicitudesProveedorPendientes,
            'facturasPendientes' => $facturasPendientes,
            'facturasDiferencia' => $facturasDiferencia,
            'mantenimientosProximos' => $mantenimientosProximos,
            'empleado' => $empleado,
            'misPendientes' => $misPendientes,
        ]);
    }
}
