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
        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->boolean('is_bonus')->default(false)->after('description');
        });

        Schema::table('roadmap_weeks', function (Blueprint $table) {
            $table->timestamp('milestone_hit_at')->nullable()->after('milestone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->dropColumn('is_bonus');
        });

        Schema::table('roadmap_weeks', function (Blueprint $table) {
            $table->dropColumn('milestone_hit_at');
        });
    }
};
