@php
    use App\Services\Feed\FeedService;

    $chips = [
        FeedService::FILTER_ALL => __('feed.filters.all'),
        FeedService::FILTER_VOTES => __('feed.filters.votes'),
        FeedService::FILTER_ARGUMENTS => __('feed.filters.arguments'),
        FeedService::FILTER_CREATED => __('feed.filters.created'),
        FeedService::FILTER_RESULTS => __('feed.filters.results'),
    ];
@endphp

<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        @foreach ($chips as $key => $label)
            <button type="button" wire:click="setFilter('{{ $key }}')"
                @class([
                    'shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition',
                    'bg-[#7C3AED] text-white' => $filter === $key,
                    'bg-white/5 text-white/60 hover:bg-white/10' => $filter !== $key,
                ])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="mt-5 space-y-4">
        @forelse ($events as $event)
            <div wire:key="feed-{{ $event->type }}-{{ $event->actor->id }}-{{ $event->battle->id }}-{{ $event->occurredAt->getTimestamp() }}">
                <x-feed.event-card :event="$event" />
            </div>
        @empty
            <div class="rounded-2xl border border-white/5 bg-white/[0.02] p-8 text-center">
                <p class="text-sm font-semibold text-white">{{ __('feed.empty.title') }}</p>
                <p class="mt-1 text-sm text-white/50">{{ __('feed.empty.body') }}</p>
            </div>
        @endforelse
    </div>

    @if ($hasMore && $events->isNotEmpty())
        <div class="mt-6 text-center">
            <button type="button" wire:click="loadMore" wire:loading.attr="disabled"
                class="rounded-xl bg-white/10 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-white/15">
                {{ __('feed.load_more') }}
            </button>
        </div>
    @endif
</div>
