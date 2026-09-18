<div>
    @push('page-title')
        Dashboard Ejecutivo
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
            icon="presentation-chart-line"
            title="En construcción"
            description="Este dashboard mostrará un resumen de alto nivel para dirección: KPIs generales, tendencias e informes de avance de Mesa de Servicio. El contenido se irá agregando en próximas iteraciones."
        />
    </x-ui.card>

    <x-ui.help-modal titulo="Dashboard Ejecutivo" :pdf-url="route('mesaservicio.ayuda.pdf', 'dashboard-ejecutivo')">
        @include('mesaservicio::ayuda.contenido', ['contenido' => \Modules\MesaServicio\Support\Ayuda\AyudaCatalog::contenido('dashboard-ejecutivo')])
    </x-ui.help-modal>
</div>
