<div>
    @push('page-title')
        Dashboard Analítico
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    <x-ui.toast-group>
        @if (session('status'))
            <x-ui.toast variant="success">{{ session('status') }}</x-ui.toast>
        @endif

        @if (session('error'))
            <x-ui.toast variant="error">{{ session('error') }}</x-ui.toast>
        @endif
    </x-ui.toast-group>

    <x-ui.card padding="p-5">
        <x-ui.empty-state
            icon="chart-pie"
            title="En construcción"
            description="Este dashboard mostrará análisis más profundo de Mesa de Servicio: patrones, tendencias históricas y comparativas. El contenido se irá agregando en próximas iteraciones."
        />
    </x-ui.card>

    <x-ui.help-modal titulo="Dashboard Analítico" :pdf-url="route('mesaservicio.ayuda.pdf', 'dashboard-analitico')">
        @include('mesaservicio::ayuda.contenido', ['contenido' => \Modules\MesaServicio\Support\Ayuda\AyudaCatalog::contenido('dashboard-analitico')])
    </x-ui.help-modal>
</div>
