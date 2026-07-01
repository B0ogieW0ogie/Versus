@props([
    'battle',
])

@php
    /** @var \App\Models\Battle $battle */
    $closesIso = optional($battle->closes_at)->toIso8601String();
@endphp

<article class="rounded-2xl border border-white/5 bg-white/[0.04] p-4 sm:p-5">
    <div data-carousel-arrow-anchor
         class="relative overflow-hidden rounded-xl aspect-[2/1] bg-navy-800">
        <div class="absolute inset-y-0 left-0 w-[55%] bg-navy-800"
             style="clip-path: polygon(0 0, 100% 0, 82% 100%, 0 100%);">
            @if ($battle->side_a_image)
                <img src="{{ $battle->side_a_image }}" alt="{{ $battle->side_a_label }}" class="h-full w-full object-cover">
            @else
                <div class="flex h-full w-full items-center justify-center">
                    <x-icon.image-placeholder class="h-10 w-10 text-white/30" />
                </div>
            @endif
        </div>
        <div class="absolute inset-y-0 right-0 w-[55%] bg-navy-800"
             style="clip-path: polygon(18% 0, 100% 0, 100% 100%, 0 100%);">
            @if ($battle->side_b_image)
                <img src="{{ $battle->side_b_image }}" alt="{{ $battle->side_b_label }}" class="h-full w-full object-cover">
            @else
                <div class="flex h-full w-full items-center justify-center">
                    <x-icon.image-placeholder class="h-10 w-10 text-white/30" />
                </div>
            @endif
        </div>

        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>

        <div class="absolute bottom-3 left-4 right-4 flex items-end justify-between gap-2 text-sm font-semibold text-white drop-shadow sm:text-base">
            <span class="max-w-[40%] truncate">{{ $battle->side_a_label }}</span>
            <span class="max-w-[40%] truncate text-right">{{ $battle->side_b_label }}</span>
        </div>

        <span class="absolute left-1/2 top-1/2 flex h-10 w-10 -translate-x-1/2 -translate-y-1/2 items-center justify-center
                     rounded-full border border-white/25 bg-navy-900 text-xs font-bold text-white/90">
            {{ __('battle.vs') }}
        </span>
    </div>

    <div class="mt-4 grid grid-cols-3 items-center gap-2">
        <a href="{{ route('battles.show', $battle) }}"
           class="rounded-xl bg-gradient-to-r from-vote-blue-from to-vote-blue-to py-3 text-center text-sm font-bold uppercase
                  tracking-wider text-white shadow-vote-blue transition hover:brightness-110">
            {{ __('battle.vote') }}
        </a>
        <div class="flex flex-col items-center justify-center gap-0.5 text-center text-xs text-white/80">
            <span class="font-semibold tabular-nums"
                  x-data="livePool({ battleId: {{ $battle->id }}, amount: {{ (float) $battle->total_pool }} })">
                💰 <span x-ref="value" x-text="display">{{ number_format((float) $battle->total_pool, 0, '.', ',') }}</span> {{ __('battle.tokens') }}
            </span>
            @if ($closesIso)
                <span class="font-mono text-[11px] text-white/65"
                      x-data="countdown('{{ $closesIso }}')"
                      x-init="start()"
                      x-text="label">—</span>
            @endif
        </div>
        <a href="{{ route('battles.show', $battle) }}"
           class="rounded-xl bg-gradient-to-r from-rose-500 to-red-700 py-3 text-center text-sm font-bold uppercase
                  tracking-wider text-white shadow-lg shadow-rose-900/40 transition hover:brightness-110">
            {{ __('battle.vote') }}
        </a>
    </div>
</article>
