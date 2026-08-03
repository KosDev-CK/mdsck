<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center px-4">
        <div class="mb-8 text-2xl font-semibold text-gray-800">{{ config('app.name') }}</div>

        <div class="w-full max-w-sm bg-white rounded-xl shadow-sm border border-gray-200 p-8">
            {{ $slot }}
        </div>
    </div>

    @livewireScripts
</body>
</html>
