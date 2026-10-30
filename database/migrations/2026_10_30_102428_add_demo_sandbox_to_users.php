<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demo visitors and their pretend friends share a sandbox ID, which keeps them apart from real members.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('demo_sandbox', 36)->nullable()->after('discoverable')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['demo_sandbox']);
            $table->dropColumn('demo_sandbox');
        });
    }
};
