<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One open dispute per person per check-in, enforced by the database: open_key is "disputer:task" while a dispute
     * is open and empty once it's settled, with a unique index (empty values don't clash). Works on MySQL and SQLite.
     */
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->string('open_key', 40)->nullable()->after('status');
        });

        // Existing open disputes: the earliest per person and check-in keeps the key; any duplicates are closed as
        // withdrawn, so the index can be added.
        $seen = [];

        DB::table('disputes')->where('status', 'open')->orderBy('id')->get(['id', 'disputer_id', 'roadmap_task_id'])
            ->each(function (object $dispute) use (&$seen) {
                $key = $dispute->disputer_id.':'.$dispute->roadmap_task_id;

                if (isset($seen[$key])) {
                    DB::table('disputes')->where('id', $dispute->id)->update(['status' => 'withdrawn', 'resolution' => 'withdrawn', 'resolved_at' => now()]);

                    return;
                }

                $seen[$key] = true;
                DB::table('disputes')->where('id', $dispute->id)->update(['open_key' => $key]);
            });

        Schema::table('disputes', function (Blueprint $table) {
            $table->unique('open_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropUnique(['open_key']);
            $table->dropColumn('open_key');
        });
    }
};
