<?php

namespace App\Services;

use App\Clock;
use App\Models\Goal;
use App\Models\Habit;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * A member's GoalLine plan as an iCalendar feed for Google, Apple, or Outlook Calendar: planned tasks on their
 * days, recurring habits, each GoalWeek's milestone, and each goal's target date. All-day events, so they sit
 * on the right day in any time zone.
 */
class CalendarFeed
{
    /**
     * How far back the feed reaches, so recent history stays visible without the feed growing forever.
     */
    public const HISTORY_DAYS = 28;

    private const DAY_CODES = [1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA', 7 => 'SU'];

    public function __construct(public User $user) {}

    public function render(): string
    {
        return Clock::for($this->user, function () {
            $since = Clock::today()->subDays(self::HISTORY_DAYS);
            $goals = $this->user->goals()->active()->with(['weeks.tasks', 'habits'])->get();
            $events = [];

            foreach ($goals as $goal) {
                $events[] = $this->event("goal-{$goal->id}", $goal->target_date, "GoalLine: {$goal->title}", 'Your target date. Cross the line!');

                foreach ($goal->weeks->filter(fn (RoadmapWeek $week) => $week->ends_on->gte($since)) as $week) {
                    $events[] = $this->event(
                        "milestone-{$week->id}",
                        $week->ends_on,
                        ($week->milestone_hit_at ? '✓ ' : '')."Milestone: {$week->milestone}",
                        "{$goal->title} · {$week->label()}: {$week->focus}",
                    );

                    foreach ($week->tasks->filter(fn (RoadmapTask $task) => $task->planned_on !== null) as $task) {
                        $events[] = $this->event(
                            "task-{$task->id}",
                            $task->planned_on,
                            ($task->completed_at ? '✓ ' : '').$task->description,
                            "{$goal->title} · {$week->label()}",
                        );
                    }
                }

                foreach ($goal->habits->whereNull('archived_at') as $habit) {
                    $events[] = $this->habitEvent($goal, $habit);
                }
            }

            return implode("\r\n", [
                'BEGIN:VCALENDAR',
                'VERSION:2.0',
                'PRODID:-//GoalLine//Gameplan//EN',
                'CALSCALE:GREGORIAN',
                'METHOD:PUBLISH',
                $this->line('X-WR-CALNAME', 'GoalLine'),
                $this->line('X-WR-CALDESC', 'Your GoalLine gameplan: tasks, habits, milestones, and target dates.'),
                'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
                'X-PUBLISHED-TTL:PT1H',
                ...array_merge(...$events),
                'END:VCALENDAR',
            ])."\r\n";
        });
    }

    /**
     * A recurring habit: on its preferred days, or as a weekly Monday reminder of its target.
     *
     * @return list<string>
     */
    private function habitEvent(Goal $goal, Habit $habit): array
    {
        $start = Clock::local($habit->created_at)->startOfDay();
        $until = 'UNTIL='.$goal->target_date->format('Ymd');

        if ($habit->days && $habit->times_per_week < 7) {
            $days = collect($habit->days)->sort()->map(fn (int $day) => self::DAY_CODES[$day])->implode(',');
            $first = $start->copy();

            while (! in_array($first->dayOfWeekIso, $habit->days, true)) {
                $first->addDay();
            }

            return $this->event("habit-{$habit->id}", $first, $habit->title, "{$goal->title} · {$habit->cadence()}", "RRULE:FREQ=WEEKLY;BYDAY={$days};{$until}");
        }

        if ($habit->times_per_week >= 7) {
            return $this->event("habit-{$habit->id}", $start, $habit->title, "{$goal->title} · Daily", "RRULE:FREQ=DAILY;{$until}");
        }

        $monday = $start->copy()->startOfWeek(CarbonInterface::MONDAY);

        return $this->event(
            "habit-{$habit->id}",
            $monday,
            "{$habit->title} ({$habit->times_per_week}× this week)",
            "{$goal->title} · Log it in GoalLine each time you do it.",
            "RRULE:FREQ=WEEKLY;BYDAY=MO;{$until}",
        );
    }

    /**
     * One all-day event.
     *
     * @return list<string>
     */
    private function event(string $uid, CarbonInterface $day, string $summary, string $description, ?string $rule = null): array
    {
        return array_values(array_filter([
            'BEGIN:VEVENT',
            "UID:{$uid}@goalline",
            'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$day->format('Ymd'),
            'DTEND;VALUE=DATE:'.$day->copy()->addDay()->format('Ymd'),
            $rule,
            $this->line('SUMMARY', $summary),
            $this->line('DESCRIPTION', $description),
            $this->line('URL', route('dashboard')),
            'TRANSP:TRANSPARENT',
            'END:VEVENT',
        ]));
    }

    /**
     * A property line with its value escaped, folded at 75 octets as the format requires.
     */
    private function line(string $name, string $value): string
    {
        $escaped = $name === 'URL' ? $value : str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $value);
        $line = "{$name}:{$escaped}";
        $folded = '';

        // 75 octets on the first line; continuation lines start with a space, leaving 74.
        for ($limit = 75; strlen($line) > $limit; $limit = 74) {
            $cut = $limit;

            // Never split a multibyte character.
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }

            $folded .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
        }

        return $folded.$line;
    }
}
