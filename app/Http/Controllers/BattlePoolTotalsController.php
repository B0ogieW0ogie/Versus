<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BattlePoolTotalsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->take(50)
            ->values();

        if ($ids->isEmpty()) {
            return response()->json((object) []);
        }

        $totals = Battle::query()
            ->whereIn('id', $ids->all())
            ->pluck('total_pool', 'id')
            ->map(fn ($total): float => (float) $total);

        return response()->json($totals->isEmpty() ? (object) [] : $totals);
    }
}
