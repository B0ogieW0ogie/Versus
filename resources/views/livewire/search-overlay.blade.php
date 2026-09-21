<div x-data="{ open: false }"
     x-on:open-search.window="open = true; $nextTick(() => $refs.input?.focus())"
     x-on:keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 bg-navy-900/95 backdrop-blur flex flex-col">

    <div class="flex items-center gap-3 px-4 py-3 border-b border-white/5">
        <x-icon.search class="h-5 w-5 text-white/60" />
        <input type="text"
               x-ref="input"
               wire:model.live.debounce.300ms="query"
               placeholder="{{ $battlesMode ? __('search.placeholder') : __('search.placeholder_challenges') }}"
               class="flex-1 bg-transparent border-0 text-white placeholder-white/40 focus:outline-none focus:ring-0" />
        <button type="button"
                x-on:click="open = false"
                class="text-sm text-glow-cyan hover:underline">
            {{ __('search.cancel') }}
        </button>
    </div>

    <div class="flex-1 overflow-y-auto">
        @if ($queryLength === 0)
            <div class="p-8 text-center text-sm text-white/45">
                {{ $battlesMode ? __('search.prompt') : __('search.prompt_challenges') }}
            </div>
        @elseif ($queryLength < 2)
            <div class="p-8 text-center text-sm text-white/45">
                {{ __('search.min_chars') }}
            </div>
        @elseif (! $battlesMode)
            @if ($people->isEmpty() && $challenges->isEmpty())
                <div class="p-8 text-center text-sm text-white/45">{{ __('search.no_results') }}</div>
            @endif
            @if ($people->isNotEmpty())
                <h3 class="px-4 pt-4 text-xs font-semibold uppercase tracking-wide text-white/40">{{ __('search.people') }}</h3>
                <ul data-search="people" class="divide-y divide-white/5">
                    @foreach ($people as $person)
                        <li>
                            <a href="{{ route('profile.show', $person) }}" x-on:click="open = false" class="flex items-center gap-3 px-4 py-3 hover:bg-white/[0.03]">
                                @if ($person->avatarUrl())
                                    <img src="{{ $person->avatarUrl() }}" alt="" class="h-9 w-9 rounded-full object-cover">
                                @else
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($person->name, 0, 1)) }}</span>
                                @endif
                                <span class="min-w-0">
                                    <span class="block truncate text-sm text-white/90">{{ $person->name }}</span>
                                    @if ($person->username)
                                        <span class="block truncate text-xs text-white/50">{{ '@'.$person->username }}</span>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($challenges->isNotEmpty())
                <h3 class="px-4 pt-4 text-xs font-semibold uppercase tracking-wide text-white/40">{{ __('challenges.nav_challenges') }}</h3>
                <ul data-search="challenges" class="divide-y divide-white/5">
                    @foreach ($challenges as $challenge)
                        <li>
                            <a href="{{ route('challenges.show', $challenge->slug) }}" x-on:click="open = false" class="flex items-center gap-3 px-4 py-3 hover:bg-white/[0.03]">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm text-white/90">{{ $challenge->title }}</span>
                                    <span class="block truncate text-xs text-white/50">
                                        {{ '@'.($challenge->user->username ?? $challenge->user->name) }}
                                        @if ($challenge->category) · {{ __('challenges.category_'.$challenge->category) }} @endif
                                    </span>
                                </span>
                                <span class="shrink-0 text-xs text-white/50">{{ __($challenge->status === \App\Models\Challenge::STATUS_ACTIVE ? 'challenges.status_active' : 'challenges.status_closed') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @elseif ($results->isEmpty())
            <div class="p-8 text-center text-sm text-white/45">
                {{ __('search.no_results') }}
            </div>
        @else
            <ul class="divide-y divide-white/5">
                @foreach ($results as $battle)
                    <li>
                        <a href="{{ route('battles.show', $battle) }}"
                           x-on:click="open = false"
                           class="flex items-center gap-3 px-4 py-3 hover:bg-white/[0.03]">
                            <span class="h-8 w-10 rounded-md bg-gradient-to-r from-vote-blue-from to-vote-purple-to"></span>
                            <span class="flex-1 min-w-0">
                                <span class="block truncate text-sm text-white/90">{{ $battle->title }}</span>
                                <span class="block text-[11px] text-white/50">
                                    @if ($battle->category) {{ $battle->category->localized_name }} · @endif
                                    {{ __('battle.status_'.$battle->status) }}
                                </span>
                            </span>
                            <span class="shrink-0 text-xs text-white/60">
                                {{ number_format((float) $battle->total_pool, 0) }}
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
