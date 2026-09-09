<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Commission') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans antialiased text-slate-700 bg-slate-50">
        <div x-data="{ sidebarOpen: false }" class="min-h-full">
            {{-- Mobile sidebar backdrop --}}
            <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden" @click="sidebarOpen = false" style="display:none"></div>

            {{-- Sidebar --}}
            <aside
                class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-slate-200 flex flex-col transition-transform duration-200 lg:translate-x-0"
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
                @include('layouts.sidebar')
            </aside>

            {{-- Main column --}}
            <div class="lg:pl-64 flex flex-col min-h-full">
                {{-- Topbar --}}
                <header class="sticky top-0 z-30 bg-white/80 backdrop-blur border-b border-slate-200">
                    <div class="flex items-center gap-4 h-16 px-4 sm:px-6 lg:px-8">
                        <button class="lg:hidden text-slate-500 hover:text-slate-700" @click="sidebarOpen = true" aria-label="Open menu">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>

                        <div class="flex-1 min-w-0">
                            @isset($header)
                                <div class="truncate">{{ $header }}</div>
                            @else
                                <h1 class="text-lg font-semibold text-slate-800 truncate">{{ $title ?? config('app.name') }}</h1>
                            @endisset
                        </div>

                        {{-- User menu --}}
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 hover:bg-slate-100 transition">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-white text-sm font-semibold">
                                        {{ strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 1)) }}
                                    </span>
                                    <span class="hidden sm:block text-sm font-medium text-slate-700">{{ auth()->user()?->name }}</span>
                                    <svg class="hidden sm:block w-4 h-4 text-slate-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <div class="px-4 py-2 text-xs text-slate-400 border-b border-slate-100">{{ auth()->user()?->email }}</div>
                                <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                        {{ __('Log Out') }}
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </header>

                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
