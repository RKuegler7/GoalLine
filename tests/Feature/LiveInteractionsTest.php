<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckInController;
use App\Models\Friendship;
use App\Models\Goal;
use App\Models\Habit;
use App\Models\Highlight;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Live interactions: actions update the page in place with JavaScript, and the same forms keep working without it.
 * The server answers both the same way (a redirect to the updated page); the browser swaps the page's data-region
 * parts and shows the status message as a toast.
 */
class LiveInteractionsTest extends TestCase
{
    use RefreshDatabase;

    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html'];

    private User $me;

    private RoadmapWeek $week;

    private RoadmapTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-04 10:00', config('app.timezone'))); // A Wednesday.
        $this->me = User::factory()->create();
        $goal = Goal::factory()->for($this->me)->create(['target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'plan_accepted_at' => now()])->save();
        $this->week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-08', 'focus' => 'Build', 'milestone' => 'Done']);
        $this->task = $this->week->tasks()->create(['position' => 1, 'description' => 'Study block', 'planned_on' => '2026-11-04']);
    }

    public function test_action_forms_are_real_forms_marked_live_inside_refreshable_regions(): void
    {
        $page = $this->actingAs($this->me)->get(route('dashboard'))->assertOk()->getContent();

        // Without JavaScript these are ordinary POST forms with a CSRF token; with it, live.js takes over.
        $this->assertMatchesRegularExpression('#<form method="POST" action="'.preg_quote(route('roadmap.tasks.check', $this->task), '#').'" data-live data-optimistic="check" data-celebrate="check" data-live-key="task-'.$this->task->id.'">#', $page);
        $this->assertStringContainsString('name="intent" value="done"', $page);
        $this->assertStringContainsString('data-region="gameplan"', $page);
        $this->assertStringContainsString('data-region="clubhouse-goal-'.$this->week->goal_id.'"', $page);
        $this->assertStringContainsString('data-progress-bar', $page);

        $this->actingAs($this->me)->get(route('goals.show', $this->week->goal))->assertSee('data-region="goalweek-'.$this->week->id.'"', false);
    }

    public function test_a_live_check_in_returns_the_updated_page_with_its_toast(): void
    {
        $response = $this->actingAs($this->me)->withHeaders(self::AJAX)
            ->post(route('roadmap.tasks.check', $this->task), ['intent' => 'done', 'return' => 'gameplan']);

        $response->assertRedirect(route('dashboard').'#gameplan')->assertSessionHas('status', CheckInController::CHECKED_IN);
        $this->assertNotNull($this->task->fresh()->completed_at);

        // The page live.js reads back: the region now shows the task done, and the status becomes the toast.
        $this->actingAs($this->me)->get(route('dashboard'))
            ->assertSee('data-flash', false)->assertSee(CheckInController::CHECKED_IN)
            ->assertSee('data-check="done"', false)->assertSee('name="intent" value="open"', false);
    }

    public function test_without_javascript_check_in_and_friend_requests_still_work(): void
    {
        $this->actingAs($this->me)->post(route('roadmap.tasks.check', $this->task))
            ->assertRedirect(route('goals.show', $this->week->goal).'#goalweek-1');
        $this->assertNotNull($this->task->fresh()->completed_at);

        $friend = User::factory()->create();
        $this->actingAs($this->me)->from(route('friends.index'))->post(route('friends.request', $friend))
            ->assertRedirect(route('friends.index'))->assertSessionHas('status');
        $this->assertSame(Friendship::PENDING, $this->me->friendshipWith($friend)->status);

        // The same request, live, does the same thing.
        $other = User::factory()->create();
        $this->actingAs($this->me)->withHeaders(self::AJAX)->from(route('friends.index'))->post(route('friends.request', $other))
            ->assertRedirect(route('friends.index'));
        $this->assertNotNull($this->me->friendshipWith($other));
    }

    public function test_a_double_tap_never_undoes_itself(): void
    {
        foreach ([1, 2] as $tap) {
            $this->actingAs($this->me)->withHeaders(self::AJAX)->post(route('roadmap.tasks.check', $this->task), ['intent' => 'done']);
        }
        $this->assertNotNull($this->task->fresh()->completed_at, 'Two taps on "check off" leave it checked.');

        foreach ([1, 2] as $tap) {
            $this->actingAs($this->me)->post(route('roadmap.tasks.check', $this->task), ['intent' => 'open']);
        }
        $this->assertNull($this->task->fresh()->completed_at, 'Two taps on "undo" leave it open.');

        $habit = Habit::factory()->for($this->week->goal)->create(['times_per_week' => 7]);
        $habit->forceFill(['created_at' => '2026-10-20'])->save();
        foreach ([1, 2] as $tap) {
            $this->actingAs($this->me)->post(route('habits.log', $habit), ['intent' => 'done']);
        }
        $this->assertSame(1, $habit->logs()->count());

        foreach ([1, 2] as $tap) {
            $this->actingAs($this->me)->post(route('goalweeks.milestone', $this->week), ['intent' => 'done']);
        }
        $this->assertNotNull($this->week->fresh()->milestone_hit_at);

        $post = Highlight::factory()->create();
        $friend = $post->user;
        $this->makeFriends($this->me, $friend);
        foreach ([1, 2] as $tap) {
            $this->actingAs($this->me)->post(route('highlights.cheer', $post), ['intent' => 'add']);
        }
        $this->assertSame(1, $post->cheers()->count());
    }

    public function test_live_requests_are_authorized_exactly_like_normal_ones(): void
    {
        // Signed out: live or not, the request goes to the sign-in page (live.js follows it there).
        $this->withHeaders(self::AJAX)->post(route('roadmap.tasks.check', $this->task))->assertRedirect(route('login'));

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->withHeaders(self::AJAX)->post(route('roadmap.tasks.check', $this->task), ['intent' => 'done'])->assertForbidden();
        $this->assertNull($this->task->fresh()->completed_at);
    }
}
