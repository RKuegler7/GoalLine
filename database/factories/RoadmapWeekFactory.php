<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\RoadmapWeek;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoadmapWeek>
 */
class RoadmapWeekFactory extends Factory
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
            'position' => 1,
            'starts_on' => today(),
            'ends_on' => today()->addDays(6),
            'focus' => 'Build the foundation',
            'milestone' => 'Finish the first practice set.',
        ];
    }
}
