<?php

namespace App\Services\Feed;

use App\Models\Battle;
use App\Models\User;
use Carbon\CarbonInterface;

class FeedEvent
{
    public const TYPE_CREATE = 'create';

    public const TYPE_VOTE = 'vote';

    public const TYPE_ARGUE = 'argue';

    public const TYPE_LIKE = 'like';

    public const TYPE_WIN = 'win';

    public const TYPE_LOSE = 'lose';

    public function __construct(
        public readonly string $type,
        public readonly User $actor,
        public readonly Battle $battle,
        public readonly CarbonInterface $occurredAt,
        public readonly ?string $argumentText = null,
        public readonly ?string $sideLabel = null,
    ) {}

    public function isOpen(): bool
    {
        return $this->battle->isOpenForVoting();
    }
}
