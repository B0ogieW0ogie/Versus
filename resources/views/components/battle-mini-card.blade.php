@props(['battle'])

@php
    use Illuminate\Support\Str;

    /** @var \App\Models\Battle $battle */
    $open = $battle->isOpenForVoting();
    $timeLeft = ($open && $battle->closes_at) ? $battle->closes_at->diff(now()) : null;
    $timeLabel = $timeLeft
        ? sprintf('%02d:%02d', (int) $timeLeft->format('%a') * 24 + (int) $timeLeft->h, $timeLeft->i)
        : null;
    $sideALabel = Str::title($battle->side_a_label);
    $sideBLabel = Str::title($battle->side_b_label);
@endphp

<a href="{{ route('battles.show', $battle) }}"
   class="block rounded-2xl bg-[#1A1F2B] p-3 transition hover:bg-[#202636]">
    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
        <span class="truncate text-sm font-bold text-white">{{ $sideALabel }}</span>
        <span class="shrink-0 text-xs font-bold uppercase tracking-wide text-white/70">{{ __('battle.vs') }}</span>
        <span class="truncate text-right text-sm font-bold text-white">{{ $sideBLabel }}</span>
    </div>
    <div class="mt-2 flex items-center justify-between text-[11px] text-white/55">
        <span>💰 {{ $battle->compactPool() }}</span>
        @if ($timeLabel)
            <span>⏱ {{ $timeLabel }}</span>
        @endif
    </div>
</a>
