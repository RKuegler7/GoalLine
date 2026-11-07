<?php

namespace Tests\Feature;

use App\MatchupOutcome;
use App\Models\Goal;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\LeagueTable;
use App\Services\ProfileStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeagueScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_robin_pairs_everyone_once_per_week_and_everyone_meets(): void
    {
        $league = $this->league(members: 4);
        $table = new LeagueTable($league);
        $met = [];

        foreach (range(1, 3) as $week) {
            $pairs = $table->pairings($week);
            $this->assertCount(2, $pairs);

            $ids = collect($pairs)->flatten()->map->id;
            $this->assertCount(4, $ids->unique(), "Week {$week}: everyone plays exactly once.");

            foreach ($pairs as [$home, $away]) {
                $met[] = collect([$home->id, $away->id])->sort()->implode('-');
            }
        }

        $this->assertCount(6, array_unique($met), 'Over three weeks, each of the six pairs meets once.');
        $this->assertEquals($table->pairings(1), $table->pairings(4), 'The round-robin repeats.');
    }

    public function test_with_an_odd_count_one_member_plays_the_league_median(): void
    {
        $league = $this->league(members: 3);

        $pairs = (new LeagueTable($league))->pairings(1);

        $this->assertCount(2, $pairs);
        $this->assertCount(1, collect($pairs)->filter(fn ($pair) => $pair[1] === null));
    }

    public function test_settled_weeks_score_results_and_matchups_soccer_style(): void
    {
        $league = $this->league(members: 2, startsOn: '2026-10-05', endsOn: '2026-10-18');
        [$alex, $blair] = $league->members->all();

        // Week 1: Alex gets past the line, Blair is in line. Week 2: both in line.
        $this->scoreWeek($alex, '2026-10-05', 'past');
        $this->scoreWeek($blair, '2026-10-05', 'in_line');
        $this->scoreWeek($alex, '2026-10-12', 'in_line');
        $this->scoreWeek($blair, '2026-10-12', 'in_line');

        $this->travelTo('2026-10-21');
        $table = new LeagueTable($league->fresh());
        $table->settle();

        $standings = $table->standings();
        $leader = $standings->first();
        $this->assertTrue($leader['member']->is($alex));
        $this->assertSame([2, 1, 1, 0], [$leader['played'], $leader['wins'], $leader['ties'], $leader['losses']]);
        $this->assertSame(2 + 2 + 1 + 1, $leader['total'], 'Past (2) + win (2), then in line (1) + draw (1).');

        $runnerUp = $standings->last();
        $this->assertSame([0, 1, 1], [$runnerUp['wins'], $runnerUp['ties'], $runnerUp['losses']]);
        $this->assertSame(1 + 0 + 1 + 1, $runnerUp['total'], 'In line and lost earns just the 1 line point.');

        $record = (new ProfileStats($alex->user))->leagueRecord();
        $this->assertSame(['wins' => 1, 'ties' => 1, 'losses' => 0], $record);
    }

    public function test_settled_results_do_not_change_when_a_member_switches_goals(): void
    {
        $league = $this->league(members: 2, startsOn: '2026-10-05', endsOn: '2026-10-11');
        [$alex, $blair] = $league->members->all();
        $this->scoreWeek($alex, '2026-10-05', 'smashed');
        $this->scoreWeek($blair, '2026-10-05', 'short');

        $this->travelTo('2026-10-21');
        (new LeagueTable($league->fresh()))->settle();

        $alex->update(['goal_id' => Goal::factory()->for($alex->user)->create()->id]);
        $table = new LeagueTable($league->fresh());
        $table->settle();

        $this->assertSame(3 + 2, $table->standings()->first()['total'], 'Smashed (3) + win (2): the most a week can earn.');
    }

    public function test_weeks_wait_to_settle_until_results_lock(): void
    {
        $league = $this->league(members: 2, startsOn: '2026-10-05', endsOn: '2026-10-11');
        [$alex, $blair] = $league->members->all();
        $this->scoreWeek($alex, '2026-10-05', 'past');
        $this->scoreWeek($blair, '2026-10-05', 'past');

        $this->travelTo('2026-10-12'); // The grace day: check-ins still open.
        $table = new LeagueTable($league->fresh());
        $table->settle();
        $this->assertSame(0, $league->results()->count());

        $this->travelTo('2026-10-13');
        (new LeagueTable($league->fresh()))->settle();
        $this->assertSame(2, $league->results()->count());
    }

    public function test_the_bye_member_wins_ties_or_loses_against_the_league_median(): void
    {
        $league = $this->league(members: 3, startsOn: '2026-10-05', endsOn: '2026-10-11');
        $members = $league->members->all();
        $this->scoreWeek($members[0], '2026-10-05', 'smashed');
        $this->scoreWeek($members[1], '2026-10-05', 'in_line');
        $this->scoreWeek($members[2], '2026-10-05', 'past');

        $this->travelTo('2026-10-21');
        $table = new LeagueTable($league->fresh());
        $bye = collect($table->matchups(1))->firstWhere('away', null);

        $others = collect($members)->reject(fn (LeagueMember $member) => $member->is($bye['home']));
        $expectedMedian = $others->map(fn (LeagueMember $member) => $table->memberResult($member, 1)['points'])->median();

        $this->assertEquals($expectedMedian, $bye['median']);
        $this->assertSame(MatchupOutcome::compare($bye['homeResult']['points'], $expectedMedian), $bye['homeOutcome']);
    }

    public function test_league_page_shows_standings_and_this_goalweeks_matchup(): void
    {
        $league = $this->league(members: 2, startsOn: '2026-10-19', endsOn: '2026-11-15');
        $this->travelTo('2026-10-21');

        $this->actingAs($league->commissioner)
            ->get(route('leagues.show', $league))
            ->assertOk()
            ->assertSee('GoalWeek 1 Matchup')
            ->assertSee(route('leagues.matchups', [$league, 1]))
            ->assertSee('See all 1 matchup')
            ->assertSee('Standings');
    }

    public function test_the_goalweek_matchup_page_shows_who_leads_bands_and_points_to_the_next_band(): void
    {
        $league = $this->league(members: 4, startsOn: '2026-10-19', endsOn: '2026-11-15');
        [$first, $second] = collect((new LeagueTable($league))->pairings(1))->first();
        $this->scoreWeek($first, '2026-10-19', 'past');
        $this->scoreWeek($second, '2026-10-19', 'in_line');
        $this->travelTo('2026-10-21');

        $response = $this->actingAs($first->user)->get(route('leagues.matchups', $league))->assertOk()
            ->assertSee('GoalWeek 1 Matchup')
            ->assertSee('Your matchup')
            ->assertSee('Live')
            ->assertSee("{$first->user->name} leads: Past the line beats In line.")
            ->assertSeeText('125%')
            ->assertSeeText('if it ended now, +4 pts')
            ->assertSee('1 more point to Past the line')
            ->assertSee('Scoreboard')
            ->assertSee('data-carousel-slide', false);

        // The viewer's matchup comes first, then the others.
        $this->assertSame(4, substr_count($response->getContent(), 'data-carousel-slide'));
        $this->assertSame(2, substr_count($response->getContent(), 'data-carousel-dot'));

        $this->actingAs($first->user)->get(route('leagues.matchups', [$league, 2]))->assertNotFound(); // Not played yet.
        $this->actingAs(User::factory()->create())->get(route('leagues.matchups', $league))->assertForbidden();
    }

    public function test_past_goalweek_matchups_are_final_and_reachable_from_the_results(): void
    {
        $league = $this->league(members: 2, startsOn: '2026-10-19', endsOn: '2026-11-15');
        [$home, $away] = collect((new LeagueTable($league))->pairings(1))->first();
        $this->scoreWeek($home, '2026-10-19', 'smashed');
        $this->scoreWeek($away, '2026-10-19', 'short');
        $this->travelTo('2026-10-28');

        $this->actingAs($home->user)->get(route('leagues.show', $league))->assertSee(route('leagues.matchups', [$league, 1]));
        $this->actingAs($home->user)->get(route('leagues.matchups', [$league, 1]))->assertOk()
            ->assertSee('Final')
            ->assertSee("{$home->user->name} won: Smashed the line beats Short of the line.")
            ->assertSeeText('+5 pts');
    }

    public function test_a_forming_league_has_no_matchups_yet(): void
    {
        $league = $this->league(members: 2);

        $this->actingAs($league->commissioner)->get(route('leagues.matchups', $league))
            ->assertRedirect(route('leagues.show', $league));
    }

    private function league(int $members, string $startsOn = '2026-10-26', string $endsOn = '2026-12-20'): League
    {
        $this->travelTo('2026-10-01');
        $league = League::factory()->create(['starts_on' => $startsOn, 'ends_on' => $endsOn]);

        foreach (range(1, $members) as $index) {
            $user = $index === 1 ? $league->commissioner : User::factory()->create();
            $goal = Goal::factory()->for($user)->create(['target_date' => '2027-01-31']);
            $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
            $league->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);
        }

        return $league->load('members');
    }

    /**
     * Give a member's goal a Monday-to-Sunday GoalWeek with the given result.
     */
    private function scoreWeek(LeagueMember $member, string $monday, string $result): void
    {
        // 2 planned tasks + the milestone = 4 planned points; bonus tasks are 1 point each.
        [$done, $bonus, $milestone, $proof] = match ($result) {
            'short' => [0, 0, false, false],
            'in_line' => [2, 0, true, false],   // 4 of 4
            'past' => [2, 1, true, false],      // 5 of 4
            'smashed' => [2, 2, true, true],    // 6 of 4, with proof
        };

        $goal = $member->goal;
        $week = $goal->weeks()->create([
            'position' => $goal->weeks()->count() + 1,
            'starts_on' => $monday,
            'ends_on' => date('Y-m-d', strtotime($monday.' +6 days')),
            'focus' => 'Focus',
            'milestone' => 'Milestone',
        ]);
        $week->forceFill(['milestone_hit_at' => $milestone ? now() : null])->save();

        foreach (range(1, 2 + $bonus) as $position) {
            $week->tasks()->create(['position' => $position, 'description' => "Task {$position}", 'is_bonus' => $position > 2])
                ->forceFill(['completed_at' => $position <= $done || $position > 2 ? now() : null, 'proof_path' => $proof && $position === 1 ? 'proofs/test.png' : null])->save();
        }
    }
}
