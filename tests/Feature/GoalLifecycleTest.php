<?php

namespace Tests\Feature;

use App\GoalCategory;
use App\GoalStatus;
use App\Models\Goal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_goal_in_its_trial_can_be_removed_outright(): void
    {
        $goal = Goal::factory()->create();

        $this->actingAs($goal->user)
            ->get(route('goals.leave', $goal))
            ->assertOk()
            ->assertSee('Remove this goal?');

        $this->actingAs($goal->user)
            ->delete(route('goals.destroy', $goal))
            ->assertRedirect(route('dashboard'));

        $this->assertModelMissing($goal);
    }

    public function test_a_goal_in_its_trial_cannot_be_retired(): void
    {
        $goal = Goal::factory()->create();

        $this->actingAs($goal->user)->post(route('goals.retire', $goal))->assertForbidden();

        $this->assertTrue($goal->fresh()->isActive());
    }

    public function test_after_the_trial_a_goal_is_locked_in_and_can_only_be_retired(): void
    {
        $goal = Goal::factory()->create();
        $this->travel(Goal::TRIAL_DAYS + 1)->days();

        $this->actingAs($goal->user)
            ->get(route('goals.leave', $goal))
            ->assertOk()
            ->assertSee('Retire this goal?');

        $this->actingAs($goal->user)->delete(route('goals.destroy', $goal))->assertForbidden();
        $this->assertModelExists($goal);

        $this->actingAs($goal->user)
            ->post(route('goals.retire', $goal))
            ->assertRedirect(route('dashboard'));

        $goal->refresh();
        $this->assertSame(GoalStatus::Retired, $goal->status);
        $this->assertNotNull($goal->retired_at);
    }

    public function test_retired_goals_leave_the_active_list_but_keep_their_history(): void
    {
        $goal = Goal::factory()->create(['title' => 'Learn Spanish']);
        $goal->retire();

        $this->actingAs($goal->user)
            ->get(route('dashboard'))
            ->assertSee('Retired goals')
            ->assertSee('Learn Spanish')
            ->assertSee('Set your first goal');

        $this->actingAs($goal->user)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertSee('You retired this goal')
            ->assertDontSee('Edit goal');
    }

    public function test_retired_goals_are_read_only(): void
    {
        $goal = Goal::factory()->create();
        $goal->retire();

        $this->actingAs($goal->user)->get(route('goals.edit', $goal))->assertForbidden();
        $this->actingAs($goal->user)->put(route('goals.update', $goal), [
            'title' => 'Changed',
            'category' => GoalCategory::Other->value,
            'target_date' => today()->addMonth()->format('Y-m-d'),
        ])->assertForbidden();
        $this->actingAs($goal->user)->post(route('goals.roadmap.store', $goal))->assertForbidden();
    }

    public function test_goal_page_shows_the_trial_or_lock_in_state(): void
    {
        $goal = Goal::factory()->create();

        $this->actingAs($goal->user)->get(route('goals.show', $goal))->assertSee('Trial:')->assertSee('Remove goal');

        $this->travel(Goal::TRIAL_DAYS + 1)->days();

        $this->actingAs($goal->user)->get(route('goals.show', $goal))->assertSee('Locked in')->assertSee('Retire goal');
    }
}
