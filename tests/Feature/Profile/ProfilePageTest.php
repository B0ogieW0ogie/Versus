<?php

use App\Livewire\ProfilePage;
use App\Models\Battle;
use App\Models\Comment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('renders for authenticated user', function () {
    $user = User::factory()->create(['name' => 'Alice']);

    Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->assertOk()
        ->assertSee('Alice');
});

test('guest is redirected to login', function () {
    $this->get('/profile')->assertRedirect(route('login'));
});

test('unverified user is redirected to verify email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/profile')
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('authenticated user sees the profile page at /profile', function () {
    $user = User::factory()->create(['name' => 'Alice']);

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertSee('Alice');
});

test('shows user name, handle, bio and follower stats', function () {
    $user = User::factory()->create([
        'name' => 'Vlad Basargin',
        'username' => 'vladbasargin',
        'bio' => 'Люблю спорить о футболе',
    ]);
    $user->followers()->attach(User::factory()->count(3)->create());
    $user->following()->attach(User::factory()->count(2)->create());

    $this->actingAs($user)
        ->get('/profile')
        ->assertSee('Vlad Basargin')
        ->assertSee('@vladbasargin')
        ->assertSee(__('profile.title_architect'))
        ->assertSee('Люблю спорить о футболе', escape: false)
        ->assertSee('2,450')
        ->assertSeeInOrder(['3', __('profile.subscribers'), '2', __('profile.following')]);
});

test('falls back to @user{id} when username is null', function () {
    $user = User::factory()->create(['username' => null]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertSee('@user'.$user->id);
});

test('edit button links to profile settings route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile')
        ->assertSee(route('profile.settings'), escape: false);
});

test('activity tab is active by default', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->assertSet('tab', 'activity');
});

test('tab is switchable via url', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->withQueryParams(['tab' => 'comments'])
        ->test(ProfilePage::class)
        ->assertSet('tab', 'comments');
});

test('invalid tab param falls back to activity', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->withQueryParams(['tab' => 'garbage'])
        ->test(ProfilePage::class)
        ->assertSet('tab', 'activity');
});

test('creation tab shows coming soon', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile?tab=creation')
        ->assertSee(__('profile.coming_soon'));
});

test('activity tab lists user battles with win/lose/active badges', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    // One active battle with a vote from the user
    $activeBattle = Battle::factory()->create([
        'title' => 'Alpha vs Beta',
        'status' => Battle::STATUS_ACTIVE,
        'side_a_label' => 'Alpha',
        'side_b_label' => 'Beta',
    ]);
    Vote::factory()->create([
        'user_id' => $user->id,
        'battle_id' => $activeBattle->id,
        'side' => Battle::SIDE_A,
        'amount' => 500,
        'payout' => null,
    ]);

    // One settled battle where the user won
    $wonBattle = Battle::factory()->create([
        'title' => 'Gamma vs Delta',
        'status' => Battle::STATUS_SETTLED,
        'winning_side' => Battle::SIDE_A,
        'settled_at' => now(),
        'side_a_label' => 'Gamma',
        'side_b_label' => 'Delta',
    ]);
    Vote::factory()->create([
        'user_id' => $user->id,
        'battle_id' => $wonBattle->id,
        'side' => Battle::SIDE_A,
        'amount' => 300,
        'payout' => 800,
    ]);

    // Noise: a vote from another user must not appear
    $noiseBattle = Battle::factory()->create(['title' => 'NOISE vs NOISE']);
    Vote::factory()->create([
        'user_id' => $other->id,
        'battle_id' => $noiseBattle->id,
    ]);

    $response = $this->actingAs($user)->get('/profile?tab=activity');

    $response->assertSee('Alpha')
        ->assertSee('Beta')
        ->assertSee('Gamma')
        ->assertSee('Delta')
        ->assertSee(__('profile.activity_badge_active'))
        ->assertSee(__('profile.activity_badge_win'))
        ->assertDontSee('NOISE vs NOISE');
});

test('activity tab aggregates votes per battle and shows selected-side stake', function () {
    $user = User::factory()->create();

    $activeBattle = Battle::factory()->create([
        'title' => 'BTC vs Stocks',
        'status' => Battle::STATUS_ACTIVE,
        'side_a_label' => 'BTC',
        'side_b_label' => 'Stocks',
    ]);
    Vote::factory()->create([
        'user_id' => $user->id,
        'battle_id' => $activeBattle->id,
        'side' => Battle::SIDE_A,
        'amount' => 700,
    ]);
    Vote::factory()->create([
        'user_id' => $user->id,
        'battle_id' => $activeBattle->id,
        'side' => Battle::SIDE_B,
        'amount' => 300,
    ]);

    $settledBattle = Battle::factory()->create([
        'title' => 'Max Carter vs Jake Lewis',
        'status' => Battle::STATUS_SETTLED,
        'winning_side' => Battle::SIDE_A,
        'settled_at' => now()->subHours(3),
        'side_a_label' => 'Max Carter',
        'side_b_label' => 'Jake Lewis',
    ]);
    Vote::factory()->create([
        'user_id' => $user->id,
        'battle_id' => $settledBattle->id,
        'side' => Battle::SIDE_A,
        'amount' => 500,
    ]);
    Vote::factory()->create([
        'user_id' => $user->id,
        'battle_id' => $settledBattle->id,
        'side' => Battle::SIDE_B,
        'amount' => 500,
    ]);

    $response = $this->actingAs($user)->get('/profile?tab=activity');

    $response->assertSee('BTC')
        ->assertSee('Stocks')
        ->assertSee(__('profile.activity_badge_active'))
        ->assertSee('Max Carter')
        ->assertSee('Jake Lewis')
        ->assertSee(__('profile.activity_badge_lose'))
        ->assertSee('500 '.__('profile.activity_vrs'))
        ->assertSee('3h ago');

    expect(substr_count($response->getContent(), 'Max Carter'))->toBe(1)
        ->and(substr_count($response->getContent(), 'Jake Lewis'))->toBe(1);
});

test('activity tab shows empty state when user has no votes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile?tab=activity')
        ->assertSee(__('profile.activity_empty'));
});

test('comments tab lists user comments with battle link', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $battle = Battle::factory()->create(['title' => 'Pepsi vs Coke']);

    Comment::factory()->create([
        'user_id' => $user->id,
        'battle_id' => $battle->id,
        'body' => 'My hot take',
    ]);
    Comment::factory()->create([
        'user_id' => $other->id,
        'battle_id' => $battle->id,
        'body' => 'Someone else',
    ]);

    $this->actingAs($user)
        ->get('/profile?tab=comments')
        ->assertSee('My hot take')
        ->assertSee('Pepsi vs Coke')
        ->assertDontSee('Someone else');
});

test('comments tab shows empty state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile?tab=comments')
        ->assertSee(__('profile.comments_empty'));
});

test('referrals tab shows referral url, list and earned total', function () {
    $user = User::factory()->create(['referral_code' => 'TESTCODE']);
    $alice = User::factory()->create([
        'name' => 'Alice',
        'referred_by_id' => $user->id,
    ]);
    Transaction::factory()->create([
        'user_id' => $user->id,
        'type' => Transaction::TYPE_REFERRAL_REWARD,
        'amount' => 42,
    ]);

    $this->actingAs($user)
        ->get('/profile?tab=referrals')
        ->assertSee('?ref=TESTCODE', escape: false)
        ->assertSee('Alice')
        ->assertSee('42');
});

test('referrals tab shows empty state when no referrals', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile?tab=referrals')
        ->assertSee(__('profile.referrals_empty'));
});
