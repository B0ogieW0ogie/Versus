<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stored News Feed events that can't be derived from other tables: statuses, achievements,
        // Top-10 moves and official VERSUS news. Challenge/response/vote/result events are derived.
        Schema::create('feed_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 16);
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('challenge_id')->nullable()->constrained('challenges')->cascadeOnDelete();
            $table->foreignId('entry_id')->nullable()->constrained('challenge_entries')->cascadeOnDelete();
            // Dedupe key, e.g. the achievement id: one row per (type, user, key).
            $table->string('key', 64)->nullable();
            $table->string('title', 200)->nullable();
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('published_at')->index();
            $table->timestamps();

            $table->index(['type', 'published_at']);
            $table->unique(['type', 'user_id', 'key']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('status', 140)->nullable();
        });

        Schema::table('challenge_entries', function (Blueprint $table): void {
            // Position in the viewing-feed Top-10 at the last checkpoint; null = outside it.
            $table->unsignedSmallInteger('top_rank')->nullable();
        });

        Schema::table('challenges', function (Blueprint $table): void {
            $table->timestamp('top_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('challenges', fn (Blueprint $table) => $table->dropColumn('top_checked_at'));
        Schema::table('challenge_entries', fn (Blueprint $table) => $table->dropColumn('top_rank'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('status'));
        Schema::dropIfExists('feed_posts');
    }
};
