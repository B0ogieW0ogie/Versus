<div class="space-y-4">
    {{-- Trending Battles --}}
    <section class="rounded-xl bg-navy-800 border border-white/5 p-4">
        <header class="flex items-center justify-between">
            <h3 class="font-semibold text-white">{{ __('sidebar.trending_battles') }}</h3>
            <span class="text-white/40 text-xs">›</span>
        </header>

        <ul class="mt-3 space-y-2">
            @forelse ($trending as $t)
                <li>
                    <a href="{{ route('battles.show', $t->slug) }}"
                       class="flex items-center justify-between gap-3 rounded-lg bg-navy-900/60 px-3 py-2 hover:bg-navy-700 transition">
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="h-8 w-8 shrink-0 rounded-md bg-gradient-to-br from-vote-blue-from to-vote-purple-to
                                         flex items-center justify-center text-xs font-bold text-white">
                                {{ mb_strtoupper(mb_substr($t->side_a_label, 0, 1)) }}
                            </span>
                            <span class="truncate text-sm text-white/90">{{ $t->title }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-white/50">
                            {{ number_format((float) $t->total_pool, 0) }}
                        </span>
                    </a>
                </li>
            @empty
                <li class="text-sm text-white/40">—</li>
            @endforelse
        </ul>
    </section>

    {{-- Top Players --}}
    <section class="rounded-xl bg-navy-800 border border-white/5 p-4">
        <header class="flex items-center justify-between">
            <h3 class="font-semibold text-white">{{ __('sidebar.top_players') }}</h3>
            <span class="text-white/40 text-xs">›</span>
        </header>

        <ul class="mt-3 space-y-2">
            @forelse ($topPlayers as $p)
                <li class="flex items-center justify-between gap-3 rounded-lg bg-navy-900/60 px-3 py-2">
                    <span class="flex items-center gap-2 min-w-0">
                        <span class="h-8 w-8 shrink-0 rounded-full bg-navy-700 flex items-center justify-center text-xs font-bold text-white">
                            {{ mb_strtoupper(mb_substr($p->name, 0, 1)) }}
                        </span>
                        <span class="truncate text-sm text-white/90">{{ $p->name }}</span>
                    </span>
                    <span class="shrink-0 text-xs text-white/60">
                        {{ number_format((float) $p->total_winnings, 0) }}
                        <span class="text-white/40">{{ __('sidebar.tokens') }}</span>
                    </span>
                </li>
            @empty
                <li class="text-sm text-white/40">—</li>
            @endforelse
        </ul>
    </section>

    {{-- Lucky Draw Pool --}}
    <section class="rounded-xl bg-navy-800 border border-white/5 p-4">
        <header class="flex items-center justify-between">
            <h3 class="font-semibold text-white">{{ __('sidebar.lucky_draw_pool') }}</h3>
            <span class="text-white/40 text-xs">›</span>
        </header>

        <div class="mt-3 rounded-lg bg-navy-900/60 p-3 text-center">
            <p class="text-xs text-white/60">
                {{ __('sidebar.current_jackpot') }}:
                <span class="font-semibold text-glow-cyan">
                    {{ number_format($jackpot, 0) }}
                </span>
                <span class="text-white/50">{{ __('sidebar.tokens') }}</span>
            </p>
            <button type="button" disabled title="{{ __('sidebar.coming_soon') }}"
                    class="mt-3 w-full rounded-lg bg-gradient-to-r from-gold-500 to-gold-600
                           px-3 py-2 text-sm font-semibold text-white shadow-jackpot
                           disabled:opacity-80 disabled:cursor-not-allowed">
                {{ __('sidebar.get_your_entries') }}
            </button>
        </div>
    </section>
</div>
