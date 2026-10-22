<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Opens every main page as a signed-in member, so a broken page can't slip through unnoticed.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return [
            'clubhouse' => ['dashboard'],
            'plan my week' => ['planner.show'],
            'new goal' => ['goals.create'],
            'goal' => ['goals.show'],
            'edit goal' => ['goals.edit'],
            'leave goal' => ['goals.leave'],
            'complete goal' => ['goals.complete'],
            'goalleagues' => ['leagues.index'],
            'start a goalleague' => ['leagues.create'],
            'goalleague' => ['leagues.show'],
            'highlights' => ['highlights.index'],
            'find people' => ['people.index'],
            'my locker' => ['profile.show'],
            'edit locker' => ['profile.edit'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_opens(string $route): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create();
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'roadmap_summary' => 'A plan.'])->save();
        $week = $goal->weeks()->create(['position' => 1, 'starts_on' => today(), 'ends_on' => today()->addDays(6), 'focus' => 'Focus', 'milestone' => 'Milestone']);
        $week->tasks()->create(['position' => 1, 'description' => 'A task']);
        $league = League::factory()->create(['commissioner_id' => $user->id]);
        $league->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);

        $parameters = match (true) {
            str_starts_with($route, 'goals.') && $route !== 'goals.create' => $goal,
            $route === 'leagues.show' => $league,
            default => [],
        };

        $this->actingAs($user)->get(route($route, $parameters))->assertOk();
    }
}
