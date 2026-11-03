<?php

namespace App\Models;

use App\Jobs\GenerateIntakeQuestions;
use App\Services\AiBudget;
use App\Services\DemoCoach;
use App\Services\IntakeQuestions;
use Database\Factories\GoalIntakeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The questions GoalLine asks about a goal before building its roadmap, like a coach's first conversation, and the
 * person's answers.
 */
class GoalIntake extends Model
{
    /** @use HasFactory<GoalIntakeFactory> */
    use HasFactory;

    /**
     * The coach is writing the questions.
     */
    public const GENERATING = 'generating';

    /**
     * The questions are ready to answer.
     */
    public const READY = 'ready';

    /**
     * The person answered (some questions may be skipped or "not sure").
     */
    public const ANSWERED = 'answered';

    /**
     * The person skipped the questions.
     */
    public const SKIPPED = 'skipped';

    /**
     * Questions written by the coach for this goal.
     */
    public const SOURCE_AI = 'ai';

    /**
     * Built-in questions for the goal's category, used when the coach isn't available.
     */
    public const SOURCE_FALLBACK = 'fallback';

    /**
     * How long to wait for the coach's questions before using the built-in ones.
     */
    public const WAIT_SECONDS = 45;

    /**
     * Default values for new intakes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::GENERATING,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'answers' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Start the questions for a goal: the coach writes them in the background, or the built-in ones are used right
     * away when the coach isn't available (no API key, or an AI cap reached).
     */
    public static function begin(Goal $goal): self
    {
        $intake = $goal->intake()->create();
        $goal->setRelation('intake', $intake);

        if (blank(config('services.anthropic.key')) || DemoCoach::applies($goal->user) || app(AiBudget::class)->blockedReason($goal->user) !== null) {
            $intake->useFallback();
        } else {
            GenerateIntakeQuestions::dispatch($intake);
        }

        return $intake;
    }

    /**
     * The goal these questions are about.
     *
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * Whether the person hasn't finished with the questions yet, so the roadmap waits for them.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::GENERATING, self::READY], true);
    }

    /**
     * Whether the coach is still writing the questions and there's still time to wait for them.
     */
    public function isGenerating(): bool
    {
        return $this->status === self::GENERATING
            && $this->created_at?->gt(now()->subSeconds(self::WAIT_SECONDS));
    }

    /**
     * Save the coach's questions, unless the built-in ones are already in use.
     *
     * @param  list<array<string, mixed>>  $questions
     */
    public function useQuestions(array $questions, string $source): void
    {
        if ($this->status !== self::GENERATING) {
            return;
        }

        $this->forceFill(['questions' => $questions, 'source' => $source, 'status' => self::READY])->save();
    }

    /**
     * Fall back to the built-in questions for the goal's category, so the flow never waits on the coach.
     */
    public function useFallback(): void
    {
        $this->useQuestions(IntakeQuestions::fallback($this->goal->category, $this->goal->isPhysical()), self::SOURCE_FALLBACK);
    }

    /**
     * Save the person's answers.
     *
     * @param  list<array{id: string, question: string, answer: string|null, status: string}>  $answers
     */
    public function answer(array $answers): void
    {
        $this->forceFill(['answers' => $answers, 'status' => self::ANSWERED, 'completed_at' => now()])->save();
    }

    /**
     * Skip the questions; the roadmap is built from the goal alone.
     */
    public function skip(): void
    {
        if ($this->isOpen()) {
            $this->forceFill(['status' => self::SKIPPED, 'completed_at' => now()])->save();
        }
    }

    /**
     * Answers the person actually gave (not skipped or "not sure").
     *
     * @return list<array{id: string, question: string, answer: string|null, status: string}>
     */
    public function givenAnswers(): array
    {
        return array_values(array_filter($this->answers ?? [], fn (array $answer) => $answer['status'] === IntakeQuestions::ANSWERED));
    }
}
