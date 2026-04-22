@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $all */
@endphp

<section id="all-battles" class="mt-5">
    <div class="px-3 mb-2 text-[11px] uppercase tracking-wider text-white/55">
        {{ __('battle.all_battles') }}
    </div>

    @if ($all->total() === 0)
        <div class="mx-3 rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-white/50">
            @if ($finished)
                {{ __('battle.no_settled_battles') }}
            @elseif ($category !== null)
                {{ __('battle.no_battles_in_category') }}
            @else
                {{ __('battle.no_battles') }}
            @endif
        </div>
    @else
        <div class="space-y-2 px-3">
            @foreach ($all as $battle)
                <x-battle-row :battle="$battle" />
            @endforeach
        </div>

        @if ($all->hasMorePages())
            <div class="px-3 mt-3">
                <button type="button" wire:click="nextPage"
                        class="w-full rounded-xl border border-dashed border-white/10 py-3 text-sm text-white/60 hover:text-white">
                    {{ __('battle.load_more') }}
                </button>
            </div>
        @endif
    @endif
</section>
