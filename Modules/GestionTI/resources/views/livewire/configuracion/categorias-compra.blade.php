<div class="space-y-6">
    @push('page-title')
        Categorías que van a Compra
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    <x-ui.toast-group>
        @if (session('status'))
            <x-ui.toast variant="success">{{ session('status') }}</x-ui.toast>
        @endif
    </x-ui.toast-group>

    <x-ui.card padding="p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-1 dark:text-gray-100">Categorías de Artículo que van a Compra</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            Marca qué categorías representan una compra real de equipo físico (laptops, PCs, impresoras, etc.) que debe pasar por "Solicitud a Proveedores". Las categorías sin marcar (telefonía, licencias, correo, etc.) no aparecen en el selector de SICs disponibles de esa pantalla — sus SICs siguen su seguimiento por otra área, sin generar una Solicitud a Proveedores.
        </p>

        <form wire:submit="save" class="space-y-4">
            <div class="space-y-3">
                @foreach ($categorias as $categoria)
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model="categoriasCompra" value="{{ $categoria }}" class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary dark:bg-gray-800 dark:border-gray-700">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $labels[$categoria] ?? $categoria }}</span>
                    </label>
                @endforeach
            </div>

            @error('categoriasCompra')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            @error('categoriasCompra.*')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="flex justify-end">
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.help-modal titulo="Categorías que van a Compra" :pdf-url="route('gestionti.ayuda.pdf', 'categorias-compra')">
        @include('gestionti::ayuda.contenido', ['contenido' => \Modules\GestionTI\Support\Ayuda\AyudaCatalog::contenido('categorias-compra')])
    </x-ui.help-modal>
</div>
