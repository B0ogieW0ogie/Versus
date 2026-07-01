<section class="rounded-2xl border border-white/5 bg-[#19191a] px-3 py-3 sm:p-6"
         @auth
             @if ($canStake)
                 x-data="commentSupportStake({
                    max: {{ (int) $maxAllowed }},
                    maxCap: {{ (int) $maxVoteAmount }},
                    walletBalance: {{ (int) $userBalance }},
                    remainingBattleStake: {{ (int) $remainingBattleStake }},
                    battleId: {{ (int) $battle->id }},
                    canStake: true,
                    i18n: @js([
                        'clamp' => __('battle.amount_clamped'),
                        'sideA' => $battle->side_a_label,
                        'sideB' => $battle->side_b_label,
                        'amountLabel' => __('battle.widget_amount_label'),
                        'confirm' => __('battle.widget_submit_vote'),
                        'cancel' => __('battle.widget_cancel'),
                        'supportTitle' => __('comments.support_modal_title'),
                    ]),
                 })"
                 x-on:balance-updated.window="onBalance($event.detail.balance)"
                 x-on:versus-stake-toast.window="onStakeSuccess($event.detail)"
             @endif
         @endauth>
    <div class="flex items-center justify-between gap-3">
        <h3 class="shrink-0 text-lg font-semibold text-white">{{ __('comments.heading') }}</h3>
        <div class="shrink-0" role="group" aria-label="{{ __('comments.sort_label') }}">
            <div class="inline-flex rounded-lg border border-white/10 bg-black/30 p-0.5">
                <button type="button" wire:click="setCommentSort('popular')"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold transition
                               {{ $commentSort === 'popular' ? 'bg-white/15 text-white shadow-sm' : 'text-[#76787a] hover:text-white/80' }}">
                    {{ __('comments.sort_popular') }}
                </button>
                <button type="button" wire:click="setCommentSort('new')"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold transition
                               {{ $commentSort === 'new' ? 'bg-white/15 text-white shadow-sm' : 'text-[#76787a] hover:text-white/80' }}">
                    {{ __('comments.sort_new') }}
                </button>
            </div>
        </div>
    </div>

    <div class="mt-3 divide-y divide-white/5" wire:key="comment-roots-{{ $commentSort }}">
        @forelse ($rootComments as $comment)
            <div wire:key="thread-{{ $comment->id }}" class="py-1">
                @include('components.comment-thread.item', ['comment' => $comment, 'battle' => $battle, 'canStake' => $canStake])

                @php($replies = $comment->flattenedDescendants())
                @if ($replies->isNotEmpty())
                    <div class="ml-8 pb-2 sm:ml-12">
                        @if ($this->isThreadExpanded($comment->id))
                            <div class="mt-1 space-y-0 border-l border-white/5 pl-3 sm:pl-4">
                                @foreach ($replies as $reply)
                                    @include('components.comment-thread.item', ['comment' => $reply, 'battle' => $battle, 'canStake' => $canStake])
                                @endforeach
                            </div>
                            <button type="button"
                                    wire:click="toggleThread({{ $comment->id }})"
                                    class="mt-3 text-xs font-medium text-[#71aaeb] transition hover:underline">
                                {{ __('comments.hide_replies') }}
                            </button>
                        @else
                            <button type="button"
                                    wire:click="toggleThread({{ $comment->id }})"
                                    class="mt-2 text-xs font-medium text-[#71aaeb] transition hover:underline">
                                {{ trans_choice('comments.show_replies', $replies->count(), ['count' => $replies->count()]) }}
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <p class="py-8 text-center text-sm text-[#76787a]">{{ __('comments.empty') }}</p>
        @endforelse
    </div>

    @auth
        @if ($replyingToCommentId === null)
            <form wire:submit="comment" class="mt-4 border-t border-white/5 pt-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="flex min-w-0 flex-1 items-center gap-2 rounded-xl border border-white/10 bg-[#141416] px-3 py-2.5">
                        <select wire:model="commentSide"
                                class="shrink-0 max-w-[9rem] truncate rounded-md border border-white/10 bg-navy-900 px-2 py-1.5 text-xs text-white [color-scheme:dark] focus:border-[#71aaeb]/40 focus:outline-none focus:ring-1 focus:ring-[#71aaeb]/40">
                            <option value="" class="bg-navy-900 text-white">{{ __('comments.side_select_none') }}</option>
                            <option value="A" class="bg-navy-900 text-white">{{ $battle->side_a_label }}</option>
                            <option value="B" class="bg-navy-900 text-white">{{ $battle->side_b_label }}</option>
                        </select>
                        <input wire:model="commentBody" type="text" maxlength="500"
                               placeholder="{{ __('comments.add_your_argument') }}"
                               class="min-w-0 flex-1 border-0 bg-transparent text-sm text-white placeholder:text-white/35 focus:ring-0">
                    </div>
                    <button type="submit"
                            class="shrink-0 rounded-lg bg-[#71aaeb]/20 px-5 py-2.5 text-xs font-semibold text-[#71aaeb] transition hover:bg-[#71aaeb]/30">
                        {{ __('comments.post') }}
                    </button>
                </div>
                @error('commentBody')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
                @error('vote')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </form>
        @else
            <p class="mt-4 border-t border-white/5 pt-4 text-xs text-[#76787a]">
                {{ __('comments.replying_hint') }}
                <button type="button" wire:click="cancelReply" class="text-[#71aaeb] hover:underline">
                    {{ __('comments.cancel') }}
                </button>
            </p>
        @endif
    @else
        <p class="mt-4 border-t border-white/5 pt-4 text-center text-sm text-[#76787a]">
            <a href="{{ route('login') }}" class="text-[#71aaeb] hover:underline">{{ __('comments.login_to_comment') }}</a>
        </p>
    @endauth

    @auth
        @if ($canStake)
            <div x-show="supportCommentId !== null"
                 x-cloak
                 class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
                 role="dialog"
                 aria-modal="true"
                 @keydown.escape.window="closeSupportModal()">
                <div class="absolute inset-0 bg-navy-900/55 backdrop-blur-[2px]"
                     @click="closeSupportModal()"
                     aria-hidden="true"></div>
                <div class="relative w-full max-w-md rounded-2xl border border-white/10 bg-navy-900 shadow-2xl shadow-black/50 sm:rounded-2xl"
                     @click.stop>
                    <div class="flex flex-col gap-4 p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-medium uppercase tracking-wide text-white/50"
                                   x-text="i18n.supportTitle"></p>
                                <h4 class="mt-1 truncate text-base font-semibold text-white"
                                    x-text="supportSide === 'A' ? i18n.sideA : i18n.sideB"></h4>
                            </div>
                            <button type="button"
                                    @click="closeSupportModal()"
                                    class="shrink-0 rounded-md p-1 text-white/45 transition hover:bg-white/10 hover:text-white/80"
                                    :aria-label="i18n.cancel">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div x-show="supportSide === 'A'" x-cloak>
                            <x-battle-vote.stake-picker side="A" amount-key="modalAmount" />
                        </div>
                        <div x-show="supportSide === 'B'" x-cloak>
                            <x-battle-vote.stake-picker side="B" amount-key="modalAmount" />
                        </div>

                        <p x-show="err"
                           x-transition.opacity
                           x-cloak
                           x-text="err"
                           class="text-xs text-amber-300"></p>

                        <div class="grid grid-cols-2 gap-3">
                            <button type="button"
                                    @click="closeSupportModal()"
                                    class="rounded-xl border border-white/15 py-3 text-sm font-semibold text-white/80 transition hover:bg-white/5">
                                <span x-text="i18n.cancel"></span>
                            </button>
                            <button type="button"
                                    @click="confirmSupportModal()"
                                    :disabled="!canSubmitKey('modalAmount')"
                                    wire:loading.attr="disabled"
                                    class="rounded-xl py-3 text-sm font-bold uppercase tracking-wider text-white transition
                                           disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="supportSide === 'A'
                                        ? 'bg-gradient-to-r from-vote-blue-from to-vote-blue-to shadow-vote-blue hover:brightness-110 disabled:hover:brightness-100'
                                        : 'bg-gradient-to-r from-rose-500 to-red-700 shadow-lg shadow-rose-900/40 hover:brightness-110 disabled:hover:brightness-100'">
                                <span x-text="i18n.confirm"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endauth
</section>
