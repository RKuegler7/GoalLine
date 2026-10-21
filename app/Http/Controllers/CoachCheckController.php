<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class CoachCheckController extends Controller
{
    /**
     * Make the single task edit the coach recommended, then let the coach re-check.
     */
    public function applyFix(Goal $goal): RedirectResponse
    {
        $fix = $goal->plan_fix;
        $week = $fix ? $goal->weeks()->where('position', $fix['stretch'])->first() : null;
        $label = $week?->label($goal->weeksPerStretch());

        $change = match (true) {
            $week === null || ! $week->isEditable() => null,
            $fix['action'] === 'add_task' => $this->addTask($week, $fix['new_text'], $label),
            $fix['action'] === 'remove_task' => $this->removeTask($week, $fix['task'], $label),
            $fix['action'] === 'reword_task' => $this->rewordTask($week, $fix['task'], $fix['new_text'], $label),
            default => null,
        };

        if ($change === null) {
            return redirect()->route('goals.show', $goal)
                ->with('status', 'That fix no longer matches your roadmap. Make the change yourself, or keep your plan.');
        }

        $goal->forceFill(['plan_fix' => null])->save();
        $goal->recordRoadmapChange("Applied the coach's fix: {$change}");

        return redirect()->to(route('goals.show', $goal).'#goalweek-'.$week->position)
            ->with('status', 'Fix applied. Your coach will re-check your plan in a moment.');
    }

    /**
     * Keep the plan as it is, despite the coach's warning.
     */
    public function dismiss(Goal $goal): RedirectResponse
    {
        $goal->forceFill(['plan_dismissed_at' => now()])->save();

        return redirect()->route('goals.show', $goal)->with('status', 'Got it. Your plan stays as you set it.');
    }

    private function addTask(RoadmapWeek $week, string $text, string $label): string
    {
        $week->tasks()->create(['position' => ($week->tasks()->max('position') ?? 0) + 1, 'description' => $text]);

        return "added \"{$text}\" to {$label}";
    }

    private function removeTask(RoadmapWeek $week, string $text, string $label): ?string
    {
        $task = $this->findTask($week, $text);
        $task?->delete();

        return $task ? "removed \"{$task->description}\" from {$label}" : null;
    }

    private function rewordTask(RoadmapWeek $week, string $text, string $newText, string $label): ?string
    {
        $task = $this->findTask($week, $text);
        $task?->update(['description' => $newText]);

        return $task ? "changed \"{$text}\" to \"{$newText}\" in {$label}" : null;
    }

    /**
     * Find the task the coach named, ignoring case and surrounding punctuation.
     */
    private function findTask(RoadmapWeek $week, string $text): ?RoadmapTask
    {
        $normalize = fn (string $value) => Str::of($value)->lower()->trim(" \t\n\r\0\x0B.\"'")->toString();

        return $week->tasks()->get()->first(fn (RoadmapTask $task) => $normalize($task->description) === $normalize($text));
    }
}
