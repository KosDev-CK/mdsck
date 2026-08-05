<div>
    <h1 class="text-lg font-semibold text-gray-900 mb-1 dark:text-gray-100">Ejemplo</h1>
    <p class="text-sm text-gray-500 mb-6 dark:text-gray-400">
        Módulo de referencia — así se ve un módulo de contenido aislado bajo <code>Modules/Ejemplo</code>,
        con su propia migración, modelo, componente y vista, resuelto vía el mismo sistema de perfiles/pantallas del core.
    </p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <x-ui.card padding="p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4 dark:text-gray-100">Nuevo registro</h2>

            <form wire:submit="create" class="space-y-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Título</label>
                    <input wire:model="title" type="text" id="title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                    @error('title') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descripción</label>
                    <textarea wire:model="description" id="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"></textarea>
                </div>

                <x-ui.button type="submit">
                    Agregar
                </x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card padding="p-5" class="lg:col-span-2">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100 dark:text-gray-400 dark:border-gray-800">
                            <th class="py-2">Título</th>
                            <th class="py-2">Descripción</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr class="border-b border-gray-50 dark:border-gray-800">
                                <td class="py-2 font-medium text-gray-900 whitespace-nowrap dark:text-gray-100">{{ $item->title }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ $item->description }}</td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    <button wire:click="delete({{ $item->id }})" wire:confirm="¿Eliminar este registro?" class="text-red-600 hover:text-red-500 text-sm dark:text-red-400 dark:hover:text-red-300">
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 text-center text-gray-400 dark:text-gray-500">Sin registros todavía.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $items->links() }}</div>
        </x-ui.card>
    </div>
</div>
