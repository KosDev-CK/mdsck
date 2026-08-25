<div>
    @push('page-title')
        Mis Formularios
    @endpush
    <p class="text-sm text-gray-500 mb-6 dark:text-gray-400">
        Genera un enlace único para que alguien externo llene un formulario sin necesidad de iniciar sesión, ligado a un ticket de mesa de servicio.
    </p>

    @if (session('status'))
        <x-ui.alert variant="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert variant="error" class="mb-6">{{ session('error') }}</x-ui.alert>
    @endif

    <x-ui.card padding="p-5" class="mb-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-4 dark:text-gray-100">Generar enlace</h2>

        <form wire:submit="generateLink" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Formulario</label>
                <select wire:model="formId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                    <option value="">Selecciona…</option>
                    @foreach ($forms as $form)
                        <option value="{{ $form->id }}">{{ $form->name }}</option>
                    @endforeach
                </select>
                @error('formId') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Número de ticket</label>
                <input wire:model="ticketNumber" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                @error('ticketNumber') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Correo destino</label>
                <input wire:model="recipientEmail" type="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                @error('recipientEmail') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-3">
                <x-ui.button type="submit">Generar y enviar enlace</x-ui.button>
            </div>
        </form>

        @if ($forms->isEmpty())
            <p class="mt-3 text-sm text-gray-400 dark:text-gray-500">No hay formularios publicados todavía.</p>
        @endif
    </x-ui.card>

    <x-ui.card padding="p-5">
        <h2 class="text-sm font-semibold text-gray-900 mb-4 dark:text-gray-100">Enlaces generados</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-100 dark:text-gray-400 dark:border-gray-800">
                        <th class="py-2">Ticket</th>
                        <th class="py-2">Formulario</th>
                        <th class="py-2">Correo</th>
                        <th class="py-2">Estado</th>
                        <th class="py-2">Generado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($links as $link)
                        @php
                            $status = $link->status();
                            $statusColor = match ($status) {
                                'used' => 'emerald',
                                'expired' => 'amber',
                                'locked' => 'red',
                                default => 'gray',
                            };
                            $statusLabel = match ($status) {
                                'used' => 'Atendido',
                                'expired' => 'Vencido',
                                'locked' => 'Bloqueado',
                                default => 'Pendiente',
                            };
                        @endphp
                        <tr wire:key="link-row-{{ $link->id }}" class="border-b border-gray-50 dark:border-gray-800">
                            <td class="py-2 text-gray-900 dark:text-gray-100">{{ $link->ticket_number }}</td>
                            <td class="py-2 text-gray-500 dark:text-gray-400">{{ $link->form->name }}</td>
                            <td class="py-2 text-gray-500 dark:text-gray-400">{{ $link->recipient_email }}</td>
                            <td class="py-2"><x-ui.badge :color="$statusColor">{{ $statusLabel }}</x-ui.badge></td>
                            <td class="py-2 text-gray-500 dark:text-gray-400">{{ $link->created_at->format('d/m/Y H:i') }}</td>
                            <td class="py-2 text-right whitespace-nowrap">
                                @if ($status === 'used')
                                    <a href="{{ route('formbuilder.links.show', $link) }}" class="text-sm text-primary hover:underline">
                                        Ver respuestas
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-gray-400 dark:text-gray-500">Aún no has generado ningún enlace.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
