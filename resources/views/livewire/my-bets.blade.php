<div class="max-w-xl mx-auto pt-4 pb-6">
    <header class="px-4 mb-3 flex items-center gap-2">
        <x-icon.chart class="h-5 w-5 text-white/70" />
        <h1 class="text-xl font-semibold text-white">{{ __('my_bets.title') }}</h1>
    </header>

    <div class="mx-3 grid grid-cols-2 rounded-xl overflow-hidden bg-white/[0.04] border border-white/5 mb-3">
        <button type="button"
                wire:click="selectTab('active')"
                class="py-2 text-xs text-center
                       {{ $tab === 'active' ? 'bg-navy-800 text-white' : 'text-white/60 hover:text-white' }}">
            {{ __('my_bets.tab_active') }}
        </button>
        <button type="button"
                wire:click="selectTab('settled')"
                class="py-2 text-xs text-center
                       {{ $tab === 'settled' ? 'bg-navy-800 text-white' : 'text-white/60 hover:text-white' }}">
            {{ __('my_bets.tab_settled') }}
        </button>
    </div>

    @if ($votes->isEmpty())
        <div class="mx-3 rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-white/50">
            {{ $tab === 'active' ? __('my_bets.empty_active') : __('my_bets.empty_settled') }}
        </div>
    @else
        <div class="space-y-2 px-3">
            @foreach ($votes as $vote)
                @php
                    $status = $this->statusFor($vote);
                    $net = $this->netAmountFor($vote);
                    $sideLabel = $vote->side === \App\Models\Battle::SIDE_A ? $vote->battle->side_a_label : $vote->battle->side_b_label;
                @endphp
                <div class="rounded-xl border border-white/5 bg-white/[0.03] p-3">
                    <a href="{{ route('battles.show', $vote->battle) }}"
                       class="block font-semibold text-white/90">{{ $vote->battle->title }}</a>
                    <div class="mt-1 text-[11px] text-white/55">
                        {{ __('my_bets.voted') }}
                        <span class="font-semibold text-white/90">{{ mb_strtoupper($sideLabel) }}</span>
                        · {{ __('my_bets.stake') }} {{ number_format((float) $vote->amount, 0) }}
                        @if ($vote->battle->status === \App\Models\Battle::STATUS_ACTIVE)
                            · {{ $vote->battle->closes_at?->diffForHumans(['parts' => 1, 'short' => true]) }}
                        @elseif ($vote->battle->settled_at)
                            · {{ $vote->battle->settled_at->diffForHumans(['parts' => 1, 'short' => true]) }}
                        @endif
                    </div>
                    <div class="mt-2 flex justify-between items-center">
                        <span @class([
                            'px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wider font-bold',
                            'bg-glow-cyan/15 text-glow-cyan' => $status === 'active',
                            'bg-green-500/15 text-green-300' => $status === 'won',
                            'bg-red-500/15 text-red-300' => $status === 'lost',
                            'bg-white/10 text-white/70' => $status === 'refund',
                        ])>{{ __('my_bets.status_'.$status) }}</span>

                        <span @class([
                            'text-xs',
                            'text-green-300' => $net > 0,
                            'text-red-300' => $net < 0,
                            'text-white/50' => $net === 0.0,
                        ])>
                            @if ($net > 0)+@endif{{ number_format($net, 0) }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="px-3 mt-3">
            {{ $votes->links() }}
        </div>
    @endif
</div>
