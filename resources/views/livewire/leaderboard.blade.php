@php
    use App\Support\LeaderboardTable;

    $tabs = [
        LeaderboardTable::TAB_CREATORS,
        LeaderboardTable::TAB_ORACLES,
        LeaderboardTable::TAB_INFLUENCERS,
    ];
    $periods = [
        LeaderboardTable::PERIOD_WEEK,
        LeaderboardTable::PERIOD_MONTH,
        LeaderboardTable::PERIOD_ALL,
    ];
@endphp

<div class="flex flex-col min-h-[calc(100dvh-4rem)] w-full lg:max-w-7xl lg:mx-auto lg:px-6">
    <header class="px-4 pt-4 shrink-0">
        <h1 class="text-xl font-semibold text-white">{{ __('leaderboard.title') }}</h1>
    </header>

    {{-- Role tabs --}}
    <div class="mt-4 flex items-end gap-5 overflow-x-auto px-4 border-b border-white/5 shrink-0 scrollbar-none">
        @foreach ($tabs as $key)
            <button type="button"
                    wire:click="setTab('{{ $key }}')"
                    class="shrink-0 pb-2.5 text-xs font-semibold whitespace-nowrap transition
                           {{ $tab === $key ? 'text-white border-b-2 border-vote-purple-to -mb-px' : 'text-white/50 hover:text-white/80' }}">
                {{ __('leaderboard.tabs.' . $key) }}
            </button>
        @endforeach
    </div>

    {{-- Period chips --}}
    <div class="mt-3 flex gap-2 overflow-x-auto px-4 pb-1 shrink-0 scrollbar-none">
        @foreach ($periods as $key)
            <button type="button"
                    wire:click="setPeriod('{{ $key }}')"
                    class="shrink-0 rounded-full px-3.5 py-1.5 text-xs font-semibold transition
                           {{ $period === $key
                               ? 'bg-vote-purple-to/20 text-white border border-vote-purple-to/50'
                               : 'bg-white/5 text-white/55 border border-white/10 hover:text-white/80' }}">
                {{ __('leaderboard.periods.' . $key) }}
            </button>
        @endforeach
    </div>

    <p class="mt-3 px-4 text-[11px] text-white/45 shrink-0">{{ __('leaderboard.metrics.' . $tab) }}</p>

    <div class="flex-1 overflow-y-auto scrollbar-none mt-2 {{ $me ? 'pb-28' : 'pb-6' }}">
        @if ($rows->isEmpty())
            <div class="mx-4 rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-white/50">
                {{ __('leaderboard.empty') }}
            </div>
        @else
            <ul class="space-y-2 px-4">
                @foreach ($rows as $index => $row)
                    @php
                        $rank = $index + 1;
                        $isMe = auth()->check() && auth()->id() === $row->id;
                        $handle = ! empty($row->username)
                            ? '@'.$row->username
                            : '@'.__('profile.username_fallback_prefix').$row->id;
                        $profileUrl = route('profile.show', $row->id);
                    @endphp
                    <li class="rounded-xl border border-white/5 bg-white/[0.03] px-3 py-3
                               {{ $isMe ? 'ring-1 ring-glow-cyan/30 bg-glow-cyan/5' : '' }}">
                        <div class="flex items-center gap-3">
                            <span class="w-12 shrink-0 text-xs font-bold text-center {{ $rank <= 3 ? 'text-gold-500' : 'text-white/50' }}">
                                {{ LeaderboardTable::rankBadge($rank) }}
                            </span>
                            <a href="{{ $profileUrl }}" class="shrink-0">
                                @if ($row->avatar_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($row->avatar_path) }}"
                                         alt=""
                                         class="h-10 w-10 rounded-full object-cover bg-navy-700"/>
                                @else
                                    <span class="h-10 w-10 rounded-full bg-navy-700 flex items-center justify-center text-xs font-semibold text-white">
                                        {{ mb_strtoupper(mb_substr($row->name, 0, 1)) }}
                                    </span>
                                @endif
                            </a>
                            <div class="min-w-0 flex-1">
                                <a href="{{ $profileUrl }}" class="block truncate text-sm font-semibold text-white/90 hover:underline">{{ $handle }}</a>
                                <div class="mt-0.5 text-[11px] text-white/45">
                                    @if ($tab === LeaderboardTable::TAB_CREATORS)
                                        {{ __('leaderboard.battles_count', ['count' => (int) ($row->battles_count ?? 0)]) }}
                                    @elseif ($tab === LeaderboardTable::TAB_ORACLES)
                                        {{ __('leaderboard.wins_total', ['wins' => (int) ($row->wins ?? 0), 'total' => (int) ($row->total_votes ?? 0)]) }}
                                    @else
                                        {{ __('leaderboard.argument_votes', ['count' => (int) ($row->argument_votes ?? 0)]) }}
                                    @endif
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                @if ($tab === LeaderboardTable::TAB_ORACLES)
                                    <div class="text-sm font-bold text-vote-purple-to">{{ number_format((float) $row->metric_value, 1) }}%</div>
                                @else
                                    <div class="text-sm font-bold text-white">{{ number_format((float) $row->metric_value, 0) }} <span class="text-[10px] font-normal text-white/45">VS</span></div>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($me)
        @php
            $authUser = auth()->user();
            $delta = $me['delta'] ?? null;
        @endphp
        <div class="fixed bottom-20 left-0 right-0 z-20 sm:bottom-0 pointer-events-none">
            <div class="w-full lg:max-w-7xl lg:mx-auto px-4 lg:px-6 pointer-events-auto">
                <div class="rounded-xl border border-glow-cyan/25 bg-navy-900/95 backdrop-blur-md px-4 py-3 shadow-lg">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-white/70">
                            {{ __('leaderboard.your_position') }}
                            <span class="font-bold text-white">#{{ $me['rank'] }}</span>
                            @if ($delta !== null && $delta > 0)
                                <span class="text-emerald-400 font-semibold">↑{{ $delta }}</span>
                            @elseif ($delta !== null && $delta < 0)
                                <span class="text-rose-400 font-semibold">↓{{ abs($delta) }}</span>
                            @endif
                        </span>
                        <span class="font-bold text-white shrink-0">
                            @if ($tab === LeaderboardTable::TAB_ORACLES)
                                {{ number_format((float) $me['metric_value'], 1) }}%
                            @else
                                {{ number_format((float) $me['metric_value'], 0) }} VS
                            @endif
                        </span>
                    </div>
                    <div class="mt-1 text-[11px] text-white/45 truncate">
                        {{ LeaderboardTable::handle($authUser) }}
                        @if ($tab === LeaderboardTable::TAB_CREATORS && isset($me['battles_count']))
                            · {{ __('leaderboard.battles_count', ['count' => $me['battles_count']]) }}
                        @elseif ($tab === LeaderboardTable::TAB_ORACLES && isset($me['wins'], $me['total_votes']))
                            · {{ __('leaderboard.wins_total', ['wins' => $me['wins'], 'total' => $me['total_votes']]) }}
                        @elseif ($tab === LeaderboardTable::TAB_INFLUENCERS && isset($me['argument_votes']))
                            · {{ __('leaderboard.argument_votes', ['count' => $me['argument_votes']]) }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
