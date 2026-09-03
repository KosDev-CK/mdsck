<div class="space-y-6">
    @push('page-title')
        Configuración de Almacenamiento
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success">{{ session('status') }}</x-ui.alert>
    @endif

    <x-ui.card padding="p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-1 dark:text-gray-100">Documentos digitalizados en SharePoint</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            Marca qué tipos de documento se suben a SharePoint (vía Microsoft Graph) en vez de guardarse en este servidor.
            Los tipos sin marcar siguen funcionando exactamente igual que antes.
        </p>

        <form wire:submit="save" class="space-y-4">
            <div class="space-y-3">
                @foreach ($tiposDocumento as $tipo)
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" wire:model="tiposSharepoint" value="{{ $tipo }}" class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary dark:bg-gray-800 dark:border-gray-700">
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $this->tipoLabel($tipo) }}</span>
                    </label>
                @endforeach
            </div>

            @error('tiposSharepoint')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            @error('tiposSharepoint.*')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            <div class="flex justify-end">
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
