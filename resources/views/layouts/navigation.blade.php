<nav class="bg-navy-900 border-b border-white/5 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            {{-- Left: logo + primary menu --}}
            <div class="flex items-center gap-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg
                                 bg-gradient-to-br from-vote-blue-from to-vote-purple-to
                                 font-black text-white shadow-vote-blue">V</span>
                    <span class="font-semibold text-white/90">Versus</span>
                </a>

                <div class="hidden sm:flex items-center gap-6 text-sm">
                    @auth
                        <a href="{{ route('feed') }}"
                           class="transition {{ request()->routeIs('feed') ? 'text-white' : 'text-white/60 hover:text-white' }}">
                            {{ __('nav.feed') }}
                        </a>
                    @endauth
                    <a href="{{ route('leaderboard') }}"
                       class="transition {{ request()->routeIs('leaderboard') ? 'text-white' : 'text-white/60 hover:text-white' }}">
                        {{ __('nav.leaderboard') }}
                    </a>
                    @auth
                        <a href="{{ route('battles.create') }}"
                           class="transition {{ request()->routeIs('battles.create') ? 'text-white' : 'text-white/60 hover:text-white' }}">
                            {{ __('nav.create_battle') }}
                        </a>
                    @endauth
                </div>
            </div>

            {{-- Right: locale + icons + user --}}
            <div class="flex items-center gap-2 sm:gap-3">
                <button type="button"
                        x-on:click="$dispatch('open-search')"
                        class="p-2 rounded-full text-white/70 hover:text-white hover:bg-white/5 transition"
                        aria-label="{{ __('search.open') }}">
                    <x-icon.search class="h-5 w-5" />
                </button>

                <div class="hidden sm:flex items-center gap-3">
                    <livewire:locale-switcher />
                </div>

                @auth
                    <div class="hidden sm:flex items-center gap-2">
                        <livewire:notification-bell />
                        <button type="button"
                                title="{{ __('sidebar.coming_soon') }}"
                                class="p-2 rounded-full text-white/60 hover:text-white hover:bg-white/5 transition"
                                aria-label="Messages">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                 stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                            </svg>
                        </button>
                    </div>

                    <x-dropdown align="right" width="48" contentClasses="py-1 bg-navy-800 text-white/90">
                        <x-slot name="trigger">
                            <button type="button" data-onboarding-target="balance"
                                    class="flex items-center gap-2 rounded-full bg-white/5 hover:bg-white/10 pl-1 pr-3 py-1 transition">
                                <span class="h-7 w-7 rounded-full bg-navy-700 flex items-center justify-center text-xs font-semibold">
                                    {{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                                </span>
                                <span class="text-xs font-semibold"
                                      x-data="{ balance: {{ (int) Auth::user()->balance }} }"
                                      x-on:balance-updated.window="balance = $event.detail.balance">
                                    <span class="text-white"
                                          x-text="new Intl.NumberFormat().format(balance)">{{ number_format((float) Auth::user()->balance, 0) }}</span>
                                    <span class="text-white/50 font-normal ml-1 hidden sm:inline">{{ __('sidebar.tokens') }}</span>
                                </span>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                     class="h-4 w-4 text-white/50 hidden sm:inline">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('wallet')"
                                             class="!text-white/80 hover:!bg-white/10 hover:!text-white">
                                {{ __('nav.wallet') }}
                            </x-dropdown-link>
                            <x-dropdown-link :href="route('profile.edit')"
                                             class="!text-white/80 hover:!bg-white/10 hover:!text-white">
                                {{ __('nav.profile') }}
                            </x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                                 class="!text-white/80 hover:!bg-white/10 hover:!text-white"
                                                 onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('nav.logout') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @endauth

                @guest
                    <a href="{{ route('login') }}"
                       class="text-sm text-white/80 hover:text-white transition">{{ __('nav.login') }}</a>
                    <a href="{{ route('register') }}"
                       class="hidden sm:inline-block text-sm rounded-md bg-white/10 hover:bg-white/20 px-3 py-1.5 transition">
                        {{ __('nav.register') }}
                    </a>
                @endguest
            </div>
        </div>
    </div>
</nav>
