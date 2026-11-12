<?php

namespace Tests\Feature;

use Anthropic\Client;
use App\Jobs\ReviewRoadmapChanges;
use App\Models\Goal;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachCheckFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A client that may be built but never used: any call to it fails the test.
        $this->mock(Client::class);
    }

    public function test_without_an_ai_key_the_coach_check_steps_aside_and_the_change_stands(): void
    {
        config(['services.anthropic.key' => null]);
        $goal = Goal::factory()->create();
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'pending_changes' => ['Accepted the plan']])->save();

        // No call is attempted (the test container would throw if the Claude client were even built).
        ReviewRoadmapChanges::dispatchSync($goal);

        $this->assertSame(RoadmapStatus::Failed, $goal->fresh()->plan_check_status);
    }

    public function test_accepting_a_plan_works_even_when_the_coach_check_cannot_run(): void
    {
        config(['services.anthropic.key' => null]);
        $goal = Goal::factory()->create();
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();

        $this->actingAs($goal->user)->post(route('goals.plan.accept', $goal))->assertRedirect(route('goals.show', $goal));
        $this->assertNotNull($goal->fresh()->plan_accepted_at);
    }
}
