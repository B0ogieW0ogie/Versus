@php
    $handle = $user->username ? '@' . $user->username : '@' . __('profile.username_fallback_prefix') . $user->id;
    $title = 'Architect of Reality';
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

    <div class="px-3 lg:max-w-7xl lg:mx-auto lg:px-6">
        {{-- Banner --}}
        <div class="aspect-[16/7] bg-white/5 flex items-center justify-center overflow-hidden">
            @if ($user->bannerUrl())
                <img src="{{ $user->bannerUrl() }}" alt="" class="w-full h-full object-cover">
            @else
                <x-icon.image-placeholder class="h-12 w-12 text-white/20" />
            @endif
        </div>

        {{-- Header row: avatar + stats + edit --}}
        <div class="-mt-12 flex items-end gap-4">
            <div data-onboarding-target="avatar"
                 class="h-24 w-24 rounded-full bg-navy-700 ring-4 ring-navy-900 overflow-hidden flex items-center justify-center">
                @if ($user->avatarUrl())
                    <img src="{{ $user->avatarUrl() }}" alt="" class="w-full h-full object-cover">
                @else
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                         class="h-12 w-12 text-white/30">
                        <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.418 0-8 2.686-8 6v2h16v-2c0-3.314-3.582-6-8-6z"/>
                    </svg>
                @endif
            </div>

            <div class="flex-1 flex items-end justify-between pb-1">
                <div class="flex gap-6">
                    <div class="text-center">
                        <div class="text-lg font-bold text-white">352</div>
                        <div class="text-[11px] text-white/55">{{ __('profile.subscribers') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-bold text-white">128</div>
                        <div class="text-[11px] text-white/55">{{ __('profile.following') }}</div>
                    </div>
                </div>
                <a href="{{ route('profile.settings') }}"
                   class="text-xs font-semibold text-white border border-white/20 rounded-lg px-4 py-1.5 hover:bg-white/5 transition">
                    {{ __('profile.edit') }}
                </a>
            </div>
        </div>

        {{-- RP --}}
        <div class="mt-2 flex items-center gap-1.5 text-sm text-white/70">
            <x-icon.trophy class="h-4 w-4" />
            <span class="font-semibold text-white">2,450</span>
            <span>{{ __('profile.rp_suffix') }}</span>
        </div>

        {{-- Name + handle + title --}}
        <div class="mt-3">
            <h2 class="text-2xl font-bold text-white">{{ $user->name }}</h2>
            <div class="mt-0.5 text-sm text-white/60 flex flex-wrap gap-x-2 items-center">
                <span>{{ $handle }}</span>
                <span class="text-white/40">·</span>
                <span class="text-vote-purple-to font-semibold">{{ $title }}</span>
            </div>
        </div>

        {{-- Bio --}}
        <div data-onboarding-target="bio" class="mt-2">
            @if ($user->bio)
                <p class="text-sm text-white/80 whitespace-pre-line">{{ $user->bio }}</p>
            @else
                <p class="text-sm text-white/45">{{ __('profile.bio_placeholder') }}</p>
            @endif
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
    </div>
</div>
