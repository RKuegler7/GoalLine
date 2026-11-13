<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explore: leagues their owner lists publicly (off by default), and muting someone so their public posts never
     * show for you. Public posts use the existing highlights.visibility column (a new "public" value).
     */
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('category');
            $table->index(['is_public', 'ends_on']);
        });

        Schema::create('user_mutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('muted_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'muted_user_id']);
        });

        Schema::table('highlights', function (Blueprint $table) {
            $table->index(['visibility', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('highlights', function (Blueprint $table) {
            $table->dropIndex(['visibility', 'id']);
        });

        Schema::dropIfExists('user_mutes');

        Schema::table('leagues', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'ends_on']);
            $table->dropColumn('is_public');
        });
    }
};
