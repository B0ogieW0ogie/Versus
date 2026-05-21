<?php

namespace Tests\Feature\Admin;

use App\Actions\Battles\GenerateDemoBattleAction;
use App\Models\Battle;
use App\Models\Category;
use App\Support\BattleSideImageGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GenerateDemoBattleTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_sponsored_battle_with_side_images(): void
    {
        Storage::fake('public');

        $battle = app(GenerateDemoBattleAction::class)([
            'placement' => ['sponsored'],
            'sponsor_handle' => 'Apple',
        ]);

        $this->assertTrue($battle->is_sponsored);
        $this->assertSame('@Apple', $battle->sponsor_handle);
        $this->assertSame(Battle::STATUS_ACTIVE, $battle->status);
        $this->assertNotNull($battle->side_a_image);
        $this->assertNotNull($battle->side_b_image);
        $this->assertStringContainsString('.svg', (string) $battle->side_a_image);
        $this->assertNotEmpty(Storage::disk('public')->allFiles('battles/sides/generated'));
    }

    public function test_generates_category_and_hot_battle(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        $battle = app(GenerateDemoBattleAction::class)([
            'placement' => ['category', 'hot'],
            'category_id' => $category->id,
            'hot_pool' => 750_000,
        ]);

        $this->assertSame($category->id, $battle->category_id);
        $this->assertFalse($battle->is_sponsored);
        $this->assertSame(750_000.0, (float) $battle->total_pool);
    }

    public function test_requires_at_least_one_placement(): void
    {
        Storage::fake('public');

        $this->expectException(ValidationException::class);

        app(GenerateDemoBattleAction::class)([
            'placement' => [],
        ]);
    }

    public function test_side_image_generator_writes_svg_with_label(): void
    {
        Storage::fake('public');

        [$urlA] = app(BattleSideImageGenerator::class)->generatePair('Test Side', 'Other');

        $path = Storage::disk('public')->allFiles('battles/sides/generated')[0] ?? '';
        $contents = Storage::disk('public')->get($path);

        $this->assertStringContainsString('<svg', $contents);
        $this->assertStringContainsString('TEST SIDE', $contents);
    }

    public function test_admin_list_page_declares_generate_action(): void
    {
        $source = file_get_contents(base_path('app/Filament/Admin/Resources/Battles/Pages/ListBattles.php'));

        $this->assertStringContainsString('GenerateBattleAction::make()', $source);
    }
}
