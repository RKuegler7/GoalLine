<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each uploaded photo keeps its content type from upload time, so serving it never has to ask storage for it.
     * Older rows stay null and fall back to the file's extension.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_mime_type', 50)->nullable()->after('avatar_path');
            $table->string('cover_mime_type', 50)->nullable()->after('cover_path');
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->string('completion_photo_mime_type', 50)->nullable()->after('completion_photo_path');
        });

        Schema::table('highlights', function (Blueprint $table) {
            $table->string('photo_mime_type', 50)->nullable()->after('photo_path');
        });

        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->string('proof_mime_type', 50)->nullable()->after('proof_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_mime_type', 'cover_mime_type']);
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn('completion_photo_mime_type');
        });

        Schema::table('highlights', function (Blueprint $table) {
            $table->dropColumn('photo_mime_type');
        });

        Schema::table('roadmap_tasks', function (Blueprint $table) {
            $table->dropColumn('proof_mime_type');
        });
    }
};
