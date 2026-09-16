{{-- Desktop (lg+) right-hand navigation for the challenge feed. Included by the feed view only. --}}
@php
    $sideItems = [
        ['route' => 'home', 'match' => ['home'], 'label' => __('nav.home'), 'icon' => 'home', 'auth' => false],
        ['route' => 'challenges.index', 'match' => ['challenges.index', 'challenges.show'], 'label' => __('challenges.nav_challenges'), 'icon' => 'bolt', 'auth' => false],
        ['route' => 'challenges.mine', 'match' => ['challenges.mine'], 'label' => __('challenges.nav_my'), 'icon' => 'swords', 'auth' => true],
        ['route' => 'profile.edit', 'match' => ['profile.*'], 'label' => __('nav.profile'), 'icon' => 'user', 'auth' => true],
    ];
@endphp

<nav data-side-nav class="hidden min-h-0 flex-col py-6 lg:flex">
    <a href="{{ route('home') }}" class="px-4 text-3xl font-black italic tracking-tight text-white">VERSUS</a>

    <ul class="mt-8 space-y-2">
        @foreach ($sideItems as $item)
            @php $isActive = request()->routeIs(...$item['match']); @endphp
            <li>
                <a href="{{ $item['auth'] && auth()->guest() ? route('login') : route($item['route']) }}"
                   @if ($isActive) aria-current="page" @endif
                   class="flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm font-semibold transition
                          {{ $isActive ? 'border-white/20 bg-white/10 text-white' : 'border-transparent text-white/60 hover:bg-white/5 hover:text-white' }}">
                    <x-dynamic-component :component="'icon.'.$item['icon']" class="h-5 w-5 shrink-0" />
                    <span class="truncate">{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="mt-auto">
        @auth
            <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
                <span class="truncate text-sm font-semibold text-white/80">{{ __('challenges.notifications') }}</span>
                {{-- At the bottom of the screen the bell's dropdown must open upwards. --}}
                <div class="[&>div>div]:bottom-full [&>div>div]:mb-2">
                    <livewire:notification-bell />
                </div>
            </div>
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
