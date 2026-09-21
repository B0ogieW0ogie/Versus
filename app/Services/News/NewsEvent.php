<?php

namespace App\Services\News;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use Carbon\CarbonInterface;

class NewsEvent
{
    public const TYPE_CREATED = 'created';

    public const TYPE_ACCEPTED = 'accepted';

    public const TYPE_RESPONDED = 'responded';

    public const TYPE_VOTED = 'voted';

    public const TYPE_RESULTS = 'results';

    public const TYPES = [self::TYPE_CREATED, self::TYPE_ACCEPTED, self::TYPE_RESPONDED, self::TYPE_VOTED, self::TYPE_RESULTS];

    /**
     * @param  User|null  $actor  null for system events (results are announced by VERSUS)
     * @param  list<ChallengeEntry>  $podium  results only: top entries, best first
     */
    public function __construct(
        public readonly string $type,
        public readonly ?User $actor,
        public readonly Challenge $challenge,
        public readonly CarbonInterface $occurredAt,
        public readonly ?ChallengeEntry $entry = null,
        public readonly array $podium = [],
    ) {}

    public function key(): string
    {
        return implode('-', [$this->type, $this->actor->id ?? 0, $this->challenge->id, $this->entry->id ?? 0, $this->occurredAt->getTimestamp()]);
    }

    public function url(): string
    {
        $entryId = $this->entry->id ?? ($this->podium[0]->id ?? null);

        return route('challenges.show', array_filter(['challenge' => $this->challenge->slug, 'entry' => $entryId]));
    }

    public function posterUrl(): ?string
    {
        return ($this->entry ?? $this->challenge->original)?->posterUrl();
    }
}
