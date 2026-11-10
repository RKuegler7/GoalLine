<?php

namespace Database\Factories;

use App\Models\ResultCard;
use App\Models\RoadmapWeek;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultCard>
 */
class ResultCardFactory extends Factory
{
    /**
     * Define the model's default state: an In line week with a close win, made by the GoalWeek's owner.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'roadmap_week_id' => RoadmapWeek::factory(),
            'user_id' => fn (array $attributes) => RoadmapWeek::find($attributes['roadmap_week_id'])->goal->user_id,
            'payload' => [
                'v' => 1,
                'name' => 'Sam',
                'initials' => 'SR',
                'weekLabel' => 'Week 3',
                'goal' => 'Run a half marathon',
                'percent' => 97,
                'band' => 'in_line',
                'bandLabel' => 'In line',
                'needsProof' => false,
                'linePoints' => 1,
                'final' => true,
                'match' => ['outcome' => 'close_win', 'label' => 'Close win', 'points' => 2, 'photoFinish' => true, 'bye' => false],
                'weeklyPoints' => 3,
                'onTheLine' => 4,
                'league' => 'CPA Study Squad',
            ],
        ];
    }

    /**
     * With its public link turned on.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => ['public_at' => now()]);
    }
}
