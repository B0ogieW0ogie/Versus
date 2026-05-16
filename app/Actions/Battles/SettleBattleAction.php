<?php

namespace App\Actions\Battles;

use App\Models\Battle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SettleBattleAction
{
    public function __invoke(Battle $battle): Battle
    {
        return DB::transaction(function () use ($battle): Battle {
            /** @var Battle $battle */
            $battle = Battle::whereKey($battle->id)->lockForUpdate()->firstOrFail();

            if ($battle->status === Battle::STATUS_SETTLED) {
                throw new RuntimeException('Баттл уже завершён.');
            }

            $weightA = (float) Vote::where('battle_id', $battle->id)
                ->where('side', Battle::SIDE_A)
                ->sum('weight');
            $weightB = (float) Vote::where('battle_id', $battle->id)
                ->where('side', Battle::SIDE_B)
                ->sum('weight');

            $pool = $this->distributablePool($battle);

            if ($pool <= 0) {
                $battle->status = Battle::STATUS_SETTLED;
                $battle->settled_at = now();
                $battle->total_pool = 0;
                $battle->save();

                return $battle;
            }

            $dist = (array) config('versus.distribution');
            $projectShare = $this->round($pool * (float) $dist['project']);
            $burnShare = $this->round($pool * (float) $dist['burn']);
            $rewardPoolShare = $this->round($pool * (float) $dist['reward_pool']);
            $winnersShare = $this->round($pool * (float) $dist['winners']);

            $this->systemCredit(Transaction::TYPE_PROJECT_FEE, $projectShare, $battle->id);
            $this->systemCredit(Transaction::TYPE_BURN, $burnShare, $battle->id);
            $this->systemCredit(Transaction::TYPE_REWARD_POOL_CREDIT, $rewardPoolShare, $battle->id);

            $winningSide = $this->decideWinner($weightA, $weightB);

            if ($winningSide === null) {
                $this->refundAll($battle);
                $battle->status = Battle::STATUS_SETTLED;
                $battle->settled_at = now();
                $battle->total_pool = $pool;
                $battle->save();

                return $battle;
            }

            $winningWeight = $winningSide === Battle::SIDE_A ? $weightA : $weightB;

            if ($winningWeight <= 0) {
                $battle->status = Battle::STATUS_SETTLED;
                $battle->winning_side = $winningSide;
                $battle->settled_at = now();
                $battle->total_pool = $pool;
                $battle->save();

                return $battle;
            }

            $winningVotes = Vote::where('battle_id', $battle->id)
                ->where('side', $winningSide)
                ->orderBy('id')
                ->get();

            $distributed = 0.0;
            $count = $winningVotes->count();

            foreach ($winningVotes as $index => $vote) {
                $share = (float) $vote->weight / $winningWeight;
                $payout = $index === $count - 1
                    ? $this->round($winnersShare - $distributed)
                    : $this->round($winnersShare * $share);

                $distributed += $payout;

                /** @var User $winner */
                $winner = User::whereKey($vote->user_id)->lockForUpdate()->firstOrFail();
                $winner->balance = $this->round((float) $winner->balance + $payout);
                $winner->save();

                $vote->payout = $payout;
                $vote->save();

                Transaction::create([
                    'user_id' => $winner->id,
                    'type' => Transaction::TYPE_VOTE_PAYOUT,
                    'amount' => $payout,
                    'balance_after' => $winner->balance,
                    'battle_id' => $battle->id,
                    'meta' => ['vote_id' => $vote->id],
                ]);

                if ($vote->referrer_id !== null && $vote->referrer_id !== $winner->id) {
                    $this->payReferral($vote->referrer_id, $winner->id, $payout, $battle->id);
                }
            }

            $battle->status = Battle::STATUS_SETTLED;
            $battle->winning_side = $winningSide;
            $battle->settled_at = now();
            $battle->total_pool = $pool;
            $battle->save();

            return $battle;
        });
    }

    private function decideWinner(float $weightA, float $weightB): ?string
    {
        if ($weightA === $weightB) {
            return null;
        }

        return $weightA > $weightB ? Battle::SIDE_A : Battle::SIDE_B;
    }

    private function refundAll(Battle $battle): void
    {
        $votes = Vote::where('battle_id', $battle->id)->orderBy('id')->get();

        foreach ($votes as $vote) {
            /** @var User $user */
            $user = User::whereKey($vote->user_id)->lockForUpdate()->firstOrFail();
            $amount = (float) $vote->amount;
            $user->balance = $this->round((float) $user->balance + $amount);
            $user->save();

            $vote->payout = $amount;
            $vote->save();

            Transaction::create([
                'user_id' => $user->id,
                'type' => Transaction::TYPE_VOTE_PAYOUT,
                'amount' => $amount,
                'balance_after' => $user->balance,
                'battle_id' => $battle->id,
                'meta' => ['vote_id' => $vote->id, 'reason' => 'tie_refund'],
            ]);
        }
    }

    private function payReferral(int $referrerId, int $winnerId, float $payout, int $battleId): void
    {
        $cut = (float) config('versus.referral.winner_cut');
        $reward = $this->round($payout * $cut);

        if ($reward <= 0) {
            return;
        }

        $rewardPoolBalance = $this->rewardPoolBalance();
        $reward = min($reward, $rewardPoolBalance);
        $reward = $this->round($reward);

        if ($reward <= 0) {
            return;
        }

        $this->systemCredit(Transaction::TYPE_REWARD_POOL_DEBIT, -$reward, $battleId);

        /** @var User $referrer */
        $referrer = User::whereKey($referrerId)->lockForUpdate()->firstOrFail();
        $referrer->balance = $this->round((float) $referrer->balance + $reward);
        $referrer->save();

        Transaction::create([
            'user_id' => $referrer->id,
            'type' => Transaction::TYPE_REFERRAL_REWARD,
            'amount' => $reward,
            'balance_after' => $referrer->balance,
            'battle_id' => $battleId,
            'meta' => ['from_user_id' => $winnerId],
        ]);
    }

    private function systemCredit(string $type, float $amount, int $battleId): void
    {
        if ($amount === 0.0) {
            return;
        }

        Transaction::create([
            'user_id' => null,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => null,
            'battle_id' => $battleId,
        ]);
    }

    private function rewardPoolBalance(): float
    {
        $credit = (float) Transaction::whereNull('user_id')
            ->where('type', Transaction::TYPE_REWARD_POOL_CREDIT)
            ->sum('amount');
        $debit = (float) Transaction::whereNull('user_id')
            ->where('type', Transaction::TYPE_REWARD_POOL_DEBIT)
            ->sum('amount');

        return $this->round($credit + $debit);
    }

    private function distributablePool(Battle $battle): float
    {
        $stakes = (float) Vote::where('battle_id', $battle->id)->sum('amount');
        $boosts = (float) Transaction::where('battle_id', $battle->id)
            ->whereNull('user_id')
            ->where('type', Transaction::TYPE_BATTLE_POOL_CREDIT)
            ->sum('amount');

        return $this->round($stakes + $boosts);
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }
}
