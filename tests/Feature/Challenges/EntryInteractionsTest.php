<?php

namespace Tests\Feature\Challenges;

use App\Actions\Challenges\PostEntryCommentAction;
use App\Actions\Challenges\RecordImpressionsAction;
use App\Actions\Challenges\ToggleEntryLikeAction;
use App\Models\ChallengeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EntryInteractionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_like_toggles_on_and_off(): void
    {
        $entry = ChallengeEntry::factory()->create();
        $user = User::factory()->create();

        $this->assertSame(['liked' => true, 'likes_count' => 1], app(ToggleEntryLikeAction::class)($user, $entry));
        $this->assertSame(['liked' => false, 'likes_count' => 0], app(ToggleEntryLikeAction::class)($user, $entry));
    }

    public function test_cannot_like_processing_entry(): void
    {
        $entry = ChallengeEntry::factory()->processing()->create();

        $this->expectException(ValidationException::class);
        app(ToggleEntryLikeAction::class)(User::factory()->create(), $entry);
    }

    public function test_impressions_are_deduped_capped_and_ready_only(): void
    {
        $ready = ChallengeEntry::factory()->create();
        $processing = ChallengeEntry::factory()->processing()->create();

        app(RecordImpressionsAction::class)([$ready->id, (string) $ready->id, $processing->id, 'junk']);

        $this->assertSame(1, $ready->fresh()->impressions_count);
        $this->assertSame(0, $processing->fresh()->impressions_count);
    }

    public function test_comment_is_stored_against_the_entry_without_a_battle(): void
    {
        $entry = ChallengeEntry::factory()->create();
        $user = User::factory()->create();

        $comment = app(PostEntryCommentAction::class)($user, $entry, '  nice kickflip ');

        $this->assertSame('nice kickflip', $comment->body);
        $this->assertNull($comment->battle_id);
        $this->assertSame($entry->id, $comment->challenge_entry_id);
    }

    public function test_blank_comment_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(PostEntryCommentAction::class)(User::factory()->create(), ChallengeEntry::factory()->create(), '   ');
    }

    public function test_profile_page_ignores_entry_comments(): void
    {
        $user = User::factory()->create();
        app(PostEntryCommentAction::class)($user, ChallengeEntry::factory()->create(), 'video comment');

        $this->actingAs($user)->get(route('profile.edit', ['tab' => 'comments']))->assertOk();
    }
}
