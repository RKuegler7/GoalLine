<?php

namespace Tests\Feature;

use App\GoalWeekResult;
use App\HighlightKind;
use App\Models\Goal;
use App\Models\Highlight;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\PostVisibility;
use App\RoadmapStatus;
use App\Services\LeagueTable;
use App\Services\MatchupBoard;
use App\Services\MatchupPager;
use App\Services\WeekPace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MatchupPagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday of league week 1 (Mon Oct 19 to Sun Oct 25).
        $this->travelTo('2026-10-22 10:00');
    }

    public function test_the_pager_lists_exactly_the_leagues_matchups_for_the_week_the_viewers_first(): void
    {
        $league = $this->league(4);
        $other = $this->league(2);
        $viewer = $league->members->last();

        $pages = $this->pager($league, $viewer->user)->pages();

        $this->assertCount(2, $pages);
        $this->assertTrue($pages[0]['mine']);
        $this->assertFalse($pages[1]['mine']);
        $this->assertSame('matchup', $pages[0]['kind']);
        $this->assertContains($viewer->user_id, collect($pages[0]['sides'])->map(fn ($side) => $side['member']->user_id)->all());
        $this->assertEqualsCanonicalizing(
            $league->members->pluck('user_id')->all(),
            collect($pages)->flatMap(fn ($page) => collect($page['sides'])->map(fn ($side) => $side['member']->user_id))->all(),
        );

        $response = $this->actingAs($viewer->user)->get(route('leagues.matchups', $league))->assertOk()
            ->assertSee('Your matchup')
            ->assertSeeText('1 of 2')
            ->assertDontSee($other->members->first()->user->name);
        $this->assertSame(2, substr_count($response->getContent(), 'data-pager-page '));
    }

    public function test_an_odd_league_has_a_bye_page_against_the_league_median(): void
    {
        $league = $this->league(3);
        $pages = $this->pager($league, $league->commissioner)->pages();

        $this->assertEqualsCanonicalizing(['matchup', 'bye'], array_column($pages, 'kind'));
        $bye = collect($pages)->firstWhere('kind', 'bye');
        $this->assertCount(1, $bye['sides']);

        $this->actingAs($bye['sides'][0]['member']->user)->get(route('leagues.matchups', $league))
            ->assertSee('Your bye week: you vs the league median')
            ->assertSee('line stars only');
    }

    public function test_a_one_member_league_is_a_solo_page_against_the_line(): void
    {
        $league = $this->league(1);
        $pages = $this->pager($league, $league->commissioner)->pages();

        $this->assertSame(['solo'], array_column($pages, 'kind'));
        $this->actingAs($league->commissioner)->get(route('leagues.matchups', $league))
            ->assertSee('Solo: against the line')
            ->assertSee('you against the line this season', false);
    }

    /**
     * @return array<string, array{float, float, bool, GoalWeekResult|null}>
     */
    public static function projections(): array
    {
        return [
            'nothing due yet' => [0, 0, true, null],
            'just under 90% of pace' => [8.99, 10, true, GoalWeekResult::Short],
            'exactly 90% of pace' => [9, 10, true, GoalWeekResult::InLine],
            'exactly on pace' => [10, 10, true, GoalWeekResult::InLine],
            'just ahead of pace' => [10.1, 10, true, GoalWeekResult::Past],
            'just under smashed pace' => [14.99, 10, true, GoalWeekResult::Past],
            'smashed pace with proof' => [15, 10, true, GoalWeekResult::Smashed],
            'smashed pace without proof projects as past' => [15, 10, false, GoalWeekResult::Past],
        ];
    }

    #[DataProvider('projections')]
    public function test_projected_band_compares_earned_with_due_so_far(float $earned, float $due, bool $verified, ?GoalWeekResult $expected): void
    {
        $this->assertSame($expected, WeekPace::band(WeekPace::share($earned, $due), $verified));
    }

    public function test_due_points_count_earlier_days_and_work_done_early_never_inflates_the_pace(): void
    {
        $league = $this->league(2);
        $member = $league->members->first();
        // One 1-point task a day, Mon to Sun, plus the milestone: 9 planned. Done Mon and Tue; today is Thursday.
        $week = $this->week($member, [[0, true], [1, true], [2, false], [3, false], [4, false], [5, false], [6, false]]);

        $this->assertEqualsWithDelta(3 / 7, WeekPace::elapsed($week), 0.0001);
        $this->assertEqualsWithDelta(3.0, WeekPace::duePoints($week), 0.0001, 'Mon, Tue and Wed are due.');
        $this->assertSame(GoalWeekResult::Short, WeekPace::project($week)['band'], '2 of 3 due: 67%.');

        $this->complete($week, 4); // Friday's task, early: due and earned both go up.
        $this->assertEqualsWithDelta(4.0, WeekPace::duePoints($week->fresh('tasks')), 0.0001);

        $this->complete($week, 2); // Wednesday's, a day late.
        $this->complete($week, 3); // Today's.
        $projection = WeekPace::project($week->fresh('tasks'));
        $this->assertSame([100, GoalWeekResult::InLine], [$projection['percent'], $projection['band']], '5 of 5 due: on pace.');

        $week->tasks()->create(['position' => 20, 'description' => 'Extra', 'is_bonus' => true])->forceFill(['completed_at' => now()])->save();
        $this->assertSame(GoalWeekResult::Past, WeekPace::project($week->fresh('tasks'))['band'], 'Bonus work puts the pace past the line.');
    }

    public function test_a_habit_target_comes_due_evenly_across_the_week(): void
    {
        $league = $this->league(2);
        $member = $league->members->first();
        $week = $this->week($member, []);
        $member->goal->habits()->create(['title' => 'Run', 'times_per_week' => 7]);

        $this->assertEqualsWithDelta(3.0, WeekPace::duePoints($week->fresh(['tasks', 'goal.habits.logs'])), 0.0001, '7 a week, 3 days in.');
    }

    public function test_a_finished_week_projects_its_real_result_and_an_unstarted_one_has_none(): void
    {
        $league = $this->league(2);
        $member = $league->members->first();
        $week = $this->week($member, [[0, true], [1, true]]);

        $this->travelTo('2026-10-27 10:00');
        $projection = WeekPace::project($week->fresh('tasks'));
        $this->assertEqualsWithDelta($week->fresh('tasks')->completionShare(), $projection['share'], 0.0001);

        $later = $member->goal->weeks()->create(['position' => 9, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-08', 'focus' => 'F', 'milestone' => 'M']);
        $this->assertNull(WeekPace::project($later->load('tasks'))['band']);
    }

    public function test_task_rows_show_done_due_today_upcoming_missed_rest_and_any_day(): void
    {
        $league = $this->league(2);
        $member = $league->members->first();
        $week = $this->week($member, [[0, true], [1, false], [3, false], [5, false]]);
        $week->tasks()->create(['position' => 10, 'description' => 'Rest day: walk or stretch', 'planned_on' => '2026-10-25']);
        $week->tasks()->create(['position' => 11, 'description' => 'Read a race guide']);

        $side = $this->pager($league, $member->user)->side(
            $this->side($league, $member),
            null,
        );
        $statuses = collect($side['days'])->flatMap(fn ($day) => collect($day['rows'])->pluck('status', 'task.description'));

        $this->assertSame([
            'Task 0' => 'done',
            'Task 1' => 'missed',
            'Task 3' => 'today',
            'Task 5' => 'upcoming',
            'Rest day: walk or stretch' => 'rest',
        ], $statuses->all());
        $this->assertSame(['upcoming'], collect($side['anytime'])->pluck('status')->all());
        $this->assertTrue(collect($side['days'])->firstWhere('label', 'Wed')['rest'], 'A day with nothing planned is a rest day.');

        $this->actingAs($member->user)->get(route('leagues.matchups', $league))
            ->assertSee('Due today')->assertSee('Missed')->assertSee('Upcoming')->assertSee('Rest day')->assertSee('Any day');
    }

    public function test_league_members_see_the_tasks_but_proof_photos_only_open_for_the_owner_opponent_and_league_owner(): void
    {
        $league = $this->league(4);
        [$owner, $opponent, $bystander] = $this->roles($league);
        $week = $this->week($owner, [[0, true, true], [1, true]]);
        $proof = $week->tasks->firstWhere('position', 1);
        $proofUrl = route('roadmap.tasks.proof', $proof);

        foreach ([$owner->user, $opponent->user, $league->commissioner] as $allowed) {
            $this->actingAs($allowed)->get(route('leagues.matchups', $league))->assertSee('Task 0')->assertSee($proofUrl, false);
        }

        $this->actingAs($bystander->user)->get(route('leagues.matchups', $league))
            ->assertSee('Task 0')
            ->assertSee('Verified')
            ->assertDontSee($proofUrl, false);
        $this->actingAs($bystander->user)->get(route('leagues.matchups.task', [$league, 1, $proof]))
            ->assertOk()
            ->assertSee('This check-in has proof.')
            ->assertDontSee($proofUrl, false);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('leagues.matchups', $league))->assertForbidden();
        $this->actingAs($outsider)->get(route('leagues.matchups.task', [$league, 1, $proof]))->assertForbidden();
    }

    public function test_the_sheet_only_opens_tasks_from_this_league_week(): void
    {
        $league = $this->league(2);
        $member = $league->members->first();
        $this->week($member, [[0, true]]);

        $elsewhere = Goal::factory()->create();
        $stranger = $elsewhere->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'F', 'milestone' => 'M'])
            ->tasks()->create(['position' => 1, 'description' => 'Someone else\'s task']);

        $this->actingAs($member->user)->get(route('leagues.matchups.task', [$league, 1, $stranger]))->assertNotFound();
        $this->actingAs($member->user)->get(route('leagues.matchups.task', [$league, 2, $member->goal->weeks->first()->tasks->first()]))->assertNotFound();
    }

    public function test_the_task_sheet_shows_details_the_visible_post_cheers_and_actions(): void
    {
        $league = $this->league(4);
        [$owner, $opponent, $bystander] = $this->roles($league);
        $week = $this->week($owner, [[0, true], [1, true]]);
        [$shared, $private] = [$week->tasks->firstWhere('position', 1), $week->tasks->firstWhere('position', 2)];
        $this->sharePost($owner, $shared, PostVisibility::League, 'Sunrise miles');
        $this->sharePost($owner, $private, PostVisibility::Proof, 'Just for me');

        $sheet = $this->actingAs($opponent->user)->get(route('leagues.matchups.task', [$league, 1, $shared]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $this->assertStringNotContainsString('<html', $sheet->getContent(), 'Fetched for the sheet: a fragment, not a page.');
        $sheet->assertSee('Task 0')
            ->assertSee('Shared to the feed')
            ->assertSee('Sunrise miles')
            ->assertSee(route('highlights.cheer', Highlight::where('headline', 'Sunrise miles')->sole()), false)
            ->assertSee('Show your work?')
            ->assertSee('>Dispute<', false);

        $this->actingAs($opponent->user)->get(route('leagues.matchups.task', [$league, 1, $private]))
            ->assertOk()
            ->assertSee('<html', false)
            ->assertDontSee('Just for me');

        // A league mate who isn't the opponent or league owner can ask for proof, but not dispute.
        $this->actingAs($bystander->user)->get(route('leagues.matchups.task', [$league, 1, $shared]))
            ->assertSee('Show your work?')
            ->assertDontSee('>Dispute<', false);

        $this->actingAs($owner->user)->get(route('leagues.matchups.task', [$league, 1, $shared]))
            ->assertSee('Show your work: add a photo')
            ->assertDontSee('Show your work?');
    }

    public function test_past_weeks_show_final_results_and_are_read_only(): void
    {
        $league = $this->league(2);
        [$home, $away] = (new LeagueTable($league))->pairings(1)[0];
        $week = $this->week($home, [[0, true], [1, true]]);
        $this->week($away, [[0, true]]);

        $this->travelTo('2026-10-28 10:00'); // League week 2; week 1 went final on Tuesday.
        $task = $week->tasks->first();

        $this->actingAs($away->user)->get(route('leagues.matchups', [$league, 1]))->assertOk()
            ->assertSee('Final')
            ->assertSee('GoalWeek 2')
            ->assertDontSee('<p>Projected, on current pace</p>', false)
            ->assertDontSee('Show your work?');
        $this->actingAs($away->user)->get(route('leagues.matchups.task', [$league, 1, $task]))
            ->assertSee('GoalWeek 1 is final, so this is read only.')
            ->assertDontSee('Show your work?');
    }

    public function test_the_scoreboard_shows_record_projection_days_left_and_when_it_ends(): void
    {
        $league = $this->league(2);
        $member = $league->members->first();
        $this->week($member, [[0, true], [1, true], [2, true]]);

        $this->actingAs($member->user)->get(route('leagues.matchups', $league))
            ->assertSeeText('0–0–0')
            ->assertSee('Projected, on current pace')
            ->assertSeeText('4 days left')
            ->assertSee('Ends Sunday 11:59 p.m.')
            ->assertSeeText('if it ended now');

        $this->travelTo('2026-10-26 10:00'); // Monday after: the grace day.
        $this->assertSame(
            'Check-ins close Monday 11:59 p.m., then it goes final',
            $this->pager($league, $member->user)->statusLine(false),
        );
    }

    public function test_league_switcher_and_player_names_link_to_their_league_highlights(): void
    {
        $league = $this->league(2);
        $member = $league->members->first();
        $second = $this->league(2);
        $second->members()->create(['user_id' => $member->user_id, 'goal_id' => $member->goal_id]);
        $mate = $league->members->last();

        $this->actingAs($member->user)->get(route('leagues.matchups', $league))
            ->assertSee('Switch league')
            ->assertSee(route('leagues.matchups', $second), false)
            ->assertSee(route('leagues.feed', [$league, 'member' => $mate->user_id]), false);

        $week = $this->week($mate, [[0, true]]);
        $this->sharePost($mate, $week->tasks->first(), PostVisibility::League, 'Mate miles');
        $this->sharePost($member, $this->week($member, [[0, true]])->tasks->first(), PostVisibility::League, 'My miles');

        $this->actingAs($member->user)->get(route('leagues.feed', [$league, 'member' => $mate->user_id]))->assertOk()
            ->assertSee('Posts by')
            ->assertSee('Mate miles')
            ->assertDontSee('My miles');
    }

    public function test_each_player_sees_plain_targets_that_never_promise_more_tasks_than_are_left(): void
    {
        $league = $this->league(2);
        $member = $league->members->first();
        // 20 tasks, 15 done, plus the 2-point milestone: 22 planned, 15 earned.
        $week = $this->week($member, collect(range(1, 20))->map(fn (int $index) => [$index % 7, $index <= 15])->all());

        $targets = MatchupBoard::targets($week);
        $this->assertSame(['points' => 5, 'tasks' => 5, 'more' => false], $targets['inLine'], '90% of 22 is 19.8: 5 more points, and the 5 open tasks cover it.');
        $this->assertSame(['points' => 8, 'tasks' => 5, 'more' => true], $targets['past'], 'Past needs 8: all 5 tasks plus habit days or the milestone.');
        $this->assertSame([15, 5], [$targets['done'], $targets['left']]);
        $this->assertSame(MatchupBoard::nextBand($week)['points'], $targets['inLine']['points'], 'Same numbers as the scoreboard.');

        $this->actingAs($member->user)->get(route('leagues.matchups', $league))->assertOk()
            ->assertSee('data-pager-sticky', false)
            ->assertSee('data-day-controls', false)
            ->assertSeeText('5 more points to In line')
            ->assertSeeText('(about 5 tasks)')
            ->assertSeeText('To reach Past the line: 8 points')
            ->assertSeeText('15 done');
    }

    private function pager(League $league, User $viewer, int $week = 1): MatchupPager
    {
        return new MatchupPager(new LeagueTable($league->fresh()), $week, $viewer);
    }

    /**
     * The board side for a member this week, as the pager gets it.
     *
     * @return array<string, mixed>
     */
    private function side(League $league, LeagueMember $member): array
    {
        $table = new LeagueTable($league->fresh());
        $matchup = collect($table->matchups(1))->first(fn ($matchup) => $matchup['home']->is($member) || $matchup['away']?->is($member));
        $home = $matchup['home']->is($member);

        return MatchupBoard::side($member, $matchup[$home ? 'homeResult' : 'awayResult'], $matchup[$home ? 'homeOutcome' : 'awayOutcome']);
    }

    /**
     * A task owner, their opponent, and a league mate who is neither, none of them the league owner.
     *
     * @return array{LeagueMember, LeagueMember, LeagueMember}
     */
    private function roles(League $league): array
    {
        $pairs = (new LeagueTable($league))->pairings(1);
        $isOwner = fn (LeagueMember $member) => $member->user_id === $league->commissioner_id;
        $free = collect($pairs)->first(fn ($pair) => ! $isOwner($pair[0]) && ! $isOwner($pair[1]));
        $withOwner = collect($pairs)->first(fn ($pair) => $isOwner($pair[0]) || $isOwner($pair[1]));

        return [$free[0], $free[1], $isOwner($withOwner[0]) ? $withOwner[1] : $withOwner[0]];
    }

    private function league(int $members): League
    {
        $league = League::factory()->create(['starts_on' => '2026-10-19', 'ends_on' => '2026-11-15']);

        foreach (range(1, $members) as $index) {
            $user = $index === 1 ? $league->commissioner : User::factory()->create();
            $goal = Goal::factory()->for($user)->create(['target_date' => '2027-01-31']);
            $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
            $league->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);
        }

        return $league->load('members.user');
    }

    /**
     * A GoalWeek for league week 1 with tasks: [day offset from Monday, done, proof].
     *
     * @param  list<array{0: int, 1: bool, 2?: bool}>  $tasks
     */
    private function week(LeagueMember $member, array $tasks): RoadmapWeek
    {
        $goal = $member->goal;
        $week = $goal->weeks()->firstOrCreate(['starts_on' => '2026-10-19'], ['position' => 1, 'ends_on' => '2026-10-25', 'focus' => 'Focus', 'milestone' => 'Milestone']);

        foreach ($tasks as $index => $spec) {
            [$day, $done] = $spec;
            $week->tasks()->create(['position' => $index + 1, 'description' => "Task {$day}", 'planned_on' => date('Y-m-d', strtotime("2026-10-19 +{$day} days"))])
                ->forceFill(['completed_at' => $done ? now() : null, 'proof_path' => ($spec[2] ?? false) ? 'proofs/test.png' : null])->save();
        }

        return $week->fresh(['tasks', 'goal.habits.logs']);
    }

    private function complete(RoadmapWeek $week, int $day): void
    {
        $week->tasks()->where('description', "Task {$day}")->sole()->forceFill(['completed_at' => now()])->save();
    }

    private function sharePost(LeagueMember $member, $task, PostVisibility $visibility, string $headline): Highlight
    {
        return Highlight::factory()->for($member->user)->create([
            'kind' => HighlightKind::CheckIn,
            'roadmap_task_id' => $task->id,
            'league_id' => $member->league_id,
            'visibility' => $visibility,
            'headline' => $headline,
        ]);
    }
}
