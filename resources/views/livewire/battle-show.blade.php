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


            <livewire:comment-thread :battle="$battle" />
        </main>

        <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
            <livewire:sidebar-widgets :battle="$battle" />
        </aside>
    </div>
</div>
