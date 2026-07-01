@props(['event'])

@php
    use App\Services\Feed\FeedEvent;
    use Illuminate\Support\Str;

    /** @var FeedEvent $event */
    $actor = $event->actor;
    $battle = $event->battle;

    $profileUrl = route('profile.show', $actor);

    $verb = match ($event->type) {
        FeedEvent::TYPE_CREATE => __('feed.event.created'),
        FeedEvent::TYPE_VOTE => __('feed.event.voted', ['side' => Str::title((string) $event->sideLabel)]),
        FeedEvent::TYPE_ARGUE => __('feed.event.argued'),
        FeedEvent::TYPE_LIKE => __('feed.event.liked'),
        FeedEvent::TYPE_WIN => __('feed.event.won'),
        FeedEvent::TYPE_LOSE => __('feed.event.lost'),
        default => '',
    };

    $headlineColor = match ($event->type) {
        FeedEvent::TYPE_WIN => 'text-emerald-400',
        FeedEvent::TYPE_LOSE => 'text-red-400',
        default => 'text-white',
    };

    $isResult = in_array($event->type, [FeedEvent::TYPE_WIN, FeedEvent::TYPE_LOSE], true);
    $endedNonResult = ! $isResult && ! $event->isOpen();
@endphp

<article class="rounded-2xl border border-white/5 bg-white/[0.035] p-4">
    <div class="flex items-start gap-3">
        <a href="{{ $profileUrl }}" class="shrink-0">
            @if ($actor->avatarUrl())
                <img src="{{ $actor->avatarUrl() }}"
                     alt="" class="h-9 w-9 rounded-full object-cover">
            @else
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-navy-700 text-sm font-bold text-white/80">
                    {{ mb_strtoupper(mb_substr($actor->name, 0, 1)) }}
                </span>
            @endif
        </a>
        <p class="min-w-0 flex-1 text-sm font-medium {{ $headlineColor }}">
            <a href="{{ $profileUrl }}" class="font-semibold hover:underline">{{ $actor->name }}</a>
            {{ $verb }}
        </p>
    </div>

    @if ($event->argumentText)
        <blockquote class="mt-2 rounded-xl bg-[#1A1F2B] p-3 text-sm italic text-white/75">
            {{ Str::limit($event->argumentText, 200) }}
        </blockquote>
    @endif

    <div class="mt-3">
        <x-battle-mini-card :battle="$battle" />
    </div>

    <div class="mt-3">
        @if ($isResult)
            <a href="{{ route('battles.show', $battle) }}"
               class="block w-full rounded-xl bg-white/10 px-4 py-2.5 text-center text-sm font-semibold text-white/80 transition hover:bg-white/15">
                {{ __('feed.cta.view_battle') }}
            </a>
        @elseif ($endedNonResult)
            <span class="block w-full cursor-default rounded-xl bg-white/5 px-4 py-2.5 text-center text-sm font-semibold text-white/40">
                {{ __('feed.cta.battle_ended') }}
            </span>
        @else
            <a href="{{ route('battles.show', $battle) }}"
               class="block w-full rounded-xl bg-[#7C3AED] px-4 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-[#6D28D9]">
                @if ($event->type === FeedEvent::TYPE_CREATE)
                    {{ __('feed.cta.vote_with_user', ['user' => $actor->name]) }}
                @else
                    {{ __('feed.cta.vote_with') }}
                @endif
            </a>
        @endif
    </div>
</article>
