@props([
    'battle',
    'size' => 'default',
])

@php
    /** @var \App\Models\Battle $battle */
    $timeLeft = $battle->closes_at
        ? $battle->closes_at->diff(now())
        : null;
    $timeLabel = $timeLeft
        ? sprintf('%02d:%02d', (int) $timeLeft->format('%a') * 24 + (int) $timeLeft->h, $timeLeft->i)
        : '—';

    $isLarge = $size === 'lg';
@endphp

<a href="{{ route('battles.show', $battle) }}"
   @class([
       'block rounded-xl border border-white/5 bg-white/[0.035] hover:bg-white/[0.06] transition',
       'p-3' => ! $isLarge,
       'p-4 sm:p-5' => $isLarge,
   ])>
    <div data-carousel-arrow-anchor
         class="relative overflow-hidden rounded-lg aspect-[2/1] bg-navy-800">
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
        <span @class([
            'absolute top-1/2 left-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-white/20 bg-navy-900 font-bold text-white/90',
            'h-7 w-7 text-[10px]' => ! $isLarge,
            'h-9 w-9 text-xs sm:h-10 sm:w-10' => $isLarge,
        ])>
            VS
        </span>
    </div>

    <div @class([
        'truncate text-white/90',
        'mt-2 text-sm' => ! $isLarge,
        'mt-3 text-sm font-semibold sm:text-base' => $isLarge,
    ])>{{ $battle->title }}</div>

    <div @class([
        'mt-1 flex items-center justify-between text-white/55',
        'text-[11px]' => ! $isLarge,
        'text-xs sm:text-sm' => $isLarge,
    ])>
        <span>💰 {{ $battle->compactPool() }}</span>
        <span>⏱ {{ $timeLabel }}</span>
    </div>
</a>
