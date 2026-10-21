<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\PlanMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanMaterial>
 */
class PlanMaterialFactory extends Factory
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
            'original_name' => 'becker-study-plan.pdf',
            'path' => 'plan-materials/example.pdf',
            'mime_type' => 'application/pdf',
            'size' => 120_000,
        ];
    }
}
