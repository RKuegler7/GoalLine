<?php

namespace Database\Factories;

use App\HighlightKind;
use App\Models\Highlight;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Highlight>
 */
class HighlightFactory extends Factory
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
            'kind' => HighlightKind::GoalCompleted,
            'headline' => 'Run a half marathon',
            'detail' => 'Completed October 18, 2026',
            'caption' => 'Hardest thing I have trained for.',
        ];
    }
}
