<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('battle_id')->constrained('comments')->cascadeOnDelete();
            $table->foreignId('reply_to_user_id')->nullable()->after('parent_id')->constrained('users')->nullOnDelete();

            $table->index(['battle_id', 'parent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['reply_to_user_id']);
            $table->dropIndex(['battle_id', 'parent_id', 'created_at']);
            $table->dropColumn(['parent_id', 'reply_to_user_id']);
        });
    }
};
