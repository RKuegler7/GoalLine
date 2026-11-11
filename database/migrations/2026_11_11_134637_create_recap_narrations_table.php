<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The short summary on a finished week's recap, saved once per member and week, with a hash of the numbers it
     * was written from so it's only rewritten if those numbers change.
     */
    public function up(): void
    {
        if (Schema::hasTable('recap_narrations')) {
            return;
        }

        Schema::create('recap_narrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_starts_on');
            $table->string('facts_hash', 64);
            $table->string('source', 20);
            $table->text('body');
            $table->timestamps();

            $table->unique(['user_id', 'week_starts_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recap_narrations');
    }
};
