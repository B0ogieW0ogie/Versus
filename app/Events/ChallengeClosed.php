<?php

namespace App\Events;

use App\Models\Challenge;
use Illuminate\Foundation\Events\Dispatchable;

class ChallengeClosed
{
    use Dispatchable;

    public function __construct(public readonly Challenge $challenge) {}
}
