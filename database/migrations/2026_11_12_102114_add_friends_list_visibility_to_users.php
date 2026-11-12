<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who can see someone's friends list: friends and league mates (default), friends only, or only them.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('friends_list_visibility', 24)->default('friends_and_leagues')->after('discoverable');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('friends_list_visibility');
        });
    }
};
