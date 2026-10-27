<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\League;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClubhouseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00', config('app.timezone'))); // A Wednesday morning.
    }

    public function test_clubhouse_greets_by_time_of_day_and_shows_the_scoreboard(): void
    {
        $user = User::factory()->create(['name' => 'Jordan Rivera']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee('Good morning, Jordan.')
            ->assertSee('GoalWeek ends Sunday, 4 days left')
            ->assertSeeInOrder(['This GoalWeek', 'Total points', 'In-line streak', 'GoalLeague record']);
    }

    public function test_tasks_can_be_checked_off_right_from_the_clubhouse(): void
    {
        [$goal, $week] = $this->goalWithCurrentWeek();
        $task = $week->tasks->first();

        $this->actingAs($goal->user)
            ->get(route('dashboard'))
            ->assertSee('Build the first prototype')
            ->assertSee('0 of 4 points (0%)');

        $this->actingAs($goal->user)
            ->post(route('roadmap.tasks.check', $task), ['return' => 'clubhouse'])
            ->assertRedirect(route('dashboard').'#goal-'.$goal->id);

        $this->assertNotNull($task->fresh()->completed_at);
        $this->actingAs($goal->user)->get(route('dashboard'))->assertSee('1 of 4 points (25%)');
    }

    public function test_week_at_a_glance_counts_check_ins_per_day(): void
    {
        [$goal, $week] = $this->goalWithCurrentWeek();
        $week->tasks[0]->forceFill(['completed_at' => now()->subDay()])->save();
        $week->tasks[1]->forceFill(['completed_at' => now()])->save();

        $this->actingAs($goal->user)
            ->get(route('dashboard'))
            ->assertSee('Week at a glance')
            ->assertSee('2 check-ins')
            ->assertSee('2 of 7 days');
    }

    public function test_goalleagues_show_rank_and_this_weeks_matchup(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 09:00', config('app.timezone')));
        $league = League::factory()->create(['name' => 'Launch Crew', 'starts_on' => '2026-10-19', 'ends_on' => '2026-11-15']);
        $me = $league->commissioner;
        $sam = User::factory()->create(['name' => 'Sam Okafor']);
        $league->members()->create(['user_id' => $me->id]);
        $league->members()->create(['user_id' => $sam->id]);
        $this->travelTo(Carbon::parse('2026-10-21 09:00', config('app.timezone')));

        $this->actingAs($me)
            ->get(route('dashboard'))
            ->assertSee('Launch Crew')
            ->assertSee('Week 1 of 4')
            ->assertSee('1st')
            ->assertSee("This week's GoalGroup", false)
            ->assertSee('Sam')
            ->assertSee('League chat');
    }

    public function test_a_great_week_prompts_you_to_share_it(): void
    {
        [$goal, $week] = $this->goalWithCurrentWeek();
        $week->tasks->each(fn ($task) => $task->forceFill(['completed_at' => now()])->save());
        $week->forceFill(['milestone_hit_at' => now()])->save();
        $week->tasks()->create(['position' => 3, 'description' => 'Extra demo', 'is_bonus' => true])->forceFill(['completed_at' => now()])->save(); // 5 of 4 points: past the line.

        $this->actingAs($goal->user)
            ->get(route('dashboard'))
            ->assertSee('Past the line in GoalWeek 1')
            ->assertSee(route('highlights.create', ['week' => $week]), false);
    }

    public function test_scoring_is_explained_on_the_clubhouse(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertSee('How scoring works')
            ->assertSee('wins 2, the same band draws 1');
    }

    /**
     * @return array{Goal, RoadmapWeek}
     */
    private function goalWithCurrentWeek(): array
    {
        $goal = Goal::factory()->create(['title' => 'Launch the business', 'target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'roadmap_summary' => 'Plan.'])->save();
        $week = $goal->weeks()->create([
            'position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25',
            'focus' => 'Prototype', 'milestone' => 'Show the prototype to three customers.',
        ]);
        $week->tasks()->create(['position' => 1, 'description' => 'Build the first prototype']);
        $week->tasks()->create(['position' => 2, 'description' => 'Book three customer calls']);

        return [$goal, $week->load('tasks')];
    }
}
