<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\League;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PersonalTaskTest extends TestCase
{
    use RefreshDatabase;

    private User $avery;

    private RoadmapWeek $week;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->travelTo(Carbon::parse('2026-10-28 12:00', config('app.timezone')));
        $this->avery = User::factory()->create(['name' => 'Avery Lee']);
        $goal = Goal::factory()->for($this->avery)->create(['target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();

        // AI-planned: tasks worth 6, 6, 6 and 18 plus the 2-point milestone = 38 points.
        $this->week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-08', 'focus' => 'Base', 'milestone' => 'Done.']);
        foreach ([1, 2, 3] as $position) {
            $this->week->tasks()->create(['position' => $position, 'description' => "Planned {$position}"])->forceFill(['points' => 6])->save();
        }
        $this->week->tasks()->create(['position' => 4, 'description' => 'Planned 4'])->forceFill(['points' => 18])->save();
    }

    public function test_personal_tasks_count_in_the_plan_and_are_capped_at_15_percent(): void
    {
        $week = $this->week->fresh('tasks');
        $this->assertSame(38, $week->aiPlannedPoints());
        $this->assertSame(5, $week->personalPointsLeft()); // 15% of 38 is 5.7, rounded down.

        $this->add('Stretch 10 minutes', 3)->assertSessionMissing('errors');
        $week = $this->week->fresh('tasks');
        $this->assertSame(41, $week->plannedPoints() - $week->habits()->sum(fn ($habit) => $habit->targetFor($week)));
        $this->assertSame(2, $week->personalPointsLeft());

        $this->add('Foam roll', 3)->assertSessionHasErrorsIn('week'.$this->week->id, ['description' => 'Only 2 personal points left this GoalWeek (15% of the planned points).']);
        $this->add('Foam roll', 6)->assertSessionHasErrorsIn('week'.$this->week->id, 'points');
        $this->add('Foam roll', 2)->assertSessionMissing('errors');
        $this->add('Ice bath', 1)->assertSessionHasErrorsIn('week'.$this->week->id, ['description' => 'No personal points left this GoalWeek. Personal tasks can add up to 15% of the planned points.']);

        $personal = $this->week->tasks()->where('is_personal', true)->get();
        $this->assertSame(['Stretch 10 minutes', 'Foam roll'], $personal->pluck('description')->all());
        $this->assertFalse($personal->first()->is_bonus);
    }

    public function test_at_most_three_personal_tasks_a_week(): void
    {
        $this->week->tasks()->where('position', 4)->first()->forceFill(['points' => 80])->save(); // Plenty of allowance.

        foreach (['One', 'Two', 'Three'] as $title) {
            $this->add($title, 1)->assertSessionMissing('errors');
        }

        $this->add('Four', 1)->assertSessionHasErrorsIn('week'.$this->week->id, ['description' => 'You can add up to 3 personal tasks a GoalWeek.']);
    }

    public function test_personal_tasks_can_be_added_until_tuesday_night_of_the_week(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 23:30', config('app.timezone'))); // Tuesday night.
        $this->add('Stretch', 1)->assertSessionMissing('errors');

        $this->travelTo(Carbon::parse('2026-11-04 00:01', config('app.timezone'))); // Wednesday.
        $this->add('Too late', 1)->assertSessionHasErrorsIn('week'.$this->week->id, ['description' => 'Personal tasks can be added until Tuesday 11:59 p.m. of the GoalWeek.']);
        $this->assertSame(1, $this->week->tasks()->where('is_personal', true)->count());
    }

    public function test_the_league_sees_who_added_a_personal_task(): void
    {
        $sam = User::factory()->create(['name' => 'Sam Okafor']);
        $samGoal = Goal::factory()->for($sam)->create(['target_date' => '2026-12-31']);
        $samGoal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $samGoal->weeks()->create(['position' => 1, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-08', 'focus' => 'Base', 'milestone' => 'Done.']);
        $league = League::factory()->create(['commissioner_id' => $sam->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-29']);
        $league->members()->create(['user_id' => $this->avery->id, 'goal_id' => $this->week->goal_id]);
        $league->members()->create(['user_id' => $sam->id, 'goal_id' => $samGoal->id]);

        $this->add('Stretch 10 minutes', 2);
        $this->actingAs($this->avery)->get(route('goals.show', $this->week->goal))->assertSee('Added by you');

        $this->travelTo(Carbon::parse('2026-11-03 12:00', config('app.timezone')));
        $this->actingAs($sam)->get(route('leagues.show', $league))->assertSeeInOrder(['Added by Avery:', 'Stretch 10 minutes (2 pts)']);
    }

    private function add(string $description, int $points): TestResponse
    {
        return $this->actingAs($this->avery)->post(route('roadmap.tasks.store', $this->week), ['description' => $description, 'points' => $points]);
    }
}
