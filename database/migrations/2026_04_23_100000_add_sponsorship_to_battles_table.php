<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->boolean('is_sponsored')->default(false)->index();
            $table->string('sponsor_handle')->nullable();
        });

        // Promote a demo battle if the seed data is present (skipped in tests).
        if (! app()->environment('testing')) {
            DB::table('battles')
                ->where('slug', 'messi-vs-ronaldo')
                ->update(['is_sponsored' => true, 'sponsor_handle' => '@brand']);
        }

        Schema::table('battles', function (Blueprint $table) {
            $table->dropIndex('battles_is_featured_index');
            $table->dropColumn('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->index();
        });

        Schema::table('battles', function (Blueprint $table) {
            $table->dropColumn(['is_sponsored', 'sponsor_handle']);
        });
    }
};
