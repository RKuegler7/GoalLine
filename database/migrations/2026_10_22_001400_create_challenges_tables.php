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
        // Short, shared challenges like "30 days of no sugar": check in each day, climb the leaderboard.
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 80);
            $table->string('description', 500)->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('target_days');
            $table->string('invite_code', 9)->unique();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('challenge_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['challenge_id', 'user_id']);
        });

        Schema::create('challenge_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('checked_on');
            $table->timestamps();

            $table->unique(['challenge_id', 'user_id', 'checked_on']);
            $table->index(['user_id', 'checked_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_check_ins');
        Schema::dropIfExists('challenge_members');
        Schema::dropIfExists('challenges');
    }
};
