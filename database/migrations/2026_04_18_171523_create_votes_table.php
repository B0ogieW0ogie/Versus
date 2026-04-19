<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('battle_id')->constrained('battles')->cascadeOnDelete();
            $table->char('side', 1);
            $table->decimal('amount', 20, 2);
            $table->decimal('weight', 20, 2);
            $table->foreignId('referrer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('payout', 20, 2)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'battle_id']);
            $table->index(['battle_id', 'side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
