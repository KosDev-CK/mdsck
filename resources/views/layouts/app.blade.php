<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }}</title>

    <script>
        (function () {
            var theme = localStorage.getItem('mds_theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @include('partials.branding-head')
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <div
        x-data="{
            sidebarOpen: false,
            collapsed: JSON.parse(localStorage.getItem('mds_sidebar_collapsed') ?? 'false'),
            toggleCollapsed() {
                this.collapsed = !this.collapsed
                localStorage.setItem('mds_sidebar_collapsed', JSON.stringify(this.collapsed))
            },
        }"
        class="min-h-screen flex"
    >
        @include('partials.sidebar')

        <div class="flex-1 flex flex-col min-w-0">
            @include('partials.topbar')

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <div class="mx-auto w-full max-w-screen-2xl">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    @livewireScripts

    @auth
        <script>
            document.addEventListener('livewire:init', () => {
                window.Echo.private('App.Models.User.{{ auth()->id() }}')
                    .notification((notification) => {
                        Livewire.dispatch('notification-received');
                    });
            });
        </script>
    @endauth
</body>
</html>
