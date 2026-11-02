<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database: a test account and the demo world. Safe to re-run.
     */
    public function run(): void
    {
        User::firstOrCreate(['email' => 'test@example.com'], User::factory()->raw(['name' => 'Test User', 'email' => 'test@example.com']));

        $this->call(DemoSeeder::class);
    }
}
