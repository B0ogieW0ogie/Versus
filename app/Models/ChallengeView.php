<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'challenge_id', 'viewed_at'])]
class ChallengeView extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }
}
