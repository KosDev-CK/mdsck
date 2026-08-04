@php
    $groups = \App\Models\Screen::whereNull('parent_id')
        ->where('is_active', true)
        ->orderBy('order')
        ->get()
        ->filter(fn ($screen) => auth()->user()->can($screen->permission_name))
        ->groupBy(fn ($screen) => $screen->group_label ?? 'General');
@endphp

<!-- Backdrop (mobile/tablet only) -->
<div
    x-show="sidebarOpen"
    x-cloak
    x-transition.opacity
    @click="sidebarOpen = false"
    class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"
></div>

<aside
    x-cloak
    :class="{
        '-translate-x-full': !sidebarOpen,
        'translate-x-0': sidebarOpen,
        'lg:w-20': collapsed,
        'lg:w-64': !collapsed,
    }"
    class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-gray-900 text-gray-300 transition-all duration-200 lg:static lg:translate-x-0"
>
    <div class="flex h-16 items-center justify-between border-b border-gray-800 px-4">
        <span x-show="!collapsed" x-transition class="truncate text-lg font-semibold text-white">
            {{ config('app.name') }}
        </span>

        <button @click="toggleCollapsed()" class="hidden rounded p-1 text-gray-400 hover:bg-gray-800 hover:text-white lg:inline-flex">
            <x-heroicon-o-chevron-double-left x-show="!collapsed" class="h-5 w-5" />
            <x-heroicon-o-chevron-double-right x-show="collapsed" x-cloak class="h-5 w-5" />
        </button>

        <button @click="sidebarOpen = false" class="rounded p-1 text-gray-400 hover:bg-gray-800 hover:text-white lg:hidden">
            <x-heroicon-o-x-mark class="h-6 w-6" />
        </button>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-2 py-4">
        @foreach ($groups as $groupLabel => $groupScreens)
            <div>
                <div x-show="!collapsed" class="mb-1 px-3 text-xs font-semibold uppercase tracking-wider text-gray-500">
                    {{ $groupLabel }}
                </div>

                <div class="space-y-1">
                    @foreach ($groupScreens as $screen)
                        @php $isActive = request()->routeIs($screen->route_name); @endphp
                        <a
                            href="{{ \Illuminate\Support\Facades\Route::has($screen->route_name) ? route($screen->route_name) : '#' }}"
                            title="{{ $screen->name }}"
                            :class="{ 'justify-center px-2': collapsed }"
                            class="group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ $isActive ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}"
                        >
                            <x-dynamic-component :component="'heroicon-o-'.$screen->icon" class="h-5 w-5 shrink-0" />
                            <span x-show="!collapsed" x-transition class="truncate">{{ $screen->name }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
</aside>
