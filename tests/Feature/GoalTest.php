<?php

namespace Tests\Feature;

use App\GoalCategory;
use App\GoalStatus;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_set_goals(): void
    {
        $this->get(route('goals.create'))->assertRedirect(route('login'));
        $this->post(route('goals.store'))->assertRedirect(route('login'));
    }

    public function test_user_can_set_a_goal(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('goals.store'), [
            'title' => 'Pass the CPA exam',
            'category' => GoalCategory::Career->value,
            'target_date' => today()->addMonths(4)->format('Y-m-d'),
            'why' => 'It opens the door to the job I want.',
        ]);

        $goal = $user->goals()->sole();
        $response->assertRedirect(route('goals.next', $goal));

        $this->assertSame('Pass the CPA exam', $goal->title);
        $this->assertSame(GoalCategory::Career, $goal->category);
        $this->assertSame(GoalStatus::Active, $goal->fresh()->status);
        $this->assertTrue($goal->target_date->isSameDay(today()->addMonths(4)));
    }

    public function test_goal_requires_a_title_category_and_future_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('goals.store'), [
            'title' => '',
            'category' => 'not-a-category',
            'target_date' => today()->format('Y-m-d'),
        ])->assertSessionHasErrors(['title', 'category', 'target_date']);

        $this->assertSame(0, Goal::count());
    }

    public function test_user_can_view_and_update_their_goal(): void
    {
        $goal = Goal::factory()->create(['title' => 'Run a half marathon']);

        $this->actingAs($goal->user)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertSee('Run a half marathon');

        $this->actingAs($goal->user)->put(route('goals.update', $goal), [
            'title' => 'Run a full marathon',
            'category' => GoalCategory::Fitness->value,
            'target_date' => today()->addMonths(6)->format('Y-m-d'),
        ])->assertRedirect(route('goals.show', $goal));

        $this->assertSame('Run a full marathon', $goal->fresh()->title);
    }

    public function test_user_can_remove_their_goal(): void
    {
        $goal = Goal::factory()->create();

        $this->actingAs($goal->user)
            ->delete(route('goals.destroy', $goal))
            ->assertRedirect(route('dashboard'));

        $this->assertModelMissing($goal);
    }

    public function test_users_cannot_see_or_change_someone_elses_goal(): void
    {
        $goal = Goal::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('goals.show', $goal))->assertForbidden();
        $this->actingAs($stranger)->get(route('goals.edit', $goal))->assertForbidden();
        $this->actingAs($stranger)->put(route('goals.update', $goal), [
            'title' => 'Hijacked',
            'category' => GoalCategory::Other->value,
            'target_date' => today()->addMonth()->format('Y-m-d'),
        ])->assertForbidden();
        $this->actingAs($stranger)->delete(route('goals.destroy', $goal))->assertForbidden();

        $this->assertNotSame('Hijacked', $goal->fresh()->title);
    }

    public function test_dashboard_lists_only_the_users_own_goals(): void
    {
        $user = User::factory()->create();
        Goal::factory()->for($user)->create(['title' => 'Finish my thesis draft']);
        Goal::factory()->create(['title' => 'Someone else\'s goal']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Finish my thesis draft')
            ->assertDontSee('Someone else&#039;s goal', false);
    }

    public function test_days_remaining_counts_whole_days_until_the_target(): void
    {
        $goal = Goal::factory()->make(['target_date' => today()->addDays(30)]);

        $this->assertSame(30, $goal->daysRemaining());
    }
}
