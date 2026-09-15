<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('rules', 500);
            $table->string('duration', 8);
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 16)->index();
            // No FK: challenge_entries is created below and references challenges.
            $table->unsignedBigInteger('winner_entry_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->double('feed_score')->default(0);
            $table->unsignedInteger('entries_count')->default(0);
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'ends_at']);
            $table->index(['status', 'feed_score']);
        });

        Schema::create('challenge_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_original')->default(false);
            $table->string('status', 16);
            $table->string('source_path')->nullable();
            $table->string('video_path')->nullable();
            $table->string('poster_path')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('failure_reason', 64)->nullable();
            $table->unsignedInteger('votes_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('impressions_count')->default(0);
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['challenge_id', 'user_id']);
            $table->index(['challenge_id', 'status', 'votes_count']);
        });

        Schema::create('challenge_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignId('entry_id')->constrained('challenge_entries')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'challenge_id']);
            $table->index(['challenge_id', 'entry_id']);
        });

        Schema::create('challenge_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->timestamp('accepted_at');

            $table->unique(['user_id', 'challenge_id']);
        });

        Schema::create('entry_likes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('entry_id')->constrained('challenge_entries')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'entry_id']);
        });

        Schema::create('challenge_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->timestamp('viewed_at');

            $table->unique(['user_id', 'challenge_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('swipe_hint_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('swipe_hint_seen_at');
        });
        Schema::dropIfExists('challenge_views');
        Schema::dropIfExists('entry_likes');
        Schema::dropIfExists('challenge_participants');
        Schema::dropIfExists('challenge_votes');
        Schema::dropIfExists('challenge_entries');
        Schema::dropIfExists('challenges');
    }
};
