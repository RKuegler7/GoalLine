<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * League invites (shareable links, email and username invites), a category for leagues, and whether people can
     * find someone by name.
     */
    public function up(): void
    {
        Schema::create('league_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->string('kind', 20)->default('link');
            $table->string('email')->nullable();
            $table->foreignId('invitee_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['league_id', 'kind']);
        });

        Schema::table('leagues', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->after('description');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('discoverable')->default(true)->after('profile_visibility');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('discoverable');
        });

        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::dropIfExists('league_invites');
    }
};
