<?php

namespace Tests\Feature\Filament;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

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
}
