<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->unsignedBigInteger('battle_id')->nullable()->change();
            $table->foreignId('challenge_entry_id')->nullable()->after('battle_id')
                ->constrained('challenge_entries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('challenge_entry_id');
        });
    }
};
