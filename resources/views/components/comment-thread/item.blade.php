@props([
    'comment',
    'battle',
    'canStake' => false,
])

@php
    $isDeleted = $comment->trashed();
    $mentionPrefix = ! $isDeleted && $comment->replyToUser
        ? $comment->replyToUser->name.','
        : null;
    $bodyText = $comment->body;
    if ($mentionPrefix !== null && str_starts_with($bodyText, $mentionPrefix)) {
        $bodyText = ltrim(mb_substr($bodyText, mb_strlen($mentionPrefix)));
    }

    $sideLabel = match ($comment->side) {
        'A' => $battle->side_a_label,
        'B' => $battle->side_b_label,
        default => null,
    };

    $sideTheme = match ($comment->side) {
        'A' => [
            'label' => 'text-vote-blue-from',
            'button' => 'bg-gradient-to-r from-vote-blue-from to-vote-blue-to shadow-vote-blue hover:brightness-110 disabled:hover:brightness-100',
        ],
        'B' => [
            'label' => 'text-rose-400',
            'button' => 'bg-gradient-to-r from-rose-500 to-red-700 shadow-lg shadow-rose-900/40 hover:brightness-110 disabled:hover:brightness-100',
        ],
        default => ['label' => '', 'button' => ''],
    };

    $showSupportButton = ! $isDeleted
        && $comment->side
        && $comment->isRoot()
        && auth()->check()
        && $battle->isOpenForVoting()
        && $canStake;

    $hasLiked = auth()->check() && (bool) ($comment->liked_by_user ?? false);

    $canLike = ! $isDeleted
        && in_array($comment->side, ['A', 'B'], true)
        && auth()->check()
        && $battle->isOpenForVoting()
        && $canStake;

    $showLikeButton = ! $isDeleted
        && in_array($comment->side, ['A', 'B'], true)
        && auth()->check()
        && ($hasLiked || $canLike);
@endphp

<article
    id="comment-{{ $comment->id }}"
    class="group"
    wire:key="comment-{{ $comment->id }}"
