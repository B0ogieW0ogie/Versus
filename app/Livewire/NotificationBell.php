<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;

    public int $unreadCount = 0;

    /** @var list<string> */
    public array $freshIds = [];

    public function mount(): void
    {
        $this->unreadCount = $this->user()->unreadNotifications()->count();
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;

        if ($this->open) {
            $this->freshIds = $this->user()->unreadNotifications()->pluck('id')->all();
            $this->user()->unreadNotifications()->update(['read_at' => now()]);
            $this->unreadCount = 0;
        } else {
            $this->freshIds = [];
        }
    }

    public function refreshCount(): void
    {
        $fresh = $this->user()->unreadNotifications()->count();

        if ($fresh > $this->unreadCount) {
            $this->dispatch('notification-ding');
        }

        $this->unreadCount = $fresh;
    }

    public function render(): View
    {
        $items = ! $this->open ? collect() : $this->user()
            ->notifications()
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'message' => $this->message($notification),
                'url' => $this->url($notification),
                'time' => $notification->created_at?->diffForHumans() ?? '',
                'fresh' => in_array($notification->id, $this->freshIds, true),
            ])
            ->filter(fn (array $item): bool => $item['message'] !== '')
            ->values();

        return view('livewire.notification-bell', ['items' => $items]);
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    private function message(DatabaseNotification $notification): string
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;
        $battle = (string) ($data['battle_title'] ?? '');

        return match (class_basename($notification->type)) {
            'BattleSettled' => __('notifications.battle_'.$data['result'], [
                'amount' => number_format((float) $data['amount'], 2),
                'battle' => $battle,
            ]),
            'ReferralPayout' => __('notifications.referral_payout', [
                'amount' => number_format((float) $data['amount'], 2),
                'name' => (string) $data['referee_name'],
                'battle' => $battle,
            ]),
            'CommentReplied' => __('notifications.comment_replied', [
                'name' => (string) $data['actor_name'],
                'battle' => $battle,
            ]),
            'CommentLiked' => __('notifications.comment_liked', [
                'name' => (string) $data['actor_name'],
                'battle' => $battle,
            ]),
            'ArgumentSupported' => __('notifications.argument_supported', [
                'name' => (string) $data['actor_name'],
                'battle' => $battle,
            ]),
            'BattleLastShot' => __('notifications.battle_last_shot', ['battle' => $battle]),
            'ChallengePublished' => __('challenges.notif_published', ['title' => (string) $data['challenge_title']]),
            'EntryProcessingFailed' => __('challenges.notif_failed', [
                'title' => (string) $data['challenge_title'],
                'reason' => __('challenges.'.$data['reason'], [
                    'seconds' => config('versus.challenges.max_video_seconds'),
                    'mb' => config('versus.challenges.max_upload_mb'),
                ]),
            ]),
            'ChallengeResponseReceived' => __('challenges.notif_response_received', [
                'name' => (string) $data['actor_name'],
                'title' => (string) $data['challenge_title'],
            ]),
            'DuelInvitation' => __('challenges.notif_duel_invitation', [
                'name' => (string) $data['actor_name'],
                'title' => (string) $data['challenge_title'],
            ]),
            'ResponsePublished' => __('challenges.notif_response_published', ['title' => (string) $data['challenge_title']]),
            'ChallengeEndingSoon' => __('challenges.notif_ending_soon', ['title' => (string) $data['challenge_title']]),
            'ChallengeResults' => __('challenges.notif_results_'.$data['result'], [
                'title' => (string) $data['challenge_title'],
                'name' => (string) ($data['winner_name'] ?? ''),
            ]),
            default => '',
        };
    }

    private function url(DatabaseNotification $notification): string
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        if (isset($data['challenge_slug'])) {
            if (class_basename($notification->type) === 'EntryProcessingFailed') {
                return url('/my-challenges');
            }

            $url = url('/c/'.$data['challenge_slug']);

            return isset($data['entry_id']) ? $url.'?entry='.$data['entry_id'] : $url;
        }

        $url = route('battles.show', ['battle' => (string) $data['battle_slug']]);

        return isset($data['comment_id']) ? $url.'#comment-'.$data['comment_id'] : $url;
    }
}
