@props([
    'battle',
    'showVote' => true,
])

<a href="{{ route('battles.show', $battle) }}"
   class="grid grid-cols-[56px_minmax(0,1fr)_auto] items-center gap-3 rounded-xl border border-white/5 bg-white/[0.035] px-3 py-2.5 hover:bg-white/[0.06] transition">
    <span class="relative h-11 w-14 shrink-0">
        <span class="absolute inset-y-1 left-0 h-9 w-9 rounded-full bg-navy-700 border-2 border-navy-900 flex items-center justify-center text-[11px] font-bold text-white/90">
            {{ mb_strtoupper(mb_substr($battle->side_a_label, 0, 1)) }}
        </span>
        <span class="absolute inset-y-1 right-0 h-9 w-9 rounded-full bg-navy-700 border-2 border-navy-900 flex items-center justify-center text-[11px] font-bold text-white/90">
            {{ mb_strtoupper(mb_substr($battle->side_b_label, 0, 1)) }}
        </span>
    </span>

    <span class="min-w-0">
        <span class="block truncate text-sm text-white/90">{{ $battle->title }}</span>
        <span class="block mt-0.5 text-[11px] text-white/50">
            @if ($battle->status === \App\Models\Battle::STATUS_SETTLED)
                @if ($battle->winning_side === null)
                    {{ __('battle.refunded_tie') }}
                @else
                    {{ __('battle.winner') }}: {{ $battle->winning_side === \App\Models\Battle::SIDE_A ? $battle->side_a_label : $battle->side_b_label }}
                @endif
            @else
                {{ __('battle.pool') }}: {{ number_format((float) $battle->total_pool, 0) }}
            @endif
        </span>
    </span>

    <span class="text-right text-[11px] text-white/60">
        @if ($battle->status === \App\Models\Battle::STATUS_ACTIVE && $battle->closes_at !== null)
            ⏱ {{ $battle->closes_at->diffForHumans(['parts' => 1, 'short' => true]) }}
        @elseif ($battle->settled_at !== null)
            {{ $battle->settled_at->diffForHumans(['parts' => 1, 'short' => true]) }}
        @endif

        @if ($showVote && $battle->isOpenForVoting())
            <span class="mt-1.5 inline-block rounded-md bg-vote-blue-from/20 px-2.5 py-1 text-[11px] font-bold text-glow-cyan">
                {{ __('battle.vote') }}
            </span>
        @endif
    </span>
</a>
