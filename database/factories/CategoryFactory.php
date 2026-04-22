<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'slug' => Str::slug($name).'-'.Str::random(4),
            'name_en' => ucfirst($name),
            'name_ru' => ucfirst($name),
            'sort_order' => 0,
        ];
    }
}
