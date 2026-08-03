<div>
    <h1 class="text-lg font-semibold text-gray-900 mb-1">Módulos</h1>
    <p class="text-sm text-gray-500 mb-6">Activa o desactiva módulos instalados sin afectar el resto del sitio.</p>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b border-gray-100">
                    <th class="py-2">Módulo</th>
                    <th class="py-2">Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($modules as $module)
                    <tr class="border-b border-gray-50">
                        <td class="py-2 font-medium text-gray-900">{{ $module->getName() }}</td>
                        <td class="py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs {{ $module->isEnabled() ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $module->isEnabled() ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="py-2 text-right">
                            <button
                                wire:click="toggle('{{ $module->getName() }}')"
                                wire:confirm="¿{{ $module->isEnabled() ? 'Desactivar' : 'Activar' }} el módulo {{ $module->getName() }}?"
                                class="text-sm {{ $module->isEnabled() ? 'text-red-600 hover:text-red-500' : 'text-indigo-600 hover:text-indigo-500' }}"
                            >
                                {{ $module->isEnabled() ? 'Desactivar' : 'Activar' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="py-6 text-center text-gray-400">No hay módulos instalados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
