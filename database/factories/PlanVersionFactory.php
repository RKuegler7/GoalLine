<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\PlanVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanVersion>
 */
class PlanVersionFactory extends Factory
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
            'kind' => PlanVersion::EDITED,
            'summary' => 'Changed a task',
            'snapshot' => [],
        ];
    }
}
