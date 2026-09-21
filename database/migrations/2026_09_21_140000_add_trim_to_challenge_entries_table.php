<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optional trim chosen in the form; the video job cuts the source to [start, end).
        Schema::table('challenge_entries', function (Blueprint $table): void {
            $table->unsignedInteger('trim_start_ms')->nullable();
            $table->unsignedInteger('trim_end_ms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('challenge_entries', fn (Blueprint $table) => $table->dropColumn(['trim_start_ms', 'trim_end_ms']));
    }
};
