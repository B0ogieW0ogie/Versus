<?php

use App\Livewire\WalletPage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guest is redirected to login', function () {
    $this->get('/wallet')->assertRedirect(route('login'));
});

test('unverified user cannot access wallet', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/wallet')
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('wallet lists signup bonus with localized label', function () {
    $user = User::factory()->create(['balance' => 10]);
    Transaction::create([
        'user_id' => $user->id,
        'type' => Transaction::TYPE_SIGNUP_BONUS,
        'amount' => 10,
        'balance_after' => 10,
    ]);

    app()->setLocale('ru');
    $this->actingAs($user)
        ->get('/wallet')
        ->assertOk()
        ->assertSee('Стартовый бонус')
        ->assertSee('+10');

    app()->setLocale('en');
    $this->actingAs($user)
        ->get('/wallet')
        ->assertOk()
        ->assertSee('Starter bonus');
});

test('livewire wallet component renders for authenticated user', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(WalletPage::class)
        ->assertOk();
});
