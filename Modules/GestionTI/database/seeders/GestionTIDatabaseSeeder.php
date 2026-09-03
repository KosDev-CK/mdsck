<?php

namespace Modules\GestionTI\Database\Seeders;

use App\Models\Screen;
use Illuminate\Database\Seeder;
use Modules\GestionTI\Models\ConfiguracionDocumentos;
use Modules\GestionTI\Models\EstatusActivo;
use Modules\GestionTI\Models\TipoAviso;
use Spatie\Permission\Models\Role;

class GestionTIDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Grupos de menú (`group_label`) usados en este módulo: "Mesa de
     * Servicio", "Compras", "Presupuesto de Proyectos", "Inventarios" (las 4
     * áreas operativas) y "Catálogos" (catálogos núcleo y de módulo, sin
     * área operativa propia — Empresa, Ubicación, Área, Unidad de Negocio,
     * Puesto, Centro de Costo, Empleado).
     */
    public function run(): void
    {
        $screens = [
            [
                'slug' => 'gestionti-dashboard',
                'module' => 'GestionTI',
                'group_label' => 'General',
                // "Dashboard de TI", no solo "Dashboard": el core YA siembra
                // un Screen `slug=dashboard` con ese nombre exacto en este
                // mismo `group_label='General'` (`database/seeders/CoreSeeder.php`)
                // — dos entradas de sidebar llamadas igual en el mismo grupo
                // confundirían al usuario.
                'name' => 'Dashboard de TI',
                'route_name' => 'gestionti.dashboard.index',
                'permission_name' => 'screens.gestionti-dashboard.manage',
                'icon' => 'chart-bar',
                // `order` es una columna sin signo (unsignedTinyInteger en la
                // migración del core) — un valor negativo revienta el INSERT
                // en MySQL real con "Out of range value" (SQLite, motor de la
                // suite de tests, no valida el rango sin signo, por eso este
                // bug no lo atrapó ningún test y solo apareció al sembrar
                // contra la BD de dev real — mismo patrón de gotcha ya
                // documentado varias veces en este módulo). 0 (no -1) sitúa
                // este Dashboard antes que "Búsqueda Global", que se corrió
                // de 0 a 2 para dejar hueco.
                'order' => 0,
            ],
            [
                'slug' => 'gestionti-busqueda-global',
                'module' => 'GestionTI',
                'group_label' => 'General',
                'name' => 'Búsqueda Global',
                'route_name' => 'gestionti.busqueda-global.index',
                'permission_name' => 'screens.gestionti-busqueda-global.manage',
                'icon' => 'magnifying-glass',
                // 2, no 0: "Dashboard de TI" ahora ocupa el 0 (debe ir
                // primero dentro de "General"). Sigue por debajo de
                // "Catálogos" (10+) y de las demás áreas operativas, mismo
                // criterio que ya explicaba esta nota (evitar depender del
                // desempate no garantizado de la consulta).
                'order' => 2,
            ],
            [
                'slug' => 'gestionti-catalogos-nucleo',
                'module' => 'GestionTI',
                'group_label' => 'Catálogos',
                'name' => 'Catálogos Núcleo',
                'route_name' => 'gestionti.catalogos.nucleo',
                'permission_name' => 'screens.gestionti-catalogos-nucleo.manage',
                'icon' => 'building-office',
                'order' => 10,
            ],
            [
                'slug' => 'gestionti-catalogos-empleados',
                'module' => 'GestionTI',
                'group_label' => 'Catálogos',
                'name' => 'Empleados',
                'route_name' => 'gestionti.catalogos.empleados',
                'permission_name' => 'screens.gestionti-catalogos-empleados.manage',
                'icon' => 'users',
                'order' => 11,
            ],
            [
                'slug' => 'gestionti-catalogos-compras',
                'module' => 'GestionTI',
                'group_label' => 'Compras',
                'name' => 'Catálogos de Compras',
                'route_name' => 'gestionti.catalogos.compras',
                'permission_name' => 'screens.gestionti-catalogos-compras.manage',
                'icon' => 'truck',
                'order' => 20,
            ],
            [
                'slug' => 'gestionti-catalogos-inventario',
                'module' => 'GestionTI',
                'group_label' => 'Inventarios',
                'name' => 'Catálogos de Inventario',
                'route_name' => 'gestionti.catalogos.inventario',
                'permission_name' => 'screens.gestionti-catalogos-inventario.manage',
                'icon' => 'archive-box',
                'order' => 30,
            ],
            [
                'slug' => 'gestionti-tickets',
                'module' => 'GestionTI',
                'group_label' => 'Mesa de Servicio',
                'name' => 'Tickets',
                'route_name' => 'gestionti.tickets.index',
                'permission_name' => 'screens.gestionti-tickets.manage',
                'icon' => 'ticket',
                'order' => 1,
            ],
            [
                'slug' => 'gestionti-solicitudes-sic',
                'module' => 'GestionTI',
                'group_label' => 'Mesa de Servicio',
                'name' => 'Solicitud de SIC',
                'route_name' => 'gestionti.solicitudes-sic.index',
                'permission_name' => 'screens.gestionti-solicitudes-sic.manage',
                'icon' => 'document-text',
                'order' => 2,
            ],
            [
                'slug' => 'gestionti-ebs-requisiciones',
                'module' => 'GestionTI',
                'group_label' => 'Mesa de Servicio',
                'name' => 'SIC en EBS',
                'route_name' => 'gestionti.ebs-requisiciones.index',
                'permission_name' => 'screens.gestionti-ebs-requisiciones.manage',
                'icon' => 'arrow-path',
                'order' => 3,
            ],
            [
                'slug' => 'gestionti-solicitudes-proveedor',
                'module' => 'GestionTI',
                'group_label' => 'Compras',
                'name' => 'Solicitud a Proveedores',
                'route_name' => 'gestionti.solicitudes-proveedor.index',
                'permission_name' => 'screens.gestionti-solicitudes-proveedor.manage',
                'icon' => 'shopping-cart',
                'order' => 21,
            ],
            [
                'slug' => 'gestionti-recepciones',
                'module' => 'GestionTI',
                'group_label' => 'Compras',
                'name' => 'Recepción de Proveedor',
                'route_name' => 'gestionti.recepciones.index',
                'permission_name' => 'screens.gestionti-recepciones.manage',
                'icon' => 'inbox-arrow-down',
                'order' => 22,
            ],
            [
                'slug' => 'gestionti-asignaciones',
                'module' => 'GestionTI',
                'group_label' => 'Inventarios',
                'name' => 'Asignación de Activo',
                'route_name' => 'gestionti.asignaciones.index',
                'permission_name' => 'screens.gestionti-asignaciones.manage',
                'icon' => 'user-plus',
                'order' => 31,
            ],
            [
                'slug' => 'gestionti-presupuestos-proyecto',
                'module' => 'GestionTI',
                'group_label' => 'Presupuesto de Proyectos',
                'name' => 'Presupuesto por Proyecto',
                'route_name' => 'gestionti.presupuestos-proyecto.index',
                'permission_name' => 'screens.gestionti-presupuestos-proyecto.manage',
                'icon' => 'banknotes',
                'order' => 1,
            ],
            [
                'slug' => 'gestionti-facturas',
                'module' => 'GestionTI',
                'group_label' => 'Compras',
                'name' => 'Facturación',
                'route_name' => 'gestionti.facturas.index',
                'permission_name' => 'screens.gestionti-facturas.manage',
                'icon' => 'document-currency-dollar',
                'order' => 23,
            ],
            [
                'slug' => 'gestionti-stock',
                'module' => 'GestionTI',
                'group_label' => 'Inventarios',
                'name' => 'Stock',
                'route_name' => 'gestionti.stock.index',
                'permission_name' => 'screens.gestionti-stock.manage',
                'icon' => 'cube',
                'order' => 32,
            ],
            [
                'slug' => 'gestionti-registro-manual',
                'module' => 'GestionTI',
                'group_label' => 'Inventarios',
                'name' => 'Registro Manual de Activo',
                'route_name' => 'gestionti.registro-manual.index',
                'permission_name' => 'screens.gestionti-registro-manual.manage',
                'icon' => 'plus-circle',
                'order' => 33,
            ],
            [
                'slug' => 'gestionti-mantenimientos',
                'module' => 'GestionTI',
                'group_label' => 'Inventarios',
                'name' => 'Mantenimiento',
                'route_name' => 'gestionti.mantenimientos.index',
                'permission_name' => 'screens.gestionti-mantenimientos.manage',
                'icon' => 'wrench-screwdriver',
                'order' => 34,
            ],
            [
                'slug' => 'gestionti-ficha-activo',
                'module' => 'GestionTI',
                'group_label' => 'Inventarios',
                'name' => 'Ficha de Activo',
                'route_name' => 'gestionti.ficha-activo.index',
                'permission_name' => 'screens.gestionti-ficha-activo.manage',
                'icon' => 'clock',
                'order' => 35,
            ],
            [
                'slug' => 'gestionti-tipos-aviso',
                'module' => 'GestionTI',
                'group_label' => 'General',
                'name' => 'Configuración de Avisos',
                'route_name' => 'gestionti.tipos-aviso.index',
                'permission_name' => 'screens.gestionti-tipos-aviso.manage',
                'icon' => 'bell-alert',
                'order' => 3,
            ],
            [
                'slug' => 'gestionti-avisos-historial',
                'module' => 'GestionTI',
                'group_label' => 'General',
                'name' => 'Historial de Avisos',
                'route_name' => 'gestionti.avisos-historial.index',
                'permission_name' => 'screens.gestionti-avisos-historial.manage',
                'icon' => 'clock',
                'order' => 4,
            ],
            [
                'slug' => 'gestionti-almacenamiento-documentos',
                'module' => 'GestionTI',
                'group_label' => 'General',
                'name' => 'Configuración de Almacenamiento',
                'route_name' => 'gestionti.almacenamiento-documentos.index',
                'permission_name' => 'screens.gestionti-almacenamiento-documentos.manage',
                'icon' => 'cloud-arrow-up',
                'order' => 5,
            ],
        ];

        foreach ($screens as $screen) {
            Screen::updateOrCreate(['slug' => $screen['slug']], $screen);
        }

        Role::findOrCreate('Administrador', 'web')->givePermissionTo(
            collect($screens)->pluck('permission_name')->all()
        );

        // Estatus de Activo — 5 valores base que la lógica de negocio del
        // ciclo de vida de activos (Fase 3) referenciará por `codigo`. El
        // CRUD de esta pantalla permite agregar más, pero estos 5 deben
        // existir siempre después de sembrar.
        $estatusBase = [
            'en_stock' => 'En stock',
            'reservado' => 'Reservado',
            'asignado' => 'Asignado',
            'en_reparacion' => 'En reparación',
            'baja' => 'Baja',
        ];

        foreach ($estatusBase as $codigo => $nombre) {
            EstatusActivo::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre]);
        }

        $this->seedTiposAviso();

        // Fase 5 (SharePoint) — crea el singleton de configuración con sus
        // defaults SOLO si no existe todavía (`firstOrCreate` dentro de
        // `current()`); si ya existe (alguien ya lo configuró desde la
        // pantalla), este seeder NUNCA lo pisa.
        ConfiguracionDocumentos::current();
    }

    /**
     * "Configuración de Avisos" (sección 7.15 / 4 del spec original) — 8 de
     * los 9 `TipoAviso` del spec. `SIC_LIGA_POR_EXPIRAR` se difiere a Fase 5
     * a propósito: depende de `FormularioSicLink`, que hoy es solo tabla sin
     * pantalla ni flujo real (ni siquiera tiene columna de expiración). Ver
     * docs/gestionti-progreso.md.
     *
     * `updateOrCreate` por `codigo`, mismo criterio idempotente del resto de
     * este seeder — los destinatarios por defecto solo se crean la primera
     * vez (si el `TipoAviso` ya existía, no se tocan sus destinatarios, para
     * no pisar lo que el usuario haya reconfigurado desde la pantalla).
     */
    private function seedTiposAviso(): void
    {
        $tipos = [
            [
                'codigo' => 'SIC_AUTORIZADA',
                'descripcion' => 'Solicitud de SIC autorizada',
                'entidad_relacionada' => 'SolicitudSicBorrador',
                'evento_disparador' => TipoAviso::EVENTO_SIC_AUTORIZADA,
                'plantilla_mensaje' => 'Tu solicitud de SIC {{folio}} para {{empleado}} fue autorizada.',
                'destinatarios' => [['tipo_destinatario' => 'dinamico_solicitante']],
            ],
            [
                'codigo' => 'SIC_RECHAZADA',
                'descripcion' => 'Solicitud de SIC rechazada',
                'entidad_relacionada' => 'SolicitudSicBorrador',
                'evento_disparador' => TipoAviso::EVENTO_SIC_RECHAZADA,
                'plantilla_mensaje' => 'Tu solicitud de SIC {{folio}} para {{empleado}} fue rechazada.',
                'destinatarios' => [['tipo_destinatario' => 'dinamico_solicitante']],
            ],
            [
                'codigo' => 'MANTENIMIENTO_PROXIMO_VENCER',
                'descripcion' => 'Mantenimiento próximo a vencer',
                'entidad_relacionada' => 'Mantenimiento',
                'evento_disparador' => TipoAviso::EVENTO_MANTENIMIENTO_PROXIMO_VENCER,
                'plantilla_mensaje' => 'El mantenimiento {{tipo}} del activo {{activo}} vence el {{fecha_programada}}.',
                'destinatarios' => [['tipo_destinatario' => 'rol_fijo', 'rol_nombre' => 'Almacén/TI']],
            ],
            [
                'codigo' => 'MANTENIMIENTO_VENCIDO',
                'descripcion' => 'Mantenimiento vencido',
                'entidad_relacionada' => 'Mantenimiento',
                'evento_disparador' => TipoAviso::EVENTO_MANTENIMIENTO_VENCIDO,
                'plantilla_mensaje' => 'El mantenimiento {{tipo}} del activo {{activo}} venció el {{fecha_programada}} y sigue sin realizarse.',
                'destinatarios' => [['tipo_destinatario' => 'rol_fijo', 'rol_nombre' => 'Almacén/TI']],
            ],
            [
                'codigo' => 'STOCK_BAJO_MINIMO',
                'descripcion' => 'Stock por debajo del mínimo',
                'entidad_relacionada' => 'StockMinimo',
                'evento_disparador' => TipoAviso::EVENTO_STOCK_BAJO_MINIMO,
                'plantilla_mensaje' => 'El stock de {{tipo_equipo}} en {{ubicacion}} está en {{stock_actual}}, por debajo del mínimo ({{cantidad_minima}}).',
                'destinatarios' => [['tipo_destinatario' => 'rol_fijo', 'rol_nombre' => 'Almacén/TI']],
            ],
            [
                'codigo' => 'PRESUPUESTO_COSTO_PENDIENTE',
                'descripcion' => 'Costo de artículo de presupuesto pendiente de captura',
                'entidad_relacionada' => 'ProyectoPresupuestoArticulo',
                'evento_disparador' => TipoAviso::EVENTO_PRESUPUESTO_COSTO_PENDIENTE,
                'plantilla_mensaje' => 'El costo del artículo "{{descripcion}}" del proyecto {{proyecto}} sigue pendiente de captura.',
                'destinatarios' => [['tipo_destinatario' => 'dinamico_responsable']],
            ],
            [
                'codigo' => 'PRESUPUESTO_LISTO_PARA_AUTORIZAR',
                'descripcion' => 'Presupuesto de proyecto listo para autorizar',
                'entidad_relacionada' => 'ProyectoPresupuesto',
                'evento_disparador' => TipoAviso::EVENTO_PRESUPUESTO_LISTO_PARA_AUTORIZAR,
                'plantilla_mensaje' => 'El presupuesto del proyecto {{proyecto}} ya tiene todos sus costos capturados y está listo para enviarse a autorización.',
                'destinatarios' => [['tipo_destinatario' => 'dinamico_responsable']],
            ],
            [
                'codigo' => 'PROYECTO_AUTORIZADO',
                'descripcion' => 'Proyecto autorizado',
                'entidad_relacionada' => 'ProyectoPresupuesto',
                'evento_disparador' => TipoAviso::EVENTO_PROYECTO_AUTORIZADO,
                'plantilla_mensaje' => 'El proyecto {{proyecto}} fue autorizado.',
                'destinatarios' => [['tipo_destinatario' => 'dinamico_responsable']],
            ],
        ];

        foreach ($tipos as $tipo) {
            $destinatarios = $tipo['destinatarios'];
            unset($tipo['destinatarios']);

            $tipoAviso = TipoAviso::updateOrCreate(['codigo' => $tipo['codigo']], $tipo);

            if ($tipoAviso->wasRecentlyCreated) {
                foreach ($destinatarios as $destinatario) {
                    $tipoAviso->destinatarios()->create($destinatario);
                }
            }
        }
    }
}
