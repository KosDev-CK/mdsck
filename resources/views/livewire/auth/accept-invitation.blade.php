<div>
    @if ($status === 'pending')
        <h1 class="text-lg font-semibold text-gray-900 mb-1">Te invitaron a MDS</h1>
        <p class="text-sm text-gray-500 mb-6">
            {{ $invitation->email }} &middot; confirma para activar tu acceso.
        </p>

        <button
            wire:click="accept"
            class="w-full inline-flex justify-center items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
            wire:loading.attr="disabled"
            wire:target="accept"
        >
            <span wire:loading.remove wire:target="accept">Aceptar invitación</span>
            <span wire:loading wire:target="accept">Activando…</span>
        </button>
    @elseif ($status === 'accepted')
        <h1 class="text-lg font-semibold text-gray-900 mb-2">Invitación ya utilizada</h1>
        <p class="text-sm text-gray-500 mb-6">Esta invitación ya fue aceptada anteriormente.</p>
        <a href="{{ route('login') }}" class="text-sm text-indigo-600 hover:text-indigo-500">Ir a iniciar sesión</a>
    @elseif ($status === 'expired')
        <h1 class="text-lg font-semibold text-gray-900 mb-2">Invitación vencida</h1>
        <p class="text-sm text-gray-500 mb-6">Pide al administrador que te envíe una nueva invitación.</p>
    @elseif ($status === 'revoked')
        <h1 class="text-lg font-semibold text-gray-900 mb-2">Invitación revocada</h1>
        <p class="text-sm text-gray-500 mb-6">Esta invitación ya no es válida.</p>
    @else
        <h1 class="text-lg font-semibold text-gray-900 mb-2">Invitación no encontrada</h1>
        <p class="text-sm text-gray-500 mb-6">Verifica el enlace o pide una nueva invitación.</p>
    @endif
</div>
