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
                    @php
                        $statusLabels = [
                            'draft' => 'Черновик',
                            'scheduled' => 'Запланирован',
                            'active' => 'Активен',
                            'closed' => 'Закрыт',
                            'settled' => 'Завершён',
                            'cancelled' => 'Отменён',
                        ];
                    @endphp
                    <p class="text-sm text-gray-500 dark:text-gray-400">Статус: <strong>{{ $statusLabels[$battle->status] ?? ucfirst($battle->status) }}</strong></p>
                    @if ($battle->closes_at)
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Закроется {{ $battle->closes_at->diffForHumans() }}
                        </p>
                    @endif
                </div>
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-2">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Сторона A</p>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ $battle->side_a_label }}</h2>
                <p class="mt-4 text-2xl font-bold text-indigo-600">{{ number_format($poolA, 2) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $votesA }} голос(ов)</p>
            </div>
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Сторона B</p>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ $battle->side_b_label }}</h2>
                <p class="mt-4 text-2xl font-bold text-pink-600">{{ number_format($poolB, 2) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $votesB }} голос(ов)</p>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Сделать ставку</h3>

            @auth
                @if ($battle->status === \App\Models\Battle::STATUS_SETTLED)
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                        Баттл завершён. Победитель:
                        <strong>
                            @if ($battle->winning_side === 'A') {{ $battle->side_a_label }}
                            @elseif ($battle->winning_side === 'B') {{ $battle->side_b_label }}
                            @else — ничья —
                            @endif
                        </strong>
                    </p>
                @elseif ($userVote)
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                        Вы поставили <strong>{{ number_format((float) $userVote->amount, 2) }}</strong>
                        на сторону <strong>{{ $userVote->side }}</strong>.
                    </p>
                @elseif (! $battle->isOpenForVoting())
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">Голосование закрыто.</p>
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
                            <label class="block text-sm text-gray-700 dark:text-gray-300">Сумма</label>
                            <input type="number" step="0.01" min="0.01" wire:model="voteAmount"
                                   class="mt-1 block w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                                   placeholder="например, 100">
                            @error('voteAmount') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Ваш баланс: {{ number_format((float) auth()->user()->balance, 2) }}
                        </p>
                        <button type="submit"
                                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-white font-semibold hover:bg-indigo-500">
                            Проголосовать
                        </button>
                    </form>
                @endif
            @else
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                    <a href="{{ route('login') }}" class="text-indigo-600 underline">Войдите</a>
                    или
                    <a href="{{ route('register') }}" class="text-indigo-600 underline">зарегистрируйтесь</a>,
                    чтобы сделать ставку.
                </p>
            @endauth
        </section>

        <section class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Обсуждение</h3>

            @auth
                <form wire:submit="comment" class="mt-4 space-y-3">
                    <textarea wire:model="commentBody" rows="3"
                              class="block w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                              placeholder="Поделитесь своим мнением…"></textarea>
                    @error('commentBody') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <div class="flex items-center justify-between">
                        <select wire:model="commentSide"
                                class="rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700 text-sm">
                            <option value="">Болею за: (необязательно)</option>
                            <option value="A">{{ $battle->side_a_label }}</option>
                            <option value="B">{{ $battle->side_b_label }}</option>
                        </select>
                        <button type="submit"
                                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-white font-semibold hover:bg-indigo-500">
                            Отправить
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
                                болеет за {{ $comment->side === 'A' ? $battle->side_a_label : $battle->side_b_label }}
                            </p>
                        @endif
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $comment->body }}</p>
                    </li>
                @empty
                    <li class="text-sm text-gray-500 dark:text-gray-400">Пока нет комментариев.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
