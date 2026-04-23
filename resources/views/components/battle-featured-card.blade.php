@props([
    'battle',
])

@php
    /** @var \App\Models\Battle $battle */
    $closesIso = optional($battle->closes_at)->toIso8601String();
    $pcts = $battle->sidePercentages();
@endphp

<article class="mx-3 rounded-2xl border border-white/5 bg-white/[0.04] p-4 sm:p-5">
    <div class="relative overflow-hidden rounded-xl aspect-[2/1] bg-navy-800">
        <div class="absolute inset-y-0 left-0 w-[55%] bg-navy-800"
             style="clip-path: polygon(0 0, 100% 0, 82% 100%, 0 100%);">
            @if ($battle->side_a_image)
                <img src="{{ $battle->side_a_image }}" alt="{{ $battle->side_a_label }}" class="h-full w-full object-cover">
            @else
                <div class="h-full w-full flex items-center justify-center">
                    <x-icon.image-placeholder class="h-10 w-10 text-white/30" />
                </div>
            @endif
        </div>
        <div class="absolute inset-y-0 right-0 w-[55%] bg-navy-800"
             style="clip-path: polygon(18% 0, 100% 0, 100% 100%, 0 100%);">
            @if ($battle->side_b_image)
                <img src="{{ $battle->side_b_image }}" alt="{{ $battle->side_b_label }}" class="h-full w-full object-cover">
            @else
                <div class="h-full w-full flex items-center justify-center">
                    <x-icon.image-placeholder class="h-10 w-10 text-white/30" />
                </div>
            @endif
        </div>

        <div class="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/65 to-transparent pointer-events-none"></div>

        <div class="absolute bottom-3 left-4 right-4 flex items-end justify-between gap-2 text-sm sm:text-base font-semibold text-white drop-shadow">
            <span class="truncate max-w-[40%]">{{ $battle->side_a_label }}</span>
            <span class="truncate max-w-[40%] text-right">{{ $battle->side_b_label }}</span>
        </div>

        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 flex items-center gap-2">
            <span class="text-sm sm:text-base font-bold text-white drop-shadow">{{ $pcts['a'] }}%</span>
            <span class="h-10 w-10 rounded-full border border-white/25 bg-navy-900
                         text-xs font-bold text-white/90 flex items-center justify-center">
                VS
            </span>
            <span class="text-sm sm:text-base font-bold text-white drop-shadow">{{ $pcts['b'] }}%</span>
        </div>
    </div>

    <div class="mt-4 flex items-center justify-center gap-6 text-sm text-white/70">
        <span>💰 {{ number_format((float) $battle->total_pool, 0, '.', ',') }} VRS</span>
        @if ($closesIso)
            <span class="font-mono" x-data="countdown('{{ $closesIso }}')" x-init="start()" x-text="label">—</span>
        @endif
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3">
        <a href="{{ route('battles.show', $battle) }}"
           class="rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 transition py-3 text-center text-sm font-semibold text-white">
            {{ __('battle.vote_for_side', ['side' => $battle->side_a_label]) }}
        </a>
        <a href="{{ route('battles.show', $battle) }}"
           class="rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 transition py-3 text-center text-sm font-semibold text-white">
            {{ __('battle.vote_for_side', ['side' => $battle->side_b_label]) }}
        </a>
    </div>

    @if ($battle->is_sponsored && $battle->sponsor_handle)
        <div class="mt-3 text-center text-xs text-white/45 tracking-wide">
            {{ __('battle.sponsored_by', ['handle' => $battle->sponsor_handle]) }}
        </div>
    @endif
</article>
