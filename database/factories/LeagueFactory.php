<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<League>
 */
class LeagueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $monday = today()->next('Monday');

        return [
            'commissioner_id' => User::factory(),
            'name' => 'CPA Study Squad',
            'starts_on' => $monday,
            'ends_on' => $monday->copy()->addWeeks(8)->subDay(),
        ];
    }
}