>
    <div class="flex gap-3 py-4">
        <div class="shrink-0 pt-0.5">
            @if ($comment->user->avatarUrl())
                <img src="{{ $comment->user->avatarUrl() }}" alt=""
                     class="h-9 w-9 rounded-full object-cover ring-1 ring-white/10">
            @else
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-xs font-semibold text-white/85">
                    {{ mb_strtoupper(mb_substr($comment->user->name, 0, 1)) }}
                </div>
            @endif
        </div>

        <div class="flex min-w-0 flex-1 gap-3">
            <div class="min-w-0 flex-1">
                <div>
                    <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                        <span class="text-[13px] font-semibold text-white">{{ $comment->user->name }}</span>
                        @if (! $isDeleted && $comment->replyToUser && $comment->reply_to_user_id !== $comment->user_id)
                            <span class="text-[13px] text-white/35" aria-hidden="true">·</span>
                            <span class="text-[13px] font-medium text-white/55">{{ $comment->replyToUser->name }}</span>
                        @endif
                    </div>
                    @if (! $isDeleted && $comment->isRoot() && $sideLabel !== null)
                        <p class="mt-0.5 text-[13px] font-medium {{ $sideTheme['label'] }}">
                            {{ __('comments.supports', ['side' => $sideLabel]) }}
                        </p>
                    @endif
                </div>

                @if ($isDeleted)
                    <p class="mt-1.5 text-[13px] italic text-[#76787a]">{{ __('comments.deleted') }}</p>
                @else
                    <p class="mt-1.5 text-[13px] leading-snug text-[#e1e3e6]">
                        @if ($comment->replyToUser)
                            <button type="button" class="font-medium text-[#71aaeb] hover:underline"
                                    wire:click="startReply({{ $comment->id }})">
                                {{ $comment->replyToUser->name }},
                            </button>
                        @endif
                        <span class="whitespace-pre-line break-words">{{ $bodyText }}</span>
                    </p>
                @endif

                @if (! $isDeleted)
                    @auth
                        <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1.5">
                            <button type="button"
                                    wire:click="startReply({{ $comment->id }})"
                                    class="text-xs font-medium text-[#76787a] transition hover:text-[#71aaeb]">
                                {{ __('comments.reply') }}
                            </button>
                            <button type="button"
                                    wire:click="reportComment({{ $comment->id }})"
                                    class="text-xs font-medium text-[#76787a] transition hover:text-white/70">
                                {{ __('comments.report') }}
                            </button>
                            @if (auth()->id() === $comment->user_id)
                                <button type="button"
                                        wire:click="deleteComment({{ $comment->id }})"
                                        wire:confirm="{{ __('comments.delete_confirm') }}"
                                        class="text-xs font-medium text-[#76787a] transition hover:text-red-400">
                                    {{ __('comments.delete') }}
                                </button>
                            @endif
                        </div>
                    @endauth
                @endif

                @auth
                    @if (! $isDeleted && $replyingToCommentId === $comment->id)
                        <form wire:submit="comment" class="mt-3">
                            <div class="flex items-center gap-2 rounded-xl border border-white/10 bg-[#141416] px-3 py-2">
                                <input wire:model="commentBody" type="text" maxlength="500" autofocus
                                       placeholder="{{ __('comments.reply_placeholder', ['name' => $replyToUserName]) }}"
                                       class="min-w-0 flex-1 border-0 bg-transparent text-sm text-white placeholder:text-white/35 focus:ring-0">
                                <button type="button" wire:click="cancelReply"
                                        class="shrink-0 text-xs text-white/45 hover:text-white/70">
                                    {{ __('comments.cancel') }}
                                </button>
                                <button type="submit"
                                        class="shrink-0 text-xs font-semibold text-[#71aaeb] hover:text-[#8bb8f0]">
                                    {{ __('comments.post') }}
                                </button>
                            </div>
                            @error('commentBody')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </form>
                    @endif
                @endauth
            </div>

            @if ($showSupportButton || $showLikeButton || (! $isDeleted && $comment->likes_count > 0 && ! auth()->check()))
                <div class="flex shrink-0 flex-col items-end justify-between gap-3 self-stretch py-0.5">
                    @if ($showSupportButton)
                        <button type="button"
                                @click="openSupportModal({{ $comment->id }}, @js($comment->side))"
                                class="rounded-md px-3 py-1.5 text-[11px] font-semibold leading-none text-white transition
                                       disabled:cursor-not-allowed disabled:opacity-50 {{ $sideTheme['button'] }}">
                            {{ __('comments.support_argument') }}
                        </button>
                    @endif

                    @if ($showLikeButton)
                        <div class="{{ $showSupportButton ? 'mt-auto' : '' }}">
                            <button type="button"
                                    wire:click="likeComment({{ $comment->id }})"
                                    wire:loading.attr="disabled"
                                    aria-label="{{ __('comments.like') }}"
                                    aria-pressed="{{ $hasLiked ? 'true' : 'false' }}"
                                    class="group/like flex items-center gap-1 rounded-md p-1 transition disabled:opacity-50">
                                <svg class="h-4 w-4 transition
                                            {{ $hasLiked ? 'fill-rose-500 stroke-rose-500 text-rose-500' : 'fill-none stroke-[#76787a] text-[#76787a] group-hover/like:stroke-rose-400 group-hover/like:text-rose-400' }}"
                                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                </svg>
                                @if ($comment->likes_count > 0)
                                    <span class="text-xs tabular-nums {{ $hasLiked ? 'text-rose-400' : 'text-[#76787a]' }}">
                                        {{ $comment->likes_count }}
                                    </span>
                                @endif
                            </button>
                        </div>
                    @elseif (! $isDeleted && $comment->likes_count > 0)
                        <div class="{{ $showSupportButton ? 'mt-auto' : '' }}">
                            <span class="flex items-center gap-1 text-xs text-[#76787a]" aria-hidden="true">
                                <svg class="h-4 w-4 fill-rose-500/80 stroke-rose-500 text-rose-500" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                </svg>
                                {{ $comment->likes_count }}
                            </span>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</article>
