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
        // Private beta invite codes. A code can be single-use or shared by a small group.
        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('note')->nullable();
            $table->unsignedSmallInteger('max_uses')->default(1);
            $table->unsignedSmallInteger('uses')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('invite_id')->nullable()->after('calendar_token')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invite_id');
        });

        Schema::dropIfExists('invites');
    }
};
