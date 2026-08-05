<div class="max-w-3xl space-y-6">
    <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Mensajes</h1>

    @if (session('status'))
        <x-ui.alert variant="success">
            {{ session('status') }}
        </x-ui.alert>
    @endif

    <x-ui.card padding="p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-4 dark:text-gray-100">Enviar aviso</h2>

        <form wire:submit="send" class="space-y-4">
            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Asunto</label>
                <input wire:model="subject" type="text" id="subject" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">
                @error('subject') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="body" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mensaje</label>
                <textarea wire:model="body" id="body" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"></textarea>
                @error('body') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-300">Destinatarios</label>

                <label class="flex items-center gap-2 text-sm text-gray-700 mb-3 dark:text-gray-300">
                    <input wire:model.live="sendToAll" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                    Todos los usuarios activos
                </label>

                @unless ($sendToAll)
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Buscar por nombre o correo…" class="mb-2 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100">

                    <div class="max-h-56 overflow-y-auto rounded-md border border-gray-200 divide-y divide-gray-100 dark:border-gray-700 dark:divide-gray-800">
                        @forelse ($users as $user)
                            <label class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" value="{{ $user->id }}" wire:model="recipientIds" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                                <span class="min-w-0">
                                    <span class="block font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</span>
                                    <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $user->email }}</span>
                                </span>
                            </label>
                        @empty
                            <div class="px-3 py-4 text-sm text-gray-400 text-center dark:text-gray-500">No hay usuarios que coincidan.</div>
                        @endforelse
                    </div>
                @endunless

                @error('recipientIds') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>

            <x-ui.button type="submit">
                Enviar mensaje
            </x-ui.button>
        </form>
    </x-ui.card>
</div>
