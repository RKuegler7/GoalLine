<?php

namespace Tests\Feature;

use App\GoalWeekResult;
use App\Models\Goal;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\LeagueStats;
use App\Services\LeagueTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeagueStandingsTest extends TestCase
{
    use RefreshDatabase;

    private League $league;

    /** @var array<string, LeagueMember> */
    private array $members = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-28 10:00'); // Wednesday of league week 4 (season Oct 5 to Nov 15).
        $this->league = League::factory()->create(['name' => 'Fall Grind', 'starts_on' => '2026-10-05', 'ends_on' => '2026-11-15']);

        foreach (['Avery Lane', 'Blake Moss', 'Casey Ruiz'] as $index => $name) {
            $user = $index === 0 ? $this->league->commissioner : User::factory()->create();
            $user->forceFill(['name' => $name])->save();
            $goal = Goal::factory()->for($user)->create(['target_date' => '2027-01-31']);
            $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
            $this->members[strtok($name, ' ')] = $this->league->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);
        }

        // Known results for weeks 1 to 3: [band, line points, outcome, match points].
        $this->results('Avery', [['in_line', 1, 'close_win', 2], ['smashed', 3, 'win', 3], ['short', 0, 'loss', 0]]);   // 9 pts, line 4
        $this->results('Blake', [['in_line', 1, 'close_loss', 1], ['past', 2, 'tie', 1], ['past', 2, 'win', 3]]);       // 10 pts, line 5
        $this->results('Casey', [['short', 0, 'loss', 0], ['in_line', 1, 'close_win', 2], ['in_line', 1, 'tie', 1]]);   // 5 pts, line 2
    }

    public function test_each_players_stats_add_up_from_the_settled_results(): void
    {
        $stats = $this->stats();
        $avery = $stats->member($this->members['Avery']);

        $this->assertSame([2, 0, 1], [$avery['wins'], $avery['ties'], $avery['losses']]);
        $this->assertSame([1, 0, 1], [$avery['closeWins'], $avery['closeLosses'], $avery['photoFinishes']]);
        $this->assertSame([9, 5, 4], [$avery['total'], $avery['matchPoints'], $avery['linePoints']]);
        $this->assertSame(2, $avery['bestWeek']['week']);
        $this->assertSame(6, $avery['bestWeek']['weeklyPoints']);
        $this->assertSame([0, 2], [$avery['weeksOnLine'], $avery['bestWeeksOnLine']], 'A Short week 3 ends the run of 2.');
        $this->assertSame(['short' => 1, 'in_line' => 1, 'past' => 0, 'smashed' => 1], $avery['bands']);
        $this->assertSame([GoalWeekResult::InLine, GoalWeekResult::Smashed, GoalWeekResult::Short], $avery['form']);

        // Week-by-week rows name the opponent, with their band.
        $this->league->results()->where('user_id', $this->members['Avery']->user_id)->where('week', 1)->update(['opponent_id' => $this->members['Blake']->user_id, 'vs_median' => false]);
        $week1 = $this->stats()->member($this->members['Avery'])['weeks'][0];
        $this->assertSame('Blake Moss', $week1['opponentName']);
        $this->assertSame(GoalWeekResult::InLine, $week1['opponentBand']);

        $blake = $stats->member($this->members['Blake']);
        $this->assertSame([1, 1, 1], [$blake['wins'], $blake['ties'], $blake['losses']]);
        $this->assertSame(2, $blake['photoFinishes'], 'A close loss and a tie are both photo finishes.');
        $this->assertSame(3, $blake['weeksOnLine']);
    }

    public function test_the_official_order_and_the_other_sorts(): void
    {
        $stats = $this->stats();
        $names = fn (string $sort) => $stats->standings($sort)->map(fn (array $row) => strtok($row['member']->user->name, ' '))->all();

        $this->assertSame(['Blake', 'Avery', 'Casey'], $names('points'));
        $this->assertSame(['Blake', 'Avery', 'Casey'], $names('line'));
        $this->assertSame(['Blake', 'Casey', 'Avery'], $names('streak'));
        $this->assertSame([1, 2, 3], $stats->standings('streak')->sortBy('rank')->pluck('rank')->all(), 'Ranks stay official whatever the sort.');
    }

    public function test_ties_break_on_line_points_then_wins(): void
    {
        // Casey gains 4 match points with no line points: 9, level with Avery on total, but fewer line points.
        $this->league->results()->create(['user_id' => $this->members['Casey']->user_id, 'week' => 4, 'result' => null, 'result_points' => 0, 'outcome' => 'win', 'matchup_points' => 3]);
        $this->league->results()->create(['user_id' => $this->members['Avery']->user_id, 'week' => 4, 'result' => 'short', 'result_points' => 0, 'outcome' => 'loss', 'matchup_points' => 0]);
        $this->league->results()->where('user_id', $this->members['Casey']->user_id)->where('week', 1)->update(['matchup_points' => 1, 'outcome' => 'tie']);

        $order = $this->stats()->standings()->map(fn (array $row) => [strtok($row['member']->user->name, ' '), $row['total'], $row['resultPoints']])->all();

        $this->assertSame([['Blake', 10, 5], ['Avery', 9, 4], ['Casey', 9, 2]], $order);
    }

    public function test_the_league_summary(): void
    {
        $summary = $this->stats()->summary();

        $this->assertSame('Blake Moss', $summary['leader']['member']->user->name);
        $this->assertSame(['Avery Lane', 1], [$summary['mostSmashed']['member']->user->name, $summary['mostSmashed']['count']]);
        $this->assertSame(['Blake Moss', 3], [$summary['longestOnLine']['member']->user->name, $summary['longestOnLine']['weeks']]);
        $this->assertSame(['Avery Lane', 2, 6], [$summary['bestWeek']['member']->user->name, $summary['bestWeek']['week'], $summary['bestWeek']['points']]);
        $this->assertSame(3, $summary['played']);
        $this->assertSame(2, $summary['weeksRemaining'], 'Six weeks, in week 4.');
    }

    public function test_members_see_the_standings_page_and_player_seasons_and_others_cannot(): void
    {
        $avery = $this->members['Avery'];

        $this->actingAs($avery->user)->get(route('leagues.standings', $this->league))->assertOk()
            ->assertSee('Season so far')
            ->assertSee('Official order: total points, then line points, then wins.')
            ->assertSee('data-season-chart', false)
            ->assertSeeInOrder(['Blake Moss', 'Avery Lane', 'Casey Ruiz']);

        $this->actingAs($avery->user)->get(route('leagues.standings', [$this->league, 'sort' => 'streak']))->assertOk()
            ->assertSeeInOrder(['Blake Moss', 'Casey Ruiz', 'Avery Lane'])
            ->assertSee('The official order is by points.');

        $this->actingAs($avery->user)->get(route('leagues.standings.player', [$this->league, $this->members['Blake']]))->assertOk()
            ->assertSee('1&ndash;1&ndash;1', false)
            ->assertSee('2 photo finishes')
            ->assertSee('Week by week')
            ->assertDontSee($this->members['Blake']->user->email);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('leagues.standings', $this->league))->assertForbidden();
        $this->actingAs($outsider)->get(route('leagues.standings.player', [$this->league, $avery]))->assertForbidden();

        // A member of another league can't be opened through this one.
        $other = League::factory()->create();
        $stranger = $other->members()->create(['user_id' => $other->commissioner_id]);
        $this->actingAs($avery->user)->get(route('leagues.standings.player', [$this->league, $stranger]))->assertNotFound();
    }

    public function test_the_league_page_shows_the_new_table_with_its_link(): void
    {
        $this->actingAs($this->members['Casey']->user)->get(route('leagues.show', $this->league))->assertOk()
            ->assertSee('Stats &amp; details', false)
            ->assertSee(route('leagues.standings.player', [$this->league, $this->members['Blake']]), false)
            ->assertSee('data-form', false);
    }

    public function test_early_season_and_solo_leagues_have_friendly_standings(): void
    {
        $fresh = League::factory()->create(['starts_on' => '2026-11-02', 'ends_on' => '2026-12-13']);
        $fresh->members()->create(['user_id' => $fresh->commissioner_id]);

        $this->actingAs($fresh->commissioner)->get(route('leagues.standings', $fresh))->assertOk()
            ->assertSee('Standings fill in after week 1')
            ->assertSee('No weeks yet');

        $summary = (new LeagueStats(new LeagueTable($fresh->fresh())))->summary();
        $this->assertNull($summary['leader']);
        $this->assertNull($summary['avgPercent']);
    }

    /**
     * @param  list<array{0: string, 1: int, 2: string, 3: int}>  $weeks
     */
    private function results(string $who, array $weeks): void
    {
        foreach ($weeks as $index => [$band, $line, $outcome, $match]) {
            $this->league->results()->create(['user_id' => $this->members[$who]->user_id, 'week' => $index + 1, 'result' => $band, 'result_points' => $line, 'outcome' => $outcome, 'matchup_points' => $match, 'vs_median' => true]);
        }
    }

    private function stats(): LeagueStats
    {
        return new LeagueStats(new LeagueTable($this->league->fresh()->load('members.user', 'members.goal.weeks.tasks', 'members.goal.habits.logs', 'results')));
    }
}
