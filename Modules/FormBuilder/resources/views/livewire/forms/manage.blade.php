<div>
    @push('page-title')
        Formularios
    @endpush
    <p class="text-sm text-gray-500 mb-6 dark:text-gray-400">
        Crea plantillas de formulario y publícalas para poder enviarlas desde "Mis Formularios".
    </p>

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert variant="error" class="mb-6">{{ session('error') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <x-ui.card padding="p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4 dark:text-gray-100">Nuevo formulario</h2>

            <form wire:submit="create" class="space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre</label>
                    <input wire:model="name" type="text" id="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                    @error('name') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción</label>
                    <textarea wire:model="description" id="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"></textarea>
                </div>

                <x-ui.button type="submit">Crear formulario</x-ui.button>
            </form>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100 dark:text-gray-400 dark:border-gray-800">
                            <th class="py-2">Nombre</th>
                            <th class="py-2">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($forms as $form)
                            <tr wire:key="form-row-{{ $form->id }}" class="border-b border-gray-50 dark:border-gray-800 {{ $selectedFormId === $form->id ? 'bg-gray-50 dark:bg-gray-800/50' : '' }}">
                                <td class="py-2">
                                    <button wire:click="selectForm({{ $form->id }})" class="font-medium text-gray-900 hover:text-primary dark:text-gray-100 text-left">
                                        {{ $form->name }}
                                    </button>
                                    <div class="text-xs text-gray-400">{{ $form->fields_count }} campos &middot; {{ $form->submissions_count }} envíos</div>
                                </td>
                                <td class="py-2">
                                    <x-ui.badge :color="$form->status === 'published' ? 'emerald' : 'gray'">
                                        {{ $form->status === 'published' ? 'Publicado' : 'Borrador' }}
                                    </x-ui.badge>
                                </td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    <button wire:click="delete({{ $form->id }})" wire:confirm="¿Eliminar este formulario?" class="text-red-600 hover:text-red-500 text-sm dark:text-red-400 dark:hover:text-red-300">
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 text-center text-gray-400 dark:text-gray-500">Sin formularios todavía.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $forms->links() }}</div>
        </x-ui.card>

        <x-ui.card padding="p-5" class="lg:col-span-2">
            @if ($selectedForm)
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $selectedForm->name }}</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $selectedForm->description }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.button size="sm" variant="secondary" wire:click="togglePublish({{ $selectedForm->id }})">
                            {{ $selectedForm->status === 'published' ? 'Despublicar' : 'Publicar' }}
                        </x-ui.button>
                        <a href="{{ route('formbuilder.forms.builder', $selectedForm) }}">
                            <x-ui.button size="sm">Construir campos</x-ui.button>
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 border-b border-gray-100 dark:text-gray-400 dark:border-gray-800">
                                <th class="py-2">Campo</th>
                                <th class="py-2">Tipo</th>
                                <th class="py-2">Obligatorio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($selectedForm->fields as $field)
                                <tr class="border-b border-gray-50 dark:border-gray-800">
                                    <td class="py-2 text-gray-900 dark:text-gray-100">{{ $field->label }}</td>
                                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $field->typeLabel() }}</td>
                                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $field->is_required ? 'Sí' : 'No' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-6 text-center text-gray-400 dark:text-gray-500">
                                        Esta plantilla no tiene campos todavía. Usa "Construir campos" para agregarlos.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-16 text-center text-gray-400 dark:text-gray-500">
                    Selecciona un formulario de la lista para administrarlo.
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
