<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_five_categories_in_order(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertSame(5, Category::count());

        $slugs = Category::query()->orderBy('sort_order')->pluck('slug')->all();
        $this->assertSame(['sports', 'memes', 'movies', 'superheroes', 'tv-shows'], $slugs);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertSame(5, Category::count());
    }
}
