<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_localized_name_returns_english_by_default(): void
    {
        app()->setLocale('en');
        $cat = Category::factory()->create(['name_en' => 'Sports', 'name_ru' => 'Спорт']);

        $this->assertSame('Sports', $cat->localized_name);
    }

    public function test_localized_name_returns_russian_when_locale_is_ru(): void
    {
        app()->setLocale('ru');
        $cat = Category::factory()->create(['name_en' => 'Sports', 'name_ru' => 'Спорт']);

        $this->assertSame('Спорт', $cat->localized_name);
    }

    public function test_localized_name_falls_back_to_english_for_unknown_locale(): void
    {
        app()->setLocale('fr');
        $cat = Category::factory()->create(['name_en' => 'Sports', 'name_ru' => 'Спорт']);

        $this->assertSame('Sports', $cat->localized_name);
    }
}
