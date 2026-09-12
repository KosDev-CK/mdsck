<div>
    @push('page-title')
        Reportes
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    <x-ui.card padding="p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Historial de cierres</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    El cierre diario se genera automáticamente cada madrugada. Descarga el Excel de cualquier día ya cerrado.
                </p>
            </div>
        </div>

        <x-ui.table
            :headers="['Periodo', 'Tipo', 'Total de tickets', 'Generado el', '']"
            :empty="$reports->isEmpty()"
            empty-title="Sin cierres generados todavía"
            empty-description="El primer cierre diario aparecerá aquí después de la primera corrida de php artisan sdp:daily-close."
        >
            @foreach ($reports as $report)
                <tr wire:key="reporte-{{ $report->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $report->periodo->format('d/m/Y') }}</td>
                    <td class="py-2">
                        <x-ui.badge color="indigo">{{ ucfirst($report->tipo) }}</x-ui.badge>
                    </td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $report->resumen_metricas['total'] ?? 0 }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $report->generado_en->format('d/m/Y H:i') }}</td>
                    <td class="py-2 text-right">
                        <x-ui.button wire:click="download({{ $report->id }})" variant="secondary" size="sm">
                            Descargar
                        </x-ui.button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.help-modal titulo="Reportes" :pdf-url="route('mesaservicio.ayuda.pdf', 'reportes')">
        @include('mesaservicio::ayuda.contenido', ['contenido' => \Modules\MesaServicio\Support\Ayuda\AyudaCatalog::contenido('reportes')])
    </x-ui.help-modal>
</div>
