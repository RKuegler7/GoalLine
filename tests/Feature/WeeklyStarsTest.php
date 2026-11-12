<?php

namespace Tests\Feature;

use App\MatchupOutcome;
use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use App\Percent;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyStarsTest extends TestCase
{
    use RefreshDatabase;

    public function test_percents_show_whole_numbers_and_decimals_only_when_needed(): void
    {
        $this->assertSame(['98%', '94%'], Percent::pair(98.4, 94.2));
        $this->assertSame(['100.4%', '100.1%'], Percent::pair(100.4, 100.1), 'Whole numbers would look tied.');
        $this->assertSame(['100.04%', '100.01%'], Percent::pair(100.04, 100.01), 'One decimal would still look tied.');
        $this->assertSame(['100%', '99%'], Percent::pair(100.4, 99.6), 'Rounded down: never a higher band\'s number.');
        $this->assertSame(['100%', '100%'], Percent::pair(100.0, 100.0), 'A tie shows as a tie only when truly equal.');
        $this->assertSame('149%', Percent::whole(149.0));
    }

    public function test_settled_weeks_are_rescored_with_stars(): void
    {
        $this->travelTo('2026-10-28 10:00');
        $league = League::factory()->create(['starts_on' => '2026-10-19', 'ends_on' => '2026-11-15']);
        $members = collect([[1004, 1000], [996, 1000]])->map(function (array $plan, int $index) use ($league) {
            [$earned, $planned] = $plan;
            $user = $index === 0 ? $league->commissioner : User::factory()->create();
            $goal = Goal::factory()->for($user)->create(['target_date' => '2027-01-31']);
            $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
            $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Focus', 'milestone' => 'Milestone']);
            // One task worth the planned points, and a bonus task for the extra (or a smaller one for the shortfall).
            $week->tasks()->create(['position' => 1, 'description' => 'Plan'])->forceFill(['points' => $planned - 2, 'completed_at' => '2026-10-20 09:00'])->save();
            $week->tasks()->create(['position' => 2, 'description' => 'Extra', 'is_bonus' => true])->forceFill(['points' => $earned - $planned + 2, 'completed_at' => '2026-10-21 09:00'])->save();
            $week->forceFill(['milestone_hit_at' => '2026-10-24 10:00'])->save();

            return $league->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);
        });

        // Stored under the old rules: a close win for 2, a close loss for 1.
        foreach ($members as $index => $member) {
            $league->results()->create(['user_id' => $member->user_id, 'week' => 1, 'result' => $index === 0 ? 'past' : 'in_line', 'result_points' => $index === 0 ? 2 : 1, 'outcome' => $index === 0 ? 'close_win' : 'close_loss', 'matchup_points' => $index === 0 ? 2 : 1, 'opponent_id' => $members[1 - $index]->user_id]);
        }

        $this->artisan('goalline:rescore-leagues')->assertSuccessful();

        $rows = $league->results()->orderBy('user_id')->get()->keyBy('user_id');
        $first = $rows[$members[0]->user_id];
        $second = $rows[$members[1]->user_id];

        // 100.4% is Past the line, 99.6% In line: a higher band wins outright, 2 matchup stars to 0.
        $this->assertSame([MatchupOutcome::Win, 2, 2], [$first->outcome, $first->matchup_points, $first->result_points]);
        $this->assertSame([MatchupOutcome::Loss, 0, 1], [$second->outcome, $second->matchup_points, $second->result_points]);
        $this->assertSame(4, $first->result_points + $first->matchup_points);
    }
}
