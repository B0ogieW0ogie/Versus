<?php

namespace Tests\Feature\Challenges;

use App\Livewire\NotificationBell;
use App\Models\Challenge;
use App\Models\ChallengeEntry;
use App\Models\User;
use App\Notifications\ChallengeResponseReceived;
use App\Notifications\ChallengeResults;
use App\Notifications\EntryProcessingFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChallengeNotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_bell_renders_challenge_notifications_with_links(): void
    {
        $author = User::factory()->create();
        $responder = User::factory()->create(['name' => 'Maya']);
        $challenge = Challenge::factory()->for($author)->create(['title' => 'Kickflip']);
        $entry = ChallengeEntry::factory()->for($challenge)->for($responder)->create();

        $author->notify(new ChallengeResponseReceived($challenge, $entry, $responder));
        $author->notify(new EntryProcessingFailed($challenge, 'reason_too_long'));
        $author->notify(new ChallengeResults($challenge, ChallengeResults::OVER, 'Maya', $entry->id));

        Livewire::actingAs($author)
            ->test(NotificationBell::class)
            ->call('toggle')
            ->assertSee(__('challenges.notif_response_received', ['name' => 'Maya', 'title' => 'Kickflip']))
            ->assertSee(__('challenges.notif_failed', [
                'title' => 'Kickflip',
                'reason' => __('challenges.reason_too_long', ['seconds' => 60]),
            ]))
            ->assertSee(__('challenges.notif_results_over', ['title' => 'Kickflip', 'name' => 'Maya']))
            ->assertSee('/c/'.$challenge->slug.'?entry='.$entry->id, false)
            ->assertSee('/my-challenges', false);
    }
}
