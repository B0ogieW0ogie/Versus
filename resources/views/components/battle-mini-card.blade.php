@props(['battle'])

@php
    use App\Models\Battle;
    use Illuminate\Support\Str;

    /** @var \App\Models\Battle $battle */
    $open = $battle->isOpenForVoting();
    $timeLeft = ($open && $battle->closes_at) ? $battle->closes_at->diff(now()) : null;
    $timeLabel = $timeLeft
        ? sprintf('%02d:%02d', (int) $timeLeft->format('%a') * 24 + (int) $timeLeft->h, $timeLeft->i)
        : null;
    $sideALabel = Str::title($battle->side_a_label);
    $sideBLabel = Str::title($battle->side_b_label);

    $settled = $battle->status === Battle::STATUS_SETTLED && $battle->winning_side !== null;
    $aWon = $settled && $battle->winning_side === Battle::SIDE_A;
    $bWon = $settled && $battle->winning_side === Battle::SIDE_B;
@endphp

<a href="{{ route('battles.show', $battle) }}"
   class="block rounded-2xl bg-[#1A1F2B] p-4 transition hover:bg-[#202636]">
    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3">
        <div class="min-w-0 text-left">
            <span class="block truncate text-lg font-bold text-white">{{ $sideALabel }}</span>
            @if ($settled)
                <span class="mt-1 block text-xs font-bold uppercase tracking-wide {{ $aWon ? 'text-emerald-400' : 'text-red-400' }}">
                    {{ $aWon ? __('battle.winner_badge') : __('battle.loser_badge') }}
                </span>
            @endif
        </div>

        <div class="flex shrink-0 flex-col items-center">
            <span class="text-base font-extrabold uppercase tracking-wide text-indigo-400">{{ __('battle.vs') }}</span>
            <span class="mt-1 text-[11px] text-white/55">💰 {{ $battle->compactPool() }}</span>
            @if ($timeLabel)
                <span class="mt-0.5 text-[11px] text-white/55">⏱ {{ $timeLabel }}</span>
            @endif
        </div>

        <div class="min-w-0 text-right">
            <span class="block truncate text-lg font-bold text-white">{{ $sideBLabel }}</span>
            @if ($settled)
                <span class="mt-1 block text-xs font-bold uppercase tracking-wide {{ $bWon ? 'text-emerald-400' : 'text-red-400' }}">
                    {{ $bWon ? __('battle.winner_badge') : __('battle.loser_badge') }}
                </span>
            @endif
        </div>
    </div>
</a>
