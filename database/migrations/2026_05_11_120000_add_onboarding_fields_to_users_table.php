<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_first_visit')->default(false)->after('balance');
            $table->unsignedTinyInteger('onboarding_step')->nullable()->after('is_first_visit');
        });

        DB::table('users')->update([
            'onboarding_step' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_first_visit', 'onboarding_step']);
        });
    }
};
