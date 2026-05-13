<?php

namespace App\Actions\Battles;

use App\Models\Battle;
use App\Models\User;
use Illuminate\Support\Str;

class CreateUserBattleAction
{
    /**
     * @param  array<string, mixed>  $data  Validated keys only (sides, images, dates, category). Title is derived as
     *                                      "side_a_label VS side_b_label" (truncated); description is always null.
     *                                      Must not include user-controlled status or pool fields.
     */
    public function __invoke(User $user, array $data): Battle
    {
        $title = Str::limit(
            trim((string) $data['side_a_label']).' VS '.trim((string) $data['side_b_label']),
            255,
            '',
        );

        $baseSlug = Str::slug($title);
        if ($baseSlug === '') {
            $baseSlug = 'battle';
        }
        $slug = $baseSlug;
        while (Battle::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.Str::lower(Str::random(4));
        }

        return Battle::query()->create([
            'slug' => $slug,
            'title' => $title,
            'description' => null,
            'side_a_label' => $data['side_a_label'],
            'side_b_label' => $data['side_b_label'],
            'side_a_subtitle' => $data['side_a_subtitle'] ?? null,
            'side_b_subtitle' => $data['side_b_subtitle'] ?? null,
            'side_a_image' => $data['side_a_image'] ?? null,
            'side_b_image' => $data['side_b_image'] ?? null,
            'opens_at' => $data['opens_at'] ?? null,
            'closes_at' => $data['closes_at'] ?? null,
            'status' => Battle::STATUS_ACTIVE,
            'total_pool' => 0,
            'winning_side' => null,
            'settled_at' => null,
            'created_by_id' => $user->id,
            'category_id' => $data['category_id'] ?? null,
            'is_sponsored' => false,
            'sponsor_handle' => null,
        ]);
    }
}
