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
        Schema::create('league_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('week');
            $table->string('result')->nullable();
            $table->unsignedTinyInteger('result_points');
            $table->foreignId('opponent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('vs_median')->default(false);
            $table->string('outcome');
            $table->unsignedTinyInteger('matchup_points');
            $table->timestamps();

            $table->unique(['league_id', 'user_id', 'week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('league_results');
    }
};
