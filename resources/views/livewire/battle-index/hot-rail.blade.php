@php /** @var \Illuminate\Support\Collection<int, \App\Models\Battle> $hot */ @endphp

@if ($hot->isNotEmpty())
    <section class="mt-5">
        <div class="flex items-baseline justify-between px-3 mb-2">
            <div class="text-[11px] uppercase tracking-wider text-white/55">
                {{ __('battle.hot') }}
            </div>
            <button type="button"
                    wire:click="clearFilters"
                    x-on:click="$nextTick(() => document.getElementById('all-battles')?.scrollIntoView({behavior:'smooth'}))"
                    class="text-[11px] text-glow-cyan hover:underline">
                {{ __('battle.view_all') }} ›
            </button>
        </div>
        <div class="grid grid-cols-1 gap-2 px-3 lg:grid-cols-2 lg:gap-3">
            @foreach ($hot as $battle)
                <x-battle-row :battle="$battle" />
            @endforeach
        </div>
    </section>
@endif
