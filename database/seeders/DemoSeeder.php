<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\DemoWorld;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * The seeded demo visitor's sign-in.
     */
    public const EMAIL = 'john.cardinal@demo.goalline.invalid';

    public const PASSWORD = 'password';

    /**
     * Build the demo world (the same one /demo makes) with a known sign-in. Safe to re-run: the last seeded world is
     * replaced, and everything is dated relative to today.
     */
    public function run(DemoWorld $world): void
    {
        User::where('demo_sandbox', DemoWorld::SEEDED_SANDBOX)->get()->each->delete();

        $world->create(DemoWorld::SEEDED_SANDBOX)
            ->forceFill(['email' => self::EMAIL, 'password' => self::PASSWORD])
            ->save();

        $this->command?->info('Demo world ready. Sign in as '.self::EMAIL.' with the password "'.self::PASSWORD.'".');
    }
}
