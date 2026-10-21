<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Give accounts created before usernames existed a handle they can change later.
     */
    public function up(): void
    {
        User::whereNull('username')->each(function (User $user) {
            $user->forceFill(['username' => User::availableUsername($user->name)])->saveQuietly();
        });
    }

    /**
     * Usernames are kept on rollback; the column itself is dropped by the earlier migration.
     */
    public function down(): void
    {
        //
    }
};
