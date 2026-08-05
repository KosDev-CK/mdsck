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
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <div class="min-h-screen flex flex-col items-center justify-center px-4">
        <div class="mb-4 flex items-center gap-3">
            <span class="text-2xl font-semibold text-gray-800 dark:text-gray-100">{{ config('app.name') }}</span>
            <x-ui.theme-toggle />
        </div>

        <div class="w-full max-w-sm bg-white rounded-xl shadow-sm border border-gray-200 p-8 dark:bg-gray-900 dark:border-gray-800">
            {{ $slot }}
        </div>
    </div>

    @livewireScripts
</body>
</html>
