<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soft voids for check-ins made before kickoff (or on a week that hadn't started): the row stays for history, with
     * what it was and why it no longer counts.
     */
    public function up(): void
    {
        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('proof_uploaded_at');
            $table->string('void_reason', 40)->nullable()->after('voided_at');
            $table->timestamp('voided_completed_at')->nullable()->after('void_reason');
            $table->string('voided_proof_path')->nullable()->after('voided_completed_at');
        });

        Schema::table('roadmap_weeks', function (Blueprint $table) {
            $table->timestamp('milestone_voided_at')->nullable()->after('milestone_hit_at');
        });

        Schema::table('habit_logs', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('habit_logs', function (Blueprint $table) {
            $table->dropColumn(['voided_at', 'void_reason']);
        });

        Schema::table('roadmap_weeks', function (Blueprint $table) {
            $table->dropColumn('milestone_voided_at');
        });

        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->dropColumn(['voided_at', 'void_reason', 'voided_completed_at', 'voided_proof_path']);
        });
    }
};
