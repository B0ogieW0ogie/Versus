<div>
    @php
        $distribution = config('versus.distribution');
        $winPct = (int) round(($distribution['winners'] ?? 0) * 100);
        $projectPct = (int) round(($distribution['project'] ?? 0) * 100);
        $drawPct = (int) round(($distribution['reward_pool'] ?? 0) * 100);
        $closesDate = optional($battle->closes_at)->isoFormat('D MMMM');
    @endphp

    <section class="rounded-2xl bg-navy-800 border border-white/5 p-4 text-white">
        <header class="flex items-center gap-3 pb-3 border-b border-white/5">
            <div class="flex">
                <div class="h-8 w-8 rounded-lg ring-2 ring-navy-800 overflow-hidden bg-gradient-to-br from-vote-blue-from to-vote-blue-to">
                    @if ($battle->side_a_image)
                        <img src="{{ $battle->side_a_image }}" alt="{{ $battle->side_a_label }}" class="h-full w-full object-cover">
                    @endif
                </div>
                <div class="-ml-2 h-8 w-8 rounded-lg ring-2 ring-navy-800 overflow-hidden bg-gradient-to-br from-vote-purple-from to-vote-purple-to">
                    @if ($battle->side_b_image)
                        <img src="{{ $battle->side_b_image }}" alt="{{ $battle->side_b_label }}" class="h-full w-full object-cover">
                    @endif
                </div>
            </div>
            <div class="leading-tight">
                @if ($closesDate)
                    <p class="text-sm font-semibold">{{ __('battle.widget_closes_on', ['date' => $closesDate]) }}</p>
                @else
                    <p class="text-sm font-semibold">{{ $battle->title }}</p>
                @endif
            </div>
        </header>

        @guest
            <p class="mt-4 text-center text-sm">
                <a href="{{ route('login') }}" class="underline hover:text-glow-cyan">
                    {{ __('battle.sign_in_to_vote') }}
                </a>
            </p>
        @else
            @if (! $battle->isOpenForVoting())
                <p class="mt-4 text-center text-sm text-white/70">{{ __('battle.voting_closed') }}</p>
            @else
                <div class="mt-4"
                     x-data="voteWidget({
                        side: '{{ $defaultSide }}',
                        poolA: {{ (float) $poolA }},
                        poolB: {{ (float) $poolB }},
                        max: {{ (int) $maxAllowed }},
                        maxCap: {{ (int) $maxVoteAmount }},
                        winnersCut: {{ (float) $winnersCut }},
                        sideALabel: @js(mb_strtoupper($battle->side_a_label)),
                        sideBLabel: @js(mb_strtoupper($battle->side_b_label)),
                        i18n: @js([
                            'clamp' => __('battle.amount_clamped'),
                            'ctaPrefix' => __('battle.widget_cta_prefix'),
                            'payoutPreview' => __('battle.widget_payout_preview'),
                            'noMultiplier' => __('battle.widget_no_multiplier'),
                        ]),
                     })"
                     x-on:balance-updated.window="onBalance($event.detail.balance)"
                     x-on:battle-voted.window="onPools({{ (float) $poolA }}, {{ (float) $poolB }})">
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button"
                                @click="pickSide('A')"
                                :class="side === 'A'
                                    ? 'bg-gradient-to-br from-vote-blue-from to-vote-blue-to shadow-vote-blue border-white/20'
                                    : 'bg-navy-900 border-white/5 hover:border-white/10'"
                                class="rounded-xl border px-3 py-3 text-center transition">
                            <div class="text-sm font-bold uppercase tracking-wider">{{ mb_strtoupper($battle->side_a_label) }}</div>
                            <div class="text-[11px] opacity-80 mt-0.5"><span x-text="percentA"></span>%</div>
                        </button>
                        <button type="button"
                                @click="pickSide('B')"
                                :class="side === 'B'
                                    ? 'bg-gradient-to-br from-vote-purple-from to-vote-purple-to shadow-vote-purple border-white/20'
                                    : 'bg-navy-900 border-white/5 hover:border-white/10'"
                                class="rounded-xl border px-3 py-3 text-center transition">
                            <div class="text-sm font-bold uppercase tracking-wider">{{ mb_strtoupper($battle->side_b_label) }}</div>
                            <div class="text-[11px] opacity-80 mt-0.5"><span x-text="percentB"></span>%</div>
                        </button>
                    </div>

                    <div class="mt-4">
                        <label class="block text-[10px] uppercase tracking-wider text-white/50">
                            {{ __('battle.widget_amount_label') }}
                        </label>
                        <div class="flex items-baseline justify-end gap-2 border-b border-white/10 py-2">
                            <input type="number"
                                   x-model.number="amount"
                                   @input="clamp()"
                                   @blur="clamp(true)"
                                   min="0"
                                   :max="max"
                                   inputmode="numeric"
                                   class="w-full bg-transparent border-0 text-right font-mono text-2xl font-bold text-white
                                          focus:outline-none focus:ring-0 placeholder:text-white/30"
                                   placeholder="0">
                            <span class="text-sm text-white/50">🪙</span>
                        </div>
                        <p x-show="err"
                           x-transition.opacity.duration.500ms
                           x-cloak
                           x-text="err"
                           class="mt-1 text-center text-xs text-amber-300"></p>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" @click="addChip(10)" class="rounded-full bg-navy-900 border border-white/5 px-3 py-1 text-xs text-white/80 hover:border-white/20">+10</button>
                        <button type="button" @click="addChip(100)" class="rounded-full bg-navy-900 border border-white/5 px-3 py-1 text-xs text-white/80 hover:border-white/20">+100</button>
                        <button type="button" @click="addChip(1000)" class="rounded-full bg-navy-900 border border-white/5 px-3 py-1 text-xs text-white/80 hover:border-white/20">+1000</button>
                        <button type="button" @click="setMax()" class="rounded-full bg-navy-900 border border-white/5 px-3 py-1 text-xs text-white/80 hover:border-white/20">{{ __('battle.widget_chip_max') }}</button>
                    </div>

                    <div x-show="payoutLabel"
                         x-cloak
                         class="mt-3 rounded-lg bg-navy-900/80 border border-glow-cyan/20 px-3 py-2 text-xs text-white/80">
                        <span x-text="payoutLabel"></span>
                    </div>

                    <button type="button"
                            @click="submit()"
                            :disabled="!canSubmit"
                            wire:loading.attr="disabled"
                            @disabled(! $canVote)
                            :class="side === 'A'
                                ? 'bg-gradient-to-r from-vote-blue-from to-vote-blue-to shadow-vote-blue'
                                : 'bg-gradient-to-r from-vote-purple-from to-vote-purple-to shadow-vote-purple'"
                            class="mt-4 w-full rounded-xl py-3 text-center font-bold uppercase tracking-wider transition
                                   hover:brightness-110
                                   disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:brightness-100">
                        <span x-text="ctaLabel"></span>
                    </button>

                    @error('vote')
                        <p class="mt-3 text-center text-sm text-red-400">{{ $message }}</p>
                    @enderror

                    @if (session('battle-status'))
                        <p class="mt-3 text-center text-sm text-emerald-400">{{ session('battle-status') }}</p>
                    @endif

                    <div class="mt-4 flex items-center justify-between text-[11px] text-white/50">
                        <span>{{ __('battle.widget_balance') }}</span>
                        <span class="font-mono text-white/80">{{ number_format((int) $userBalance) }} 🪙</span>
                    </div>

                    <p class="mt-2 text-center text-[10px] tracking-wider text-white/40">
                        {{ __('battle.distribution', ['win' => $winPct, 'project' => $projectPct, 'draw' => $drawPct]) }}
                    </p>
                </div>
            @endif
        @endguest
    </section>
</div>
