<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\Habit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Habit>
 */
class HabitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goal_id' => Goal::factory(),
            'title' => 'Run',
            'times_per_week' => 3,
        ];
    }
}
