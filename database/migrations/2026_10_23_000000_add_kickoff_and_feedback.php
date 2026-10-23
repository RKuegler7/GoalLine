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
        // When a member hid the Kickoff checklist on their Clubhouse.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('kickoff_dismissed_at')->nullable()->after('onboarded_at');
        });

        // Notes from beta testers, with the page they were on.
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('page', 500)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('kickoff_dismissed_at');
        });
    }
};
