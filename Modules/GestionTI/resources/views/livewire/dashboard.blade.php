<div class="space-y-6">
    @push('page-title')
        Dashboard
    @endpush

    {{-- Sección 1 — Métricas globales. Cada tarjeta solo aparece si el
         componente calculó su dato (permiso real, no solo visual — ver
         Dashboard::render()). --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @if ($sicsEnCaptura !== null)
            <a href="{{ route('gestionti.solicitudes-sic.index') }}" class="block">
                <x-ui.stat-tile
                    label="SICs en captura"
                    :value="$sicsEnCaptura"
                    icon="document-text"
                    color="info"
                    hint="Capturadas o con SIC creada"
                    class="hover:ring-1 hover:ring-primary/40 transition"
                />
            </a>
        @endif

        @if ($solicitudesProveedorPendientes !== null)
            <a href="{{ route('gestionti.solicitudes-proveedor.index') }}" class="block">
                <x-ui.stat-tile
                    label="Solicitudes a proveedor pendientes"
                    :value="$solicitudesProveedorPendientes"
                    icon="shopping-cart"
                    color="warning"
                    hint="Solicitadas o parcialmente recibidas"
                    class="hover:ring-1 hover:ring-primary/40 transition"
                />
            </a>
        @endif

        @if ($facturasPendientes !== null)
            <a href="{{ route('gestionti.facturas.index') }}" class="block">
                <x-ui.stat-tile
                    label="Facturas pendientes de pago"
                    :value="$facturasPendientes"
                    icon="document-currency-dollar"
                    color="danger"
                    class="hover:ring-1 hover:ring-primary/40 transition"
                />
            </a>
        @endif

        @if ($facturasDiferencia !== null)
            <a href="{{ route('gestionti.facturas.index') }}" class="block">
                <x-ui.stat-tile
                    label="Diferencias a revisar"
                    :value="$facturasDiferencia"
                    icon="exclamation-triangle"
                    color="danger"
                    hint="Facturas marcadas con diferencia"
                    class="hover:ring-1 hover:ring-primary/40 transition"
                />
            </a>
        @endif

        @if ($stockBajoMinimo !== null)
            <a href="{{ route('gestionti.stock.index') }}" class="block">
                <x-ui.stat-tile
                    label="Alertas de stock bajo mínimo"
                    :value="$stockBajoMinimo->count()"
                    icon="exclamation-triangle"
                    color="warning"
                    hint="Tipo/ubicación por debajo del mínimo configurado"
                    class="hover:ring-1 hover:ring-primary/40 transition"
                />
            </a>
        @endif

        @if ($mantenimientosProximos !== null)
            <a href="{{ route('gestionti.mantenimientos.index') }}" class="block">
                <x-ui.stat-tile
                    label="Mantenimientos próximos"
                    :value="$mantenimientosProximos"
                    icon="wrench-screwdriver"
                    color="info"
                    hint="Programados en los próximos 7 días"
                    class="hover:ring-1 hover:ring-primary/40 transition"
                />
            </a>
        @endif
    </div>

    @if ($activosPorEstatus !== null)
        <x-ui.card padding="p-5">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Activos por estatus</h3>
                <a href="{{ route('gestionti.stock.index') }}" class="text-sm text-primary hover:brightness-90">
                    Ver Stock
                </a>
            </div>

            @if ($activosPorEstatus->isEmpty())
                <x-ui.empty-state icon="archive-box" title="Sin activos registrados todavía" />
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($activosPorEstatus as $item)
                        <div wire:key="estatus-{{ $item->codigo }}" class="rounded-lg border border-gray-100 dark:border-gray-800 p-3 text-center">
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $item->cantidad }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item->nombre }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    @endif

    @if ($stockPorTipo !== null)
        <x-ui.card padding="p-5">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Stock disponible por tipo</h3>
                <a href="{{ route('gestionti.stock.index') }}" class="text-sm text-primary hover:brightness-90">
                    Ver Stock
                </a>
            </div>

            <x-ui.table :headers="['Tipo de equipo', 'En stock']" :empty="$stockPorTipo->isEmpty()" emptyTitle="Sin stock disponible">
                @foreach ($stockPorTipo as $item)
                    <tr wire:key="stock-tipo-{{ $loop->index }}" class="border-b border-gray-50 dark:border-gray-800/60 last:border-0">
                        <td class="py-2 text-gray-900 dark:text-gray-100">{{ $item->tipo }}</td>
                        <td class="py-2 text-gray-700 dark:text-gray-300">{{ $item->cantidad }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    {{-- Sección 2 — "Mis pendientes": panel personalizado, solo si el
         usuario tiene un Empleado vinculado por correo. --}}
    <x-ui.card padding="p-5">
        <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-gray-200">Mis pendientes</h3>

        @if ($empleado === null)
            <x-ui.empty-state
                icon="user"
                title="No tienes un registro de empleado vinculado a tu cuenta"
                description="Este panel personalizado necesita un Empleado en el catálogo con el mismo correo que tu cuenta."
            />
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                @if ($misPendientes['costos_pendientes'] !== null)
                    <a href="{{ route('gestionti.presupuestos-proyecto.index') }}" class="block">
                        <x-ui.stat-tile
                            label="Costos de proyecto por capturar"
                            :value="$misPendientes['costos_pendientes']"
                            icon="banknotes"
                            color="info"
                            class="hover:ring-1 hover:ring-primary/40 transition"
                        />
                    </a>
                @endif

                @if ($misPendientes['autorizaciones_accionables'] !== null)
                    <a href="{{ route('gestionti.presupuestos-proyecto.index') }}" class="block">
                        <x-ui.stat-tile
                            label="Autorizaciones pendientes de mi aprobación"
                            :value="$misPendientes['autorizaciones_accionables']"
                            icon="banknotes"
                            color="warning"
                            class="hover:ring-1 hover:ring-primary/40 transition"
                        />
                    </a>
                @endif

                <x-ui.stat-tile
                    label="Notificaciones sin leer"
                    :value="$misPendientes['notificaciones_sin_leer']"
                    icon="bell"
                    color="primary"
                />
            </div>
        @endif
    </x-ui.card>
</div>
