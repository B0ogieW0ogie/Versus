<?php

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Categories\Pages\ListCategories;
use App\Models\Battle;
use App\Models\Category;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The admin panel is not registered as Filament's default, so Livewire tests
     * that mount its pages directly have to enter the panel context by hand.
     */
    private function actingAsAdminInPanel(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        return $admin;
    }

    public function test_admin_can_list_categories(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Category::factory()->create(['name_en' => 'Sports']);

        $this->actingAs($admin)
            ->get('/admin/categories')
            ->assertOk()
            ->assertSee('Sports');
    }

    public function test_non_admin_cannot_access_categories_panel(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get('/admin/categories')
            ->assertForbidden();
    }

    public function test_admin_can_delete_a_category_from_the_table(): void
    {
        $this->actingAsAdminInPanel();
        $category = Category::factory()->create();

        Livewire::test(ListCategories::class)
            ->assertActionVisible(TestAction::make(DeleteAction::class)->table($category))
            ->callAction(TestAction::make(DeleteAction::class)->table($category))
            ->assertNotified();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_deleting_a_category_keeps_its_battles_and_clears_their_category(): void
    {
        $this->actingAsAdminInPanel();
        $category = Category::factory()->create();
        $battle = Battle::factory()->create(['category_id' => $category->id]);

        Livewire::test(ListCategories::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($category));

        $this->assertDatabaseHas('battles', [
            'id' => $battle->id,
            'category_id' => null,
        ]);
    }

    public function test_delete_confirmation_warns_how_many_battles_lose_their_category(): void
    {
        $this->actingAsAdminInPanel();
        $category = Category::factory()->create();
        Battle::factory()->count(3)->create(['category_id' => $category->id]);

        Livewire::test(ListCategories::class)
            ->mountAction(TestAction::make(DeleteAction::class)->table($category))
            ->assertMountedActionModalSee('Батлов в этой категории: 3');
    }
}
