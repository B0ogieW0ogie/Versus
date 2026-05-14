<div class="min-h-screen bg-gradient-to-b from-violet-950 via-navy-900 to-navy-900 text-white">
    @php
        $closesIso = optional($battle->closes_at)->toIso8601String();
    @endphp

    <div class="mx-auto max-w-7xl px-4 py-8 lg:grid lg:grid-cols-[1fr_300px] lg:gap-8">
        <main class="min-w-0 space-y-6">
            <article class="relative overflow-hidden rounded-2xl border border-white/10 bg-navy-800 shadow-xl shadow-black/40">
                <div class="pointer-events-none absolute inset-0 opacity-30
                            bg-[radial-gradient(ellipse_at_top,_rgba(139,92,246,0.25),transparent_55%),radial-gradient(ellipse_at_bottom,_rgba(59,130,246,0.12),transparent_50%)]"></div>

                <div class="relative">
                    <header class="px-6 pb-2 pt-6 text-center sm:px-8 sm:pt-8">
                        <h1 class="text-2xl font-semibold sm:text-3xl">{{ $battle->title }}</h1>
                    </header>

                    <div class="relative mx-4 mb-[10px] mt-4 aspect-[2/1] overflow-hidden rounded-xl bg-navy-900 sm:mx-6 sm:mt-6">
                        <div class="absolute inset-y-0 left-0 w-[55%] bg-navy-900"
                             style="clip-path: polygon(0 0, 100% 0, 82% 100%, 0 100%);">
                            @if ($battle->side_a_image)
                                <img src="{{ $battle->side_a_image }}" alt="{{ $battle->side_a_label }}"
                                     class="h-full w-full object-cover">
                            @else
                                <div class="h-full w-full bg-gradient-to-br from-vote-blue-from to-vote-blue-to"></div>
                            @endif
                        </div>
                        <div class="absolute inset-y-0 right-0 w-[55%] bg-navy-900"
                             style="clip-path: polygon(18% 0, 100% 0, 100% 100%, 0 100%);">
                            @if ($battle->side_b_image)
                                <img src="{{ $battle->side_b_image }}" alt="{{ $battle->side_b_label }}"
                                     class="h-full w-full object-cover">
                            @else
                                <div class="h-full w-full bg-gradient-to-br from-vote-purple-from to-vote-purple-to"></div>
                            @endif
                        </div>

                        <svg class="pointer-events-none absolute inset-0 z-[4] h-full w-full"
                             viewBox="0 0 100 50"
                             preserveAspectRatio="none"
                             aria-hidden="true">
                            <defs>
                                <linearGradient id="versus-seam-fire-{{ $battle->id }}"
                                                gradientUnits="userSpaceOnUse"
                                                x1="55" y1="0" x2="45" y2="50">
                                    <stop offset="0%" stop-color="#fde047"/>
                                    <stop offset="40%" stop-color="#fb923c"/>
                                    <stop offset="100%" stop-color="#dc2626"/>
                                </linearGradient>
                            </defs>
                            <line x1="55" y1="0" x2="45" y2="50"
                                  stroke="url(#versus-seam-fire-{{ $battle->id }})"
                                  stroke-width="2.25"
                                  stroke-linecap="round"
                                  vector-effect="non-scaling-stroke"/>
                        </svg>

                        <div class="pointer-events-none absolute inset-x-0 bottom-0 z-[2] h-1/2 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>

                        <div class="pointer-events-none absolute inset-y-0 left-0 z-[5] w-[55%] [box-shadow:inset_0_0_0_2px_rgba(56,189,248,0.95),inset_0_0_48px_rgba(56,189,248,0.14)]"
                             style="clip-path: polygon(0 0, 100% 0, 82% 100%, 0 100%);"
                             aria-hidden="true"></div>
                        <div class="pointer-events-none absolute inset-y-0 right-0 z-[5] w-[55%] [box-shadow:inset_0_0_0_2px_rgba(251,113,133,0.95),inset_0_0_48px_rgba(244,63,94,0.16)]"
                             style="clip-path: polygon(18% 0, 100% 0, 100% 100%, 0 100%);"
                             aria-hidden="true"></div>

                        <div class="pointer-events-none absolute bottom-4 left-5 z-[6] max-w-[40%]">
                            <p class="text-lg font-bold uppercase tracking-wide text-white drop-shadow sm:text-xl">{{ $battle->side_a_label }}</p>
                            @if ($battle->side_a_subtitle)
                                <p class="text-xs text-white/70 drop-shadow">{{ $battle->side_a_subtitle }}</p>
                            @endif
                        </div>
                        <div class="pointer-events-none absolute bottom-4 right-5 z-[6] max-w-[40%] text-right">
                            <p class="text-lg font-bold uppercase tracking-wide text-white drop-shadow sm:text-xl">{{ $battle->side_b_label }}</p>
                            @if ($battle->side_b_subtitle)
                                <p class="text-xs text-white/70 drop-shadow">{{ $battle->side_b_subtitle }}</p>
                            @endif
                        </div>

                        <div class="pointer-events-none absolute left-1/2 top-1/2 z-[7] -translate-x-1/2 -translate-y-1/2 rounded-full
                                    bg-gradient-to-br from-amber-300 via-orange-500 to-red-600 p-[2.5px]
                                    shadow-[0_0_26px_rgba(251,146,60,0.55),0_0_12px_rgba(239,68,68,0.35)]">
                            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-950 text-sm font-bold tracking-wider text-white sm:h-16 sm:w-16 sm:text-base">
                                {{ __('battle.vs') }}
                            </div>
                        </div>
                    </div>

                    <livewire:battle-vote-widget :battle="$battle" />
                </div>
            </article>

            {{-- =================== Comments (dark, with SUPPORT) =================== --}}
            <section class="rounded-2xl border border-white/5 bg-navy-800 p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-lg font-semibold">{{ __('comments.heading') }}</h3>
                    <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('comments.sort_label') }}">
                        <div class="inline-flex rounded-lg border border-white/10 bg-navy-900/80 p-0.5">
                            <button type="button" wire:click="$set('commentSort', 'popular')"
                                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition
                                           {{ $commentSort === 'popular' ? 'bg-white/15 text-white shadow-sm' : 'text-white/55 hover:text-white/80' }}">
                                {{ __('comments.sort_popular') }}
                            </button>
                            <button type="button" wire:click="$set('commentSort', 'new')"
                                    class="rounded-md px-3 py-1.5 text-xs font-semibold transition
                                           {{ $commentSort === 'new' ? 'bg-white/15 text-white shadow-sm' : 'text-white/55 hover:text-white/80' }}">
                                {{ __('comments.sort_new') }}
                            </button>
                        </div>
                    </div>
                </div>

                <ul class="mt-4 space-y-3">
                    @forelse ($comments as $c)
                        <li class="flex items-start gap-3 rounded-xl bg-navy-900/60 p-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-navy-700
                                        text-sm font-semibold text-white/80">
                                {{ mb_strtoupper(mb_substr($c->user->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <strong class="text-sm">{{ $c->user->name }}</strong>
                                    <span class="text-[11px] text-white/40">{{ $c->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-line break-words text-sm text-white/80">{{ $c->body }}</p>
                            </div>

                            @if ($c->side)
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="rounded-full bg-white/5 px-3 py-1 text-xs font-medium text-white/80">
                                        {{ number_format((int) $c->author_side_votes_sum) }}
                                        <span class="text-white/50">{{ __('comments.votes') }}</span>
                                        <span class="ml-1 text-white/40">›</span>
                                    </span>
                                    @auth
                                        @if ($battle->isOpenForVoting())
                                            <button type="button"
                                                    wire:click="supportFor({{ $c->id }})"
                                                    wire:loading.attr="disabled"
                                                    class="rounded-lg px-3 py-2 text-[11px] font-bold uppercase tracking-wider transition
                                                           hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:brightness-100
                                                           {{ $c->side === 'A'
                                                               ? 'bg-gradient-to-r from-vote-blue-from to-vote-blue-to shadow-vote-blue'
                                                               : 'bg-gradient-to-r from-vote-purple-from to-vote-purple-to shadow-vote-purple' }}">
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
                                    class="shrink-0 rounded border-0 bg-navy-700 px-2 py-1 text-xs text-white/90
                                           focus:ring-1 focus:ring-glow-cyan/50">
                                <option value="">{{ __('comments.side_select_none') }}</option>
                                <option value="A">{{ $battle->side_a_label }}</option>
                                <option value="B">{{ $battle->side_b_label }}</option>
                            </select>
                            <input wire:model="commentBody" type="text"
                                   placeholder="{{ __('comments.add_your_argument') }}"
                                   class="min-w-0 flex-1 border-0 bg-transparent text-sm text-white placeholder:text-white/40 focus:ring-0">
                            <button type="submit"
                                    class="shrink-0 rounded-md bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-wider
                                           text-white transition hover:bg-white/20">
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

        <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
            <livewire:sidebar-widgets :battle="$battle" />
        </aside>
    </div>
</div>
