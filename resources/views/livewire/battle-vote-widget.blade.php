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
                 wire:key="vote-dual-{{ $battle->id }}"
                 x-data="voteBattleDual({
                    totalPool: {{ (float) $totalPool }},
                    max: {{ (int) $maxAllowed }},
                    maxCap: {{ (int) $maxVoteAmount }},
                    walletBalance: {{ (int) $userBalance }},
                    battleId: {{ (int) $battle->id }},
                    poolPollUrl: @js(route('battles.pool-total', $battle)),
                    i18n: @js([
                        'clamp' => __('battle.amount_clamped'),
                        'sideA' => $battle->side_a_label,
                        'sideB' => $battle->side_b_label,
                        'amountLabel' => __('battle.widget_amount_label'),
                        'confirm' => __('battle.widget_submit_vote'),
                        'cancel' => __('battle.widget_cancel'),
                    ]),
                 })"
                 x-on:balance-updated.window="onBalance($event.detail.balance)"
                 @versus-stake-toast.window="onStakeSuccess($event.detail)">
                {{-- Mobile: pool + dual vote buttons --}}
                <div class="flex flex-col gap-4 p-4 lg:hidden">
                    <div class="flex flex-col items-center justify-center gap-3 text-center">
                        <p class="text-sm text-white/90">
                            {{ __('battle.total_prize_pool') }}:
                            <span class="inline-block font-semibold tabular-nums text-white"
                                  x-text="Math.round(totalPool).toLocaleString()"></span>
                            {{ __('battle.tokens') }}
                        </p>
                        @if ($closesAtIso)
                            <div class="inline-flex flex-col items-center gap-1 rounded-full bg-navy-800 px-4 py-2 text-xs">
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

                    <div class="grid grid-cols-2 gap-3">
                        <button type="button"
                                @click="openStakeModal('A')"
                                wire:loading.attr="disabled"
                                @disabled(! $canVote)
                                class="rounded-xl bg-gradient-to-r from-vote-blue-from to-vote-blue-to py-3 text-center text-sm font-bold uppercase tracking-wider text-white shadow-vote-blue transition
                                       hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:brightness-100">
                            {{ __('battle.widget_submit_vote') }}
                        </button>
                        <button type="button"
                                @click="openStakeModal('B')"
                                wire:loading.attr="disabled"
                                @disabled(! $canVote)
                                class="rounded-xl bg-gradient-to-r from-rose-500 to-red-700 py-3 text-center text-sm font-bold uppercase tracking-wider text-white shadow-lg shadow-rose-900/40 transition
                                       hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:brightness-100">
                            {{ __('battle.widget_submit_vote') }}
                        </button>
                    </div>
                </div>

                {{-- Desktop: full stake + vote layout --}}
                <div class="hidden flex-col gap-6 p-4 sm:p-6 lg:grid lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] lg:items-stretch lg:gap-6">
                    <div class="flex flex-col gap-3">
                        <x-battle-vote.stake-picker side="A" />
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

                    <div class="flex flex-col items-center justify-center gap-3 border-y border-white/5 py-4 text-center lg:min-w-[220px] lg:border-x lg:border-y-0 lg:px-4">
                        <p class="text-sm text-white/90">
                            {{ __('battle.total_prize_pool') }}:
                            <span x-ref="poolAmount"
                                  class="inline-block origin-center font-semibold tabular-nums text-white will-change-transform"
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

                    <div class="flex flex-col gap-3">
                        <x-battle-vote.stake-picker side="B" />
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

                {{-- Mobile stake sheet --}}
                <div x-show="stakeModalSide !== null"
                     x-cloak
                     class="fixed inset-0 z-50 lg:hidden"
                     role="dialog"
                     aria-modal="true"
                     @keydown.escape.window="closeStakeModal()">
                    <div class="absolute inset-0 bg-navy-900/55 backdrop-blur-[2px]"
                         @click="closeStakeModal()"
                         aria-hidden="true"></div>
                    <div class="absolute inset-x-0 bottom-0 rounded-t-2xl border border-white/10 bg-navy-900 shadow-2xl shadow-black/50"
                         @click.stop>
                        <div class="mx-auto mt-3 h-1 w-10 rounded-full bg-white/20" aria-hidden="true"></div>
                        <div class="flex flex-col gap-4 p-4 pb-6">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium uppercase tracking-wide text-white/50"
                                       x-text="i18n.amountLabel"></p>
                                    <h4 class="mt-1 truncate text-base font-semibold text-white"
                                        x-text="stakeModalSide === 'A' ? i18n.sideA : i18n.sideB"></h4>
                                </div>
                                <button type="button"
                                        @click="closeStakeModal()"
                                        class="shrink-0 rounded-md p-1 text-white/45 transition hover:bg-white/10 hover:text-white/80"
                                        :aria-label="i18n.cancel">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <template x-if="stakeModalSide === 'A'">
                                <x-battle-vote.stake-picker side="A" />
                            </template>
                            <template x-if="stakeModalSide === 'B'">
                                <x-battle-vote.stake-picker side="B" />
                            </template>

                            <div class="grid grid-cols-2 gap-3">
                                <button type="button"
                                        @click="closeStakeModal()"
                                        class="rounded-xl border border-white/15 py-3 text-sm font-semibold text-white/80 transition hover:bg-white/5">
                                    <span x-text="i18n.cancel"></span>
                                </button>
                                <button type="button"
                                        @click="confirmStakeModal()"
                                        :disabled="!canSubmit(stakeModalSide)"
                                        wire:loading.attr="disabled"
                                        class="rounded-xl py-3 text-sm font-bold uppercase tracking-wider text-white transition
                                               disabled:cursor-not-allowed disabled:opacity-50"
                                        :class="stakeModalSide === 'A'
                                            ? 'bg-gradient-to-r from-vote-blue-from to-vote-blue-to shadow-vote-blue hover:brightness-110 disabled:hover:brightness-100'
                                            : 'bg-gradient-to-r from-rose-500 to-red-700 shadow-lg shadow-rose-900/40 hover:brightness-110 disabled:hover:brightness-100'">
                                    <span x-text="i18n.confirm"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endguest
</div>
