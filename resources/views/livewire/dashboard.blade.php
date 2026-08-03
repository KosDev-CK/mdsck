<div>
    <h1 class="text-lg font-semibold text-gray-900 mb-6">Bienvenido, {{ auth()->user()->name }}</h1>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-sm text-gray-500">Tus perfiles</div>
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach (auth()->user()->getRoleNames() as $role)
                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">
                        {{ $role }}
                    </span>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-sm text-gray-500">Último acceso</div>
            <div class="mt-2 text-sm text-gray-900">
                {{ auth()->user()->last_login_at?->diffForHumans() ?? 'Primer acceso' }}
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="text-sm text-gray-500">Verificación en dos pasos</div>
            <div class="mt-2 text-sm {{ auth()->user()->hasTwoFactorEnabled() ? 'text-emerald-600' : 'text-amber-600' }}">
                {{ auth()->user()->hasTwoFactorEnabled() ? 'Activada' : 'No activada' }}
            </div>
        </div>
    </div>
</div>
