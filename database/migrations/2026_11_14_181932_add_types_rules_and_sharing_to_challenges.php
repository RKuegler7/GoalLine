<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Challenges v2: a type (Daily, Weekly, Abstain, Target) with its rules, strict or flexible mode, Sundays off, a
     * template, Public or Private, an optional member cap, and an optional GoalLeague for side Challenges. Check-ins
     * gain a status (done or slipped, or voided by a dispute), a number, the checklist ticked, and an optional proof
     * photo. In-app invites and disputes get their own small tables (no change to the GoalLeague ones).
     *
     * Existing Challenges become flexible Daily Challenges whose allowed misses keep their old "X of Y days" target,
     * so nobody's standing or finish changes.
     */
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->string('type', 10)->default('daily')->after('description');
            $table->json('rules')->nullable()->after('type');
            $table->string('mode', 10)->default('flexible')->after('rules');
            $table->unsignedTinyInteger('allowed_misses')->default(0)->after('mode');
            $table->boolean('sundays_off')->default(false)->after('allowed_misses');
            $table->string('template', 40)->nullable()->after('sundays_off');
            $table->boolean('is_public')->default(false)->after('template');
            $table->unsignedSmallInteger('max_members')->nullable()->after('is_public');
            $table->foreignId('league_id')->nullable()->after('creator_id')->constrained()->nullOnDelete();

            $table->index(['is_public', 'starts_on']);
        });

        Schema::table('challenge_check_ins', function (Blueprint $table) {
            $table->string('status', 10)->default('done')->after('checked_on');
            $table->decimal('value', 10, 2)->nullable()->after('status');
            $table->json('rules_done')->nullable()->after('value');
            $table->string('proof_path')->nullable()->after('rules_done');
            $table->string('proof_mime_type', 50)->nullable()->after('proof_path');
            $table->timestamp('proof_uploaded_at')->nullable()->after('proof_mime_type');
        });

        Schema::create('challenge_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invitee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();

            $table->unique(['challenge_id', 'invitee_id']);
            $table->index(['invitee_id', 'accepted_at']);
        });

        Schema::create('challenge_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_check_in_id')->constrained()->cascadeOnDelete();
            $table->foreignId('disputer_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 200)->nullable();
            $table->string('status', 10)->default('open');
            $table->string('resolution', 20)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['challenge_check_in_id', 'disputer_id']);
            $table->index(['status']);
        });

        // Existing Challenges: flexible Daily, missing at most what their old target allowed (capped at 10).
        DB::table('challenges')->orderBy('id')->each(function (object $challenge) {
            $length = (int) round((strtotime($challenge->ends_on) - strtotime($challenge->starts_on)) / 86400) + 1;
            DB::table('challenges')->where('id', $challenge->id)->update([
                'allowed_misses' => max(0, min(10, $length - (int) $challenge->target_days)),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_disputes');
        Schema::dropIfExists('challenge_invites');

        Schema::table('challenge_check_ins', function (Blueprint $table) {
            $table->dropColumn(['status', 'value', 'rules_done', 'proof_path', 'proof_mime_type', 'proof_uploaded_at']);
        });

        Schema::table('challenges', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'starts_on']);
            $table->dropConstrainedForeignId('league_id');
            $table->dropColumn(['type', 'rules', 'mode', 'allowed_misses', 'sundays_off', 'template', 'is_public', 'max_members']);
        });
    }
};
