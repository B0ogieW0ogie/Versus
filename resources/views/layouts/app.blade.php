<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="pool-totals-url" content="{{ route('battles.pool-totals') }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="pb-20 sm:pb-0">
                {{ $slot }}
            </main>

            @include('layouts.bottom-nav')
        </div>

        @auth
            @if (auth()->user()->hasVerifiedEmail())
                <livewire:verified-welcome-modal />
            @endif
        @endauth

        @if (session('toast_onboarding'))
            <div class="fixed bottom-24 left-1/2 z-[80] -translate-x-1/2 max-w-sm px-4 pointer-events-none"
                 x-data="{ show: true }"
                 x-show="show"
                 x-init="setTimeout(() => show = false, 4500)"
                 x-transition.opacity>
                <div class="rounded-xl border border-white/15 bg-navy-800/95 px-4 py-3 text-center text-sm text-white shadow-lg backdrop-blur-sm">
                    {{ session('toast_onboarding') }}
                </div>
            </div>
        @endif

        <div class="fixed bottom-24 left-1/2 z-[80] w-full max-w-sm -translate-x-1/2 px-4 pointer-events-none"
             x-data="versusToast()"
             @versus-stake-toast.window="show($event.detail)"
             x-show="visible"
             x-cloak
             x-transition.opacity>
            <div class="rounded-xl border border-white/15 bg-navy-800/95 px-4 py-3 text-center shadow-lg backdrop-blur-sm">
                <p class="text-sm font-semibold text-white" x-text="title"></p>
                <p class="mt-1 text-xs leading-relaxed text-white/75" x-show="body" x-text="body"></p>
            </div>
        </div>

        <livewire:search-overlay />
    </body>
</html>
