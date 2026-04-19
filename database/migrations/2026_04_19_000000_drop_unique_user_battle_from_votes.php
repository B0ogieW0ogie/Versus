<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'battle_id']);
            $table->index(['user_id', 'battle_id']);
        });
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'battle_id']);
            $table->unique(['user_id', 'battle_id']);
        });
    }
};
