@php
    $tabs = [
        [
            'route' => 'home',
            'match' => ['home', 'battles.*'],
            'label' => __('nav.home'),
            'icon'  => 'home',
        ],
        [
            'route' => 'leaderboard',
            'match' => ['leaderboard'],
            'label' => __('nav.leaderboard'),
            'icon'  => 'trophy',
        ],
        [
            'route' => 'my-bets',
            'match' => ['my-bets'],
            'label' => __('nav.my_bets'),
            'icon'  => 'chart',
        ],
        [
            'route' => 'profile.edit',
            'match' => ['profile.*'],
            'label' => __('nav.profile'),
            'icon'  => 'user',
        ],
    ];
@endphp

<nav class="fixed bottom-0 inset-x-0 z-40 sm:hidden bg-navy-900/95 backdrop-blur border-t border-white/5 pb-[env(safe-area-inset-bottom)]">
    <ul class="grid grid-cols-4">
        @foreach ($tabs as $tab)
            @if (Route::has($tab['route']))
                <li>
                    <a href="{{ route($tab['route']) }}"
                       class="flex flex-col items-center gap-1 py-2.5 text-[10px] {{ request()->routeIs(...$tab['match']) ? 'text-white' : 'text-white/55 hover:text-white' }}">
                        <x-dynamic-component :component="'icon.'.$tab['icon']" class="h-5 w-5" />
                        <span>{{ $tab['label'] }}</span>
                    </a>
                </li>
            @endif
        @endforeach
    </ul>
</nav>
