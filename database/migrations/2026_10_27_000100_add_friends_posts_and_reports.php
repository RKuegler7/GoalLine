<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Friends replace follows: a request, then an accepted friendship.
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('addressee_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['requester_id', 'addressee_id']);
            $table->index(['addressee_id', 'status']);
        });

        // People who followed each other become friends; a one-way follow becomes a request they can accept.
        $follows = DB::table('follows')->get(['follower_id', 'followed_id', 'created_at']);
        $pairs = $follows->mapWithKeys(fn ($follow) => ["{$follow->follower_id}-{$follow->followed_id}" => $follow]);
        $done = [];

        foreach ($follows as $follow) {
            $key = min($follow->follower_id, $follow->followed_id).'-'.max($follow->follower_id, $follow->followed_id);

            if (isset($done[$key])) {
                continue;
            }

            $done[$key] = true;
            $mutual = $pairs->has("{$follow->followed_id}-{$follow->follower_id}");

            DB::table('friendships')->insert([
                'requester_id' => $follow->follower_id,
                'addressee_id' => $follow->followed_id,
                'status' => $mutual ? 'accepted' : 'pending',
                'accepted_at' => $mutual ? now() : null,
                'created_at' => $follow->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }

        Schema::dropIfExists('follows');

        // Check-in posts: a photo of the work behind a task, shared with the league (and friends, if chosen).
        Schema::table('highlights', function (Blueprint $table) {
            $table->foreignId('roadmap_task_id')->nullable()->after('roadmap_week_id')->constrained()->nullOnDelete();
            $table->foreignId('league_id')->nullable()->after('roadmap_task_id')->constrained()->nullOnDelete();
            $table->string('visibility', 20)->nullable()->after('caption');
            $table->string('location', 60)->nullable()->after('visibility');
        });

        // Reports: hidden from the reporter, recorded for the league owner.
        Schema::create('post_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('highlight_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('league_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('reason', 200)->nullable();
            $table->timestamps();

            $table->unique(['highlight_id', 'reporter_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_reports');

        Schema::table('highlights', function (Blueprint $table) {
            $table->dropConstrainedForeignId('roadmap_task_id');
            $table->dropConstrainedForeignId('league_id');
            $table->dropColumn(['visibility', 'location']);
        });

        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followed_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['follower_id', 'followed_id']);
        });

        foreach (DB::table('friendships')->get() as $friendship) {
            DB::table('follows')->insert(['follower_id' => $friendship->requester_id, 'followed_id' => $friendship->addressee_id, 'created_at' => $friendship->created_at, 'updated_at' => now()]);

            if ($friendship->status === 'accepted') {
                DB::table('follows')->insert(['follower_id' => $friendship->addressee_id, 'followed_id' => $friendship->requester_id, 'created_at' => $friendship->created_at, 'updated_at' => now()]);
            }
        }

        Schema::dropIfExists('friendships');
    }
};
