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
            $table->string('roadmap_status')->default('none')->after('status');
            $table->text('roadmap_summary')->nullable()->after('roadmap_status');
            $table->timestamp('roadmap_generated_at')->nullable()->after('roadmap_summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn(['roadmap_status', 'roadmap_summary', 'roadmap_generated_at']);
        });
    }
};
