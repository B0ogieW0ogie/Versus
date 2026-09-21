@php
    use App\Services\News\NewsEvent;

    $select = 'rounded-xl border-white/10 bg-white/5 py-2.5 pl-4 pr-10 text-sm font-semibold text-white focus:border-indigo-500 focus:ring-indigo-500 [&>option]:bg-navy-800';
    $handle = fn (?\App\Models\User $u) => $u === null ? '' : ($u->username ?? $u->name);
@endphp

<div data-page="news" class="min-h-screen bg-navy-900 px-4 pb-24 pt-6 text-white sm:pb-10 lg:px-0">
    <h1 class="text-2xl font-bold lg:text-3xl">{{ __('news.title') }}</h1>

    <div class="mt-4 flex flex-wrap gap-3">
        <select wire:model.live="source" data-filter="source" aria-label="{{ __('news.source_label') }}" class="{{ $select }} min-w-[11rem]">
            @foreach ($sources as $option)
                <option value="{{ $option }}">{{ __('news.source_'.$option) }}</option>
            @endforeach
        </select>
        <select wire:model.live="filter" data-filter="type" aria-label="{{ __('news.type_label') }}" class="{{ $select }} min-w-[12rem]">
            <option value="">{{ __('news.type_all') }}</option>
            @foreach ($filters as $option)
                <option value="{{ $option }}">{{ __('news.type_'.$option) }}</option>
            @endforeach
        </select>
    </div>

    <div class="mt-5 space-y-2.5">
        @forelse ($events as $event)
            @php /** @var NewsEvent $event */ $url = $event->url(); @endphp
            <article wire:key="news-{{ $event->key() }}" data-news-event="{{ $event->type }}"
                     @class([
                         'relative flex items-start gap-4 rounded-2xl border border-white/10 bg-white/[0.03] pr-12 transition',
                         'p-3' => $event->isChallengeEvent(),
                         'px-3 py-2.5' => ! $event->isChallengeEvent(),
                         'hover:border-white/20 hover:bg-white/[0.05]' => $url !== null,
                     ])>
                {{-- Avatar → profile; VERSUS uses its own logo --}}
                @php $avatarSize = $event->isChallengeEvent() ? 'h-12 w-12' : 'h-10 w-10'; @endphp
                @if ($event->actor)
                    <a href="{{ route('profile.show', $event->actor) }}" class="relative z-10 shrink-0">
                        @if ($event->actor->avatarUrl())
                            <img src="{{ $event->actor->avatarUrl() }}" alt="" class="{{ $avatarSize }} rounded-full object-cover">
                        @else
                            <span class="{{ $avatarSize }} flex items-center justify-center rounded-full bg-indigo-600 text-lg font-bold">{{ mb_strtoupper(mb_substr($event->actor->name, 0, 1)) }}</span>
                        @endif
                    </a>
                @else
                    <span data-versus-avatar class="{{ $avatarSize }} flex shrink-0 items-center justify-center rounded-full border-2 border-indigo-400/70 bg-navy-900 text-xl font-black italic text-white shadow-[0_0_12px_rgba(99,102,241,0.45)]">V</span>
                @endif

                <div class="min-w-0 flex-1 self-center">
                    <p class="truncate text-sm text-white/55">
                        @if ($event->actor)
                            <a href="{{ route('profile.show', $event->actor) }}" class="relative z-10 text-[15px] font-semibold text-white hover:underline">{{ $handle($event->actor) }}</a>
                        @else
                            <span class="text-[15px] font-semibold text-white">VERSUS</span>
                        @endif
                        <span class="ml-1">{{ $event->action() }}</span>
                        <span aria-hidden="true">·</span>
                        <time datetime="{{ $event->occurredAt->toIso8601String() }}">{{ $event->occurredAt->diffForHumans() }}</time>
                    </p>

                    @if ($url)
                        <a href="{{ $url }}" class="mt-0.5 block truncate text-[17px] font-medium after:absolute after:inset-0">{{ $event->detail() }}</a>
                    @else
                        <p class="mt-0.5 truncate text-[15px] text-white/90">{{ $event->detail() }}</p>
                    @endif

                    {{-- Results: places only — no likes, votes or views --}}
                    @if ($event->type === NewsEvent::TYPE_RESULTS)
                        @if ($event->podium === [])
                            <p class="mt-2 text-sm text-white/50">{{ __('challenges.no_votes') }}</p>
                        @else
                            <ol data-podium class="mt-2.5 flex flex-wrap gap-2">
                                @foreach ($event->podium as $place => $entry)
                                    <li class="relative z-10 flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 py-1 pl-1 pr-3 text-sm">
                                        <span aria-label="#{{ $place + 1 }}" class="text-lg leading-none">{{ ['🥇', '🥈', '🥉'][$place] }}</span>
                                        @if ($entry->user->avatarUrl())
                                            <img src="{{ $entry->user->avatarUrl() }}" alt="" class="h-6 w-6 rounded-full object-cover">
                                        @else
                                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-[11px] font-bold">{{ mb_strtoupper(mb_substr($entry->user->name, 0, 1)) }}</span>
                                        @endif
                                        <a href="{{ route('profile.show', $entry->user) }}" class="hover:underline">{{ '@'.$handle($entry->user) }}</a>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    @endif
                </div>

                {{-- Same-size video thumbnail for every challenge event; none for the compact rows --}}
                @if ($event->isChallengeEvent())
                    <div data-thumb class="h-20 w-16 shrink-0 overflow-hidden rounded-xl bg-white/5">
                        @if ($event->posterUrl())
                            <img src="{{ $event->posterUrl() }}" alt="" class="h-full w-full object-cover">
                        @endif
                    </div>
                @endif

                {{-- ••• context menu --}}
                <div class="absolute right-2 top-2" :class="open ? 'z-40' : 'z-20'" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" @click="open = !open" class="rounded-full px-2 py-1 text-lg leading-none text-white/60 hover:bg-white/10 hover:text-white"
                            aria-label="{{ __('news.menu') }}" :aria-expanded="open">•••</button>
                    <div x-show="open" x-cloak x-transition.opacity
                         class="absolute right-0 top-full mt-1 w-52 overflow-hidden rounded-xl border border-white/10 bg-navy-800 py-1 text-sm shadow-2xl">
                        @if ($url)
                            <a href="{{ $url }}" class="block px-4 py-2 hover:bg-white/10">{{ $event->type === NewsEvent::TYPE_RESPONDED || $event->type === NewsEvent::TYPE_TOP_CHANGE ? __('news.open_response') : __('news.open_challenge') }}</a>
                            <button type="button" class="block w-full px-4 py-2 text-left hover:bg-white/10"
                                    @click="navigator.clipboard?.writeText(@js($url)); open = false; $dispatch('versus-stake-toast', { title: @js(__('challenges.link_copied')) })">
                                {{ __('news.copy_link') }}
                            </button>
                        @endif
                        @if ($event->actor)
                            <a href="{{ route('profile.show', $event->actor) }}" class="block px-4 py-2 hover:bg-white/10">{{ __('news.open_profile') }}</a>
                        @endif
                        @if (! $url && ! $event->actor)
                            <span class="block px-4 py-2 text-white/50">VERSUS</span>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-8 text-center">
                <p class="font-semibold">{{ __('news.empty_title') }}</p>
                <p class="mt-1 text-sm text-white/50">{{ __('news.empty_body') }}</p>
                <a href="{{ route('challenges.index') }}" class="mt-4 inline-block rounded-full bg-indigo-600 px-5 py-2 text-sm font-semibold">{{ __('challenges.go_to_challenges') }}</a>
            </div>
        @endforelse
    </div>

    @if ($hasMore)
        <button type="button" wire:click="loadMore" class="mt-6 w-full rounded-xl border border-white/15 py-3 text-sm font-semibold hover:bg-white/5">{{ __('feed.load_more') }}</button>
    @endif
</div>
