<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\LeagueInvite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeagueInvite>
 */
class LeagueInviteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'league_id' => League::factory(),
            'kind' => LeagueInvite::LINK,
            'expires_at' => now()->addDays(LeagueInvite::DEFAULT_EXPIRY_DAYS),
        ];
    }
}
