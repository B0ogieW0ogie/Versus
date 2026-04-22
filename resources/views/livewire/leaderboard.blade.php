<div class="max-w-xl mx-auto pt-4 pb-6">
    <header class="px-4 mb-4">
        <h1 class="text-xl font-semibold text-white">{{ __('leaderboard.title') }}</h1>
        <p class="text-xs text-white/50 mt-1">{{ __('leaderboard.top_winners_all_time') }}</p>
    </header>

    @if ($rows->isEmpty() || $rows->every(fn ($r) => (float) $r->total_winnings === 0.0))
        <div class="mx-4 rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-white/50">
            {{ __('leaderboard.empty') }}
        </div>
    @else
        <ul class="divide-y divide-white/5">
            @foreach ($rows as $index => $row)
                @php $rank = $index + 1; @endphp
                <li class="grid grid-cols-[32px_32px_1fr_auto] items-center gap-3 px-4 py-2.5
                           {{ auth()->check() && auth()->id() === $row->id ? 'bg-glow-cyan/10' : '' }}">
                    <span class="text-sm font-bold text-center {{ $rank <= 3 ? 'text-gold-500' : 'text-white/45' }}">
                        {{ $rank }}
                    </span>
                    <span class="h-8 w-8 rounded-full bg-navy-700 flex items-center justify-center text-[11px] font-semibold text-white">
                        {{ mb_strtoupper(mb_substr($row->name, 0, 1)) }}
                    </span>
                    <span class="min-w-0 truncate text-sm text-white/90">{{ $row->name }}</span>
                    <span class="text-xs text-white/70">{{ number_format((float) $row->total_winnings, 0) }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($me)
        <div class="mt-4 px-4">
            <div class="text-[11px] uppercase tracking-wider text-white/55 mb-2">
                {{ __('leaderboard.your_position') }}
            </div>
            <div class="grid grid-cols-[32px_32px_1fr_auto] items-center gap-3 rounded-xl bg-glow-cyan/10 border border-glow-cyan/20 px-3 py-2.5">
                <span class="text-sm font-bold text-center text-white/80">{{ $me['rank'] }}</span>
                <span class="h-8 w-8 rounded-full bg-navy-700 flex items-center justify-center text-[11px] font-semibold text-white">
                    {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                </span>
                <span class="min-w-0 truncate text-sm text-white/90">{{ auth()->user()->name }}</span>
                <span class="text-xs text-white/70">{{ number_format($me['total_winnings'], 0) }}</span>
            </div>
        </div>
    @endif
</div>
