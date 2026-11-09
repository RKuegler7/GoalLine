<?php

namespace Tests\Feature;

use App\MatchupOutcome;
use App\Models\Goal;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
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
        $this->assertSame(2 + 3 + 1 + 1, $leader['total'], 'Past (2) + win (3), then in line (1) + a photo-finish tie at 100% (1).');

        $runnerUp = $standings->last();
        $this->assertSame([0, 1, 1], [$runnerUp['wins'], $runnerUp['ties'], $runnerUp['losses']]);
        $this->assertSame(1 + 0 + 1 + 1, $runnerUp['total'], 'In line and lost to a higher band earns just the 1 line point.');

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

        $this->assertSame(3 + 3, $table->standings()->first()['total'], 'Smashed (3) + win (3): the most a week can earn.');
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
        $expectedMedianPercent = $others->map(fn (LeagueMember $member) => $table->memberResult($member, 1)['percent'])->median();
        $this->assertEquals($expectedMedianPercent, $bye['medianPercent']);
        $this->assertSame(MatchupOutcome::compare($bye['homeResult']['points'], $expectedMedian, $bye['homeResult']['percent'], $expectedMedianPercent), $bye['homeOutcome']);
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
            ->assertSeeText('if it ended now, +5 pts')
            ->assertSeeText('2 line + 3 match · Win')
            ->assertSee('1 more point to Past the line')
            ->assertSee('Scoreboard')
            ->assertSee('data-pager-page', false);

        // One page per matchup, the viewer's first.
        $this->assertSame(2, substr_count($response->getContent(), 'data-pager-page '));
        $this->assertSame(2, substr_count($response->getContent(), 'data-pager-dot '));

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
            ->assertSeeText('+6 pts');
    }

    public function test_a_forming_league_has_no_matchups_yet(): void
    {
        $league = $this->league(members: 2);

        $this->actingAs($league->commissioner)->get(route('leagues.matchups', $league))
            ->assertRedirect(route('leagues.show', $league));
    }

    public function test_same_band_is_a_photo_finish_the_higher_credited_percent_wins_2_to_1(): void
    {
        $league = $this->league(members: 2, startsOn: '2026-10-05', endsOn: '2026-10-11');
        [$alex, $blair] = $league->members->all();
        $this->weekOf($alex, '2026-10-05', planned: 10, earned: 10);  // 100%, In line
        $this->weekOf($blair, '2026-10-05', planned: 10, earned: 9);  // 90%, In line

        $this->travelTo('2026-10-21');
        $table = new LeagueTable($league->fresh());
        $table->settle();

        $this->assertSame([MatchupOutcome::CloseWin, 2, 3], $this->settled($league, $alex));
        $this->assertSame([MatchupOutcome::CloseLoss, 1, 2], $this->settled($league, $blair));

        $standings = $table->standings();
        $this->assertSame([1, 0, 0], [$standings->first()['wins'], $standings->first()['ties'], $standings->first()['losses']], 'A close win is a win in the record.');
        $this->assertSame([0, 0, 1], [$standings->last()['wins'], $standings->last()['ties'], $standings->last()['losses']], 'A close loss is a loss in the record.');
        $this->assertSame(['wins' => 0, 'ties' => 0, 'losses' => 1], (new ProfileStats($blair->user))->leagueRecord());

        $this->actingAs($alex->user)->get(route('leagues.matchups', [$league, 1]))->assertOk()
            ->assertSee('Photo finish')
            ->assertSee("Photo finish: both In line. {$alex->user->name} takes the close win, 100% to 90%.")
            ->assertSeeText('1 line + 2 match · Close win')
            ->assertSeeText('1 line + 1 match · Close loss');
    }

    public function test_exactly_equal_credited_percents_are_a_tie_worth_1_each(): void
    {
        $league = $this->league(members: 2, startsOn: '2026-10-05', endsOn: '2026-10-11');
        [$alex, $blair] = $league->members->all();
        // Different raw points, the same whole percent: 9.5 of 10 and 19 of 20 are both 95%.
        $this->weekOf($alex, '2026-10-05', planned: 10, earned: 9.5);
        $this->weekOf($blair, '2026-10-05', planned: 20, earned: 19);

        $this->travelTo('2026-10-21');
        (new LeagueTable($league->fresh()))->settle();

        $this->assertSame([MatchupOutcome::Tie, 1, 2], $this->settled($league, $alex));
        $this->assertSame([MatchupOutcome::Tie, 1, 2], $this->settled($league, $blair));
        $this->actingAs($alex->user)->get(route('leagues.matchups', [$league, 1]))
            ->assertSee('Photo finish: both In line. A tie at 95%.');
    }

    public function test_a_fraction_of_a_percent_does_not_decide_a_photo_finish(): void
    {
        $league = $this->league(members: 2, startsOn: '2026-10-05', endsOn: '2026-10-11');
        [$alex, $blair] = $league->members->all();
        // 99.9% and 99.0% both credit as 99.
        $this->weekOf($alex, '2026-10-05', planned: 1000, earned: 999);
        $this->weekOf($blair, '2026-10-05', planned: 100, earned: 99);

        $this->travelTo('2026-10-21');
        (new LeagueTable($league->fresh()))->settle();

        $this->assertSame(MatchupOutcome::Tie, $this->settled($league, $alex)[0]);
    }

    public function test_an_unconfirmed_smashed_week_is_credited_as_past_and_capped_at_149(): void
    {
        $league = $this->league(members: 2, startsOn: '2026-10-05', endsOn: '2026-10-11');
        [$alex, $blair] = $league->members->all();
        $this->weekOf($alex, '2026-10-05', planned: 10, earned: 24);     // 240%, no proof: Past, credited 149
        $this->weekOf($blair, '2026-10-05', planned: 100, earned: 149);  // a real 149%, Past

        $this->travelTo('2026-10-21');
        $table = new LeagueTable($league->fresh());

        $this->assertSame(149, $table->memberResult($alex, 1)['percent']);
        $table->settle();
        $this->assertSame([MatchupOutcome::Tie, 1, 3], $this->settled($league, $alex), 'Capped at 149, so it ties a real 149.');
        $this->assertSame([MatchupOutcome::Tie, 1, 3], $this->settled($league, $blair));
    }

    public function test_once_smashed_is_proven_the_higher_band_wins_outright(): void
    {
        $league = $this->league(members: 2, startsOn: '2026-10-05', endsOn: '2026-10-11');
        [$alex, $blair] = $league->members->all();
        $this->weekOf($alex, '2026-10-05', planned: 10, earned: 24, proof: true);
        $this->weekOf($blair, '2026-10-05', planned: 100, earned: 149);

        $this->travelTo('2026-10-21');
        (new LeagueTable($league->fresh()))->settle();

        $this->assertSame([MatchupOutcome::Win, 3, 6], $this->settled($league, $alex));
        $this->assertSame([MatchupOutcome::Loss, 0, 2], $this->settled($league, $blair));
    }

    public function test_points_held_in_a_dispute_are_left_out_of_the_credited_percent(): void
    {
        $league = $this->league(members: 2, startsOn: '2026-10-05', endsOn: '2026-10-11');
        [$alex, $blair] = $league->members->all();
        $alexWeek = $this->weekOf($alex, '2026-10-05', planned: 10, earned: 10);  // 100%
        $this->weekOf($blair, '2026-10-05', planned: 20, earned: 19);             // 95%

        // One of Alex's 1-point check-ins is disputed and still open when the week closes: 9 of 10, 90%.
        $alexWeek->tasks()->where('points', 1)->first()->forceFill(['dispute_status' => RoadmapTask::DISPUTE_OPEN])->save();

        $this->travelTo('2026-10-21');
        $table = new LeagueTable($league->fresh());
        $this->assertSame(90, $table->memberResult($alex, 1)['percent']);
        $table->settle();

        $this->assertSame([MatchupOutcome::CloseLoss, 1, 2], $this->settled($league, $alex));
        $this->assertSame([MatchupOutcome::CloseWin, 2, 3], $this->settled($league, $blair));
    }

    public function test_a_bye_in_the_same_band_as_the_median_is_a_photo_finish_against_the_median_percent(): void
    {
        $league = $this->league(members: 3, startsOn: '2026-10-05', endsOn: '2026-10-11');
        $table = new LeagueTable($league);
        $byeMember = collect($table->pairings(1))->firstWhere(1, null)[0];
        [$first, $second] = $league->members->reject(fn (LeagueMember $member) => $member->is($byeMember))->values()->all();

        $this->weekOf($byeMember, '2026-10-05', planned: 100, earned: 97);  // 97%
        $this->weekOf($first, '2026-10-05', planned: 100, earned: 94);      // 94%
        $this->weekOf($second, '2026-10-05', planned: 100, earned: 100);    // 100%: median 97%

        $this->travelTo('2026-10-21');
        $bye = collect((new LeagueTable($league->fresh()))->matchups(1))->firstWhere('away', null);

        $this->assertEquals(1, $bye['median']);
        $this->assertEquals(97, $bye['medianPercent']);
        $this->assertSame(MatchupOutcome::Tie, $bye['homeOutcome']);
    }

    /**
     * A settled result: the outcome, its match points, and the week's total.
     *
     * @return array{MatchupOutcome, int, int}
     */
    private function settled(League $league, LeagueMember $member): array
    {
        $result = $league->results()->where('user_id', $member->user_id)->where('week', 1)->sole();

        return [$result->outcome, $result->matchup_points, $result->result_points + $result->matchup_points];
    }

    /**
     * A Monday-to-Sunday GoalWeek with exact planned and earned points. Planned is the milestone (2), three 1-point
     * tasks and one big task for the rest. Shortfalls come off the big task (as a numeric result), and anything over
     * the plan is one bonus task.
     */
    private function weekOf(LeagueMember $member, string $monday, int $planned, float $earned, bool $proof = false): RoadmapWeek
    {
        $goal = $member->goal;
        $week = $goal->weeks()->create([
            'position' => $goal->weeks()->count() + 1,
            'starts_on' => $monday,
            'ends_on' => date('Y-m-d', strtotime($monday.' +6 days')),
            'focus' => 'Focus',
            'milestone' => 'Milestone',
        ]);
        $week->forceFill(['milestone_hit_at' => now()])->save();

        $big = $planned - 2 - 3;
        $missed = max(0, $planned - $earned);

        foreach ([1, 1, 1, $big] as $index => $points) {
            $fill = ['points' => $points, 'completed_at' => now(), 'proof_path' => $proof && $index === 0 ? 'proofs/test.png' : null];

            if ($index === 3 && $missed > 0) {
                $fill += ['result_required' => true, 'result_target' => $big * 2, 'result_value' => ($big - $missed) * 2];
            }

            $week->tasks()->create(['position' => $index + 1, 'description' => "Task {$index}"])->forceFill($fill)->save();
        }

        if ($earned > $planned) {
            $week->tasks()->create(['position' => 9, 'description' => 'Extra', 'is_bonus' => true])
                ->forceFill(['points' => (int) ($earned - $planned), 'completed_at' => now()])->save();
        }

        $week = $week->fresh(['tasks', 'goal.habits.logs']);
        $this->assertEqualsWithDelta($earned, $week->earnedPoints(), 0.0001, 'Test setup: earned points.');
        $this->assertSame($planned, $week->plannedPoints(), 'Test setup: planned points.');

        return $week;
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
