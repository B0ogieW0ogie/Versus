<?php

namespace App\Actions\Battles;

use App\Models\Battle;
use App\Models\Category;
use App\Support\BattleDurationPreset;
use App\Support\BattleSideImageGenerator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GenerateDemoBattleAction
{
    public function __construct(
        private readonly BattleSideImageGenerator $images,
        private readonly AddBattlePoolAction $addBattlePool,
    ) {}

    /**
     * @param  array{
     *     placement: list<string>,
     *     sponsor_handle?: string|null,
     *     category_id?: int|string|null,
     *     hot_pool?: float|int|string|null,
     * }  $input
     */
    public function __invoke(array $input): Battle
    {
        $placement = array_values(array_intersect(
            ['sponsored', 'category', 'hot'],
            $input['placement'] ?? [],
        ));

        if ($placement === []) {
            throw ValidationException::withMessages([
                'placement' => 'Выберите хотя бы один вариант показа.',
            ]);
        }

        $pair = $this->randomPair();
        [$imageA, $imageB] = $this->images->generatePair($pair['side_a'], $pair['side_b']);

        $opensAt = now();
        $isSponsored = in_array('sponsored', $placement, true);
        $categoryId = in_array('category', $placement, true)
            ? $this->resolveCategoryId($input['category_id'] ?? null)
            : null;

        $battle = Battle::create([
            'slug' => $this->uniqueSlug($pair['title']),
            'title' => $pair['title'],
            'description' => $pair['description'],
            'side_a_label' => $pair['side_a'],
            'side_b_label' => $pair['side_b'],
            'side_a_image' => $imageA,
            'side_b_image' => $imageB,
            'status' => Battle::STATUS_ACTIVE,
            'opens_at' => $opensAt,
            'closes_at' => BattleDurationPreset::closesAt(BattleDurationPreset::DEFAULT, $opensAt),
            'total_pool' => 0,
            'category_id' => $categoryId,
            'is_sponsored' => $isSponsored,
            'sponsor_handle' => $isSponsored ? $this->normalizeSponsorHandle($input['sponsor_handle'] ?? null) : null,
            'ai_screened_at' => now(),
        ]);

        if (in_array('hot', $placement, true)) {
            $pool = $this->resolveHotPool($input['hot_pool'] ?? null);
            ($this->addBattlePool)($battle, $pool, 'Admin generated HOT pool');
        }

        return $battle->fresh();
    }

    /**
     * @return array{title: string, description: string, side_a: string, side_b: string}
     */
    private function randomPair(): array
    {
        $pairs = [
            ['title' => 'iPhone vs Android', 'side_a' => 'Apple', 'side_b' => 'Android', 'description' => 'Какая экосистема победит в 2026?'],
            ['title' => 'Messi vs Ronaldo', 'side_a' => 'Messi', 'side_b' => 'Ronaldo', 'description' => 'Вечное противостояние легенд.'],
            ['title' => 'Marvel vs DC', 'side_a' => 'Marvel', 'side_b' => 'DC', 'description' => 'Чья вселенная круче на большом экране?'],
            ['title' => 'Кофе vs Чай', 'side_a' => 'Кофе', 'side_b' => 'Чай', 'description' => 'Утренний ритуал миллионов.'],
            ['title' => 'PlayStation vs Xbox', 'side_a' => 'PlayStation', 'side_b' => 'Xbox', 'description' => 'Консольные войны продолжаются.'],
            ['title' => 'Лето vs Зима', 'side_a' => 'Лето', 'side_b' => 'Зима', 'description' => 'Какое время года вы берёте?'],
            ['title' => 'Коты vs Собаки', 'side_a' => 'Коты', 'side_b' => 'Собаки', 'description' => 'Кто лучший домашний питомец?'],
            ['title' => 'Пицца vs Бургер', 'side_a' => 'Пицца', 'side_b' => 'Бургер', 'description' => 'Фастфуд-баттл века.'],
        ];

        return $pairs[array_rand($pairs)];
    }

    private function uniqueSlug(string $title): string
    {
        return Str::slug($title).'-'.Str::lower(Str::random(6));
    }

    private function normalizeSponsorHandle(?string $handle): string
    {
        $trimmed = trim((string) $handle);
        if ($trimmed === '') {
            $handles = ['@Apple', '@Brand', '@Versus', '@Demo'];

            return $handles[array_rand($handles)];
        }

        return str_starts_with($trimmed, '@') ? $trimmed : '@'.$trimmed;
    }

    private function resolveCategoryId(mixed $categoryId): int
    {
        if ($categoryId !== null && $categoryId !== '') {
            $id = (int) $categoryId;
            if (Category::query()->whereKey($id)->exists()) {
                return $id;
            }
        }

        $random = Category::query()->inRandomOrder()->value('id');
        if ($random === null) {
            throw ValidationException::withMessages([
                'category_id' => 'Сначала создайте хотя бы одну категорию.',
            ]);
        }

        return (int) $random;
    }

    private function resolveHotPool(mixed $amount): float
    {
        if ($amount !== null && $amount !== '' && is_numeric($amount)) {
            $value = round((float) $amount, 2);
            if ($value >= 1) {
                return $value;
            }
        }

        return (float) random_int(150_000, 1_500_000);
    }
}
