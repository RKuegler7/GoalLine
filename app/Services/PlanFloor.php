<?php

namespace App\Services;

use App\Clock;
use App\GoalCategory;
use App\Models\Goal;
use App\Models\GoalIntake;
use App\Models\RoadmapWeek;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * The plan floor: the minimum planned points each GoalWeek needs for the deadline, worked out by code, never by the
 * AI. A point is about one focused session: a regular task is 1 point, each planned habit day is 1, and the
 * milestone is 2. Plans can be harder than the floor, never easier, so asking the coach for an easy plan can't buy
 * an easy Smashed week. Formulas, per full week:
 *
 *   floor = base for the category
 *           × deadline pressure (a plan of 4 weeks or less ×1.25, up to 12 weeks ×1.0, longer ×0.9)
 *           × progression (fitness builds from ×0.8 to ×1.1 across the plan; other goals stay at ×1.0)
 *           × taper (the last week of a fitness or academic goal ×0.8: race or exam week)
 *           × starting point (fitness: brand new ×0.8, competitive ×1.15)
 *           × health (a reported injury or health condition ×0.85)
 *   then capped at 80% of the hours a week the person said they have (an hour is about a point),
 *   scaled by the stretch's length (a 4-day first week gets 4/7), rounded, and never below 3 (2 for a short week).
 */
class PlanFloor
{
    /**
     * Base minimum points for a full week, by category.
     */
    public const BASE = [
        'academic' => 6,
        'career' => 5,
        'fitness' => 5,
        'creative' => 5,
        'personal' => 4,
        'other' => 4,
    ];

    public const MIN_POINTS = 3;

    public const MIN_POINTS_SHORT_WEEK = 2;

    /**
     * The floor can't ask for more than this share of the hours a week someone said they have.
     */
    public const CAPACITY_SHARE = 0.8;

    /**
     * Most tasks added to raise one week; the points are spread across them.
     */
    public const MAX_RAISE_TASKS = 3;

    public function __construct(private WeekPlanner $weekPlanner) {}

    /**
     * The floor for one stretch of a plan: its 0-based place among the plan's stretches, how many there are, how many
     * weeks the whole plan spans, and how many days this stretch has.
     */
    public static function forStretch(Goal $goal, int $index, int $count, float $planWeeks, int $days): int
    {
        $answers = self::answers($goal);
        $floor = (float) self::BASE[$goal->category->value];

        $floor *= match (true) {
            $planWeeks <= 4 => 1.25,
            $planWeeks <= 12 => 1.0,
            default => 0.9,
        };

        if ($goal->category === GoalCategory::Fitness || $goal->isPhysical()) {
            $floor *= 0.8 + 0.3 * ($count > 1 ? $index / ($count - 1) : 1);
            $floor *= match (self::answerTo($answers, 'experience')) {
                'Brand new' => 0.8,
                'Competitive' => 1.15,
                default => 1.0,
            };
        }

        if ($count > 1 && $index === $count - 1 && in_array($goal->category, [GoalCategory::Fitness, GoalCategory::Academic], true)) {
            $floor *= 0.8;
        }

        if (self::reportsHealthConcern($answers)) {
            $floor *= 0.85;
        }

        if (($hours = self::hoursPerWeek($answers)) !== null) {
            $floor = min($floor, max(self::MIN_POINTS, floor($hours * self::CAPACITY_SHARE)));
        }

        $scaled = round($floor * $days / 7);

        return (int) max($days < 7 ? self::MIN_POINTS_SHORT_WEEK : self::MIN_POINTS, $scaled);
    }

    /**
     * Floors for every stretch of a plan, in order.
     *
     * @param  list<array{starts_on: CarbonInterface, ends_on: CarbonInterface}>  $periods
     * @return list<int>
     */
    public static function forPeriods(Goal $goal, array $periods): array
    {
        if ($periods === []) {
            return [];
        }

        $planWeeks = ($periods[0]['starts_on']->diffInDays(end($periods)['ends_on']) + 1) / 7;

        return array_values(array_map(
            fn (array $period, int $index) => self::forStretch($goal, $index, count($periods), $planWeeks, (int) $period['starts_on']->diffInDays($period['ends_on']) + 1),
            $periods,
            array_keys($periods),
        ));
    }

