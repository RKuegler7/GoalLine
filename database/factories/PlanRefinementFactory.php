<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\PlanRefinement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanRefinement>
 */
class PlanRefinementFactory extends Factory
{
    /**
     * Define the model's default state: a request still with the coach.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goal_id' => Goal::factory(),
            'kind' => PlanRefinement::REFINE,
            'instruction' => 'Make next week lighter, I have finals.',
            'status' => PlanRefinement::PENDING,
        ];
    }
}
