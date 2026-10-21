<?php

namespace Database\Factories;

use App\GoalCategory;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->randomElement([
                'Pass the CPA exam',
                'Run a half marathon',
                'Finish my thesis draft',
                'Land a product design role',
            ]),
            'category' => fake()->randomElement(GoalCategory::cases()),
            'why' => fake()->sentence(),
            'target_date' => today()->addMonths(3),
        ];
    }
}
