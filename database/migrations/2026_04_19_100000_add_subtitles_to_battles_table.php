<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->string('side_a_subtitle')->nullable()->after('side_b_label');
            $table->string('side_b_subtitle')->nullable()->after('side_a_subtitle');
        });
    }

    public function down(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->dropColumn(['side_a_subtitle', 'side_b_subtitle']);
        });
    }
};
