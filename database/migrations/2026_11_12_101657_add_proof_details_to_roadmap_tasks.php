<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Show your work" details on a check-in: an optional caption, place and time window, with the same visibility
     * as the proof. A photo is still what earns the Verified check.
     */
    public function up(): void
    {
        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->string('proof_caption', 280)->nullable()->after('proof_uploaded_at');
            $table->string('proof_location', 60)->nullable()->after('proof_caption');
            // morning, afternoon, evening, or exact (with the start and end below).
            $table->string('proof_window', 12)->nullable()->after('proof_location');
            $table->timestamp('proof_starts_at')->nullable()->after('proof_window');
            $table->timestamp('proof_ends_at')->nullable()->after('proof_starts_at');
            // proof (opponent and league owner only), league, or league_friends.
            $table->string('proof_visibility', 20)->nullable()->after('proof_ends_at');
            $table->timestamp('details_added_at')->nullable()->after('proof_visibility');
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->dropColumn(['proof_caption', 'proof_location', 'proof_window', 'proof_starts_at', 'proof_ends_at', 'proof_visibility', 'details_added_at']);
        });
    }
};
