@php
    use App\Services\News\NewsEvent;
    use App\Services\News\NewsFeedService;

    $select = 'rounded-xl border-white/10 bg-white/5 py-2 pl-4 pr-10 text-sm font-semibold text-white focus:border-indigo-500 focus:ring-indigo-500';
    $name = fn (?\App\Models\User $u) => $u === null ? '' : ($u->username ?? $u->name);
@endphp

<div data-page="news" class="min-h-screen bg-navy-900 px-4 pb-24 pt-6 text-white sm:pb-10 lg:px-0">
    <h1 class="text-2xl font-bold">{{ __('news.title') }}</h1>

    <div class="mt-4 flex flex-wrap gap-3">
        @auth
            <select wire:model.live="scope" aria-label="{{ __('news.scope_label') }}" class="{{ $select }} [&>option]:bg-navy-800">
                <option value="{{ NewsFeedService::SCOPE_ALL }}">{{ __('news.scope_all') }}</option>
                <option value="{{ NewsFeedService::SCOPE_FOLLOWING }}">{{ __('news.scope_following') }}</option>
            </select>
        @endauth
        <select wire:model.live="type" aria-label="{{ __('news.type_label') }}" class="{{ $select }} [&>option]:bg-navy-800">
            <option value="">{{ __('news.type_all') }}</option>
            @foreach ($types as $option)
                <option value="{{ $option }}">{{ __('news.type_'.$option) }}</option>
            @endforeach
        </select>
    </div>

    <div class="mt-5 space-y-3">
        @forelse ($events as $event)
            @php /** @var NewsEvent $event */ @endphp
            <article wire:key="news-{{ $event->key() }}" data-news-event="{{ $event->type }}"
                     class="relative flex gap-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4 transition hover:border-white/20 hover:bg-white/[0.05]">
                {{-- Avatar --}}
                @if ($event->actor)
                    <a href="{{ route('profile.show', $event->actor) }}" class="relative z-10 shrink-0">
                        @if ($event->actor->avatarUrl())
                            <img src="{{ $event->actor->avatarUrl() }}" alt="" class="h-12 w-12 rounded-full object-cover">
                        @else
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-600 text-lg font-bold">{{ mb_strtoupper(mb_substr($event->actor->name, 0, 1)) }}</span>
                        @endif
                    </a>
                @else
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-indigo-500/40 bg-indigo-500/10 text-2xl">🏆</span>
                @endif

                <div class="min-w-0 flex-1">
                    <p class="text-sm text-white/60">
                        @if ($event->actor)
                            <a href="{{ route('profile.show', $event->actor) }}" class="relative z-10 text-base font-semibold text-white hover:underline">{{ $name($event->actor) }}</a>
                        @else
                            <span class="text-base font-semibold text-white">VERSUS</span>
                        @endif
                        {{ __('news.event_'.$event->type) }} · {{ $event->occurredAt->diffForHumans() }}
                    </p>
                    <a href="{{ $event->url() }}" class="mt-1 block text-lg font-medium leading-snug after:absolute after:inset-0">
                        «{{ $event->challenge->title }}»
                    </a>

                    @if ($event->type === NewsEvent::TYPE_RESULTS)
                        @if ($event->podium === [])
                            <p class="mt-3 text-sm text-white/50">{{ __('challenges.no_votes') }}</p>
                        @else
                            <ol class="mt-3 flex flex-wrap gap-2">
                                @foreach ($event->podium as $place => $entry)
                                    <li class="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 py-1.5 pl-1.5 pr-3 text-sm">
                                        <span @class([
                                            'flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold',
                                            'bg-amber-400 text-black' => $place === 0,
                                            'bg-slate-300 text-black' => $place === 1,
                                            'bg-orange-600' => $place === 2,
                                        ])>{{ $place + 1 }}</span>
                                        {{ '@'.$name($entry->user) }}
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    @endif
                </div>

                @if ($event->posterUrl())
                    <img src="{{ $event->posterUrl() }}" alt="" class="h-24 w-16 shrink-0 rounded-xl object-cover sm:h-28 sm:w-20">
                @endif
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
