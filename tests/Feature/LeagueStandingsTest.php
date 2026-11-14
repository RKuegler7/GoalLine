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
        $this->assertSame([1, 1, 0], [$avery['photoFinishWins'], $avery['photoFinishes'], $avery['byes']]);
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
        $this->assertSame(2, $blake['photoFinishes'], 'A photo finish loss and a tie are both photo finishes.');
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

    public function test_members_see_the_table_by_default_and_player_seasons_and_others_cannot(): void
    {
        $avery = $this->members['Avery'];

        $this->actingAs($avery->user)->get(route('leagues.standings', $this->league))->assertOk()
            ->assertSee('data-standings-switch', false)
            ->assertSeeInOrder(['Table', 'League stats'])
            ->assertSee('Official order: total Stars, then Line ★, then Past-the-Line weeks.')
            ->assertSeeInOrder(['Blake Moss', 'Avery Lane', 'Casey Ruiz'])
            ->assertDontSee('data-season-chart', false);

        $this->actingAs($avery->user)->get(route('leagues.standings.player', [$this->league, $this->members['Blake']]))->assertOk()
            ->assertSee('1&ndash;1&ndash;1', false)
            ->assertSee('2 photo finishes')
            ->assertSee('Week by week')
            ->assertDontSee($this->members['Blake']->user->email);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('leagues.standings', $this->league))->assertForbidden();
        $this->actingAs($outsider)->get(route('leagues.standings', [$this->league, 'view' => 'league']))->assertForbidden();
        $this->actingAs($outsider)->get(route('leagues.standings.player', [$this->league, $avery]))->assertForbidden();
        $this->actingAs($outsider)->get(route('leagues.standings.player', [$this->league, $avery]), ['X-Requested-With' => 'XMLHttpRequest'])->assertForbidden();

        // A member of another league can't be opened through this one.
        $other = League::factory()->create();
        $stranger = $other->members()->create(['user_id' => $other->commissioner_id]);
        $this->actingAs($avery->user)->get(route('leagues.standings.player', [$this->league, $stranger]))->assertNotFound();
    }

    public function test_league_stats_has_the_highlights_and_chart_and_no_second_table(): void
    {
        $this->actingAs($this->members['Avery']->user)->get(route('leagues.standings', [$this->league, 'view' => 'league']))->assertOk()
            ->assertSee('Season so far')
            ->assertSee('data-season-chart', false)
            ->assertSee('data-league-summary', false)
            ->assertDontSee('data-standings-row', false);
    }

    public function test_every_column_comes_from_the_scoring_numbers(): void
    {
        // Avery met Blake in weeks 1 and 3 and Casey in week 2.
        foreach ([1 => 'Blake', 2 => 'Casey', 3 => 'Blake'] as $week => $opponent) {
            $this->league->results()->where('user_id', $this->members['Avery']->user_id)->where('week', $week)
                ->update(['opponent_id' => $this->members[$opponent]->user_id, 'vs_median' => false]);
        }

        $stats = $this->stats();

        foreach ($stats->standings() as $row) {
            $this->assertSame($row['total'], $row['resultPoints'] + $row['matchupPoints'], 'Stars = Line ★ + Matchup ★ for '.$row['member']->user->name);
            $this->assertSame($row['total'], $row['stats']['total']);
        }

        $avery = $stats->member($this->members['Avery']);
        // Avery's weeks: 3, 6 and 0 stars. Blake had 2 (week 1) and 5 (week 3); Casey had 3 (week 2).
        $this->assertSame([9, 10, -1], [$avery['starsFor'], $avery['starsAgainst'], $avery['plusMinus']]);
        $this->assertSame(['W', 'W', 'L'], array_column($avery['formWeeks'], 'letter'));
        $this->assertSame('Last 5: W In Line, W Smashed, L Short', LeagueStats::formLabel($avery['formWeeks']));

        // Weeks against the league median (no real opponent) count for neither.
        $blake = $stats->member($this->members['Blake']);
        $this->assertSame([0, 0, 0], [$blake['starsFor'], $blake['starsAgainst'], $blake['plusMinus']]);
        $this->assertSame(['L', 'D', 'W'], array_column($blake['formWeeks'], 'letter'));

        $page = $this->actingAs($this->members['Avery']->user)->get(route('leagues.standings', $this->league))->assertOk();
        $row = $this->rowHtml($page->getContent(), $this->members['Avery']->id);
        foreach (['total' => 9, 'line' => 4, 'match' => 5, 'best_week' => 6, 'for' => 9, 'against' => 10, 'plus_minus' => -1] as $cell => $value) {
            $this->assertMatchesRegularExpression('/data-value="'.$value.'"\s+data-cell="'.$cell.'"/', $row, $cell);
        }
        $this->assertStringContainsString('2-0-1', $row, 'Record');
        $this->assertStringContainsString('−1', $row, '+/− shows a real minus sign');
        // Last 5 circles: band colour, W/D/L letter, and the text alternative.
        $this->assertStringContainsString('bg-grass text-ink" data-form-week="1" data-letter="W"', $row);
        $this->assertStringContainsString('bg-sky-700 text-white" data-form-week="2" data-letter="W"', $row);
        $this->assertStringContainsString('bg-red-100 text-red-800" data-form-week="3" data-letter="L"', $row);
        $this->assertStringContainsString('aria-label="Last 5: W In Line, W Smashed, L Short.', $row);

        // The informational columns say they don't rank anyone.
        $page->assertSee('For interest only, not ranking.');
    }

    public function test_sorting_by_a_column_and_resetting_keeps_the_official_ranks(): void
    {
        $user = $this->members['Avery']->user;

        $this->actingAs($user)->get(route('leagues.standings', [$this->league, 'col' => 'streak']))->assertOk()
            ->assertSeeInOrder(['Blake Moss', 'Casey Ruiz', 'Avery Lane'])
            ->assertSeeInOrder(['data-rank="1"', 'data-rank="3"', 'data-rank="2"'], false)
            ->assertSee('Sorted by On the line, highest first.')
            ->assertSee('aria-sort="descending"', false);

        // Unknown or unsortable columns fall back to the official order, with Reset hidden.
        foreach (['nope', 'form'] as $column) {
            $html = $this->actingAs($user)->get(route('leagues.standings', [$this->league, 'col' => $column]))->assertOk()
                ->assertSeeInOrder(['Blake Moss', 'Avery Lane', 'Casey Ruiz'])
                ->assertSee('Official order: total Stars')
                ->getContent();
            $this->assertMatchesRegularExpression('/data-reset-order\s+hidden/', $html);
        }

        $names = fn (?string $column) => $this->stats()->statsRows($column)->map(fn (array $row) => strtok($row['member']->user->name, ' '))->all();
        $this->assertSame(['Blake', 'Avery', 'Casey'], $names(null));
        $this->assertSame(['Blake', 'Casey', 'Avery'], $names('streak'));
    }

    public function test_the_table_scrolls_in_its_own_box_and_rows_open_a_sheet(): void
    {
        $html = $this->actingAs($this->members['Avery']->user)->get(route('leagues.standings', $this->league))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="gl-table-scroll overflow-x-auto[^"]*" data-standings data-table-scroll/', $html);
        $this->assertStringContainsString('data-task-sheet aria-labelledby="player-sheet-title"', $html);
        $this->assertStringContainsString('data-task-sheet-open data-row-link', $html);
        $this->assertStringContainsString('data-swipe-hint', $html);

        // The sheet loads just the player's season, without the app around it.
        $this->actingAs($this->members['Avery']->user)
            ->get(route('leagues.standings.player', [$this->league, $this->members['Casey']]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('id="player-sheet-title"', false)
            ->assertSee('id="week-by-week"', false)
            ->assertDontSee('<html', false);
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
            ->assertSee('Season starts Monday, Nov 2. Everyone starts level.')
            ->assertDontSee('data-sort-col', false);

        // In season but before week 1 is final: a one-line note above a level table.
        $this->travelTo('2026-11-04 10:00');
        $this->actingAs($fresh->commissioner)->get(route('leagues.standings', $fresh))->assertOk()
            ->assertSee('Standings fill in after week 1')
            ->assertSee('No weeks yet');
        $this->actingAs($fresh->commissioner)->get(route('leagues.standings', [$fresh, 'view' => 'league']))->assertOk()
            ->assertSee('Standings fill in after week 1');

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

    private function rowHtml(string $html, int $memberId): string
    {
        preg_match('/<tr[^>]*data-standings-row="'.$memberId.'".*?<\/tr>/s', $html, $match);

        return $match[0] ?? '';
    }

    private function stats(): LeagueStats
    {
        return new LeagueStats(new LeagueTable($this->league->fresh()->load('members.user', 'members.goal.weeks.tasks', 'members.goal.habits.logs', 'results')));
    }
}
