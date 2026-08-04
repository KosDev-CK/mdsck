<div>
    <h1 class="text-lg font-semibold text-gray-900 mb-1">Ejemplo</h1>
    <p class="text-sm text-gray-500 mb-6">
        Módulo de referencia — así se ve un módulo de contenido aislado bajo <code>Modules/Ejemplo</code>,
        con su propia migración, modelo, componente y vista, resuelto vía el mismo sistema de perfiles/pantallas del core.
    </p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">Nuevo registro</h2>

            <form wire:submit="create" class="space-y-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Título</label>
                    <input wire:model="title" type="text" id="title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Descripción</label>
                    <textarea wire:model="description" id="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"></textarea>
                </div>

                <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    Agregar
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100">
                            <th class="py-2">Título</th>
                            <th class="py-2">Descripción</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr class="border-b border-gray-50">
                                <td class="py-2 font-medium text-gray-900 whitespace-nowrap">{{ $item->title }}</td>
                                <td class="py-2 text-gray-500">{{ $item->description }}</td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    <button wire:click="delete({{ $item->id }})" wire:confirm="¿Eliminar este registro?" class="text-red-600 hover:text-red-500 text-sm">
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-6 text-center text-gray-400">Sin registros todavía.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $items->links() }}</div>
        </div>
    </div>
</div>
