<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two optional details a member can add when making a league goal their own: a target result ("4:15", "an A")
     * and a few lines of context (a short description plus the "Make it yours" chips). Both feed the intake
     * questions and the plan. Safe to run twice.
     */
    public function up(): void
    {
        if (Schema::hasColumn('goals', 'target_result')) {
            return;
        }

        Schema::table('goals', function (Blueprint $table) {
            $table->string('target_result', 80)->nullable()->after('why');
            $table->string('context', 600)->nullable()->after('target_result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn(['target_result', 'context']);
        });
    }
};
