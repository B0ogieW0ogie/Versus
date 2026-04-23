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
    $pcts = $battle->sidePercentages();
@endphp

<a href="{{ route('battles.show', $battle) }}"
   class="block rounded-xl border border-white/5 bg-white/[0.035] p-3 hover:bg-white/[0.06] transition">
    <div class="relative overflow-hidden rounded-lg aspect-[2/1] bg-navy-800">
        <div class="absolute inset-y-0 left-0 w-[55%] bg-navy-800"
             style="clip-path: polygon(0 0, 100% 0, 82% 100%, 0 100%);">
            @if ($battle->side_a_image)
                <img src="{{ $battle->side_a_image }}" alt="{{ $battle->side_a_label }}" class="h-full w-full object-cover">
            @else
                <div class="h-full w-full flex items-center justify-center">
                    <x-icon.image-placeholder />
                </div>
            @endif
        </div>
        <div class="absolute inset-y-0 right-0 w-[55%] bg-navy-800"
             style="clip-path: polygon(18% 0, 100% 0, 100% 100%, 0 100%);">
            @if ($battle->side_b_image)
                <img src="{{ $battle->side_b_image }}" alt="{{ $battle->side_b_label }}" class="h-full w-full object-cover">
            @else
                <div class="h-full w-full flex items-center justify-center">
                    <x-icon.image-placeholder />
                </div>
            @endif
        </div>
        <div class="absolute inset-0 flex items-center justify-center gap-1.5">
            <span class="text-[10px] font-bold text-white drop-shadow">{{ $pcts['a'] }}%</span>
            <span class="h-7 w-7 rounded-full border border-white/20 bg-navy-900
                         text-[10px] font-bold text-white/90 flex items-center justify-center">
                VS
            </span>
            <span class="text-[10px] font-bold text-white drop-shadow">{{ $pcts['b'] }}%</span>
        </div>
    </div>

    <div class="mt-2 text-sm text-white/90 truncate">{{ $battle->title }}</div>

    <div class="mt-1 flex items-center justify-between text-[11px] text-white/55">
        <span>💰 {{ $battle->compactPool() }}</span>
        <span>⏱ {{ $timeLabel }}</span>
    </div>
</a>
