<div>
    @push('page-title')
        Dashboard de Operación
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
            icon="wrench-screwdriver"
            title="En construcción"
            description="Este dashboard mostrará el día a día operativo del equipo de Mesa de Servicio. El contenido se irá agregando en próximas iteraciones."
        />
    </x-ui.card>

    <x-ui.help-modal titulo="Dashboard de Operación" :pdf-url="route('mesaservicio.ayuda.pdf', 'dashboard-operacion')">
        @include('mesaservicio::ayuda.contenido', ['contenido' => \Modules\MesaServicio\Support\Ayuda\AyudaCatalog::contenido('dashboard-operacion')])
    </x-ui.help-modal>
</div>
