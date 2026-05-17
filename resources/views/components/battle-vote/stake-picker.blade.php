@props([
    'side',
])

@php
    assert(in_array($side, ['A', 'B'], true));
    $amountModel = $side === 'A' ? 'amountA' : 'amountB';
    $ringClass = $side === 'A' ? 'ring-sky-500/30' : 'ring-rose-500/35';
@endphp

<div {{ $attributes->merge(['class' => "flex items-center gap-2 rounded-lg bg-navy-800/90 px-2 py-2 ring-1 {$ringClass}"]) }}>
    <div class="flex flex-1 flex-wrap gap-1">
        <button type="button" @click="addChip('{{ $side }}', 10)"
                class="rounded-md bg-navy-900 px-2 py-1 text-[11px] font-semibold text-white/85 hover:bg-navy-700">
            +10
        </button>
        <button type="button" @click="addChip('{{ $side }}', 100)"
                class="rounded-md bg-navy-900 px-2 py-1 text-[11px] font-semibold text-white/85 hover:bg-navy-700">
            +100
        </button>
        <button type="button" @click="addChip('{{ $side }}', 1000)"
                class="rounded-md bg-navy-900 px-2 py-1 text-[11px] font-semibold text-white/85 hover:bg-navy-700">
            +1000
        </button>
        <button type="button" @click="setMax('{{ $side }}')"
                class="rounded-md bg-navy-900 px-2 py-1 text-[11px] font-semibold text-white/85 hover:bg-navy-700">
            {{ __('battle.widget_chip_max') }}
        </button>
    </div>
    <input type="number"
           x-model.number="{{ $amountModel }}"
           @input="clamp('{{ $side }}')"
           @blur="clamp('{{ $side }}', true)"
           min="0"
           :max="max"
           inputmode="numeric"
           class="w-20 shrink-0 bg-transparent text-right font-mono text-lg font-bold text-white
                  focus:outline-none focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
</div>
