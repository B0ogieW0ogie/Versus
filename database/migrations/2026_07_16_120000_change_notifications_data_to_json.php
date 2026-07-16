<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Laravel's stock notifications table stores `data` as text, and nothing in
     * the framework queries into it. SupportCommentAction does: it dedupes
     * "one notification per supporter per comment" on data->comment_id and
     * data->actor_id. Postgres has no ->> operator for a text column, so that
     * query raises SQLSTATE[42883] and — because the send is best-effort — the
     * notification silently never arrives.
     *
     * SQLite (test DB) applies json_extract to text happily, so this is a
     * Postgres-only fix and a Postgres-only guard.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE json USING data::json');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
    }
};
