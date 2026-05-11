@php
    $tabs = [
        [
            'route' => 'home',
            'match' => ['home', 'battles.*', 'categories.*'],
            'label' => __('nav.home'),
            'icon' => 'home',
        ],
        [
            'route' => 'feed',
            'match' => ['feed'],
            'label' => __('nav.feed'),
            'icon' => 'feed',
            'auth' => true,
        ],
        [
            'route' => 'battles.create',
            'match' => ['battles.create'],
            'label' => __('nav.create'),
            'icon' => 'plus',
            'fab' => true,
        ],
        [
            'route' => 'leaderboard',
            'match' => ['leaderboard'],
            'label' => __('nav.leaderboard'),
            'icon' => 'trophy',
        ],
        [
            'route' => 'profile.edit',
            'match' => ['profile.*'],
            'label' => __('nav.profile'),
            'icon' => 'user',
        ],
    ];
@endphp

<nav class="fixed bottom-0 inset-x-0 z-40 sm:hidden bg-navy-900/95 backdrop-blur border-t border-white/5 pt-5 pb-[env(safe-area-inset-bottom)]">
    <ul class="grid grid-cols-5 items-end">
        @foreach ($tabs as $tab)
            <li class="flex justify-center">
                @if (! empty($tab['fab']))
                    @if (! empty($tab['route']) && Route::has($tab['route']))
                        <a href="{{ auth()->check() ? route($tab['route']) : route('login') }}"
                           data-onboarding-target="fabCreate"
                           class="-mt-7 h-14 w-14 rounded-full bg-gradient-to-br from-vote-blue-from to-vote-purple-to shadow-vote-blue text-white flex items-center justify-center
                                  {{ auth()->check() && request()->routeIs(...$tab['match']) ? 'ring-2 ring-white/55 ring-offset-2 ring-offset-navy-900' : '' }}"
                           @if (auth()->check() && request()->routeIs(...$tab['match'])) aria-current="page" @endif>
                            <x-dynamic-component :component="'icon.'.$tab['icon']" class="h-6 w-6" />
                            <span class="sr-only">{{ $tab['label'] }}</span>
                        </a>
                    @else
                        <button type="button" disabled aria-disabled="true"
                                title="{{ __('nav.coming_soon') }}"
                                class="-mt-7 h-14 w-14 rounded-full bg-gradient-to-br from-vote-blue-from to-vote-purple-to shadow-vote-blue text-white flex items-center justify-center cursor-not-allowed">
                            <x-dynamic-component :component="'icon.'.$tab['icon']" class="h-6 w-6" />
                            <span class="sr-only">{{ $tab['label'] }}</span>
                        </button>
                    @endif
                @elseif (! empty($tab['auth']) && ! auth()->check())
                    <a href="{{ route('login') }}"
                       class="flex flex-col items-center gap-1 py-2.5 text-[10px] text-white/55 hover:text-white">
                        <x-dynamic-component :component="'icon.'.$tab['icon']" class="h-5 w-5" />
                        <span>{{ $tab['label'] }}</span>
                    </a>
                @elseif ($tab['route'] && Route::has($tab['route']))
                    <a href="{{ route($tab['route']) }}"
                       class="flex flex-col items-center gap-1 py-2.5 text-[10px] {{ request()->routeIs(...$tab['match']) ? 'text-white' : 'text-white/55 hover:text-white' }}">
                        <x-dynamic-component :component="'icon.'.$tab['icon']" class="h-5 w-5" />
                        <span>{{ $tab['label'] }}</span>
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
