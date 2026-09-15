<?php

namespace App\Notifications;

use App\Models\Challenge;

class ChallengeResults extends ChallengeNotification
{
    public const WON = 'won';

    public const OVER = 'over';

    public const NO_VOTES = 'no_votes';

    public function __construct(
        Challenge $challenge,
        private readonly string $result,
        private readonly ?string $winnerName,
        private readonly ?int $entryId,
    ) {
        parent::__construct($challenge);
    }

    protected function extra(): array
    {
        return ['result' => $this->result, 'winner_name' => $this->winnerName, 'entry_id' => $this->entryId];
    }
}
