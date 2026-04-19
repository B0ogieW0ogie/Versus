<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
        <section>
            <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-100 mb-4">
                Активные баттлы
            </h2>
            @if ($active->isEmpty())
                <p class="text-gray-500 dark:text-gray-400">Сейчас нет активных баттлов.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($active as $battle)
                        <a href="{{ route('battles.show', $battle) }}"
                           class="block bg-white dark:bg-gray-800 shadow rounded-lg p-4 hover:ring-2 hover:ring-indigo-500 transition">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $battle->title }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                {{ $battle->side_a_label }} vs {{ $battle->side_b_label }}
                            </p>
                            <div class="mt-3 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>Банк: {{ number_format((float) $battle->total_pool, 2) }}</span>
                                <span>Закроется {{ optional($battle->closes_at)->diffForHumans() }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($settled->isNotEmpty())
            <section>
                <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-100 mb-4">
                    Недавно завершённые
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($settled as $battle)
                        <a href="{{ route('battles.show', $battle) }}"
                           class="block bg-gray-50 dark:bg-gray-900 shadow rounded-lg p-4 hover:ring-2 hover:ring-gray-400 transition">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $battle->title }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Победитель:
                                <strong>
                                    @if ($battle->winning_side === 'A') {{ $battle->side_a_label }}
                                    @elseif ($battle->winning_side === 'B') {{ $battle->side_b_label }}
                                    @else — ничья —
                                    @endif
                                </strong>
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                Банк: {{ number_format((float) $battle->total_pool, 2) }}
                            </p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
