<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('balance', 20, 2)->default(0)->after('password');
            $table->string('referral_code', 16)->unique()->nullable()->after('balance');
            $table->foreignId('referred_by_id')->nullable()->after('referral_code')
                ->constrained('users')->nullOnDelete();
            $table->boolean('is_admin')->default(false)->after('referred_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by_id']);
            $table->dropColumn(['balance', 'referral_code', 'referred_by_id', 'is_admin']);
        });
    }
};
