<?php

namespace Database\Seeders;

use App\Models\Battle;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@versus.test'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        Battle::firstOrCreate(
            ['slug' => 'pepsi-vs-coke'],
            [
                'title' => 'Pepsi vs Coca-Cola',
                'description' => 'Классика кол: какая сторона сильнее в 2026 году?',
                'side_a_label' => 'Pepsi',
                'side_b_label' => 'Coca-Cola',
                'status' => Battle::STATUS_ACTIVE,
                'opens_at' => now()->subDay(),
                'closes_at' => now()->addDays(3),
                'created_by_id' => $admin->id,
            ]
        );

        Battle::firstOrCreate(
            ['slug' => 'vim-vs-emacs'],
            [
                'title' => 'Vim vs Emacs',
                'description' => 'Вечный холивар редакторов.',
                'side_a_label' => 'Vim',
                'side_b_label' => 'Emacs',
                'status' => Battle::STATUS_ACTIVE,
                'opens_at' => now()->subHour(),
                'closes_at' => now()->addDay(),
                'created_by_id' => $admin->id,
            ]
        );
    }
}
