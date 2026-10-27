<?php

namespace App\Services;

use Anthropic\Client;
use App\Exceptions\AiLimitReached;
use App\Models\Highlight;
use App\Models\RoadmapTask;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Suggests a short caption for a check-in post. Only the task's words go to Claude, never the photo, and the
 * suggestion just fills the caption box: the member edits it and decides whether to post.
 */
class CaptionWriter
{
    private const SYSTEM = <<<'TEXT'
        You write one short caption for a photo a person is posting to their friends and GoalLeague on GoalLine, a goal-tracking app. The photo shows the work behind a task they just checked off.

        - Write in first person, as them. Warm, a little proud, never boastful or salesy.
        - Celebrate the process (showing up, the reps, the effort), not just results.
        - Under 140 characters. At most one emoji. No hashtags. No quotation marks around it.
        - Reply with the caption only.
        TEXT;

    public function __construct(private Client $client, private AiBudget $budget) {}

    /**
     * A caption suggestion, or null if Claude didn't give a usable one.
     *
     * @throws AiLimitReached when a spend limit is reached
     */
    public function suggest(User $user, RoadmapTask $task): ?string
    {
        $this->budget->ensureAvailable($user);

        $week = $task->week;
        $details = implode("\n", array_filter([
            'Goal: '.$week->goal->title,
            'This GoalWeek: '.$week->focus,
            'Task checked off: '.$task->description,
            $task->result_required && $task->result_value !== null
                ? 'Result: '.$task->formatNumber($task->result_value).' of '.$task->formatNumber($task->result_target).' '.$task->result_unit
                : null,
        ]));

        $message = $this->client->beta->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 400,
            system: self::SYSTEM,
            messages: [['role' => 'user', 'content' => $details]],
            outputConfig: ['effort' => 'low'],
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );

        $this->budget->record($user, 'caption', $message->usage->inputTokens, $message->usage->outputTokens);

        if ($message->stopReason !== 'end_turn') {
            return null;
        }

        $caption = trim((string) collect($message->content)->firstWhere('type', 'text')?->text, " \n\"'");

        return $caption === '' ? null : Str::limit($caption, Highlight::MAX_POST_CAPTION, '');
    }
}
