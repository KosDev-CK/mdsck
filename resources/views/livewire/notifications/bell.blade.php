<div class="relative" x-data="{ open: false }">
    <button @click="open = !open" class="relative text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>

        @if ($unreadCount > 0)
            <span class="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-red-500 text-white text-[10px] leading-4 text-center">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        @click.outside="open = false"
        x-cloak
        class="absolute right-0 mt-2 w-80 bg-white rounded-md shadow-lg border border-gray-200 z-10 dark:bg-gray-800 dark:border-gray-700"
    >
        <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Notificaciones</span>
                @if ($unreadCount > 0)
                    <button wire:click="markAllAsRead" class="text-xs text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
                        Marcar todas como leídas
                    </button>
                @endif
            </div>
            <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                Total: {{ $totalCount }} · Leídas: {{ $readCount }} · Sin leer: {{ $unreadCount }}
            </div>
        </div>

        <div class="max-h-80 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
            @forelse ($notifications as $notification)
                <div class="group flex items-start gap-1 px-4 py-3 text-sm {{ $notification->read_at ? 'text-gray-500 dark:text-gray-400' : 'text-gray-900 bg-indigo-50/40 dark:text-gray-100 dark:bg-indigo-500/10' }} hover:bg-gray-50 dark:hover:bg-gray-700">
                    <button wire:click="markAsRead('{{ $notification->id }}')" class="flex-1 min-w-0 text-left">
                        <div class="font-medium">{{ $notification->data['title'] ?? 'Notificación' }}</div>
                        <div class="text-xs text-gray-500 mt-0.5 dark:text-gray-400">{{ $notification->data['message'] ?? '' }}</div>
                        <div class="text-xs text-gray-400 mt-1 dark:text-gray-500">{{ $notification->created_at->diffForHumans() }}</div>
                    </button>

                    @if ($notification->read_at)
                        <button
                            wire:click="deleteNotification('{{ $notification->id }}')"
                            wire:confirm="¿Eliminar esta notificación?"
                            title="Eliminar"
                            class="shrink-0 text-gray-400 hover:text-red-600 opacity-0 group-hover:opacity-100 dark:text-gray-500 dark:hover:text-red-400"
                        >
                            <x-heroicon-o-x-mark class="h-4 w-4" />
                        </button>
                    @endif
                </div>
            @empty
                <div class="px-4 py-6 text-sm text-gray-400 text-center dark:text-gray-500">Sin notificaciones</div>
            @endforelse
        </div>
    </div>
</div>
