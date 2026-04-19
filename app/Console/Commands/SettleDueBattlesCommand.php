<?php

namespace App\Console\Commands;

use App\Actions\Battles\SettleBattleAction;
use App\Models\Battle;
use Illuminate\Console\Command;
use Throwable;

class SettleDueBattlesCommand extends Command
{
    protected $signature = 'battles:settle-due';

    protected $description = 'Settle every active battle whose closes_at has passed.';

    public function handle(SettleBattleAction $settle): int
    {
        $due = Battle::query()
            ->where('status', Battle::STATUS_ACTIVE)
            ->whereNotNull('closes_at')
            ->where('closes_at', '<=', now())
            ->get();

        foreach ($due as $battle) {
            $battle->status = Battle::STATUS_CLOSED;
            $battle->save();

            try {
                $settle($battle);
                $this->info("Settled battle #{$battle->id} ({$battle->slug}).");
            } catch (Throwable $e) {
                $this->error("Failed to settle battle #{$battle->id}: {$e->getMessage()}");
            }
        }

        if ($due->isEmpty()) {
            $this->info('No battles due for settlement.');
        }

        return self::SUCCESS;
    }
}