    /**
     * Set the floor on GoalWeeks that don't have one yet (or on the given ones), from today on.
     *
     * @param  iterable<RoadmapWeek>|null  $weeks  Defaults to every GoalWeek of the goal.
     */
    public function setFloors(Goal $goal, ?iterable $weeks = null): void
    {
        $all = $goal->weeks()->get()->values();
        $floors = self::forPeriods($goal, $all->map(fn (RoadmapWeek $week) => ['starts_on' => $week->starts_on, 'ends_on' => $week->ends_on])->all());
        $targets = collect($weeks ?? $all)->keyBy('id');

        foreach ($all as $index => $week) {
            $targets->get($week->id)?->forceFill(['floor_points' => $floors[$index]])->save();
        }
    }

    /**
     * Raise a GoalWeek to its floor if its plan falls short, with minimum-pace sessions worth the missing points,
     * and a plain note saying so. Returns the points added.
     */
    public function raise(RoadmapWeek $week): int
    {
        $week->load('tasks', 'goal.habits.logs');
        $floor = $week->effectiveFloor();
        $missing = $floor === null ? 0 : (int) ceil($floor - $week->plannedPoints());

        if ($missing <= 0) {
            return 0;
        }

        $count = min(self::MAX_RAISE_TASKS, $missing);
        $position = (int) $week->tasks->max('position');

        foreach (range(0, $count - 1) as $index) {
            $points = intdiv($missing, $count) + ($index < $missing % $count ? 1 : 0);
            $week->tasks()->create(['position' => ++$position, 'description' => Str::limit('Minimum-pace session: '.$week->focus, 250)])
                ->forceFill(['is_floor' => true, 'points' => $points])->save();
        }

        $week->forceFill(['floor_note' => "We raised this week to the minimum pace your deadline needs (+{$missing} ".Str::plural('point', $missing).').'])->save();
        $this->weekPlanner->autoPlan($week->load('tasks'));

        return $missing;
    }

    /**
     * Whether taking these points out of a GoalWeek would put it below its floor.
     */
    public static function wouldDropBelow(RoadmapWeek $week, float $points): bool
    {
        $floor = $week->effectiveFloor();

        return $floor !== null && $week->plannedPoints() - $points < $floor;
    }

    /**
     * Give every active goal's current and upcoming GoalWeeks a floor if they lack one, and raise upcoming weeks that
     * fall short. Safe to run again. Returns how many GoalWeeks got a floor.
     */
    public function backfill(Goal $goal): int
    {
        $missing = $goal->weeks()->whereNull('floor_points')->whereDate('ends_on', '>=', Clock::today()->toDateString())->get();

        if ($missing->isEmpty()) {
            return 0;
        }

        $this->setFloors($goal, $missing);
        $missing->filter(fn (RoadmapWeek $week) => $week->starts_on->gt(Clock::today()))->each(fn (RoadmapWeek $week) => $this->raise($week));

        return $missing->count();
    }

    /**
     * The person's intake answers, keyed by question id.
     *
     * @return array<string, array{id: string, question: string, answer: string|null, value: mixed, status: string}>
     */
    private static function answers(Goal $goal): array
    {
        $intake = $goal->exists ? $goal->intake : null;

        return $intake?->status === GoalIntake::ANSWERED
            ? collect($intake->givenAnswers())->keyBy('id')->all()
            : [];
    }

    private static function answerTo(array $answers, string $id): ?string
    {
        return $answers[$id]['answer'] ?? null;
    }

    /**
     * Hours a week from the intake: an "hours" answer, or minutes a day turned into hours a week.
     */
    private static function hoursPerWeek(array $answers): ?float
    {
        foreach ($answers as $answer) {
            if (! is_numeric($answer['value'] ?? null)) {
                continue;
            }

            $text = Str::lower($answer['question'].' '.$answer['answer']);
            $perDay = str_contains($text, 'a day') || str_contains($text, 'per day') || str_contains($text, 'each day');

            if (str_contains($text, 'hour')) {
                return (float) $answer['value'] * ($perDay ? 7 : 1);
            }

            if (str_contains($text, 'minute')) {
                return (float) $answer['value'] * ($perDay ? 7 : 1) / 60;
            }
        }

        return null;
    }

    /**
     * Whether the person reported an injury or health condition the plan should respect.
     */
    private static function reportsHealthConcern(array $answers): bool
    {
        foreach ($answers as $answer) {
            if (preg_match('/injur|pain|health|medical|condition/i', $answer['question']) === 1
                && ! in_array(Str::lower(trim((string) $answer['answer'], ' .!')), ['', 'no', 'none', 'nope', 'n/a', 'na', 'nothing', 'not really'], true)) {
                return true;
            }
        }

        return false;
    }
}
