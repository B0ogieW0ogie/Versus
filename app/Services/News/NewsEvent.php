<?php

namespace App\Services\News;

use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\FeedPost;
use App\Models\User;
use Carbon\CarbonInterface;

class NewsEvent
{
    public const TYPE_CREATED = 'created';

    public const TYPE_ACCEPTED = 'accepted';

    public const TYPE_RESPONDED = 'responded';

    public const TYPE_VOTED = 'voted';

    public const TYPE_RESULTS = 'results';

    public const TYPE_TOP_CHANGE = FeedPost::TYPE_TOP_CHANGE;

    public const TYPE_STATUS = FeedPost::TYPE_STATUS;

    public const TYPE_ACHIEVEMENT = FeedPost::TYPE_ACHIEVEMENT;

    public const TYPE_VERSUS_NEWS = FeedPost::TYPE_VERSUS_NEWS;

    /**
     * @param  User|null  $actor  null for VERSUS itself (results, official news)
     * @param  list<ChallengeEntry>  $podium  results only: 1st–3rd place, best first
     */
    public function __construct(
        public readonly string $type,
        public readonly ?User $actor,
        public readonly CarbonInterface $occurredAt,
        public readonly ?Challenge $challenge = null,
        public readonly ?ChallengeEntry $entry = null,
        public readonly array $podium = [],
        public readonly ?FeedPost $post = null,
    ) {}

    public function key(): string
    {
        return implode('-', [
            $this->type, $this->actor->id ?? 0, $this->challenge->id ?? 0,
            $this->entry->id ?? 0, $this->post->id ?? 0, $this->occurredAt->getTimestamp(),
        ]);
    }

    /** Challenge events get the full row with a video thumbnail; the rest are compact two-liners. */
    public function isChallengeEvent(): bool
    {
        return $this->challenge !== null;
    }

    /** Where a click on the news goes: the response itself for response/Top events, else the challenge. */
    public function url(): ?string
    {
        if ($this->challenge === null) {
            return null;
        }

        return route('challenges.show', array_filter([
            'challenge' => $this->challenge->slug,
            'entry' => in_array($this->type, [self::TYPE_RESPONDED, self::TYPE_TOP_CHANGE], true) ? $this->entry?->id : null,
        ]));
    }

    /** The response's own thumbnail for response/Top events, the original challenge video otherwise. */
    public function posterUrl(): ?string
    {
        return ($this->entry ?? $this->challenge?->original)?->posterUrl();
    }

    /** The second line of the row. */
    public function detail(): string
    {
        return match ($this->type) {
            self::TYPE_STATUS => (string) $this->post?->body,
            self::TYPE_ACHIEVEMENT => $this->achievementLabel(),
            self::TYPE_VERSUS_NEWS => (string) $this->post?->title,
            default => '“'.($this->challenge->title ?? '').'”',
        };
    }

    /** The action phrase after the name in the first line. */
    public function action(): string
    {
        if ($this->type === self::TYPE_TOP_CHANGE) {
            $data = (array) $this->post?->data;
            $from = $data['from'] ?? null;
            $to = (int) ($data['to'] ?? 0);

            return match (true) {
                $from === null => __('news.top_entered', ['to' => $to]),
                (int) $from > $to => __('news.top_up', ['from' => $from, 'to' => $to]),
                default => __('news.top_down', ['from' => $from, 'to' => $to]),
            };
        }

        return __('news.event_'.$this->type);
    }

    private function achievementLabel(): string
    {
        // key = "{kind}_{count}", e.g. votes_100
        [$kind, $count] = array_pad(explode('_', (string) $this->post?->key, 2), 2, '0');

        return trans_choice('news.achievement_'.$kind, (int) $count, ['count' => (int) $count]);
    }
}
