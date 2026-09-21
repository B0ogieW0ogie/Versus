<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table): void {
            // Nullable: challenges created before categories existed have none.
            $table->string('category', 24)->nullable()->index();
            $table->string('format', 8)->default('public');
            $table->foreignId('opponent_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('opponent_id');
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'format']);
        });
    }
};
