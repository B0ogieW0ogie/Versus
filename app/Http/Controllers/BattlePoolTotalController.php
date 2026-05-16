<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use Illuminate\Http\JsonResponse;

class BattlePoolTotalController extends Controller
{
    public function __invoke(Battle $battle): JsonResponse
    {
        $total = (float) $battle->fresh()->total_pool;

        return response()->json(['total' => $total]);
    }
}
