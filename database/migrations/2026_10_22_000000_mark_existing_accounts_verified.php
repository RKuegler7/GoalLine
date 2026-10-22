<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Accounts created before email verification existed are treated as verified.
     */
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    /**
     * Nothing to undo: verification dates are kept.
     */
    public function down(): void
    {
        //
    }
};
