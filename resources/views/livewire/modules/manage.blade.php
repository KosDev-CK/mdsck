<div>
    @push('page-title')
        Módulos
    @endpush

    <p class="text-sm text-gray-500 mb-6 dark:text-gray-400">Activa o desactiva módulos instalados sin afectar el resto del sitio.</p>

    <x-ui.card padding="p-5">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100 dark:text-gray-400 dark:border-gray-800">
                        <th class="py-2">Módulo</th>
                        <th class="py-2">Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($modules as $module)
                        <tr class="border-b border-gray-50 dark:border-gray-800">
                            <td class="py-2 font-medium text-gray-900 whitespace-nowrap dark:text-gray-100">{{ $module->getName() }}</td>
                            <td class="py-2 whitespace-nowrap">
                                <x-ui.badge :color="$module->isEnabled() ? 'emerald' : 'gray'">
                                    {{ $module->isEnabled() ? 'Activo' : 'Inactivo' }}
                                </x-ui.badge>
                            </td>
                            <td class="py-2 text-right whitespace-nowrap">
                                <button
                                    wire:click="toggle('{{ $module->getName() }}')"
                                    wire:confirm="¿{{ $module->isEnabled() ? 'Desactivar' : 'Activar' }} el módulo {{ $module->getName() }}?"
                                    class="text-sm {{ $module->isEnabled() ? 'text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300' : 'text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300' }}"
                                >
                                    {{ $module->isEnabled() ? 'Desactivar' : 'Activar' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-6 text-center text-gray-400 dark:text-gray-500">No hay módulos instalados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
