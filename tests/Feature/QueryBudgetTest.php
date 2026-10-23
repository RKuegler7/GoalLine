<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Goal;
use App\Models\Highlight;
use App\Models\League;
use App\Models\User;
use App\ProfileVisibility;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Keeps the main pages fast: a busy member's page shouldn't need more than a set number of database queries.
 * If one of these fails after a change, look for a relation that's loaded inside a loop.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function pages(): array
    {
        return [
            'clubhouse' => ['dashboard', 70],
            'plan my week' => ['planner.show', 15],
            'my locker' => ['profile.show', 30],
            'highlights' => ['highlights.index', 40],
            'goalleague' => ['leagues.show', 30],
            'challenge' => ['challenges.show', 15],
            'goal' => ['goals.show', 15],
        ];
    }

    #[DataProvider('pages')]
    public function test_busy_pages_stay_within_their_query_budget(string $route, int $budget): void
    {
        [$me, $league, $challenge] = $this->busyMember();
        $parameters = match ($route) {
            'leagues.show' => [$league],
            'challenges.show' => [$challenge],
            'goals.show' => [$me->goals()->first()],
            default => [],
        };

        // The first visit can settle finished league weeks; budgets are for everyday visits after that.
        $this->actingAs($me)->get(route($route, $parameters))->assertOk();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $this->actingAs($me)->get(route($route, $parameters))->assertOk();

        $this->assertLessThanOrEqual($budget, $queries, "{$route} ran {$queries} queries; its budget is {$budget}.");
    }

    /**
     * A member with three goals, a full GoalLeague of friends, highlights, and a challenge, mid-season.
     *
     * @return array{User, League, Challenge}
     */
    private function busyMember(): array
    {
        $this->travelTo(Carbon::parse('2026-09-01 09:00', config('app.timezone')));
        $me = User::factory()->create(['profile_visibility' => ProfileVisibility::Everyone]);
        $friends = User::factory()->count(5)->create(['profile_visibility' => ProfileVisibility::Everyone]);
        $league = League::factory()->create(['commissioner_id' => $me->id, 'starts_on' => '2026-09-07', 'ends_on' => '2026-11-29']);

        foreach ([$me, ...$friends] as $person) {
            foreach (range(1, 3) as $number) {
                $goal = Goal::factory()->for($person)->create(['target_date' => '2026-12-31']);
                $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();

                foreach (range(0, 11) as $offset) {
                    $monday = Carbon::parse('2026-09-07')->addWeeks($offset);
                    $week = $goal->weeks()->create(['position' => $offset + 1, 'starts_on' => $monday, 'ends_on' => $monday->copy()->addDays(6), 'focus' => 'Focus', 'milestone' => 'Milestone']);

                    foreach (range(1, 3) as $task) {
                        $week->tasks()->create(['position' => $task, 'description' => "Task {$task}", 'planned_on' => $monday->copy()->addDays($task)]);
                    }
                }

                $goal->habits()->create(['title' => 'Run', 'times_per_week' => 3]);
            }

            $league->members()->create(['user_id' => $person->id, 'goal_id' => $person->goals()->first()->id]);

            if (! $person->is($me)) {
                $me->following()->attach($person);
                $person->following()->attach($me);
                Highlight::factory()->for($person)->count(2)->create();
            }
        }

        $challenge = Challenge::factory()->for($me, 'creator')->create(['starts_on' => '2026-10-19', 'ends_on' => '2026-10-31']);
        foreach ([$me, ...$friends] as $person) {
            $challenge->members()->create(['user_id' => $person->id]);
        }

        $this->travelTo(Carbon::parse('2026-10-21 09:00', config('app.timezone')));

        return [$me, $league, $challenge];
    }
}
