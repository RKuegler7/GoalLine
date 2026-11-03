<?php

namespace Database\Factories;

use App\Models\Dispute;
use App\Models\RoadmapTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dispute>
 */
class DisputeFactory extends Factory
{
    /**
     * Define the model's default state: an open dispute.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'roadmap_task_id' => RoadmapTask::factory(),
            'disputer_id' => User::factory(),
            'reason' => 'Can you show the run?',
            'status' => Dispute::OPEN,
        ];
    }
}
