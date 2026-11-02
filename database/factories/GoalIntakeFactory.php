<?php

namespace Database\Factories;

use App\GoalCategory;
use App\Models\Goal;
use App\Models\GoalIntake;
use App\Services\IntakeQuestions;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoalIntake>
 */
class GoalIntakeFactory extends Factory
{
    /**
     * Define the model's default state: the built-in questions, ready to answer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goal_id' => Goal::factory(),
            'status' => GoalIntake::READY,
            'source' => GoalIntake::SOURCE_FALLBACK,
            'questions' => IntakeQuestions::fallback(GoalCategory::Fitness, true),
        ];
    }

    /**
     * The coach is still writing the questions.
     */
    public function generating(): static
    {
        return $this->state(fn () => ['status' => GoalIntake::GENERATING, 'source' => null, 'questions' => null]);
    }
}
