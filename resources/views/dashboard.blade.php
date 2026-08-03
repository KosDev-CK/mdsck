<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-md bg-white rounded-xl border border-gray-200 p-6">
            <h1 class="text-lg font-semibold text-gray-900">Bienvenido, {{ auth()->user()->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">Dashboard en construcción (task #6).</p>

            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                @csrf
                <button type="submit" class="text-sm text-indigo-600 hover:text-indigo-500">Cerrar sesión</button>
            </form>
        </div>
    </div>
</body>
</html>
