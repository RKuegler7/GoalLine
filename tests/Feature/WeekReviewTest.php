<?php

namespace Tests\Feature;

use App\Exceptions\RoadmapPlanningException;
use App\Jobs\CoachWeekReview;
use App\Models\Goal;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Models\WeekReview;
use App\Notifications\CoachTipReady;
use App\RoadmapStatus;
use App\Services\RoadmapPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class WeekReviewTest extends TestCase
{
    use RefreshDatabase;

    private Goal $goal;

    private RoadmapWeek $week;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-24 10:00', config('app.timezone'))); // Saturday: review time.
        $this->goal = Goal::factory()->create(['title' => 'Launch the business', 'target_date' => '2026-12-31']);
        $this->goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $this->week = $this->goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Prototype', 'milestone' => 'Show three customers.']);
        $this->week->tasks()->create(['position' => 1, 'description' => 'Build the prototype']);
        $this->goal->weeks()->create(['position' => 2, 'starts_on' => '2026-10-26', 'ends_on' => '2026-11-01', 'focus' => 'Customers', 'milestone' => 'Five interviews.']);
    }

    public function test_the_review_opens_on_the_last_weekend_of_the_goalweek(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 10:00', config('app.timezone'))); // Thursday.
        $this->actingAs($this->goal->user)->get(route('goalweeks.review', $this->week))->assertRedirect();
        $this->actingAs($this->goal->user)->get(route('dashboard'))->assertDontSee('Wrap up GoalWeek 1.');

        $this->travelTo(Carbon::parse('2026-10-24 10:00', config('app.timezone'))); // Saturday.
        $this->actingAs($this->goal->user)->get(route('goalweeks.review', $this->week))->assertOk()->assertSee('How did it go?');
        $this->actingAs($this->goal->user)->get(route('dashboard'))->assertSee('Wrap up GoalWeek 1.');

        // Monday, check-ins are still open for last week: so is its review.
        $this->travelTo(Carbon::parse('2026-10-26 10:00', config('app.timezone')));
        $this->actingAs($this->goal->user)->get(route('dashboard'))->assertSee('Wrap up GoalWeek 1.');
    }

    public function test_saving_a_review_asks_the_coach(): void
    {
        Queue::fake();

        $this->actingAs($this->goal->user)
            ->post(route('goalweeks.review.store', $this->week), ['feeling' => 4, 'went_well' => 'Built it in two evenings.', 'in_the_way' => 'Work trip.'])
            ->assertRedirect(route('goalweeks.review', $this->week));

        $review = $this->week->review()->sole();
        $this->assertSame('Strong', $review->feelingLabel());
        $this->assertSame(RoadmapStatus::Pending, $review->coach_status);
        Queue::assertPushed(CoachWeekReview::class);

        $this->actingAs($this->goal->user)->get(route('goalweeks.review', $this->week))->assertSee('Your coach is reading your week');
    }

    public function test_the_coach_tip_shows_on_the_review_and_on_next_weeks_card(): void
    {
        Notification::fake();
        $this->partialMock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('coachTip')->once()->andReturn('Block Tuesday and Thursday mornings for customer calls before work.'));

        $this->actingAs($this->goal->user)->post(route('goalweeks.review.store', $this->week), ['in_the_way' => 'Work trip.']);

        $this->assertSame(RoadmapStatus::Ready, $this->week->review->coach_status);
        Notification::assertSentTo($this->goal->user, CoachTipReady::class);
        $this->actingAs($this->goal->user)->get(route('goalweeks.review', $this->week))->assertSee('Block Tuesday and Thursday mornings');

        $this->travelTo(Carbon::parse('2026-10-27 10:00', config('app.timezone'))); // Next GoalWeek.
        $this->actingAs($this->goal->user)->get(route('dashboard'))->assertSee('Coach&rsquo;s tip:', false)->assertSee('Block Tuesday and Thursday mornings');
    }

    public function test_a_failed_tip_can_be_retried_without_losing_the_reflection(): void
    {
        $this->partialMock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('coachTip')->andThrow(new RoadmapPlanningException('Nope.')));

        try {
            $this->actingAs($this->goal->user)->post(route('goalweeks.review.store', $this->week), ['went_well' => 'Shipped it.']);
        } catch (RoadmapPlanningException) {
            // The sync queue rethrows; on a real queue the job's failed() handler runs.
        }
        (new CoachWeekReview($this->week->review()->sole()))->failed(null);

        $this->actingAs($this->goal->user)->get(route('goalweeks.review', $this->week))->assertSee('Try again');

        Queue::fake();
        $this->actingAs($this->goal->user)->post(route('goalweeks.review.store', $this->week), ['retry' => 1]);

        $this->assertSame('Shipped it.', WeekReview::sole()->went_well);
        Queue::assertPushed(CoachWeekReview::class);
    }

    public function test_the_coach_reads_the_week_the_reflection_and_next_weeks_plan(): void
    {
        $this->week->tasks()->create(['position' => 2, 'description' => 'Book three calls'])->forceFill(['completed_at' => now()])->save();
        $this->goal->habits()->create(['title' => 'Cold outreach', 'times_per_week' => 3]);
        $review = $this->week->review()->create(['feeling' => 2, 'in_the_way' => 'Work trip.']);
        $this->goal->weeks()->where('position', 2)->sole()->tasks()->create(['position' => 1, 'description' => 'Interview five customers']);

        $brief = $this->partialMock(RoadmapPlanner::class)->coachBrief($review->fresh()->load('week.goal.weeks.tasks', 'week.goal.habits.logs', 'week.tasks'));

        $this->assertStringContainsString('[missed] Build the prototype', $brief);
        $this->assertStringContainsString('[done] Book three calls', $brief);
        $this->assertStringContainsString('Cold outreach: 0 of 3', $brief);
        $this->assertStringContainsString("<reflection>\nHow it felt: Meh", $brief);
        $this->assertStringContainsString('What got in the way: Work trip.', $brief);
        $this->assertStringContainsString('Interview five customers', $brief);
    }

    public function test_only_the_owner_can_review(): void
    {
        $this->actingAs(User::factory()->create())->get(route('goalweeks.review', $this->week))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('goalweeks.review.store', $this->week), ['went_well' => 'Hi'])->assertForbidden();
    }
}
