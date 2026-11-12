<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profile identity ("Why I'm here" and up to five interest tags) and who can see each goal on a profile:
     * everyone, friends and league mates (the default), or only the owner.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('why_here', 80)->nullable()->after('bio');
            $table->json('interests')->nullable()->after('why_here');
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->string('visibility', 24)->default('friends_and_leagues')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['why_here', 'interests']);
        });
    }
};
