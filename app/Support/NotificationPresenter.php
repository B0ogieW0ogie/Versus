<?php

namespace App\Support;

use Illuminate\Notifications\DatabaseNotification;

/** Turns stored database notifications into a display line and a link (bell dropdown and Notifications page). */
class NotificationPresenter
{
    public function message(DatabaseNotification $notification): string
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

    public function url(DatabaseNotification $notification): string
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
