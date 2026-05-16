<section class="rounded-2xl border border-white/5 bg-[#19191a] p-5 sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h3 class="text-lg font-semibold text-white">{{ __('comments.heading') }}</h3>
        <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('comments.sort_label') }}">
            <div class="inline-flex rounded-lg border border-white/10 bg-black/30 p-0.5">
                <button type="button" wire:click="$set('commentSort', 'popular')"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold transition
                               {{ $commentSort === 'popular' ? 'bg-white/15 text-white shadow-sm' : 'text-[#76787a] hover:text-white/80' }}">
                    {{ __('comments.sort_popular') }}
                </button>
                <button type="button" wire:click="$set('commentSort', 'new')"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold transition
                               {{ $commentSort === 'new' ? 'bg-white/15 text-white shadow-sm' : 'text-[#76787a] hover:text-white/80' }}">
                    {{ __('comments.sort_new') }}
                </button>
            </div>
        </div>
    </div>

    <div class="mt-2 divide-y divide-white/5">
        @forelse ($rootComments as $comment)
            <div wire:key="thread-{{ $comment->id }}">
                @include('components.comment-thread.item', ['comment' => $comment, 'battle' => $battle])

                @if ($comment->flattenedDescendants()->isNotEmpty())
                    <div class="ml-11 border-l border-white/5 pl-3">
                        @foreach ($comment->flattenedDescendants() as $reply)
                            @include('components.comment-thread.item', ['comment' => $reply, 'battle' => $battle])
                        @endforeach
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
                                class="shrink-0 max-w-[9rem] truncate rounded-md border-0 bg-white/5 px-2 py-1 text-xs text-white/90 focus:ring-1 focus:ring-[#71aaeb]/40">
                            <option value="">{{ __('comments.side_select_none') }}</option>
                            <option value="A">{{ $battle->side_a_label }}</option>
                            <option value="B">{{ $battle->side_b_label }}</option>
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
</section>
