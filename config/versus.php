<?php

return [
    'signup_bonus' => 10,

    'max_vote_amount' => 10000,

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
];
