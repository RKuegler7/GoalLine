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
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('location');
            $table->string('cover_path')->nullable()->after('avatar_path');
            $table->foreignId('featured_goal_id')->nullable()->after('cover_path')->constrained('goals')->nullOnDelete();
            $table->string('profile_visibility')->default('leagues')->after('featured_goal_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('featured_goal_id');
            $table->dropColumn(['timezone', 'cover_path', 'profile_visibility']);
        });
    }
};
