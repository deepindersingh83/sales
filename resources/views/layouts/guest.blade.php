<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-700 antialiased">
        <div class="min-h-screen flex flex-col justify-center items-center py-10 px-4 bg-gradient-to-b from-slate-50 to-slate-100">
            <a href="/" class="flex items-center gap-2.5 mb-6">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white text-xl font-bold">₡</span>
                <span class="text-lg font-semibold text-slate-800">{{ config('app.name', 'Commission') }}</span>
            </a>

            <div class="w-full sm:max-w-md px-6 py-8 bg-white shadow-card border border-slate-200 rounded-2xl">
                {{ $slot }}
            </div>

            <p class="mt-6 text-xs text-slate-400">Sales Commission &amp; Incentive Management</p>
        </div>
    </body>
</html>
