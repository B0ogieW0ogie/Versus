@props([
    'battle',
])

@php
    /** @var \App\Models\Battle $battle */
    $timeLeft = $battle->closes_at
        ? $battle->closes_at->diff(now())
        : null;
    $timeLabel = $timeLeft
        ? sprintf('%02d:%02d', (int) $timeLeft->format('%a') * 24 + (int) $timeLeft->h, $timeLeft->i)
        : '—';
@endphp

<a href="{{ route('battles.show', $battle) }}"
   class="block rounded-xl border border-white/5 bg-white/[0.035] p-3 hover:bg-white/[0.06] transition">
    <div class="relative flex items-stretch gap-2">
        <div class="flex-1 aspect-square rounded-lg bg-navy-800 overflow-hidden flex items-center justify-center">
            @if ($battle->side_a_image)
                <img src="{{ $battle->side_a_image }}" alt="{{ $battle->side_a_label }}" class="h-full w-full object-cover">
            @else
                <x-icon.image-placeholder />
            @endif
        </div>
        <div class="flex-1 aspect-square rounded-lg bg-navy-800 overflow-hidden flex items-center justify-center">
            @if ($battle->side_b_image)
                <img src="{{ $battle->side_b_image }}" alt="{{ $battle->side_b_label }}" class="h-full w-full object-cover">
            @else
                <x-icon.image-placeholder />
            @endif
        </div>
        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
                     h-8 w-8 rounded-full border border-white/20 bg-navy-900
                     text-[10px] font-bold text-white/90 flex items-center justify-center">
            VS
        </span>
    </div>

    <div class="mt-2 text-sm text-white/90 truncate">{{ $battle->title }}</div>

    <div class="mt-1 flex items-center justify-between text-[11px] text-white/55">
        <span>💰 {{ $battle->compactPool() }}</span>
        <span>⏱ {{ $timeLabel }}</span>
    </div>
</a>
