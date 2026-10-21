<?php

namespace Database\Factories;

use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoadmapTask>
 */
class RoadmapTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'roadmap_week_id' => RoadmapWeek::factory(),
            'position' => 1,
            'description' => 'Review the first chapter.',
        ];
    }
}
