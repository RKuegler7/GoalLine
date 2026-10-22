<?php

namespace Tests\Feature;

use App\Badge;
use App\Models\Goal;
use App\Models\Habit;
use App\Models\User;
use App\Notifications\DailyReminder;
use App\RoadmapStatus;
use App\Services\DailyStreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DailyStreakTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Habit $habit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00', config('app.timezone'))); // A Wednesday morning.
        $this->user = User::factory()->create();
        $goal = Goal::factory()->for($this->user)->create(['target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Work', 'milestone' => 'Done.']);
        $this->habit = $goal->habits()->create(['title' => 'Run', 'times_per_week' => 7]);
    }

    public function test_the_streak_counts_consecutive_days_and_stays_alive_until_tonight(): void
    {
        $this->logDaysAgo(3, 2, 1); // Sunday, Monday, Tuesday.

        $streak = new DailyStreak($this->user);
        $this->assertSame(3, $streak->current());
        $this->assertTrue($streak->isAtRisk());

        $this->logDaysAgo(0);
        $streak = new DailyStreak($this->user);
        $this->assertSame(4, $streak->current());
        $this->assertFalse($streak->isAtRisk());
    }

    public function test_a_missed_day_resets_the_streak_and_task_check_ins_count_too(): void
    {
        $this->logDaysAgo(4, 3); // Then nothing Monday or Tuesday.
        $this->assertSame(0, (new DailyStreak($this->user))->current());

        $task = $this->user->goals()->first()->weeks()->first()->tasks()->create(['position' => 1, 'description' => 'Ship it']);
        $task->forceFill(['completed_at' => now()])->save();

        $this->assertSame(1, (new DailyStreak($this->user))->current());
    }

    public function test_the_clubhouse_shows_the_streak(): void
    {
        $this->actingAs($this->user)->get(route('dashboard'))->assertSee('Check in today to start a streak');

        $this->logDaysAgo(2, 1);
        $this->actingAs($this->user)->get(route('dashboard'))->assertSee('2-day streak &middot; check in today to keep it', false);

        $this->logDaysAgo(0);
        $this->actingAs($this->user)->get(route('dashboard'))->assertSee('3-day streak');
    }

    public function test_the_evening_nudge_saves_a_streak_even_with_nothing_planned(): void
    {
        Notification::fake();
        $this->logDaysAgo(2, 1);

        $this->travelTo(Carbon::parse('2026-10-21 20:00', config('app.timezone')));
        $this->artisan('goalline:send-reminders');

        Notification::assertSentTo($this->user, DailyReminder::class, fn (DailyReminder $reminder) => $reminder->moment === 'evening'
            && $reminder->headline === 'Keep your 2-day streak alive tonight');
    }

    public function test_seven_days_in_a_row_earns_week_warrior(): void
    {
        $this->logDaysAgo(6, 5, 4, 3, 2, 1);
        $this->assertFalse($this->user->badges()->where('badge', Badge::WeekWarrior)->exists());

        $this->logDaysAgo(0);
        $this->assertTrue($this->user->badges()->where('badge', Badge::WeekWarrior)->exists());
    }

    private function logDaysAgo(int ...$days): void
    {
        foreach ($days as $daysAgo) {
            $this->habit->logs()->create(['logged_on' => today()->subDays($daysAgo)]);
        }
    }
}
