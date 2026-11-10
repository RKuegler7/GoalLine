<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weekly result cards: a frozen snapshot of what the card shows (computed by the scoring code when it was made),
     * so a shared card never changes or reveals more later. A public link is opt-in (public_at) and revocable.
     */
    public function up(): void
    {
        if (Schema::hasTable('result_cards')) {
            return;
        }

        Schema::create('result_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('roadmap_week_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->json('payload');
            $table->string('content_hash', 40);
            $table->timestamp('public_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'roadmap_week_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_cards');
    }
};
