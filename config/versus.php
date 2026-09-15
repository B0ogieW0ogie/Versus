<?php

return [
    /** Battles + token economy UI. Off for the Challenges launch; code and data stay. */
    'battles_enabled' => (bool) env('VERSUS_BATTLES_ENABLED', false),

    'challenges' => [
        'durations' => ['24h' => 24 * 60, '3d' => 3 * 24 * 60, '7d' => 7 * 24 * 60], // minutes
        'max_video_seconds' => 60,
        'max_upload_mb' => 100,
        'upload_chunk_mb' => 5,
        'daily_create_limit' => 10,
        'carousel_top_by_votes' => 7,
        'carousel_fresh_slots' => 3,
        'reminder_minutes_before' => 60,
        'comment_max_length' => 1000,
        'feed' => [
            'freshness_half_life_hours' => 24,
            'activity_window_hours' => 24,
            'weight_activity' => 1.0,
            'weight_freshness' => 1.0,
            'ending_soon_hours' => 6,
            'weight_ending_soon' => 0.5,
        ],
    ],

    'signup_bonus' => 10,

    'max_vote_amount' => 30000,

    /** Max total stake per user across all votes in one battle (any sides). */
    'max_battle_stake_per_user' => 30000,

    'distribution' => [
        'winners' => 0.88,
        'project' => 0.05,
        'burn' => 0.03,
        'reward_pool' => 0.04,
    ],

    'referral' => [
        'winner_cut' => 0.10,
    ],

    'mechanics' => [
        'stomp_threshold' => 0.90, // side share at/above which the battle is void
    ],

    'leaderboard' => [
        'creator_fee_cut' => 0.01,
        'argument_referral_cut' => 0.04,
        'oracle_min_votes' => 10,
    ],
];
