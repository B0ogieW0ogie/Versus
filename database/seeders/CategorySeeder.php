<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $seeds = [
            ['slug' => 'sports',      'name_en' => 'Sports',      'name_ru' => 'Спорт',     'sort_order' => 10],
            ['slug' => 'memes',       'name_en' => 'Memes',       'name_ru' => 'Мемы',      'sort_order' => 20],
            ['slug' => 'movies',      'name_en' => 'Movies',      'name_ru' => 'Фильмы',    'sort_order' => 30],
            ['slug' => 'superheroes', 'name_en' => 'Superheroes', 'name_ru' => 'Супергерои', 'sort_order' => 40],
            ['slug' => 'tv-shows',    'name_en' => 'TV Shows',    'name_ru' => 'Сериалы',   'sort_order' => 50],
        ];

        foreach ($seeds as $row) {
            Category::query()->updateOrCreate(['slug' => $row['slug']], $row);
        }
    }
}
