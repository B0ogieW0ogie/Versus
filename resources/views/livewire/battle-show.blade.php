<div>
    @php
        $distribution = config('versus.distribution');
        $winPct = (int) round(($distribution['winners'] ?? 0) * 100);
        $projectPct = (int) round(($distribution['project'] ?? 0) * 100);
        $drawPct = (int) round(($distribution['reward_pool'] ?? 0) * 100);
        $closesIso = optional($battle->closes_at)->toIso8601String();
        $totalPool = (float) ($poolA + $poolB);
        $remainingCap = max(0.0, $voteCap - $userTotalStaked);
        $userBalance = auth()->check() ? (float) auth()->user()->balance : 0.0;
        $canVote = auth()->check()
            && $battle->isOpenForVoting()
            && $remainingCap >= 1
            && $userBalance >= 1;
    @endphp

    {{-- =================== Central battle card (dark) =================== --}}
    <section class="bg-navy-900 text-white py-10">
        <div class="max-w-4xl mx-auto px-4">
            <article class="relative overflow-hidden rounded-2xl bg-navy-800 p-6 sm:p-8
                            bg-[radial-gradient(ellipse_at_top,_rgba(99,102,241,0.18),transparent_60%),radial-gradient(ellipse_at_bottom,_rgba(168,85,247,0.12),transparent_55%)]">
                {{-- Starry dots --}}
                <div class="pointer-events-none absolute inset-0 opacity-40
                            bg-[radial-gradient(1px_1px_at_20%_30%,white,transparent),radial-gradient(1px_1px_at_70%_60%,white,transparent),radial-gradient(1px_1px_at_40%_80%,white,transparent),radial-gradient(1px_1px_at_85%_20%,white,transparent)]
                            bg-[length:400px_400px]"></div>

                <div class="relative">
                    <header class="flex flex-col items-center gap-4 text-center">
                        <h1 class="text-2xl sm:text-3xl font-semibold">{{ $battle->title }}</h1>
                        @if ($closesIso)
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1 text-sm">
                                <span class="text-white/70">{{ __('battle.ends_in') }}</span>
                                <span class="font-mono font-semibold"
                                      x-data="countdown('{{ $closesIso }}')"
                                      x-init="start()"
                                      x-text="label"></span>
                            </span>
                        @endif
                    </header>

                    <div class="mt-8 grid grid-cols-[1fr_auto_1fr] items-center gap-4 sm:gap-6">
                        <figure class="flex flex-col items-center">
                            <div class="relative w-full aspect-square max-w-[240px] overflow-hidden rounded-2xl ring-1 ring-blue-400/30 shadow-vote-blue">
                                @if ($battle->side_a_image)
                                    <img src="{{ $battle->side_a_image }}" alt="{{ $battle->side_a_label }}"
                                         class="h-full w-full object-cover">
                                @else
                                    <div class="h-full w-full bg-gradient-to-br from-vote-blue-from to-vote-blue-to"></div>
                                @endif
                            </div>
                            <figcaption class="mt-3 text-center">
                                <p class="text-lg font-bold uppercase tracking-wide">{{ $battle->side_a_label }}</p>
                                @if ($battle->side_a_subtitle)
                                    <p class="text-xs text-white/60">{{ $battle->side_a_subtitle }}</p>
                                @endif
                            </figcaption>
                        </figure>

                        <div class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-full
                                    bg-navy-700 text-sm sm:text-base font-bold tracking-wider shadow-vs-glow ring-1 ring-glow-cyan/40">
                            {{ __('battle.vs') }}
                        </div>

                        <figure class="flex flex-col items-center">
                            <div class="relative w-full aspect-square max-w-[240px] overflow-hidden rounded-2xl ring-1 ring-purple-400/30 shadow-vote-purple">
                                @if ($battle->side_b_image)
                                    <img src="{{ $battle->side_b_image }}" alt="{{ $battle->side_b_label }}"
                                         class="h-full w-full object-cover">
                                @else
                                    <div class="h-full w-full bg-gradient-to-br from-vote-purple-from to-vote-purple-to"></div>
                                @endif
                            </div>
                            <figcaption class="mt-3 text-center">
                                <p class="text-lg font-bold uppercase tracking-wide">{{ $battle->side_b_label }}</p>
                                @if ($battle->side_b_subtitle)
                                    <p class="text-xs text-white/60">{{ $battle->side_b_subtitle }}</p>
                                @endif
                            </figcaption>
                        </figure>
                    </div>

                    <p class="mt-8 text-center text-sm text-white/70">
                        {{ __('battle.total_prize_pool') }}:
                        <span class="font-semibold text-white">{{ number_format($totalPool, 0) }}</span>
                        {{ __('battle.tokens') }}
                    </p>

                    @auth
                        @if ($battle->isOpenForVoting())
                            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                <button type="button"
                                        wire:click="voteFor('A')"
                                        wire:loading.attr="disabled"
                                        @disabled(! $canVote)
                                        class="rounded-xl py-3 text-center font-bold uppercase tracking-wider
                                               bg-gradient-to-r from-vote-blue-from to-vote-blue-to
                                               shadow-vote-blue transition hover:brightness-110
                                               disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:brightness-100">
                                    <span class="block">{{ __('battle.vote_for', ['name' => mb_strtoupper($battle->side_a_label)]) }}</span>
                                    <span class="block text-[11px] font-normal opacity-80 normal-case tracking-normal mt-1">
                                        {{ __('battle.token_to_vote_rate') }}
                                    </span>
                                </button>
                                <button type="button"
                                        wire:click="voteFor('B')"
                                        wire:loading.attr="disabled"
                                        @disabled(! $canVote)
                                        class="rounded-xl py-3 text-center font-bold uppercase tracking-wider
                                               bg-gradient-to-r from-vote-purple-from to-vote-purple-to
                                               shadow-vote-purple transition hover:brightness-110
                                               disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:brightness-100">
                                    <span class="block">{{ __('battle.vote_for', ['name' => mb_strtoupper($battle->side_b_label)]) }}</span>
                                    <span class="block text-[11px] font-normal opacity-80 normal-case tracking-normal mt-1">
                                        {{ __('battle.token_to_vote_rate') }}
                                    </span>
                                </button>
                            </div>

                            @error('vote')
                                <p class="mt-3 text-center text-sm text-red-400">{{ $message }}</p>
                            @enderror

                            @if (session('battle-status'))
                                <p class="mt-3 text-center text-sm text-emerald-400">{{ session('battle-status') }}</p>
                            @endif
                        @else
                            <p class="mt-6 text-center text-sm text-white/70">{{ __('battle.voting_closed') }}</p>
                        @endif
                    @else
                        <p class="mt-6 text-center text-sm">
                            <a href="{{ route('login') }}" class="underline hover:text-glow-cyan">
                                {{ __('battle.sign_in_to_vote') }}
                            </a>
                        </p>
                    @endauth

                    <p class="mt-5 text-center text-[11px] tracking-wider text-white/50">
                        {{ __('battle.distribution', [
                            'win' => $winPct,
                            'project' => $projectPct,
                            'draw' => $drawPct,
                        ]) }}
                    </p>
                </div>
            </article>
        </div>
    </section>

    {{-- =================== Comments (unchanged, existing light style) =================== --}}
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <section class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Обсуждение</h3>

                @auth
                    <form wire:submit="comment" class="mt-4 space-y-3">
                        <textarea wire:model="commentBody" rows="3"
                                  class="block w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 dark:placeholder-gray-500"
                                  placeholder="Поделитесь своим мнением…"></textarea>
                        @error('commentBody') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <div class="flex items-center justify-between">
                            <select wire:model="commentSide"
                                    class="rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 text-sm">
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
</div>
