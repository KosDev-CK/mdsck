<div>
    <h1 class="text-lg font-semibold text-gray-900 mb-6 dark:text-gray-100">Bienvenido, {{ auth()->user()->name }}</h1>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 xl:gap-6">
        <x-ui.card padding="p-5">
            <div class="text-sm text-gray-500 dark:text-gray-400">Tus perfiles</div>
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach (auth()->user()->getRoleNames() as $role)
                    <x-ui.badge color="indigo">{{ $role }}</x-ui.badge>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card padding="p-5">
            <div class="text-sm text-gray-500 dark:text-gray-400">Último acceso</div>
            <div class="mt-2 text-sm text-gray-900 dark:text-gray-100">
                {{ auth()->user()->last_login_at?->diffForHumans() ?? 'Primer acceso' }}
            </div>
        </x-ui.card>

        <x-ui.card padding="p-5">
            <div class="text-sm text-gray-500 dark:text-gray-400">Verificación en dos pasos</div>
            <div class="mt-2 text-sm {{ auth()->user()->hasTwoFactorEnabled() ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                {{ auth()->user()->hasTwoFactorEnabled() ? 'Activada' : 'No activada' }}
            </div>
        </x-ui.card>
    </div>
</div>
