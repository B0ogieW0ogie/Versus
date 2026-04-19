<div class="bg-navy-900 text-white min-h-screen">
    @php
        $distribution = config('versus.distribution');
        $winPct = (int) round(($distribution['winners'] ?? 0) * 100);
        $projectPct = (int) round(($distribution['project'] ?? 0) * 100);
        $drawPct = (int) round(($distribution['reward_pool'] ?? 0) * 100);
        $closesIso = optional($battle->closes_at)->toIso8601String();
        $totalPool = (float) ($poolA + $poolB);
        $userBalance = auth()->check() ? (float) auth()->user()->balance : 0.0;
        $maxAllowed = (int) min((int) $userBalance, $maxVoteAmount);
        $canVote = auth()->check()
            && $battle->isOpenForVoting()
            && $userBalance >= 1;
    @endphp

    <div class="max-w-7xl mx-auto px-4 py-8 grid gap-6 lg:grid-cols-[1fr_320px]">
        <main class="space-y-6 min-w-0">
            {{-- =================== Central battle card =================== --}}
            <article class="relative overflow-hidden rounded-2xl bg-navy-800 p-6 sm:p-8
                            bg-[radial-gradient(ellipse_at_top,_rgba(99,102,241,0.18),transparent_60%),radial-gradient(ellipse_at_bottom,_rgba(168,85,247,0.12),transparent_55%)]">
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
                            <div class="mt-6"
                                 x-data="voteForm({{ $maxAllowed }}, @js(__('battle.amount_clamped')))">
                                <label class="flex items-center justify-center gap-3 text-sm text-white/80">
                                    <span class="uppercase tracking-wider text-white/60">{{ __('battle.amount_label') }}</span>
                                    <input type="number"
                                           x-model.number="amount"
                                           @input="clamp()"
                                           @blur="clamp(true)"
                                           min="1"
                                           :max="max"
                                           inputmode="numeric"
                                           class="w-32 rounded-lg bg-navy-900 border border-white/10 px-3 py-2 text-center
                                                  font-mono text-white focus:outline-none focus:ring-2 focus:ring-glow-cyan/50">
                                </label>

                                <p x-show="err"
                                   x-transition.opacity.duration.500ms
                                   x-cloak
                                   x-text="err"
                                   class="mt-2 text-center text-xs text-amber-300"></p>

                                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                    <button type="button"
                                            @click="submit('A')"
                                            :disabled="amount < 1 || amount > max"
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
                                            @click="submit('B')"
                                            :disabled="amount < 1 || amount > max"
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

            {{-- =================== Comments (dark, with SUPPORT) =================== --}}
            <section class="rounded-2xl bg-navy-800 border border-white/5 p-6">
                <h3 class="text-lg font-semibold">{{ __('comments.heading') }}</h3>

                <ul class="mt-4 space-y-3">
                    @forelse ($comments as $c)
                        <li class="flex items-start gap-3 rounded-xl bg-navy-900/60 p-4">
                            <div class="h-10 w-10 shrink-0 rounded-full bg-navy-700 flex items-center justify-center
                                        text-sm font-semibold text-white/80">
                                {{ mb_strtoupper(mb_substr($c->user->name, 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <strong class="text-sm">{{ $c->user->name }}</strong>
                                    <span class="text-[11px] text-white/40">{{ $c->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 text-sm text-white/80 whitespace-pre-line break-words">{{ $c->body }}</p>
                            </div>

                            @if ($c->side)
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="rounded-full bg-white/5 px-3 py-1 text-xs font-medium text-white/80">
                                        {{ number_format((int) $c->author_side_votes_sum) }}
                                        <span class="text-white/50">{{ __('comments.votes') }}</span>
                                        <span class="text-white/40 ml-1">›</span>
                                    </span>
                                    @auth
                                        @if ($battle->isOpenForVoting())
                                            <button type="button"
                                                    wire:click="supportFor({{ $c->id }})"
                                                    wire:loading.attr="disabled"
                                                    @disabled(! $canVote)
                                                    class="rounded-lg px-3 py-2 text-[11px] font-bold uppercase tracking-wider
                                                           {{ $c->side === 'A'
                                                               ? 'bg-gradient-to-r from-vote-blue-from to-vote-blue-to shadow-vote-blue'
                                                               : 'bg-gradient-to-r from-vote-purple-from to-vote-purple-to shadow-vote-purple' }}
                                                           transition hover:brightness-110
                                                           disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:brightness-100">
                                                {{ __('comments.support') }}
                                                {{ mb_strtoupper($c->side === 'A' ? $battle->side_a_label : $battle->side_b_label) }}
                                            </button>
                                        @endif
                                    @endauth
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-white/50">{{ __('comments.empty') }}</li>
                    @endforelse
                </ul>

                @auth
                    <form wire:submit="comment" class="mt-5 space-y-2">
                        <div class="flex items-center gap-2 rounded-xl bg-navy-900/60 p-2">
                            <select wire:model="commentSide"
                                    class="shrink-0 bg-navy-700 text-xs text-white/90 border-0 rounded px-2 py-1
                                           focus:ring-1 focus:ring-glow-cyan/50">
                                <option value="">{{ __('comments.side_select_none') }}</option>
                                <option value="A">{{ $battle->side_a_label }}</option>
                                <option value="B">{{ $battle->side_b_label }}</option>
                            </select>
                            <input wire:model="commentBody" type="text"
                                   placeholder="{{ __('comments.add_your_argument') }}"
                                   class="flex-1 min-w-0 bg-transparent border-0 text-sm text-white
                                          placeholder:text-white/40 focus:ring-0">
                            <button type="submit"
                                    class="shrink-0 rounded-md bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-wider
                                           text-white hover:bg-white/20 transition">
                                {{ __('comments.post') }}
                            </button>
                        </div>
                        @error('commentBody')
                            <p class="text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </form>
                @endauth
            </section>
        </main>

        {{-- =================== Sidebar =================== --}}
        <aside class="lg:sticky lg:top-6 lg:self-start">
            <livewire:sidebar-widgets :battle="$battle" />
        </aside>
    </div>
</div>
