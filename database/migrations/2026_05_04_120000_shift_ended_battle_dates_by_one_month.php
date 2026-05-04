<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pushes opens_at / closes_at / settled_at one month forward for every battle
     * whose voting window has already passed (closes_at < now).
     *
     * Dev/local helper: keeps seeded "active" battles on the homepage timeline after
     * real time catches up. Does not reopen settled battles (ledger); use make fresh
     * if the DB has only settled rows and you need a full demo reset.
     */
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        DB::table('battles')
            ->whereNotNull('closes_at')
            ->where('closes_at', '<', now())
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $closesAt = Carbon::parse($row->closes_at)->addMonth();

                    DB::table('battles')->where('id', $row->id)->update([
                        'opens_at' => $row->opens_at !== null
                            ? Carbon::parse($row->opens_at)->addMonth()
                            : null,
                        'closes_at' => $closesAt,
                        'settled_at' => $row->settled_at !== null
                            ? Carbon::parse($row->settled_at)->addMonth()
                            : null,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * Not safely reversible (battles may have been edited since).
     */
    public function down(): void {}
};
