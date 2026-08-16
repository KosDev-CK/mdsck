<div class="space-y-6">
    @push('page-title')
        Branding
    @endpush

    @if (session('status'))
        <x-ui.alert variant="success">
            {{ session('status') }}
        </x-ui.alert>
    @endif

    <x-ui.card padding="p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-4 dark:text-gray-100">Identidad visual</h2>

        <form wire:submit="saveIdentity" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Logotipo</label>
                    <p class="text-xs text-gray-400 mb-2 dark:text-gray-500">Se usa en el menú lateral y en el encabezado de los correos.</p>

                    <div class="flex items-center gap-4">
                        <div class="h-16 w-16 rounded-md border border-gray-200 bg-white flex items-center justify-center overflow-hidden shrink-0 dark:border-gray-700">
                            @if ($logo)
                                <img src="{{ $logo->temporaryUrl() }}" class="max-h-full max-w-full object-contain" alt="Logo">
                            @elseif ($settings->logoUrl())
                                <img src="{{ $settings->logoUrl() }}" class="max-h-full max-w-full object-contain" alt="Logo">
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-500">Sin logo</span>
                            @endif
                        </div>
                        <div class="space-y-2">
                            <input wire:model="logo" type="file" accept="image/*" class="text-sm text-gray-600 dark:text-gray-300">
                            @if ($settings->logoUrl())
                                <button wire:click="removeLogo" wire:confirm="¿Quitar el logotipo actual?" type="button" class="block text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">
                                    Quitar logotipo
                                </button>
                            @endif
                        </div>
                    </div>
                    @error('logo') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Favicon</label>
                    <p class="text-xs text-gray-400 mb-2 dark:text-gray-500">Ícono que se muestra en la pestaña del navegador.</p>

                    <div class="flex items-center gap-4">
                        <div class="h-16 w-16 rounded-md border border-gray-200 bg-white flex items-center justify-center overflow-hidden shrink-0 dark:border-gray-700">
                            @if ($favicon)
                                <img src="{{ $favicon->temporaryUrl() }}" class="max-h-full max-w-full object-contain" alt="Favicon">
                            @elseif ($settings->faviconUrl())
                                <img src="{{ $settings->faviconUrl() }}" class="max-h-full max-w-full object-contain" alt="Favicon">
                            @else
                                <span class="text-xs text-gray-400 dark:text-gray-500">Sin favicon</span>
                            @endif
                        </div>
                        <div class="space-y-2">
                            <input wire:model="favicon" type="file" accept="image/*" class="text-sm text-gray-600 dark:text-gray-300">
                            @if ($settings->faviconUrl())
                                <button wire:click="removeFavicon" wire:confirm="¿Quitar el favicon actual?" type="button" class="block text-sm text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">
                                    Quitar favicon
                                </button>
                            @endif
                        </div>
                    </div>
                    @error('favicon') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <x-ui.button type="submit">
                Guardar identidad visual
            </x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card padding="p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-1 dark:text-gray-100">Colores</h2>
        <p class="text-sm text-gray-500 mb-4 dark:text-gray-400">Se aplican de inmediato a botones, alertas y estados en todo el sitio.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ([
                ['prop' => 'primaryColor', 'label' => 'Primario'],
                ['prop' => 'successColor', 'label' => 'Success'],
                ['prop' => 'dangerColor', 'label' => 'Danger'],
                ['prop' => 'warningColor', 'label' => 'Warning'],
                ['prop' => 'infoColor', 'label' => 'Info'],
            ] as $field)
                <div x-data="{ color: @entangle($field['prop']) }">
                    <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-300">{{ $field['label'] }}</label>
                    <div class="flex items-center gap-2">
                        <input type="color" x-model="color" class="h-9 w-9 rounded border border-gray-300 cursor-pointer dark:border-gray-700">
                        <input type="text" x-model="color" maxlength="7" class="w-28 rounded-md border-gray-300 shadow-sm sm:text-sm font-mono uppercase dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    @error($field['prop']) <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2 mt-6">
            <x-ui.button wire:click="saveColors">
                Guardar colores
            </x-ui.button>

            <input wire:model="newPresetName" type="text" placeholder="Nombre del preset…" class="rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
            <x-ui.button wire:click="saveAsPreset" variant="secondary">
                Guardar como preset
            </x-ui.button>
        </div>
        @error('newPresetName') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </x-ui.card>

    <x-ui.card padding="p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-1 dark:text-gray-100">Barra superior y menú lateral</h2>
        <p class="text-sm text-gray-500 mb-4 dark:text-gray-400">El menú lateral usa dos tonos: uno para el encabezado (logo/nombre) y otro para el cuerpo (los enlaces). La barra superior conserva su color solo en modo claro; en modo oscuro sigue usando el gris oscuro de siempre.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ([
                ['prop' => 'topbarColor', 'label' => 'Barra superior'],
                ['prop' => 'sidebarHeaderColor', 'label' => 'Menú — encabezado'],
                ['prop' => 'sidebarBodyColor', 'label' => 'Menú — cuerpo'],
            ] as $field)
                <div x-data="{ color: @entangle($field['prop']) }">
                    <label class="block text-sm font-medium text-gray-700 mb-1 dark:text-gray-300">{{ $field['label'] }}</label>
                    <div class="flex items-center gap-2">
                        <input type="color" x-model="color" class="h-9 w-9 rounded border border-gray-300 cursor-pointer dark:border-gray-700">
                        <input type="text" x-model="color" maxlength="7" class="w-28 rounded-md border-gray-300 shadow-sm sm:text-sm font-mono uppercase dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                    </div>
                    @error($field['prop']) <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2 mt-6">
            <x-ui.button wire:click="saveColors">
                Guardar colores
            </x-ui.button>

            <input wire:model="newPresetName" type="text" placeholder="Nombre del preset…" class="rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
            <x-ui.button wire:click="saveAsPreset" variant="secondary">
                Guardar como preset
            </x-ui.button>
        </div>
        @error('newPresetName') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </x-ui.card>

    <x-ui.card padding="p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-1 dark:text-gray-100">Presets guardados</h2>
        <p class="text-sm text-gray-500 mb-4 dark:text-gray-400">Un preset guarda toda la configuración de colores del sitio (marca + barra superior + menú), no solo los 5 colores básicos. "Predeterminado" regresa a los colores originales de la plantilla.</p>

        <div class="space-y-2">
            @foreach ($presets as $preset)
                <div class="flex items-center justify-between gap-4 rounded-md border border-gray-100 px-3 py-2 dark:border-gray-800">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex shrink-0">
                            @foreach (\App\Models\BrandingPreset::COLOR_FIELDS as $colorField)
                                <span class="h-5 w-5 rounded-full border-2 border-white -ml-1.5 first:ml-0 dark:border-gray-900" style="background-color: {{ $preset->$colorField }}"></span>
                            @endforeach
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-900 truncate dark:text-gray-100">{{ $preset->name }}</div>
                            @if ($preset->is_system)
                                <div class="text-xs text-gray-400 dark:text-gray-500">Preset de referencia</div>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <x-ui.button wire:click="applyPreset({{ $preset->id }})" size="sm" variant="secondary">
                            Aplicar
                        </x-ui.button>
                        @unless ($preset->is_system)
                            <button wire:click="deletePreset({{ $preset->id }})" wire:confirm="¿Eliminar el preset {{ $preset->name }}?" class="text-red-600 hover:text-red-500 text-sm dark:text-red-400 dark:hover:text-red-300">
                                Eliminar
                            </button>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>
    </x-ui.card>
</div>
