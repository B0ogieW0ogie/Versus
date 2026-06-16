<div class="mt-3">
    @if ($created->isEmpty())
        <div class="rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-white/50">
            {{ __('profile.created_empty') }}
        </div>
    @else
        <ul class="space-y-2">
            @foreach ($created as $battle)
                @php
                    $isActive = $battle->status === \App\Models\Battle::STATUS_ACTIVE;
                @endphp
                <li>
                    <div class="mb-1 flex items-center justify-between text-[11px] text-white/40">
                        <span>{{ $battle->created_at?->diffForHumans(['parts' => 1, 'short' => true]) }}</span>
                        <span class="font-semibold {{ $isActive ? 'text-glow-cyan' : 'text-white/45' }}">
                            {{ $isActive ? __('profile.activity_badge_active') : __('profile.created_badge_closed') }}
                        </span>
                    </div>

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
                                <div class="text-sm font-bold uppercase tracking-widest leading-none text-vote-purple-to">VS</div>
                                <div class="mt-1 text-xs text-white/70">
                                    {{ number_format((float) $battle->total_pool, 0) }} {{ __('profile.activity_vrs') }}
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
            {{ $created->links() }}
        </div>
    @endif
</div>
