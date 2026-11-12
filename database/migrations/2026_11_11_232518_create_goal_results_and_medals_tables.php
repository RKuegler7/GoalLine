<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Goal Day check-ins (self-reported, never scored) and medals (computed by code).
     */
    public function up(): void
    {
        Schema::create('goal_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The league the goal was played in, if any, so league mates can see a shared result.
            $table->foreignId('league_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome', 12);
            $table->boolean('finished_early')->default(false);
            $table->string('actual_result', 80)->nullable();
            $table->string('hardest_part', 280)->nullable();
            $table->string('helped_most', 280)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_mime_type', 50)->nullable();
            $table->boolean('shared')->default(true);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('answered_at');
            // Up to three next-goal ideas from the Goal Recommender (validated AI, or null while the templates show).
            $table->json('recommendations')->nullable();
            // "Share your season": an opt-in, revocable public link to the season card.
            $table->string('share_token', 64)->nullable()->unique();
            $table->timestamp('public_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['league_id', 'shared']);
        });

        Schema::create('medals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('tier', 20)->nullable();
            $table->foreignId('league_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('goal_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('season')->default(1);
            // One medal of a type per person per league (or goal): awarding again is a no-op.
            $table->string('award_key', 120)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->index(['user_id', 'awarded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medals');
        Schema::dropIfExists('goal_results');
    }
};
