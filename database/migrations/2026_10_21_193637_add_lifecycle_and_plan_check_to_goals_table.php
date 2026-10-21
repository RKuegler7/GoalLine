<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->timestamp('retired_at')->nullable()->after('completed_at');
            $table->json('pending_changes')->nullable()->after('roadmap_generated_at');
            $table->string('plan_check_status')->default('none')->after('pending_changes');
            $table->string('plan_verdict')->nullable()->after('plan_check_status');
            $table->text('plan_note')->nullable()->after('plan_verdict');
            $table->text('plan_suggestion')->nullable()->after('plan_note');
            $table->timestamp('plan_checked_at')->nullable()->after('plan_suggestion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn([
                'retired_at', 'pending_changes', 'plan_check_status',
                'plan_verdict', 'plan_note', 'plan_suggestion', 'plan_checked_at',
            ]);
        });
    }
};
