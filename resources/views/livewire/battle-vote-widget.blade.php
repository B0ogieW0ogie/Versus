<div>
    @guest
        <div class="border-t border-white/10 bg-navy-950/80 px-4 py-6 text-center text-sm text-white/90">
            <a href="{{ route('login') }}" class="font-semibold text-glow-cyan underline hover:text-white">
                {{ __('battle.sign_in_to_vote') }}
            </a>
        </div>
    @else
        @if (! $battle->isOpenForVoting())
            <div class="border-t border-white/10 bg-navy-950/80 px-4 py-6 text-center text-sm text-white/70">
                {{ __('battle.voting_closed') }}
            </div>
        @else
            <div class="border-t border-white/10 bg-navy-950/90"
                     wire:key="vote-dual-{{ $battle->id }}-{{ number_format($totalPool, 2, '.', '') }}-{{ (int) $userBalance }}"
                     x-data="voteBattleDual({
                        totalPool: {{ (float) $totalPool }},
                        max: {{ (int) $maxAllowed }},
                        maxCap: {{ (int) $maxVoteAmount }},
                        walletBalance: {{ (int) $userBalance }},
                        battleId: {{ (int) $battle->id }},
                        poolPollUrl: @js(route('battles.pool-total', $battle)),
                        i18n: @js([
                            'clamp' => __('battle.amount_clamped'),
                        ]),
                     })"
                     x-on:balance-updated.window="onBalance($event.detail.balance)"
                     @versus-stake-toast.window="onStakeSuccess($event.detail)">
                    <div class="flex flex-col gap-6 p-4 sm:p-6 lg:grid lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] lg:items-stretch lg:gap-6">
                        {{-- Side A --}}
                        <div class="order-2 flex flex-col gap-3 lg:order-none">
                            <div class="flex items-center gap-2 rounded-lg bg-navy-800/90 px-2 py-2 ring-1 ring-sky-500/30">
                                <div class="flex flex-1 flex-wrap gap-1">
                                    <button type="button" @click="addChip('A', 10)"
                                            class="rounded-md bg-navy-900 px-2 py-1 text-[11px] font-semibold text-white/85 hover:bg-navy-700">
                                        +10
                                    </button>
                                    <button type="button" @click="addChip('A', 100)"
                                            class="rounded-md bg-navy-900 px-2 py-1 text-[11px] font-semibold text-white/85 hover:bg-navy-700">
                                        +100
                                    </button>
                                    <button type="button" @click="addChip('A', 1000)"
                                            class="rounded-md bg-navy-900 px-2 py-1 text-[11px] font-semibold text-white/85 hover:bg-navy-700">
                                        +1000
                                    </button>
                                    <button type="button" @click="setMax('A')"
                                            class="rounded-md bg-navy-900 px-2 py-1 text-[11px] font-semibold text-white/85 hover:bg-navy-700">
                                        {{ __('battle.widget_chip_max') }}
                                    </button>
                                </div>
                                <input type="number"
                                       x-model.number="amountA"
                                       @input="clamp('A')"
                                       @blur="clamp('A', true)"
                                       min="0"
                                       :max="max"
                                       inputmode="numeric"
                                       class="w-20 shrink-0 bg-transparent text-right font-mono text-lg font-bold text-white
                                              focus:outline-none focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                            </div>
                            <button type="button"
                                    @click="submit('A')"
                                    :disabled="!canSubmit('A')"
                                    wire:loading.attr="disabled"
                                    @disabled(! $canVote)
                                    class="mt-auto w-full rounded-xl bg-gradient-to-r from-vote-blue-from to-vote-blue-to py-3 text-center text-sm font-bold uppercase tracking-wider text-white shadow-vote-blue transition
                                           hover:brightness-110
                                           disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:brightness-100">
                                {{ __('battle.widget_submit_vote') }}
                            </button>
                        </div>

                        {{-- Center: pool + countdown --}}
                        <div class="order-1 flex flex-col items-center justify-center gap-3 border-y border-white/5 py-4 text-center lg:order-none lg:min-w-[220px] lg:border-x lg:border-y-0 lg:px-4">
                            <p class="text-sm text-white/90">
                                {{ __('battle.total_prize_pool') }}:
                                <span x-ref="poolAmount"
                                      class="inline-block font-semibold tabular-nums text-white"
                                      x-text="Math.round(totalPool).toLocaleString()"></span>
                                {{ __('battle.tokens') }}
                            </p>
                            @if ($closesAtIso)
                                <div class="inline-flex flex-col items-center gap-1 rounded-full bg-navy-800 px-4 py-2 text-xs sm:flex-row sm:gap-2">
                                    <span class="text-white/60">{{ __('battle.ends_in') }}</span>
                                    <span class="font-mono text-sm font-semibold text-white"
                                          x-data="countdown('{{ $closesAtIso }}')"
                                          x-init="start()"
                                          x-text="label"></span>
                                </div>
                            @endif
                            <p x-show="err"
                               x-transition.opacity.duration.400ms
                               x-cloak
                               x-text="err"
                               class="max-w-xs text-xs text-amber-300"></p>
                        </div>

                        {{-- Side B --}}
                        <div class="order-3 flex flex-col gap-3 lg:order-none">
                            <div class="flex items-center gap-2 rounded-lg bg-navy-800/90 px-2 py-2 ring-1 ring-rose-500/35">
                                <div class="flex flex-1 flex-wrap gap-1">
                                    <button type="button" @click="addChip('B', 10)"
                                            class="rounded-md bg-navy-900 px-2 py-1 text-[11px] font-semibold text-white/85 hover:bg-navy-700">
                                        +10
                                    </button>
                                    <button type="button" @click="addChip('B', 100)"
                                            class="rounded-md bg-navy-900 px-2 py-1 text-[11px] font-semibold text-white/85 hover:bg-navy-700">
                                        +100
                                    </button>
                                    <button type="button" @click="addChip('B', 1000)"
                                            class="rounded-md bg-navy-900 px-2 py-1 text-[11px] font-semibold text-white/85 hover:bg-navy-700">
                                        +1000
                                    </button>
                                    <button type="button" @click="setMax('B')"
                                            class="rounded-md bg-navy-900 px-2 py-1 text-[11px] font-semibold text-white/85 hover:bg-navy-700">
                                        {{ __('battle.widget_chip_max') }}
                                    </button>
                                </div>
                                <input type="number"
                                       x-model.number="amountB"
                                       @input="clamp('B')"
                                       @blur="clamp('B', true)"
                                       min="0"
                                       :max="max"
                                       inputmode="numeric"
                                       class="w-20 shrink-0 bg-transparent text-right font-mono text-lg font-bold text-white
                                              focus:outline-none focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                            </div>
                            <button type="button"
                                    @click="submit('B')"
                                    :disabled="!canSubmit('B')"
                                    wire:loading.attr="disabled"
                                    @disabled(! $canVote)
                                    class="mt-auto w-full rounded-xl bg-gradient-to-r from-rose-500 to-red-700 py-3 text-center text-sm font-bold uppercase tracking-wider text-white shadow-lg shadow-rose-900/40 transition
                                           hover:brightness-110
                                           disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:brightness-100">
                                {{ __('battle.widget_submit_vote') }}
                            </button>
                        </div>
                    </div>

                    @error('vote')
                        <p class="px-4 pb-3 text-center text-sm text-red-400 lg:px-6">{{ $message }}</p>
                    @enderror

                    <div class="border-t border-white/5 px-4 py-4 text-center text-[11px] text-white/50">
                        <span>{{ __('battle.widget_balance') }}:
                            <span class="font-mono text-white/85" x-text="new Intl.NumberFormat().format(walletBalance) + ' 🪙'"></span>
                        </span>
                    </div>
                </div>
        @endif
    @endguest
</div>
