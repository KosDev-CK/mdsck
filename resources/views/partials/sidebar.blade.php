@php
    $screens = \App\Models\Screen::whereNull('parent_id')
        ->where('is_active', true)
        ->orderBy('order')
        ->get()
        ->filter(fn ($screen) => auth()->user()->can($screen->permission_name));
@endphp

<aside class="w-64 shrink-0 bg-gray-900 text-gray-300 flex flex-col">
    <div class="h-16 flex items-center px-6 text-white font-semibold text-lg border-b border-gray-800">
        {{ config('app.name') }}
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1">
        @foreach ($screens as $screen)
            @php $isActive = request()->routeIs($screen->route_name); @endphp
            <a
                href="{{ \Illuminate\Support\Facades\Route::has($screen->route_name) ? route($screen->route_name) : '#' }}"
                class="block px-3 py-2 rounded-md text-sm font-medium {{ $isActive ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 hover:text-white' }}"
            >
                {{ $screen->name }}
            </a>
        @endforeach
    </nav>
</aside>
