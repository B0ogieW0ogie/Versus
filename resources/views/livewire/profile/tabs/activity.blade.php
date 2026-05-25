<div class="mt-3">
    @if ($votes->isEmpty())
        <div class="rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-white/50">
            {{ __('profile.activity_empty') }}
        </div>
    @else
        <ul class="space-y-2">
            @foreach ($votes as $battle)
                @php
                    $status = $this->statusFor($battle);
                    $selectedSide = $this->selectedSideFor($battle);
                    $selectedStake = $this->selectedStakeFor($battle);
                @endphp
                <li>
                    @if ($battle->status !== \App\Models\Battle::STATUS_ACTIVE && $battle->settled_at)
                        <div class="mb-1 text-[11px] text-white/40">
                            {{ $battle->settled_at->diffForHumans(['parts' => 1, 'short' => true]) }}
                        </div>
                    @endif

                    <a href="{{ route('battles.show', $battle) }}"
                       class="block rounded-xl border border-white/5 bg-white/[0.03] p-3">
                        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="h-10 w-8 rounded-md bg-white/5 overflow-hidden flex-shrink-0">
                                    @if ($battle->side_a_image)
                                        <img src="{{ $battle->side_a_image }}" alt="{{ $battle->side_a_label }}" class="h-full w-full object-cover">
                                    @endif
                                </div>
                                <div class="truncate text-base font-semibold text-white/90">{{ $battle->side_a_label }}</div>
                            </div>

                            <div class="text-center">
                                <div class="flex items-center justify-center text-sm font-bold uppercase tracking-wide leading-none">
                                    @if ($selectedSide === \App\Models\Battle::SIDE_A)
                                        <span class="w-20 text-right {{ $status === 'win' ? 'text-green-300' : ($status === 'lose' ? 'text-red-300' : 'text-glow-cyan') }}">◀ {{ __('profile.activity_badge_' . $status) }}</span>
                                        <span class="mx-2 text-vote-purple-to tracking-widest">VS</span>
                                        <span class="w-20"></span>
                                    @else
                                        <span class="w-20"></span>
                                        <span class="mx-2 text-vote-purple-to tracking-widest">VS</span>
                                        <span class="w-20 text-left {{ $status === 'win' ? 'text-green-300' : ($status === 'lose' ? 'text-red-300' : 'text-glow-cyan') }}">{{ __('profile.activity_badge_' . $status) }} ▶</span>
                                    @endif
                                </div>
                                <div class="mt-1 text-xs text-white/70">
                                    {{ number_format($selectedStake, 0) }} {{ __('profile.activity_vrs') }}
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 min-w-0">
                                <div class="truncate text-base font-semibold text-white/90">{{ $battle->side_b_label }}</div>
                                <div class="h-10 w-8 rounded-md bg-white/5 overflow-hidden flex-shrink-0">
                                    @if ($battle->side_b_image)
                                        <img src="{{ $battle->side_b_image }}" alt="{{ $battle->side_b_label }}" class="h-full w-full object-cover">
                                    @endif
                                </div>
                            </div>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-3">
            {{ $votes->links() }}
        </div>
    @endif
</div>
