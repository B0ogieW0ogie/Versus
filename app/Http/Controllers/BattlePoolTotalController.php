<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;

class BattlePoolTotalController extends Controller
{
    public function __invoke(Battle $battle): JsonResponse
    {
        $total = (float) Vote::query()
            ->where('battle_id', $battle->id)
            ->sum('amount');

        return response()->json(['total' => $total]);
    }
}
