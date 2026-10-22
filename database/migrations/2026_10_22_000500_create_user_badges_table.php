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
        // Personal badges, and GoalLeague season awards (those carry the league they were won in).
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('badge', 40);
            $table->foreignId('league_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('detail')->nullable();
            $table->timestamp('earned_at');
            $table->timestamps();

            $table->index(['user_id', 'badge']);
            $table->index(['league_id', 'badge']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};
