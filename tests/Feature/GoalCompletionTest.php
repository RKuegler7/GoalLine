<?php

namespace Tests\Feature;

use App\GoalStatus;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoalCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_complete_a_goal_with_a_reflection_and_photo(): void
    {
        Storage::fake(Goal::PHOTO_DISK);
        $goal = Goal::factory()->create(['title' => 'Run a half marathon']);

        $this->actingAs($goal->user)->get(route('goals.complete', $goal))->assertOk()->assertSee('Did you cross the line?');

        $this->actingAs($goal->user)->post(route('goals.complete.store', $goal), [
            'completion_note' => 'Hardest thing I have trained for.',
            'completion_photo' => UploadedFile::fake()->image('finish.png'),
        ])->assertRedirect(route('goals.show', $goal));

        $goal->refresh();
        $this->assertSame(GoalStatus::Completed, $goal->status);
        $this->assertNotNull($goal->completed_at);
        Storage::disk(Goal::PHOTO_DISK)->assertExists($goal->completion_photo_path);

        $this->actingAs($goal->user)
            ->get(route('goals.show', $goal))
            ->assertSee('You crossed the GoalLine.')
            ->assertSee('Hardest thing I have trained for.');

        $this->actingAs($goal->user)->get(route('goals.photo', $goal))->assertOk();
    }

    public function test_completed_goals_are_read_only_and_appear_in_the_trophy_case(): void
    {
        $goal = Goal::factory()->create(['title' => 'Learn to swim']);
        $goal->complete();

        $this->actingAs($goal->user)->get(route('goals.edit', $goal))->assertForbidden();
        $this->actingAs($goal->user)->get(route('goals.complete', $goal))->assertForbidden();

        $this->actingAs($goal->user)
            ->get(route('profile.show'))
            ->assertSee('Learn to swim')
            ->assertSee('Completed');
    }

    public function test_only_the_owner_can_complete_or_see_the_photo(): void
    {
        $goal = Goal::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post(route('goals.complete.store', $goal))->assertForbidden();
        $this->actingAs($stranger)->get(route('goals.photo', $goal))->assertForbidden();
    }
}
