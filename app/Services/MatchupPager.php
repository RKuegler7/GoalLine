<?php

namespace App\Services;

use App\Clock;
use App\LeagueStatus;
use App\Models\Highlight;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The matchup pager, fantasy-football style: one page per matchup in a league week, the viewer's first. Each page
 * has a scoreboard (record, live percent, band, projected band, points if the week ended now, days left) and, where
 * the players would be, each side's tasks for the week grouped by day. All the numbers are worked out here; the
 * view only shows them. Only what the viewer is allowed to see is included: proof photos open for the check-in's
 * owner, their matchup opponent and the league owner, and related posts follow the post's own visibility.
 */
class MatchupPager
{
    public const STATUS_DONE = 'done';

    public const STATUS_TODAY = 'today';

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_MISSED = 'missed';

    public const STATUS_REST = 'rest';

    /**
     * @var array<int, array{wins: int, ties: int, losses: int}>|null
     */
    private ?array $records = null;

    public function __construct(public LeagueTable $table, public int $week, public User $viewer) {}

    /**
     * Every page for the week: matchups, a bye against the league median, or a solo week against the line.
     *
     * @return list<array{kind: string, mine: bool, card: array<string, mixed>, sides: list<array<string, mixed>>, final: bool, readOnly: bool, daysLeft: int, status: string}>
     */
    public function pages(): array
    {
        $league = $this->table->league;
        $final = $this->table->isFinal($this->week);
        $solo = $league->members->count() === 1;
        $board = new MatchupBoard($this->table, $this->week);

        return collect($board->cards($league->memberFor($this->viewer)))
            ->map(function (array $card) use ($final, $solo) {
                $opponentOf = fn (LeagueMember $member) => $card['home']['member']->is($member) ? $card['away']['member'] ?? null : $card['home']['member'];

                return [
                    'kind' => $solo ? 'solo' : ($card['away'] === null ? 'bye' : 'matchup'),
                    'mine' => $card['mine'],
                    'card' => $card,
                    'sides' => collect([$card['home'], $card['away']])
                        ->filter()
                        ->map(fn (array $side) => $this->side($side, $opponentOf($side['member'])))
                        ->values()
                        ->all(),
                    'final' => $final,
                    'readOnly' => $final,
                    'daysLeft' => $this->daysLeft(),
                    'status' => $this->statusLine($final),
                ];
            })
            ->all();
    }

    /**
     * Days left in the league week, today included. Zero once Sunday has passed.
     */
    public function daysLeft(?CarbonInterface $today = null): int
    {
        $today ??= Clock::today();
        $sunday = $this->table->league->weekEndsOn($this->week);

        return $today->gt($sunday) ? 0 : (int) $today->diffInDays($sunday) + 1;
    }

    /**
     * "Ends Sunday 11:59 p.m.", "Ends tonight at 11:59 p.m.", "Check-ins close Monday 11:59 p.m.", or "Final".
     */
    public function statusLine(bool $final, ?CarbonInterface $today = null): string
    {
        $today ??= Clock::today();
        $sunday = $this->table->league->weekEndsOn($this->week);

        return match (true) {
            $final => 'Final',
            $today->isSameDay($sunday) => 'Ends tonight at 11:59 p.m.',
            $today->lt($sunday) => 'Ends '.$sunday->format('l').' 11:59 p.m.',
            default => 'Check-ins close '.$sunday->copy()->addDays(RoadmapWeek::CHECK_IN_GRACE_DAYS)->format('l').' 11:59 p.m., then it goes final',
        };
    }

    /**
     * One side of a page: the scoreboard numbers plus the week's tasks by day.
     *
     * @param  array<string, mixed>  $side  From MatchupBoard::side().
     * @return array<string, mixed>
     */
    public function side(array $side, ?LeagueMember $opponent): array
    {
        /** @var LeagueMember $member */
        $member = $side['member'];
        /** @var RoadmapWeek|null $goalWeek */
        $goalWeek = $side['goalWeek'];
        $canSeeProof = $this->canSeeProof($member, $opponent);

        return $side + [
            'record' => $this->record($member),
            'projection' => $goalWeek ? WeekPace::project($goalWeek) : ['share' => null, 'percent' => null, 'band' => null],
            'days' => $goalWeek ? $this->days($goalWeek, $canSeeProof) : [],
            'anytime' => $goalWeek ? $this->rows($goalWeek->tasks->whereNull('planned_on'), $goalWeek, $canSeeProof) : [],
            'opponent' => $opponent,
            'highlightsUrl' => route('leagues.feed', [$this->table->league, 'member' => $member->user_id]),
        ];
    }

    /**
     * The member's W–T–L record in this league, from settled weeks. Close wins count as wins, close losses as losses.
     *
     * @return array{wins: int, ties: int, losses: int}
     */
    public function record(LeagueMember $member): array
    {
        $this->records ??= $this->table->standings()
            ->mapWithKeys(fn (array $row) => [$row['member']->id => ['wins' => $row['wins'], 'ties' => $row['ties'], 'losses' => $row['losses']]])
            ->all();

        return $this->records[$member->id] ?? ['wins' => 0, 'ties' => 0, 'losses' => 0];
    }

    /**
     * Whether the viewer can open a member's proof photos: their own, their matchup opponent's, or as league owner.
     */
    public function canSeeProof(LeagueMember $member, ?LeagueMember $opponent): bool
    {
        return $member->user_id === $this->viewer->id
            || $opponent?->user_id === $this->viewer->id
            || $this->table->league->commissioner_id === $this->viewer->id;
    }

