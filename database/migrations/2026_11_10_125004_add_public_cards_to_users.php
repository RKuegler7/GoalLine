<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A member can turn public result-card links off entirely; every link they made then stops working.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'public_cards')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('public_cards')->default(true)->after('discoverable');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('public_cards');
        });
    }
};
