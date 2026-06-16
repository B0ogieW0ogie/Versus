@php
    $handle = $user->username ? '@' . $user->username : '@' . __('profile.username_fallback_prefix') . $user->id;
@endphp

<div class="pb-20">
    {{-- Header --}}
    <header class="sticky top-0 z-10 bg-navy-900/95 backdrop-blur px-4 h-12 flex items-center justify-between border-b border-white/5">
        <h1 class="text-base font-semibold text-white">{{ __('profile.title') }}</h1>
        <div class="flex items-center gap-1 text-white/40">
            <button type="button" disabled aria-disabled="true"
                    title="{{ __('profile.coming_soon') }}"
                    class="p-2 cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
            </button>
            <button type="button" disabled aria-disabled="true"
                    title="{{ __('profile.coming_soon') }}"
                    class="p-2 cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                    <circle cx="5" cy="12" r="1.5" fill="currentColor"/>
                    <circle cx="12" cy="12" r="1.5" fill="currentColor"/>
                    <circle cx="19" cy="12" r="1.5" fill="currentColor"/>
                </svg>
            </button>
        </div>
    </header>

    <div class="w-full lg:max-w-7xl lg:mx-auto px-4 lg:px-6">
        <div data-onboarding-target="avatar" class="relative">
            {{-- Banner --}}
            <div class="aspect-[24/7] bg-white/5 flex items-center justify-center overflow-hidden">
                @if ($user->bannerUrl())
                    <img src="{{ $user->bannerUrl() }}" alt="" class="w-full h-full object-cover">
                @else
                    <x-icon.image-placeholder class="h-12 w-12 text-white/20" />
                @endif
            </div>

            {{-- Row under banner: avatar · name · stats (aligned to banner bottom) --}}
            <div class="-mt-24 space-y-2">
                <div class="flex items-end gap-3 sm:gap-5">
                    <div class="h-48 w-48 shrink-0 rounded-full bg-navy-700 ring-4 ring-navy-900 overflow-hidden flex items-center justify-center">
                        @if ($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="" class="w-full h-full object-cover">
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                 class="h-24 w-24 text-white/30">
                                <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.418 0-8 2.686-8 6v2h16v-2c0-3.314-3.582-6-8-6z"/>
                            </svg>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1 pb-0.5">
                        <h2 class="text-2xl font-bold text-vote-purple-to leading-tight">{{ $user->name }}</h2>
                        <p class="mt-0.5 text-sm text-white/60">{{ $handle }}</p>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-x-4 gap-y-1.5 shrink-0 pb-0.5 sm:gap-x-6">
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-lg font-bold text-white">352</span>
                            <span class="text-[11px] text-white/55">{{ __('profile.subscribers') }}</span>
                        </div>
                        <div class="flex items-baseline gap-1.5">
                            <span class="text-lg font-bold text-white">128</span>
                            <span class="text-[11px] text-white/55">{{ __('profile.following') }}</span>
                        </div>
                        <a href="{{ route('profile.settings') }}"
                           class="text-xs font-semibold text-white border border-white/20 rounded-lg px-4 py-1.5 hover:bg-white/5 transition shrink-0">
                            {{ __('profile.edit') }}
                        </a>
                    </div>
                </div>

                <div class="flex gap-3 sm:gap-5">
                    <div class="w-48 shrink-0">
                        <div class="flex items-center gap-1.5 text-sm text-white/70">
                            <x-icon.trophy class="h-4 w-4 shrink-0" />
                            <span class="font-semibold text-white">2,450</span>
                            <span>{{ __('profile.rp_suffix') }}</span>
                        </div>
                        <p class="mt-0.5 text-sm font-semibold text-vote-purple-to">{{ __('profile.title_architect') }}</p>
                    </div>
                    <div data-onboarding-target="bio" class="min-w-0 flex-1 pt-0.5">
                        @if ($user->bio)
                            <p class="text-sm text-white/80 whitespace-pre-line">{{ $user->bio }}</p>
                        @else
                            <p class="text-sm text-white/45">{{ __('profile.bio_placeholder') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab bar --}}
        <div class="mt-4 flex items-end gap-4 border-b border-white/5">
            @foreach (['activity', 'creation', 'comments', 'referrals'] as $key)
                <button type="button"
                        wire:click="selectTab('{{ $key }}')"
                        class="pb-2 text-xs font-semibold tracking-wide transition
                               {{ $tab === $key ? 'text-white border-b-2 border-vote-purple-to -mb-px' : 'text-white/50 hover:text-white/80' }}">
                    {{ __('profile.tab_' . $key) }}
                </button>
            @endforeach
            <div class="flex-1"></div>
            <button type="button" disabled aria-disabled="true"
                    title="{{ __('profile.coming_soon') }}"
                    class="mb-1 p-1.5 rounded-lg border border-white/10 text-white/35 cursor-not-allowed">
                <x-icon.trophy class="h-4 w-4" />
            </button>
        </div>

        {{-- Tab content --}}
        @switch($tab)
            @case('creation')
                @include('livewire.profile.tabs.creation')
                @break
            @case('comments')
                @include('livewire.profile.tabs.comments')
                @break
            @case('referrals')
                @include('livewire.profile.tabs.referrals')
                @break
            @default
                @include('livewire.profile.tabs.activity')
        @endswitch

        @if ($user->is_first_visit && $user->onboarding_step !== null && (int) $user->onboarding_step >= 4)
            <section class="mt-8 space-y-3" aria-label="{{ __('onboarding.hints_section_label') }}">
                <div data-onboarding-target="homeBattles"
                     class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.04] p-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white">
                        <x-icon.home class="h-5 w-5" />
                    </span>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-white/50">{{ __('onboarding.hint_home_label') }}</div>
                        <div class="text-sm text-white/80">{{ __('onboarding.hint_home_caption') }}</div>
                    </div>
                </div>
                <div data-onboarding-target="fabCreate"
                     class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.04] p-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-vote-blue-from to-vote-purple-to text-white shadow-vote-blue">
                        <x-icon.plus class="h-5 w-5" />
                    </span>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-white/50">{{ __('onboarding.hint_create_label') }}</div>
                        <div class="text-sm text-white/80">{{ __('onboarding.hint_create_caption') }}</div>
                    </div>
                </div>
                <div data-onboarding-target="feed"
                     class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.04] p-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white">
                        <x-icon.feed class="h-5 w-5" />
                    </span>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-white/50">{{ __('onboarding.hint_feed_label') }}</div>
                        <div class="text-sm text-white/80">{{ __('onboarding.hint_feed_caption') }}</div>
                    </div>
                </div>
            </section>
        @endif

        @if ($user->is_first_visit && $user->onboarding_step !== null)
            <livewire:onboarding-tour />
        @endif
    </div>
</div>