    /**
     * Monday to Sunday (or the GoalWeek's own days, for a short first week): each day's tasks, or a rest day.
     *
     * @return list<array{date: CarbonInterface, label: string, isToday: bool, rest: bool, rows: list<array<string, mixed>>}>
     */
    public function days(RoadmapWeek $goalWeek, bool $canSeeProof): array
    {
        $today = Clock::today();
        $days = [];

        for ($day = $goalWeek->starts_on->copy(); $day->lte($goalWeek->ends_on); $day = $day->copy()->addDay()) {
            $tasks = $goalWeek->tasksOn($day);
            $rows = $this->rows($tasks, $goalWeek, $canSeeProof);

            $days[] = [
                'date' => $day,
                'label' => $day->format('D'),
                'isToday' => $day->isSameDay($today),
                'rest' => $tasks->isEmpty() || $tasks->every(fn (RoadmapTask $task) => self::isRestTask($task)),
                'rows' => $rows,
            ];
        }

        return $days;
    }

    /**
     * Task rows, in plan order.
     *
     * @param  Collection<int, RoadmapTask>  $tasks
     * @return list<array<string, mixed>>
     */
    public function rows(Collection $tasks, RoadmapWeek $goalWeek, bool $canSeeProof): array
    {
        return $tasks->sortBy('position')->map(fn (RoadmapTask $task) => $this->row($task, $goalWeek, $canSeeProof))->values()->all();
    }

    /**
     * One task: what it's worth, where it stands, its number, and its proof if the viewer may see it.
     *
     * @return array<string, mixed>
     */
    public function row(RoadmapTask $task, RoadmapWeek $goalWeek, bool $canSeeProof): array
    {
        $status = self::status($task, $goalWeek);

        return [
            'task' => $task,
            'status' => $status,
            'statusLabel' => self::statusLabel($status),
            'points' => $task->points,
            'result' => $task->result_required && $task->result_value !== null
                ? $task->formatNumber($task->result_value).($task->result_target ? ' of '.$task->formatNumber($task->result_target) : '')
                : null,
            'verified' => $task->isVerified(),
            'group' => $task->isGroupTask(),
            'personal' => (bool) $task->is_personal,
            'bonus' => (bool) $task->is_bonus,
            'proofUrl' => $task->isVerified() && $canSeeProof ? route('roadmap.tasks.proof', $task) : null,
            'sheetUrl' => route('leagues.matchups.task', [$this->table->league, $this->week, $task]),
        ];
    }

    /**
     * Where a task stands: done, due today, upcoming, missed, or a rest day.
     */
    public static function status(RoadmapTask $task, RoadmapWeek $week, ?CarbonInterface $today = null): string
    {
        $today ??= Clock::today();

        return match (true) {
            $task->completed_at !== null => self::STATUS_DONE,
            self::isRestTask($task) => self::STATUS_REST,
            $task->planned_on === null => $week->ends_on->lt($today) ? self::STATUS_MISSED : self::STATUS_UPCOMING,
            $task->planned_on->isSameDay($today) => self::STATUS_TODAY,
            $task->planned_on->gt($today) => self::STATUS_UPCOMING,
            default => self::STATUS_MISSED,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_DONE => 'Done',
            self::STATUS_TODAY => 'Due today',
            self::STATUS_UPCOMING => 'Upcoming',
            self::STATUS_MISSED => 'Missed',
            self::STATUS_REST => 'Rest day',
        };
    }

    /**
     * A planned rest day ("Rest day: walk or stretch").
     */
    public static function isRestTask(RoadmapTask $task): bool
    {
        return (bool) preg_match('/^rest\b/i', trim($task->description));
    }

    /**
     * Find a task on one of this week's pages, with its member and their opponent. Null if it isn't part of the
     * league week, so the sheet can't be used to look at other goals.
     *
     * @return array{member: LeagueMember, opponent: LeagueMember|null, goalWeek: RoadmapWeek, row: array<string, mixed>}|null
     */
    public function locate(RoadmapTask $task): ?array
    {
        foreach ($this->table->matchups($this->week) as $matchup) {
            foreach ([['home', 'homeResult', 'away'], ['away', 'awayResult', 'home']] as [$side, $result, $other]) {
                $member = $matchup[$side];

                if ($member === null || $matchup[$result]['goalWeek']?->id !== $task->roadmap_week_id) {
                    continue;
                }

                $goalWeek = $matchup[$result]['goalWeek'];
                $task = $goalWeek->tasks->firstWhere('id', $task->id) ?? $task;

                return [
                    'member' => $member,
                    'opponent' => $matchup[$other],
                    'goalWeek' => $goalWeek,
                    'row' => $this->row($task, $goalWeek, $this->canSeeProof($member, $matchup[$other])),
                ];
            }
        }

        return null;
    }

    /**
     * The post shared from a check-in, if the viewer is allowed to see it.
     */
    public function highlightFor(RoadmapTask $task): ?Highlight
    {
        return Highlight::query()
            ->visibleTo($this->viewer)
            ->where('roadmap_task_id', $task->id)
            ->with(['user', 'cheers'])
            ->first();
    }

    /**
     * The viewer's leagues that have matchups (not still forming), for the league switcher.
     *
     * @return Collection<int, League>
     */
    public static function switchableLeagues(User $viewer): Collection
    {
        return $viewer->leagues()->orderBy('name')->get()
            ->reject(fn (League $league) => $league->status() === LeagueStatus::Forming)
            ->values();
    }
}
