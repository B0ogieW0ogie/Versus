<?php

namespace App\Console\Commands;

use App\Actions\Challenges\CloseChallengeAction;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\ChallengeParticipant;
use App\Notifications\ChallengeEndingSoon;
use Illuminate\Console\Command;
use Throwable;

class CloseDueChallengesCommand extends Command
{
    protected $signature = 'challenges:close-due';

    protected $description = 'Close challenges past their deadline and send "1 hour left" reminders.';

    public function handle(CloseChallengeAction $close): int
    {
        Challenge::query()
            ->where('status', Challenge::STATUS_ACTIVE)
            ->where('ends_at', '<=', now())
            ->eachById(function (Challenge $challenge) use ($close): void {
                try {
                    $close($challenge);
                    $this->info("Closed challenge #{$challenge->id}.");
                } catch (Throwable $e) {
                    report($e);
                    $this->error("Failed to close challenge #{$challenge->id}: {$e->getMessage()}");
                }
            });

        $this->sendReminders();

        return self::SUCCESS;
    }

    private function sendReminders(): void
    {
        $horizon = now()->addMinutes((int) config('versus.challenges.reminder_minutes_before'));

        Challenge::query()
            ->where('status', Challenge::STATUS_ACTIVE)
            ->whereNull('reminder_sent_at')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', $horizon)
            ->eachById(function (Challenge $challenge): void {
                // Conditional update claims the reminder so overlapping runs cannot double-send.
                $claimed = Challenge::whereKey($challenge->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);
                if ($claimed === 0) {
                    return;
                }

                $answered = ChallengeEntry::where('challenge_id', $challenge->id)->pluck('user_id');

                ChallengeParticipant::query()
                    ->where('challenge_id', $challenge->id)
                    ->whereNotIn('user_id', $answered)
                    ->with('user')
                    ->each(function (ChallengeParticipant $participant) use ($challenge): void {
                        try {
                            $participant->user->notify(new ChallengeEndingSoon($challenge));
                        } catch (Throwable $e) {
                            report($e);
                        }
                    });
            });
    }
}
