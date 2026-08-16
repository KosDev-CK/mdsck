<header style="background-color: var(--topbar-bg)" class="h-16 shrink-0 border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 gap-3 dark:border-gray-800">
    <div class="flex items-center gap-3 min-w-0 flex-1">
        <button @click="sidebarOpen = true" class="shrink-0 text-gray-500 hover:text-gray-700 lg:hidden dark:text-gray-400 dark:hover:text-gray-200">
            <x-heroicon-o-bars-3 class="h-6 w-6" />
        </button>

        <h1 class="min-w-0 truncate text-base sm:text-lg font-semibold text-gray-900 dark:text-gray-100">
            @stack('page-title')
        </h1>
    </div>

    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
        <div class="flex items-center gap-2">
            @stack('page-actions')
        </div>

        <x-ui.theme-toggle />

        @livewire('notifications.bell')

        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" class="flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100">
                <x-ui.avatar :name="auth()->user()->name" />
                <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
            </button>

            <div
                x-show="open"
                @click.outside="open = false"
                x-cloak
                class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg border border-gray-200 py-1 z-10 dark:bg-gray-800 dark:border-gray-700"
            >
                <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">Mi perfil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">Cerrar sesión</button>
                </form>
            </div>
        </div>
    </div>
</header>
