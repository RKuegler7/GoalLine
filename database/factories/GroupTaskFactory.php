<?php

namespace Database\Factories;

use App\Models\GroupTask;
use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupTask>
 */
class GroupTaskFactory extends Factory
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
            'proposer_id' => User::factory(),
            'title' => fake()->randomElement(['Read for 20 minutes', 'Walk 10,000 steps', 'No phone after 10 p.m.']),
            'points' => GroupTask::DEFAULT_POINTS,
            'week' => 1,
            'closes_at' => now()->addDay(),
        ];
    }
}
