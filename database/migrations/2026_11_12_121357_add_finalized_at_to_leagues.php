<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a league's season finale ran (awards, the chat post, notifications), so it runs exactly once, even for a
     * season where nobody scored and there are no awards to check for. Leagues that already have awards are marked.
     */
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->timestamp('finalized_at')->nullable()->after('ends_on');
        });

        DB::table('leagues')->whereIn('id', DB::table('user_badges')->whereNotNull('league_id')->select('league_id'))
            ->update(['finalized_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('finalized_at');
        });
    }
};
