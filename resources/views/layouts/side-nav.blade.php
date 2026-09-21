{{-- Desktop (lg+) right-hand menu. Part of the app layout, so it stays put while only the centre column changes. --}}
@php
    $sideItems = [
        ['route' => 'home', 'match' => ['home'], 'label' => __('nav.home'), 'icon' => 'home', 'auth' => false],
        ['route' => 'profile.edit', 'match' => ['profile.edit', 'profile.show', 'profile.subscribers', 'profile.following'], 'label' => __('nav.profile'), 'icon' => 'user', 'auth' => true],
        ['route' => 'challenges.index', 'match' => ['challenges.*'], 'label' => __('challenges.nav_challenges'), 'icon' => 'swords', 'auth' => false],
        ['route' => 'notifications', 'match' => ['notifications'], 'label' => __('notifications.title'), 'icon' => 'bell', 'auth' => true],
    ];
    $unread = auth()->user()?->unreadNotifications()->count() ?? 0;
    $itemClass = 'flex w-full items-center gap-3 rounded-2xl border px-4 py-3 text-base font-semibold transition';
    $idle = 'border-transparent text-white/60 hover:bg-white/5 hover:text-white';
    $active = 'border-indigo-400/40 bg-gradient-to-r from-indigo-600/40 to-indigo-600/10 text-white shadow-lg shadow-indigo-600/10';
@endphp

<nav data-side-nav class="flex min-h-0 flex-col py-6">
    <a href="{{ route('home') }}" class="px-4 text-3xl font-black italic tracking-tight text-white">VERSUS</a>

    <ul class="mt-8 space-y-2">
        @foreach ($sideItems as $item)
            @php $isActive = request()->routeIs(...$item['match']); @endphp
            <li>
                <a href="{{ $item['auth'] && auth()->guest() ? route('login') : route($item['route']) }}"
                   data-side-item="{{ $item['route'] }}"
                   @if ($isActive) aria-current="page" @endif
                   class="{{ $itemClass }} {{ $isActive ? $active : $idle }}">
                    <span class="relative">
                        <x-dynamic-component :component="'icon.'.$item['icon']" class="h-6 w-6 shrink-0" />
                        @if ($item['route'] === 'notifications' && $unread > 0)
                            <span data-unread class="absolute -right-2 -top-2 flex h-[1.1rem] min-w-[1.1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>
                        @endif
                    </span>
                    <span class="truncate">{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
        <li class="!mt-4 border-t border-white/10 pt-4">
            <button type="button" data-side-item="search" x-data x-on:click="$dispatch('open-search')" class="{{ $itemClass }} {{ $idle }}">
                <x-icon.search class="h-6 w-6 shrink-0" />
                <span class="truncate">{{ __('search.title') }}</span>
            </button>
        </li>
    </ul>

    <div class="mt-auto">
        @auth
            @php $isActive = request()->routeIs('profile.settings'); @endphp
            <a href="{{ route('profile.settings') }}" data-side-item="settings" @if ($isActive) aria-current="page" @endif
               class="flex items-center gap-3 rounded-2xl border px-4 py-3 text-base font-semibold transition {{ $isActive ? $active : 'border-white/10 bg-white/[0.03] text-white/80 hover:bg-white/10 hover:text-white' }}">
                <x-icon.cog class="h-6 w-6 shrink-0" />
                <span class="flex-1 truncate">{{ __('nav.settings') }}</span>
                <span aria-hidden="true" class="text-white/50">›</span>
            </a>
        @else
            <div class="flex flex-col gap-2">
                <a href="{{ route('login') }}" class="rounded-full border border-white/20 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-white/10">{{ __('nav.login') }}</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="rounded-full bg-indigo-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-indigo-500">{{ __('nav.register') }}</a>
                @endif
            </div>
        @endauth
    </div>
</nav>
