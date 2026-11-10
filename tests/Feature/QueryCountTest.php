<?php

namespace Tests\Feature;

use App\HighlightKind;
use App\Models\Goal;
use App\Models\Highlight;
use App\Models\League;
use App\Models\User;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * No N+1 queries on the busiest screens: the number of queries stays flat as the data grows.
 */
class QueryCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-22 10:00');
    }

    public function test_the_highlights_feed_does_not_query_once_per_post(): void
    {
        $viewer = User::factory()->create();
        [$few, $many] = [$this->queriesForFeed($viewer, 3), $this->queriesForFeed($viewer, 12)];

        $this->assertLessThanOrEqual($few + 2, $many, "Highlights: {$few} queries for 3 posts, {$many} for 15.");
    }

    public function test_the_dashboard_does_not_query_once_per_goal_or_friend(): void
    {
        $viewer = User::factory()->create();
        $few = $this->queriesForDashboard($viewer, 2);
        $many = $this->queriesForDashboard($viewer, 6);

        $this->assertLessThanOrEqual($few + 4, $many, "Dashboard: {$few} queries with 2 goals, {$many} with 8.");
    }

    public function test_the_matchup_pager_does_not_query_once_per_member(): void
    {
        $few = $this->queriesForMatchups(4);
        $many = $this->queriesForMatchups(10);

        $this->assertLessThanOrEqual($few + 4, $many, "Matchups: {$few} queries for 4 members, {$many} for 10.");
    }

    private function queriesForFeed(User $viewer, int $posts): int
    {
        foreach (range(1, $posts) as $index) {
            $friend = User::factory()->create();
            $this->makeFriends($viewer, $friend);
            Highlight::factory()->for($friend)->create(['kind' => HighlightKind::GoalCompleted, 'headline' => "Goal {$index}"]);
        }

        return $this->countQueries(fn () => $this->actingAs($viewer)->get(route('highlights.index'))->assertOk());
    }

    private function queriesForDashboard(User $viewer, int $goals): int
    {
        foreach (range(1, $goals) as $index) {
            $goal = Goal::factory()->for($viewer)->create(['title' => "Goal {$index}", 'target_date' => '2027-01-31']);
            $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
            $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Focus', 'milestone' => 'Milestone']);
            $week->tasks()->create(['position' => 1, 'description' => 'Task', 'planned_on' => '2026-10-22']);

            $friend = User::factory()->create();
            $this->makeFriends($viewer, $friend);
            Highlight::factory()->for($friend)->create();
        }

        return $this->countQueries(fn () => $this->actingAs($viewer)->get(route('dashboard'))->assertOk());
    }

    private function queriesForMatchups(int $members): int
    {
        $league = League::factory()->create(['starts_on' => '2026-10-19', 'ends_on' => '2026-11-15']);

        foreach (range(1, $members) as $index) {
            $user = $index === 1 ? $league->commissioner : User::factory()->create();
            $goal = Goal::factory()->for($user)->create(['target_date' => '2027-01-31']);
            $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
            $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Focus', 'milestone' => 'Milestone']);
            $week->tasks()->create(['position' => 1, 'description' => 'Task', 'planned_on' => '2026-10-20'])->forceFill(['completed_at' => now()])->save();
            $league->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);
        }

        return $this->countQueries(fn () => $this->actingAs($league->commissioner)->get(route('leagues.matchups', $league))->assertOk());
    }

    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
