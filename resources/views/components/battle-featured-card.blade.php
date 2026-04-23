@props([
    'battle',
])

@php
    /** @var \App\Models\Battle $battle */
    $closesIso = optional($battle->closes_at)->toIso8601String();
@endphp

<article class="mx-3 rounded-2xl border border-white/5 bg-white/[0.04] p-4 sm:p-5">
    <div class="relative flex items-stretch gap-3">
        <figure class="flex-1 flex flex-col items-center gap-2">
            <div class="w-full aspect-square rounded-xl bg-navy-800 overflow-hidden flex items-center justify-center">
                @if ($battle->side_a_image)
                    <img src="{{ $battle->side_a_image }}" alt="{{ $battle->side_a_label }}" class="h-full w-full object-cover">
                @else
                    <x-icon.image-placeholder class="h-10 w-10 text-white/30" />
                @endif
            </div>
            <figcaption class="font-semibold text-white">{{ $battle->side_a_label }}</figcaption>
        </figure>

        <figure class="flex-1 flex flex-col items-center gap-2">
            <div class="w-full aspect-square rounded-xl bg-navy-800 overflow-hidden flex items-center justify-center">
                @if ($battle->side_b_image)
                    <img src="{{ $battle->side_b_image }}" alt="{{ $battle->side_b_label }}" class="h-full w-full object-cover">
                @else
                    <x-icon.image-placeholder class="h-10 w-10 text-white/30" />
                @endif
            </div>
            <figcaption class="font-semibold text-white">{{ $battle->side_b_label }}</figcaption>
        </figure>

        <span class="absolute top-[30%] left-1/2 -translate-x-1/2 -translate-y-1/2
                     h-10 w-10 rounded-full border border-white/25 bg-navy-900
                     text-xs font-bold text-white/90 flex items-center justify-center">
            VS
        </span>
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
