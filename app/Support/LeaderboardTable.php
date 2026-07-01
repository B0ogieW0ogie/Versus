<?php

namespace App\Support;

use App\Models\Battle;
use App\Models\CommentLike;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaderboardTable
{
    public const TAB_CREATORS = 'creators';

    public const TAB_ORACLES = 'oracles';

    public const TAB_INFLUENCERS = 'influencers';

    public const PERIOD_WEEK = 'week';

    public const PERIOD_MONTH = 'month';

    public const PERIOD_ALL = 'all';

    private const int LIMIT = 100;

    /**
     * @return Collection<int, \stdClass>
     */
    public function rows(string $tab, string $period): Collection
    {
        return match ($tab) {
            self::TAB_CREATORS => $this->creatorRows($period),
            self::TAB_ORACLES => $this->oracleRows($period),
            self::TAB_INFLUENCERS => $this->influencerRows($period),
            default => collect(),
        };
    }

    /**
     * @return array{
     *     rank: int,
     *     metric_value: float,
     *     battles_count?: int,
     *     wins?: int,
     *     total_votes?: int,
     *     argument_votes?: int,
     *     delta: ?int
     * }|null
     */
    public function selfRow(int $userId, string $tab, string $period): ?array
    {
        $rows = $this->rows($tab, $period);
        $index = $rows->search(fn ($row) => (int) $row->id === $userId);

        if ($index !== false) {
            $row = $rows[$index];

            return [
                'rank' => $index + 1,
                'metric_value' => (float) $row->metric_value,
                'battles_count' => isset($row->battles_count) ? (int) $row->battles_count : null,
                'wins' => isset($row->wins) ? (int) $row->wins : null,
                'total_votes' => isset($row->total_votes) ? (int) $row->total_votes : null,
                'argument_votes' => isset($row->argument_votes) ? (int) $row->argument_votes : null,
                'delta' => $this->rankDelta($userId, $tab, $period, $index + 1),
            ];
        }

        $metric = $this->userMetric($userId, $tab, $period);
        if ($metric === null) {
            return null;
        }

        $rank = $this->rankForMetric($userId, $tab, $period, $metric);

        return [
            'rank' => $rank,
            'metric_value' => $metric['metric_value'],
            'battles_count' => $metric['battles_count'] ?? null,
            'wins' => $metric['wins'] ?? null,
            'total_votes' => $metric['total_votes'] ?? null,
            'argument_votes' => $metric['argument_votes'] ?? null,
            'delta' => $this->rankDelta($userId, $tab, $period, $rank),
        ];
    }

    public static function rankBadge(int $rank): string
    {
        return match ($rank) {
            1 => '#1 🥇',
            2 => '#2 🥈',
            3 => '#3 🥉',
            default => '#'.$rank,
        };
    }

    public static function handle(User $user): string
    {
        return ! empty($user->username)
            ? '@'.$user->username
            : '@'.__('profile.username_fallback_prefix').$user->id;
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function creatorRows(string $period): Collection
    {
        $cut = (float) config('versus.leaderboard.creator_fee_cut');
        $bounds = $this->periodBounds($period);

        $stats = Battle::query()
            ->where('status', Battle::STATUS_SETTLED)
            ->whereNotNull('created_by_id')
            ->when($bounds['since'] !== null, fn (Builder $q) => $q->where('settled_at', '>=', $bounds['since']))
            ->when($bounds['until'] !== null, fn (Builder $q) => $q->where('settled_at', '<', $bounds['until']))
            ->selectRaw('created_by_id AS user_id')
            ->selectRaw('ROUND(COALESCE(SUM(total_pool), 0) * ?, 2) AS metric_value', [$cut])
            ->selectRaw('COUNT(*) AS battles_count')
            ->groupBy('created_by_id')
            ->havingRaw('ROUND(COALESCE(SUM(total_pool), 0) * ?, 2) > 0', [$cut])
            ->orderByDesc('metric_value')
            ->orderBy('user_id')
            ->limit(self::LIMIT)
            ->toBase();

        return $this->attachUsers($stats);
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function oracleRows(string $period): Collection
    {
        $minVotes = (int) config('versus.leaderboard.oracle_min_votes');
        $bounds = $this->periodBounds($period);

        $stats = Vote::query()
            ->join('battles', 'battles.id', '=', 'votes.battle_id')
            ->where('battles.status', Battle::STATUS_SETTLED)
            ->whereNotNull('battles.winning_side')
            ->when($bounds['since'] !== null, fn (Builder $q) => $q->where('votes.created_at', '>=', $bounds['since']))
            ->when($bounds['until'] !== null, fn (Builder $q) => $q->where('votes.created_at', '<', $bounds['until']))
            ->selectRaw('votes.user_id')
            ->selectRaw('COUNT(*) AS total_votes')
            ->selectRaw('SUM(CASE WHEN votes.side = battles.winning_side THEN 1 ELSE 0 END) AS wins')
            ->selectRaw('ROUND(100.0 * SUM(CASE WHEN votes.side = battles.winning_side THEN 1 ELSE 0 END) / COUNT(*), 2) AS metric_value')
            ->groupBy('votes.user_id')
            ->havingRaw('COUNT(*) >= ?', [$minVotes])
            ->orderByDesc('metric_value')
            ->orderByDesc('wins')
            ->orderBy('votes.user_id')
            ->limit(self::LIMIT)
            ->toBase();

        return $this->attachUsers($stats);
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function influencerRows(string $period): Collection
    {
        $bounds = $this->periodBounds($period);

        $income = Transaction::query()
            ->where('type', Transaction::TYPE_COMMENT_LIKE_CREDIT)
            ->when($bounds['since'] !== null, fn (Builder $q) => $q->where('created_at', '>=', $bounds['since']))
            ->when($bounds['until'] !== null, fn (Builder $q) => $q->where('created_at', '<', $bounds['until']))
            ->selectRaw('user_id')
            ->selectRaw('ROUND(COALESCE(SUM(amount), 0), 2) AS metric_value')
            ->groupBy('user_id')
            ->havingRaw('ROUND(COALESCE(SUM(amount), 0), 2) > 0')
            ->toBase();

        $likes = CommentLike::query()
            ->join('comments', 'comments.id', '=', 'comment_likes.comment_id')
            ->whereIn('comments.side', [Battle::SIDE_A, Battle::SIDE_B])
            ->when($bounds['since'] !== null, fn (Builder $q) => $q->where('comment_likes.created_at', '>=', $bounds['since']))
            ->when($bounds['until'] !== null, fn (Builder $q) => $q->where('comment_likes.created_at', '<', $bounds['until']))
            ->selectRaw('comments.user_id')
            ->selectRaw('COUNT(*) AS argument_votes')
            ->groupBy('comments.user_id')
            ->toBase();

        $stats = DB::query()
            ->fromSub($income, 'income')
            ->joinSub($likes, 'likes', 'likes.user_id', '=', 'income.user_id')
            ->selectRaw('income.user_id')
            ->selectRaw('income.metric_value')
            ->selectRaw('likes.argument_votes')
            ->orderByDesc('income.metric_value')
            ->orderByDesc('likes.argument_votes')
            ->orderBy('income.user_id')
            ->limit(self::LIMIT);

        return $this->attachUsers($stats);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $stats
     * @return Collection<int, \stdClass>
     */
    private function attachUsers($stats): Collection
    {
        $ranked = collect($stats->get());

        if ($ranked->isEmpty()) {
            return collect();
        }

        $users = User::query()
            ->whereIn('id', $ranked->pluck('user_id'))
            ->get(['id', 'name', 'username', 'avatar_path', 'is_admin'])
            ->keyBy('id');

        return $ranked->map(function ($row) use ($users) {
            $user = $users->get((int) $row->user_id);
            $row->id = (int) $row->user_id;
            $row->name = $user !== null ? $user->name : '';
            $row->username = $user?->username;
            $row->avatar_path = $user?->avatar_path;
            $row->is_admin = (bool) ($user?->is_admin);
            $row->metric_value = (float) $row->metric_value;

            return $row;
        });
    }

    /**
     * @return array{since: ?Carbon, until: ?Carbon}
     */
    private function periodBounds(string $period): array
    {
        return match ($period) {
            self::PERIOD_WEEK => ['since' => now()->subDays(7), 'until' => null],
            self::PERIOD_MONTH => ['since' => now()->subDays(30), 'until' => null],
            default => ['since' => null, 'until' => null],
        };
    }

    /**
     * @return array{since: ?Carbon, until: ?Carbon}
     */
    private function previousPeriodBounds(string $period): array
    {
        return match ($period) {
            self::PERIOD_WEEK => ['since' => now()->subDays(14), 'until' => now()->subDays(7)],
            self::PERIOD_MONTH => ['since' => now()->subDays(60), 'until' => now()->subDays(30)],
            default => ['since' => null, 'until' => null],
        };
    }

    private function rankDelta(int $userId, string $tab, string $period, int $currentRank): ?int
    {
        if ($period === self::PERIOD_ALL) {
            return null;
        }

        $previousRank = $this->rankInBounds($userId, $tab, $this->previousPeriodBounds($period));
        if ($previousRank === null) {
            return null;
        }

        return $previousRank - $currentRank;
    }

    /**
     * @param  array{since: ?Carbon, until: ?Carbon}  $bounds
     */
    private function rankInBounds(int $userId, string $tab, array $bounds): ?int
    {
        if ($bounds['since'] === null) {
            return null;
        }

        $rows = match ($tab) {
            self::TAB_CREATORS => $this->creatorRowsForBounds($bounds['since'], $bounds['until']),
            self::TAB_ORACLES => $this->oracleRowsForBounds($bounds['since'], $bounds['until']),
            self::TAB_INFLUENCERS => $this->influencerRowsForBounds($bounds['since'], $bounds['until']),
            default => collect(),
        };

        $index = $rows->search(fn ($row) => (int) $row->id === $userId);

        return $index === false ? null : $index + 1;
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function creatorRowsForBounds(Carbon $since, ?Carbon $until): Collection
    {
        $cut = (float) config('versus.leaderboard.creator_fee_cut');

        $stats = Battle::query()
            ->where('status', Battle::STATUS_SETTLED)
            ->whereNotNull('created_by_id')
            ->where('settled_at', '>=', $since)
            ->when($until !== null, fn (Builder $q) => $q->where('settled_at', '<', $until))
            ->selectRaw('created_by_id AS user_id')
            ->selectRaw('ROUND(COALESCE(SUM(total_pool), 0) * ?, 2) AS metric_value', [$cut])
            ->selectRaw('COUNT(*) AS battles_count')
            ->groupBy('created_by_id')
            ->havingRaw('ROUND(COALESCE(SUM(total_pool), 0) * ?, 2) > 0', [$cut])
            ->orderByDesc('metric_value')
            ->orderBy('user_id')
            ->toBase();

        return $this->attachUsers($stats);
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function oracleRowsForBounds(Carbon $since, ?Carbon $until): Collection
    {
        $minVotes = (int) config('versus.leaderboard.oracle_min_votes');

        $stats = Vote::query()
            ->join('battles', 'battles.id', '=', 'votes.battle_id')
            ->where('battles.status', Battle::STATUS_SETTLED)
            ->whereNotNull('battles.winning_side')
            ->where('votes.created_at', '>=', $since)
            ->when($until !== null, fn (Builder $q) => $q->where('votes.created_at', '<', $until))
            ->selectRaw('votes.user_id')
            ->selectRaw('COUNT(*) AS total_votes')
            ->selectRaw('SUM(CASE WHEN votes.side = battles.winning_side THEN 1 ELSE 0 END) AS wins')
            ->selectRaw('ROUND(100.0 * SUM(CASE WHEN votes.side = battles.winning_side THEN 1 ELSE 0 END) / COUNT(*), 2) AS metric_value')
            ->groupBy('votes.user_id')
            ->havingRaw('COUNT(*) >= ?', [$minVotes])
            ->orderByDesc('metric_value')
            ->orderByDesc('wins')
            ->orderBy('votes.user_id')
            ->toBase();

        return $this->attachUsers($stats);
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function influencerRowsForBounds(Carbon $since, ?Carbon $until): Collection
    {
        $income = Transaction::query()
            ->where('type', Transaction::TYPE_COMMENT_LIKE_CREDIT)
            ->where('created_at', '>=', $since)
            ->when($until !== null, fn (Builder $q) => $q->where('created_at', '<', $until))
            ->selectRaw('user_id')
            ->selectRaw('ROUND(COALESCE(SUM(amount), 0), 2) AS metric_value')
            ->groupBy('user_id')
            ->havingRaw('ROUND(COALESCE(SUM(amount), 0), 2) > 0')
            ->toBase();

        $likes = CommentLike::query()
            ->join('comments', 'comments.id', '=', 'comment_likes.comment_id')
            ->whereIn('comments.side', [Battle::SIDE_A, Battle::SIDE_B])
            ->where('comment_likes.created_at', '>=', $since)
            ->when($until !== null, fn (Builder $q) => $q->where('comment_likes.created_at', '<', $until))
            ->selectRaw('comments.user_id')
            ->selectRaw('COUNT(*) AS argument_votes')
            ->groupBy('comments.user_id')
            ->toBase();

        $stats = DB::query()
            ->fromSub($income, 'income')
            ->joinSub($likes, 'likes', 'likes.user_id', '=', 'income.user_id')
            ->selectRaw('income.user_id')
            ->selectRaw('income.metric_value')
            ->selectRaw('likes.argument_votes')
            ->orderByDesc('income.metric_value')
            ->orderByDesc('likes.argument_votes')
            ->orderBy('income.user_id');

        return $this->attachUsers($stats);
    }

    /**
     * @return array{
     *     metric_value: float,
     *     battles_count?: int,
     *     wins?: int,
     *     total_votes?: int,
     *     argument_votes?: int
     * }|null
     */
    private function userMetric(int $userId, string $tab, string $period): ?array
    {
        $bounds = $this->periodBounds($period);

        return match ($tab) {
            self::TAB_CREATORS => $this->creatorMetric($userId, $bounds['since'], $bounds['until']),
            self::TAB_ORACLES => $this->oracleMetric($userId, $bounds['since'], $bounds['until']),
            self::TAB_INFLUENCERS => $this->influencerMetric($userId, $bounds['since'], $bounds['until']),
            default => null,
        };
    }

    /**
     * @return array{metric_value: float, battles_count: int}|null
     */
    private function creatorMetric(int $userId, ?Carbon $since, ?Carbon $until): ?array
    {
        $cut = (float) config('versus.leaderboard.creator_fee_cut');

        /** @var object{metric_value: float|int|string, battles_count: int}|null $row */
        $row = Battle::query()
            ->where('status', Battle::STATUS_SETTLED)
            ->where('created_by_id', $userId)
            ->when($since !== null, fn (Builder $q) => $q->where('settled_at', '>=', $since))
            ->when($until !== null, fn (Builder $q) => $q->where('settled_at', '<', $until))
            ->selectRaw('ROUND(COALESCE(SUM(total_pool), 0) * ?, 2) AS metric_value', [$cut])
            ->selectRaw('COUNT(*) AS battles_count')
            ->toBase()
            ->first();

        if ($row === null || (float) $row->metric_value <= 0) {
            return null;
        }

        return [
            'metric_value' => (float) $row->metric_value,
            'battles_count' => (int) $row->battles_count,
        ];
    }

    /**
     * @return array{metric_value: float, wins: int, total_votes: int}|null
     */
    private function oracleMetric(int $userId, ?Carbon $since, ?Carbon $until): ?array
    {
        $minVotes = (int) config('versus.leaderboard.oracle_min_votes');

        $row = Vote::query()
            ->join('battles', 'battles.id', '=', 'votes.battle_id')
            ->where('votes.user_id', $userId)
            ->where('battles.status', Battle::STATUS_SETTLED)
            ->whereNotNull('battles.winning_side')
            ->when($since !== null, fn (Builder $q) => $q->where('votes.created_at', '>=', $since))
            ->when($until !== null, fn (Builder $q) => $q->where('votes.created_at', '<', $until))
            ->selectRaw('COUNT(*) AS total_votes')
            ->selectRaw('SUM(CASE WHEN votes.side = battles.winning_side THEN 1 ELSE 0 END) AS wins')
            ->first();

        $total = (int) ($row->total_votes ?? 0);
        if ($total < $minVotes) {
            return null;
        }

        $wins = (int) ($row->wins ?? 0);

        return [
            'metric_value' => round(100 * $wins / $total, 2),
            'wins' => $wins,
            'total_votes' => $total,
        ];
    }

    /**
     * @return array{metric_value: float, argument_votes: int}|null
     */
    private function influencerMetric(int $userId, ?Carbon $since, ?Carbon $until): ?array
    {
        $income = (float) Transaction::query()
            ->where('user_id', $userId)
            ->where('type', Transaction::TYPE_COMMENT_LIKE_CREDIT)
            ->when($since !== null, fn (Builder $q) => $q->where('created_at', '>=', $since))
            ->when($until !== null, fn (Builder $q) => $q->where('created_at', '<', $until))
            ->sum('amount');

        $votes = (int) CommentLike::query()
            ->join('comments', 'comments.id', '=', 'comment_likes.comment_id')
            ->where('comments.user_id', $userId)
            ->whereIn('comments.side', [Battle::SIDE_A, Battle::SIDE_B])
            ->when($since !== null, fn (Builder $q) => $q->where('comment_likes.created_at', '>=', $since))
            ->when($until !== null, fn (Builder $q) => $q->where('comment_likes.created_at', '<', $until))
            ->count();

        if ($income <= 0) {
            return null;
        }

        return [
            'metric_value' => round($income, 2),
            'argument_votes' => $votes,
        ];
    }

    /**
     * @param  array{
     *     metric_value: float,
     *     battles_count?: int,
     *     wins?: int,
     *     total_votes?: int,
     *     argument_votes?: int
     * }  $metric
     */
    private function rankForMetric(int $userId, string $tab, string $period, array $metric): int
    {
        $value = $metric['metric_value'];

        return match ($tab) {
            self::TAB_CREATORS => $this->countAheadCreators($value, $userId, $period) + 1,
            self::TAB_ORACLES => $this->countAheadOracles($value, $metric['wins'] ?? 0, $userId, $period) + 1,
            self::TAB_INFLUENCERS => $this->countAheadInfluencers($value, $metric['argument_votes'] ?? 0, $userId, $period) + 1,
            default => 1,
        };
    }

    private function countAheadCreators(float $value, int $userId, string $period): int
    {
        $bounds = $this->periodBounds($period);
        $cut = (float) config('versus.leaderboard.creator_fee_cut');

        $sub = Battle::query()
            ->where('status', Battle::STATUS_SETTLED)
            ->whereNotNull('created_by_id')
            ->when($bounds['since'] !== null, fn (Builder $q) => $q->where('settled_at', '>=', $bounds['since']))
            ->when($bounds['until'] !== null, fn (Builder $q) => $q->where('settled_at', '<', $bounds['until']))
            ->selectRaw('created_by_id AS user_id')
            ->selectRaw('ROUND(COALESCE(SUM(total_pool), 0) * ?, 2) AS metric_value', [$cut])
            ->groupBy('created_by_id')
            ->havingRaw('ROUND(COALESCE(SUM(total_pool), 0) * ?, 2) > 0', [$cut])
            ->toBase();

        return (int) DB::query()
            ->fromSub($sub, 'stats')
            ->where(function ($q) use ($value, $userId) {
                $q->whereRaw('CAST(metric_value AS REAL) > ?', [$value])
                    ->orWhere(function ($q2) use ($value, $userId) {
                        $q2->whereRaw('CAST(metric_value AS REAL) = ?', [$value])
                            ->where('user_id', '<', $userId);
                    });
            })
            ->count();
    }

    private function countAheadOracles(float $value, int $wins, int $userId, string $period): int
    {
        $bounds = $this->periodBounds($period);
        $minVotes = (int) config('versus.leaderboard.oracle_min_votes');

        $sub = Vote::query()
            ->join('battles', 'battles.id', '=', 'votes.battle_id')
            ->where('battles.status', Battle::STATUS_SETTLED)
            ->whereNotNull('battles.winning_side')
            ->when($bounds['since'] !== null, fn (Builder $q) => $q->where('votes.created_at', '>=', $bounds['since']))
            ->when($bounds['until'] !== null, fn (Builder $q) => $q->where('votes.created_at', '<', $bounds['until']))
            ->selectRaw('votes.user_id')
            ->selectRaw('SUM(CASE WHEN votes.side = battles.winning_side THEN 1 ELSE 0 END) AS wins')
            ->selectRaw('ROUND(100.0 * SUM(CASE WHEN votes.side = battles.winning_side THEN 1 ELSE 0 END) / COUNT(*), 2) AS metric_value')
            ->groupBy('votes.user_id')
            ->havingRaw('COUNT(*) >= ?', [$minVotes])
            ->toBase();

        return (int) DB::query()
            ->fromSub($sub, 'stats')
            ->where(function ($q) use ($value, $wins, $userId) {
                $q->whereRaw('CAST(metric_value AS REAL) > ?', [$value])
                    ->orWhere(function ($q2) use ($value, $wins, $userId) {
                        $q2->whereRaw('CAST(metric_value AS REAL) = ?', [$value])
                            ->where(function ($q3) use ($wins, $userId) {
                                $q3->whereRaw('CAST(wins AS INTEGER) > ?', [$wins])
                                    ->orWhere(function ($q4) use ($wins, $userId) {
                                        $q4->whereRaw('CAST(wins AS INTEGER) = ?', [$wins])
                                            ->where('user_id', '<', $userId);
                                    });
                            });
                    });
            })
            ->count();
    }

    private function countAheadInfluencers(float $value, int $argumentVotes, int $userId, string $period): int
    {
        $bounds = $this->periodBounds($period);

        $income = Transaction::query()
            ->where('type', Transaction::TYPE_COMMENT_LIKE_CREDIT)
            ->when($bounds['since'] !== null, fn (Builder $q) => $q->where('created_at', '>=', $bounds['since']))
            ->when($bounds['until'] !== null, fn (Builder $q) => $q->where('created_at', '<', $bounds['until']))
            ->selectRaw('user_id')
            ->selectRaw('ROUND(COALESCE(SUM(amount), 0), 2) AS metric_value')
            ->groupBy('user_id')
            ->havingRaw('ROUND(COALESCE(SUM(amount), 0), 2) > 0')
            ->toBase();

        $likes = CommentLike::query()
            ->join('comments', 'comments.id', '=', 'comment_likes.comment_id')
            ->whereIn('comments.side', [Battle::SIDE_A, Battle::SIDE_B])
            ->when($bounds['since'] !== null, fn (Builder $q) => $q->where('comment_likes.created_at', '>=', $bounds['since']))
            ->when($bounds['until'] !== null, fn (Builder $q) => $q->where('comment_likes.created_at', '<', $bounds['until']))
            ->selectRaw('comments.user_id')
            ->selectRaw('COUNT(*) AS argument_votes')
            ->groupBy('comments.user_id')
            ->toBase();

        $sub = DB::query()
            ->fromSub($income, 'income')
            ->joinSub($likes, 'likes', 'likes.user_id', '=', 'income.user_id')
            ->selectRaw('income.user_id')
            ->selectRaw('income.metric_value')
            ->selectRaw('likes.argument_votes');

        return (int) DB::query()
            ->fromSub($sub, 'stats')
            ->where(function ($q) use ($value, $argumentVotes, $userId) {
                $q->whereRaw('CAST(metric_value AS REAL) > ?', [$value])
                    ->orWhere(function ($q2) use ($value, $argumentVotes, $userId) {
                        $q2->whereRaw('CAST(metric_value AS REAL) = ?', [$value])
                            ->where(function ($q3) use ($argumentVotes, $userId) {
                                $q3->whereRaw('CAST(argument_votes AS INTEGER) > ?', [$argumentVotes])
                                    ->orWhere(function ($q4) use ($argumentVotes, $userId) {
                                        $q4->whereRaw('CAST(argument_votes AS INTEGER) = ?', [$argumentVotes])
                                            ->where('user_id', '<', $userId);
                                    });
                            });
                    });
            })
            ->count();
    }
}
