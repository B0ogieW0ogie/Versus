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

    public const TYPE_VOTE_ARGUE = 'vote_argue';

    public const TYPE_WIN = 'win';

    public const TYPE_LOSE = 'lose';

    public function __construct(
        public readonly string $type,
        public readonly User $actor,
        public readonly Battle $battle,
        public readonly CarbonInterface $occurredAt,
        public readonly ?string $argumentText = null,
    ) {}

    public function groupKey(): string
    {
        return $this->actor->getKey().':'.$this->battle->getKey();
    }

    public function isGroupable(): bool
    {
        return in_array($this->type, [self::TYPE_VOTE, self::TYPE_ARGUE], true);
    }

    public function isOpen(): bool
    {
        return $this->battle->isOpenForVoting();
    }
}
