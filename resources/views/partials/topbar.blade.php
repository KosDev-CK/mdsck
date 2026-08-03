<header class="h-16 bg-white border-b border-gray-200 flex items-center justify-end px-6 gap-4">
    @livewire('notifications.bell')

    <div class="relative" x-data="{ open: false }">
        <button @click="open = !open" class="flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900">
            <span class="h-8 w-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-medium">
                {{ mb_substr(auth()->user()->name, 0, 1) }}
            </span>
            <span>{{ auth()->user()->name }}</span>
        </button>

        <div
            x-show="open"
            @click.outside="open = false"
            x-cloak
            class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg border border-gray-200 py-1 z-10"
        >
            <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Mi perfil</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Cerrar sesión</button>
            </form>
        </div>
    </div>
</header>
