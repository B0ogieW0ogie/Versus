<div class="min-h-screen bg-navy-900 text-white">
<div class="mx-auto max-w-3xl px-4 pb-8 pt-6 lg:px-0"
     @if (session('challenge_status'))
         x-init="$nextTick(() => window.dispatchEvent(new CustomEvent('versus-stake-toast', { detail: { title: @js(session('challenge_status')) } })))"
     @endif>
    <div class="mb-5">@include('layouts.challenges-tabs')</div>

    <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-baseline gap-4">
            <h1 class="text-3xl font-bold">{{ __('challenges.my_title') }}</h1>
            <span class="text-white/60">{{ __('challenges.active_count', ['count' => $activeCount]) }}</span>
        </div>
        <a href="{{ route('challenges.create') }}" data-create-challenge
           class="inline-flex h-11 items-center gap-2 rounded-xl bg-indigo-600 px-4 text-sm font-semibold uppercase tracking-wide shadow-lg shadow-indigo-600/20 hover:bg-indigo-500">
            <x-icon.plus class="h-5 w-5" />
            {{ __('challenges.create_title') }}
        </a>
    </header>

    @if ($cards === [])
        <div class="mt-20 space-y-4 text-center text-white/70">
            <p>{{ __('challenges.my_empty') }}</p>
            <a href="{{ route('challenges.index') }}" class="inline-block rounded-full bg-indigo-600 px-5 py-2 font-semibold text-white">{{ __('challenges.go_to_challenges') }}</a>
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($cards as $card)
            @php
                /** @var \App\Models\Challenge $challenge */
                $challenge = $card['challenge'];
                $pill = match ($challenge->status) {
                    \App\Models\Challenge::STATUS_ACTIVE => ['challenges.status_active', 'bg-teal-500/15 text-teal-300'],
                    \App\Models\Challenge::STATUS_PROCESSING => ['challenges.status_processing', 'bg-white/10 text-white/60'],
                    \App\Models\Challenge::STATUS_FAILED => ['challenges.status_failed', 'bg-rose-500/15 text-rose-300'],
                    default => ['challenges.status_closed', 'bg-violet-500/20 text-violet-300'],
                };
                $hasPage = ! in_array($challenge->status, [\App\Models\Challenge::STATUS_PROCESSING, \App\Models\Challenge::STATUS_FAILED], true);
                $showUrl = $hasPage ? route('challenges.show', $challenge->slug) : null;
                $linkTag = $hasPage ? 'a' : 'div';
            @endphp
            <article wire:key="card-{{ $challenge->id }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                <div class="flex gap-4">
                    {{-- Processing/failed challenges have no public page yet, so poster and title are plain elements. --}}
                    <{{ $linkTag }} @if ($showUrl) href="{{ $showUrl }}" @endif class="relative h-36 w-28 shrink-0 overflow-hidden rounded-xl bg-white/5">
                        @if ($challenge->original?->posterUrl())
                            <img src="{{ $challenge->original->posterUrl() }}" alt="" class="h-full w-full object-cover">
                        @endif
                        @if ($card['has_entry'])
                            <span class="absolute inset-0 flex items-center justify-center">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/70 text-xl">✓</span>
                            </span>
                        @endif
                    </{{ $linkTag }}>
                    <{{ $linkTag }} @if ($showUrl) href="{{ $showUrl }}" @endif class="min-w-0 flex-1">
                        <h2 class="text-lg font-semibold leading-tight">{{ $challenge->title }}</h2>
                        <p class="mt-1 text-white/60">{{ __('challenges.by', ['name' => '@'.($challenge->user->username ?? $challenge->user->name)]) }}</p>
                        <p class="mt-2 line-clamp-3 text-sm text-white/70">{{ $challenge->rules }}</p>
                    </{{ $linkTag }}>
                </div>

                <div class="mt-3 flex items-center justify-between">
                    @if ($card['state'] === 'closed')
                        <span class="text-sm text-white/70">
                            {{ $card['winner_name'] ? __('challenges.winner_short', ['name' => $card['winner_name']]) : __('challenges.no_votes') }}
                        </span>
                    @elseif ($challenge->ends_at)
                        <span class="text-sm text-violet-300">🕒 <span x-data="countdown(@js($challenge->ends_at->toIso8601String()))" x-init="start()" x-text="label"></span></span>
                    @else
                        <span></span>
                    @endif
                    <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $pill[1] }}">{{ __($pill[0]) }}</span>
                </div>

                <div class="mt-3 flex gap-3">
                    @switch($card['state'])
                        @case('accepted_open')
                            <a href="{{ route('challenges.respond', $challenge->slug) }}" class="flex h-12 flex-1 items-center justify-center rounded-xl bg-indigo-600 text-sm font-semibold uppercase">⬆ {{ __('challenges.upload_response') }}</a>
                            <button type="button" wire:click="leave({{ $challenge->id }})" wire:confirm="{{ __('challenges.remove_from_mine') }}?" class="h-12 rounded-xl border border-white/15 px-4" aria-label="{{ __('challenges.remove_from_mine') }}">•••</button>
                            @break
                        @case('response_processing')
                            <span class="flex h-12 flex-1 items-center justify-center rounded-xl bg-white/10 text-sm text-white/60">{{ __('challenges.response_processing') }}</span>
                            @break
                        @case('accepted_expired')
                            <span class="flex h-12 flex-1 items-center justify-center rounded-xl bg-white/10 text-sm text-white/60">{{ __('challenges.not_open') }}</span>
                            @break
                        @case('response_ready')
                            <a href="{{ route('challenges.show', ['challenge' => $challenge->slug, 'entry' => $card['my_entry_id']]) }}" class="flex h-12 flex-1 items-center justify-center rounded-xl border border-white/15 text-sm font-semibold uppercase">{{ __('challenges.my_response') }}</a>
                            @break
                        @case('own')
                            <a href="{{ $showUrl }}" class="flex h-12 flex-1 items-center justify-center rounded-xl border border-white/15 text-sm font-semibold uppercase">{{ __('challenges.responses_count', ['count' => $challenge->entries_count]) }}</a>
                            @if ($challenge->isDuel())
                                <button type="button" data-copy-invite
                                        @click="navigator.clipboard?.writeText(@js($showUrl)); $dispatch('versus-stake-toast', { title: @js(__('challenges.link_copied')) })"
                                        class="flex h-12 items-center justify-center gap-2 rounded-xl border border-orange-500/40 bg-orange-500/10 px-4 text-sm font-semibold text-orange-200">
                                    🔗 {{ __('challenges.copy_invite') }}
                                </button>
                            @endif
                            @break
                        @case('own_processing')
                            <span data-plate="own_processing" class="flex h-12 flex-1 items-center justify-center rounded-xl bg-white/10 text-sm text-white/60">{{ __('challenges.status_processing') }}</span>
                            @break
                        @case('own_failed')
                            <a href="{{ route('challenges.create', ['retry' => $challenge->slug]) }}" class="flex h-12 flex-1 items-center justify-center rounded-xl bg-rose-600 text-sm font-semibold uppercase">{{ __('challenges.upload_again') }}</a>
                            @break
                        @case('closed')
                            <a href="{{ $card['winner_entry_id'] ? route('challenges.show', ['challenge' => $challenge->slug, 'entry' => $card['winner_entry_id']]) : $showUrl }}" class="flex h-12 flex-1 items-center justify-center rounded-xl border border-white/15 text-sm font-semibold uppercase">{{ __('challenges.results') }}</a>
                            @break
                    @endswitch
                </div>
            </article>
        @endforeach
    </div>

    @if ($hasMore)
        <button type="button" wire:click="loadMore" class="mt-6 w-full rounded-xl border border-white/15 py-3">{{ __('challenges.load_more') }}</button>
    @endif
</div>
</div>
