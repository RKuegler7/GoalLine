<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When someone finished or skipped the first-run tour. Accounts that already exist count as done, so only new
     * and demo accounts are offered it. Safe to run twice.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'tour_completed_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('tour_completed_at')->nullable()->after('onboarded_at');
        });

        DB::table('users')->update(['tour_completed_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tour_completed_at');
        });
    }
};
