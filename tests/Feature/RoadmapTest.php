<?php

namespace Tests\Feature;

use App\Exceptions\RoadmapPlanningException;
use App\Jobs\GenerateRoadmap;
use App\Models\Goal;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\RoadmapPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class RoadmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_a_goal_queues_its_roadmap(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('goals.store'), [
            'title' => 'Pass the CPA exam',
            'category' => 'career',
            'target_date' => today()->addWeeks(8)->format('Y-m-d'),
        ]);

        $goal = $user->goals()->sole();
        $this->assertSame(RoadmapStatus::Pending, $goal->roadmap_status);
        Queue::assertPushed(GenerateRoadmap::class, fn (GenerateRoadmap $job) => $job->goal->is($goal));
    }

    public function test_stretches_follow_monday_to_sunday_weeks_ending_on_the_target_date(): void
    {
        $this->travelTo('2026-10-21'); // A Wednesday.
        $goal = Goal::factory()->make(['target_date' => '2026-11-10']);

        $periods = Mockery::mock(RoadmapPlanner::class)->makePartial()->periods($goal);

        $this->assertSame([
            ['2026-10-21', '2026-10-25'],
            ['2026-10-26', '2026-11-01'],
            ['2026-11-02', '2026-11-08'],
            ['2026-11-09', '2026-11-10'],
        ], array_map(fn (array $period) => [$period['starts_on']->toDateString(), $period['ends_on']->toDateString()], $periods));
    }

    public function test_a_one_or_two_day_first_week_folds_into_the_next(): void
    {
        $this->travelTo('2026-10-24'); // A Saturday.
        $goal = Goal::factory()->make(['target_date' => '2026-11-15']);

        $periods = Mockery::mock(RoadmapPlanner::class)->makePartial()->periods($goal);

        $this->assertSame('2026-11-01', $periods[0]['ends_on']->toDateString());
        $this->assertSame('2026-11-02', $periods[1]['starts_on']->toDateString());
    }

    public function test_goals_up_to_a_year_stay_weekly_and_longer_ones_use_multi_week_stretches(): void
    {
        $this->travelTo('2026-10-19'); // A Monday.
        $planner = Mockery::mock(RoadmapPlanner::class)->makePartial();

        $year = $planner->periods(Goal::factory()->make(['target_date' => '2027-10-17']));
        $this->assertCount(52, $year);

        $twoYears = $planner->periods(Goal::factory()->make(['target_date' => '2028-10-15']));
        $this->assertLessThanOrEqual(RoadmapPlanner::MAX_PERIODS, count($twoYears));
        $this->assertSame(20, (int) $twoYears[1]['starts_on']->diffInDays($twoYears[1]['ends_on'])); // 3-week stretches
        $this->assertSame('2028-10-15', end($twoYears)['ends_on']->toDateString());
    }

    public function test_job_saves_the_roadmap_with_dated_stretches_and_tasks(): void
    {
        $this->travelTo('2026-10-19'); // A Monday, so the two GoalWeeks are full weeks.
        $goal = Goal::factory()->create(['target_date' => today()->addDays(13)]);
        $this->fakePlanner([
            'summary' => 'Two focused weeks: learn it, then prove it.',
            'periods' => [
                ['focus' => 'Learn the material', 'milestone' => 'Finish every chapter.', 'tasks' => ['Read chapter one', 'Read chapter two']],
                ['focus' => 'Prove it', 'milestone' => 'Score 80% on a practice test.', 'tasks' => ['Take a practice test']],
            ],
        ]);

        GenerateRoadmap::dispatchSync($goal);

        $goal->refresh();
        $this->assertSame(RoadmapStatus::Ready, $goal->roadmap_status);
        $this->assertSame('Two focused weeks: learn it, then prove it.', $goal->roadmap_summary);
        $this->assertCount(2, $goal->weeks);
        $this->assertSame('Learn the material', $goal->weeks[0]->focus);
        $this->assertTrue($goal->weeks[1]->ends_on->isSameDay(today()->addDays(13)));
        $this->assertSame(['Read chapter one', 'Read chapter two'], $goal->weeks[0]->tasks->pluck('description')->all());
    }

    public function test_rebuilding_replaces_the_previous_roadmap(): void
    {
        $this->travelTo('2026-10-19');
        $goal = Goal::factory()->create(['target_date' => today()->addDays(6)]);
        $roadmap = fn (string $focus) => ['summary' => 'One week.', 'periods' => [['focus' => $focus, 'milestone' => 'Done.', 'tasks' => ['Do it']]]];

        $this->fakePlanner($roadmap('First plan'));
        GenerateRoadmap::dispatchSync($goal);
        $this->fakePlanner($roadmap('Second plan'));
        GenerateRoadmap::dispatchSync($goal);

        $this->assertSame(['Second plan'], $goal->weeks()->pluck('focus')->all());
    }

    public function test_a_failed_roadmap_is_marked_failed(): void
    {
        $goal = Goal::factory()->create();
        $this->mock(RoadmapPlanner::class, function (MockInterface $mock) {
            $mock->shouldReceive('periods')->andReturn([]);
            $mock->shouldReceive('plan')->andThrow(new RoadmapPlanningException('Nope.'));
        });

        try {
            GenerateRoadmap::dispatchSync($goal);
        } catch (RoadmapPlanningException) {
            // The sync queue rethrows after running failed().
        }

        $this->assertSame(RoadmapStatus::Failed, $goal->fresh()->roadmap_status);
    }

    public function test_owner_can_rebuild_but_others_cannot(): void
    {
        Queue::fake();
        $goal = Goal::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('goals.roadmap.store', $goal))
            ->assertForbidden();
        Queue::assertNothingPushed();

        $this->actingAs($goal->user)
            ->post(route('goals.roadmap.store', $goal))
            ->assertRedirect(route('goals.show', $goal));
        Queue::assertPushed(GenerateRoadmap::class);
    }

    public function test_goal_page_shows_the_roadmap(): void
    {
        $goal = Goal::factory()->create(['roadmap_summary' => 'A steady build.']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $week = $goal->weeks()->create([
            'position' => 1, 'starts_on' => today(), 'ends_on' => today()->addDays(6),
            'focus' => 'Lay the groundwork', 'milestone' => 'Book the exam date.',
        ]);
        $week->tasks()->create(['position' => 1, 'description' => 'Pick a study guide']);

        $this->actingAs($goal->user)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertSee('A steady build.')
            ->assertSee('Lay the groundwork')
            ->assertSee('Pick a study guide')
            ->assertSee('GoalWeek 1')
            ->assertSee('You are here');
    }

    public function test_multi_week_stretches_are_labelled_by_goalweek_range(): void
    {
        $goal = Goal::factory()->create();
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'roadmap_summary' => 'A long build.'])->save();
        $goal->weeks()->create([
            'position' => 1, 'starts_on' => today(), 'ends_on' => today()->addDays(13),
            'focus' => 'Validate the idea', 'milestone' => 'Talk to ten customers.',
        ]);

        $this->assertSame(2, $goal->weeksPerStretch());
        $this->assertSame('GoalWeeks 1–2', $goal->weeks->first()->label());

        $this->actingAs($goal->user)
            ->get(route('goals.show', $goal))
            ->assertSee('GoalWeeks 1–2')
            ->assertSee('2 GoalWeeks');
    }

    /**
     * Swap the planner's Claude call for a canned roadmap, keeping the real date math.
     *
     * @param  array<string, mixed>  $roadmap
     */
    private function fakePlanner(array $roadmap): void
    {
        $this->partialMock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('plan')->andReturn($roadmap));
    }
}
