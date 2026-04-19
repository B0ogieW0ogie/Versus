<div class="py-12">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
        <header class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $battle->title }}</h1>
                    @if ($battle->description)
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $battle->description }}</p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Status: <strong>{{ ucfirst($battle->status) }}</strong></p>
                    @if ($battle->closes_at)
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Closes {{ $battle->closes_at->diffForHumans() }}
                        </p>
                    @endif
                </div>
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-2">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Side A</p>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ $battle->side_a_label }}</h2>
                <p class="mt-4 text-2xl font-bold text-indigo-600">{{ number_format($poolA, 2) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $votesA }} vote(s)</p>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Side B</p>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ $battle->side_b_label }}</h2>
                <p class="mt-4 text-2xl font-bold text-pink-600">{{ number_format($poolB, 2) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $votesB }} vote(s)</p>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Place your vote</h3>

            @auth
                @if ($battle->status === \App\Models\Battle::STATUS_SETTLED)
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                        Battle settled. Winner:
                        <strong>
                            @if ($battle->winning_side === 'A') {{ $battle->side_a_label }}
                            @elseif ($battle->winning_side === 'B') {{ $battle->side_b_label }}
                            @else — tie —
                            @endif
                        </strong>
                    </p>
                @elseif ($userVote)
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                        You voted <strong>{{ number_format((float) $userVote->amount, 2) }}</strong>
                        on side <strong>{{ $userVote->side }}</strong>.
                    </p>
                @elseif (! $battle->isOpenForVoting())
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">Voting is closed.</p>
                @else
                    @if (session('battle-status'))
                        <p class="mt-2 text-sm text-green-600">{{ session('battle-status') }}</p>
                    @endif
                    <form wire:submit="vote" class="mt-4 space-y-3">
                        <div class="flex gap-4">
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" wire:model="voteSide" value="A" class="text-indigo-600">
                                <span>{{ $battle->side_a_label }}</span>
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" wire:model="voteSide" value="B" class="text-pink-600">
                                <span>{{ $battle->side_b_label }}</span>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-700 dark:text-gray-300">Amount</label>
                            <input type="number" step="0.01" min="0.01" wire:model="voteAmount"
                                   class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                                   placeholder="e.g. 100">
                            @error('voteAmount') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Your balance: {{ number_format((float) auth()->user()->balance, 2) }}
                        </p>
                        <button type="submit"
                                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-white font-semibold hover:bg-indigo-500">
                            Vote
                        </button>
                    </form>
                @endif
            @else
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                    <a href="{{ route('login') }}" class="text-indigo-600 underline">Sign in</a>
                    or
                    <a href="{{ route('register') }}" class="text-indigo-600 underline">register</a>
                    to place a vote.
                </p>
            @endauth
        </section>

        <section class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Discussion</h3>

            @auth
                <form wire:submit="comment" class="mt-4 space-y-3">
                    <textarea wire:model="commentBody" rows="3"
                              class="block w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                              placeholder="Share your take…"></textarea>
                    @error('commentBody') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <div class="flex items-center justify-between">
                        <select wire:model="commentSide"
                                class="rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700 text-sm">
                            <option value="">Rooting for: (optional)</option>
                            <option value="A">{{ $battle->side_a_label }}</option>
                            <option value="B">{{ $battle->side_b_label }}</option>
                        </select>
                        <button type="submit"
                                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-white font-semibold hover:bg-indigo-500">
                            Post
                        </button>
                    </div>
                </form>
            @endauth

            <ul class="mt-6 space-y-4">
                @forelse ($comments as $comment)
                    <li class="border-b border-gray-200 dark:border-gray-700 pb-3">
                        <div class="flex items-baseline justify-between">
                            <strong class="text-gray-900 dark:text-gray-100">{{ $comment->user->name }}</strong>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                        </div>
                        @if ($comment->side)
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                rooting for {{ $comment->side === 'A' ? $battle->side_a_label : $battle->side_b_label }}
                            </p>
                        @endif
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $comment->body }}</p>
                    </li>
                @empty
                    <li class="text-sm text-gray-500 dark:text-gray-400">No comments yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
