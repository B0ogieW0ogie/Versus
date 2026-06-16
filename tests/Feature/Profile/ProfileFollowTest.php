<?php

use App\Actions\Users\ToggleFollowAction;
use App\Livewire\ConnectionsPage;
use App\Livewire\ProfilePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('own profile shows the edit button, not a follow button', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertSee(__('profile.edit'))
        ->assertDontSee(__('profile.follow'));
});

test('another user\'s profile shows a follow button instead of edit', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create(['name' => 'Target User']);

    $this->actingAs($viewer)
        ->get(route('profile.show', $target))
        ->assertSee('Target User')
        ->assertSee(__('profile.follow'))
        ->assertDontSee(__('profile.edit'));
});

test('profile shows unfollow when already following', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    $viewer->following()->attach($target);

    $this->actingAs($viewer)
        ->get(route('profile.show', $target))
        ->assertSee(__('profile.unfollow'));
});

test('toggleFollow follows then unfollows a target', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();

    $component = Livewire::actingAs($viewer)->test(ProfilePage::class, ['user' => $target]);

    $component->call('toggleFollow');
    expect($viewer->fresh()->isFollowing($target))->toBeTrue();

    $component->call('toggleFollow');
    expect($viewer->fresh()->isFollowing($target))->toBeFalse();
});

test('toggleFollow on own profile is a no-op', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->call('toggleFollow');

    expect($user->fresh()->following()->count())->toBe(0);
});

test('follower and following counts reflect real relationships', function () {
    $user = User::factory()->create();
    $user->followers()->attach(User::factory()->count(4)->create());
    $user->following()->attach(User::factory()->count(2)->create());

    Livewire::actingAs($user)
        ->test(ProfilePage::class)
        ->assertViewHas('followersCount', 4)
        ->assertViewHas('followingCount', 2);
});

test('referrals tab is hidden on another user\'s profile', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('profile.show', $target).'?tab=referrals')
        ->assertDontSee(__('profile.referrals_link_heading'));
});

test('subscribers list shows the users who follow the profile', function () {
    $target = User::factory()->create();
    $follower = User::factory()->create(['name' => 'Follower One']);
    $target->followers()->attach($follower);

    $this->actingAs($target)
        ->get(route('profile.subscribers', $target))
        ->assertOk()
        ->assertSee('Follower One');
});

test('following list shows the users the profile follows', function () {
    $user = User::factory()->create();
    $followed = User::factory()->create(['name' => 'Followed One']);
    $user->following()->attach($followed);

    $this->actingAs($user)
        ->get(route('profile.following', $user))
        ->assertOk()
        ->assertSee('Followed One');
});

test('connections list toggles follow state for a listed user', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create();
    $listed = User::factory()->create(['name' => 'Listed Person']);
    $target->followers()->attach($listed);

    Livewire::actingAs($viewer)
        ->test(ConnectionsPage::class, ['user' => $target, 'type' => 'subscribers'])
        ->call('toggleFollow', $listed->id);

    expect($viewer->fresh()->isFollowing($listed))->toBeTrue();
});

test('self-follow attempt via action stores nothing', function () {
    $user = User::factory()->create();

    app(ToggleFollowAction::class)->execute($user, $user);

    expect($user->fresh()->following()->count())->toBe(0);
});
