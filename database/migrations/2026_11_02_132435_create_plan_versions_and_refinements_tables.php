<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plan history (a snapshot of the roadmap at each change) and coach refinements waiting for the person's OK.
     */
    public function up(): void
    {
        Schema::create('plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('summary', 500);
            $table->json('snapshot');
            $table->timestamps();

            $table->index(['goal_id', 'id']);
        });

        Schema::create('plan_refinements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('refine');
            $table->string('instruction', 500)->nullable();
            $table->foreignId('plan_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('explanation')->nullable();
            $table->json('proposal')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['goal_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_refinements');
        Schema::dropIfExists('plan_versions');
    }
};
