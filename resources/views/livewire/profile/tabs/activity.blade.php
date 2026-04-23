<div class="mt-3">
    @if ($votes->isEmpty())
        <div class="mx-4 rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-white/50">
            {{ __('profile.activity_empty') }}
        </div>
    @else
        <ul class="space-y-2 px-4">
            @foreach ($votes as $vote)
                @php
                    $status = $this->statusFor($vote);
                    $net = $this->netAmountFor($vote);
                    $sideLabel = $vote->side === \App\Models\Battle::SIDE_A
                        ? $vote->battle->side_a_label
                        : $vote->battle->side_b_label;
                @endphp
                <li class="rounded-xl border border-white/5 bg-white/[0.03] p-3 flex gap-3">
                    <div class="h-12 w-20 bg-white/5 rounded-md flex-shrink-0 flex items-center justify-center text-[10px] text-white/40">VS</div>
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('battles.show', $vote->battle) }}"
                           class="block font-semibold text-white/90 truncate">{{ $vote->battle->title }}</a>
                        <div class="text-[11px] text-white/55 mt-0.5">
                            {{ __('profile.activity_you_voted') }}
                            <span class="text-white/80 font-medium">{{ $sideLabel }}</span>
                        </div>
                        <div class="text-[11px] text-white/55">
                            {{ __('profile.activity_amount') }}
                            {{ number_format((float) $vote->amount, 0) }} {{ __('profile.activity_vrs') }}
                        </div>
                    </div>
                    <div class="text-right flex flex-col items-end justify-between">
                        <span @class([
                            'px-2 py-0.5 rounded-full text-[10px] uppercase tracking-wider font-bold',
                            'bg-glow-cyan/15 text-glow-cyan' => $status === 'active',
                            'bg-green-500/15 text-green-300' => $status === 'win',
                            'bg-red-500/15 text-red-300' => $status === 'lose',
                            'bg-white/10 text-white/70' => $status === 'refund',
                        ])>{{ __('profile.activity_badge_' . $status) }}</span>

                        <span @class([
                            'text-xs',
                            'text-green-300' => $net > 0,
                            'text-red-300' => $net < 0,
                            'text-white/40' => $net === 0.0,
                        ])>
                            @if ($net > 0)+@endif{{ number_format($net, 0) }} {{ __('profile.activity_vrs') }}
                        </span>

                        <span class="text-[10px] text-white/40">
                            @if ($vote->battle->status === \App\Models\Battle::STATUS_ACTIVE)
                                {{ $vote->battle->closes_at?->diffForHumans(['parts' => 1, 'short' => true]) }}
                            @elseif ($vote->battle->settled_at)
                                {{ $vote->battle->settled_at->diffForHumans(['parts' => 1, 'short' => true]) }}
                            @endif
                        </span>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="px-4 mt-3">
            {{ $votes->links() }}
        </div>
    @endif
</div>
