<?php

namespace Tests\Feature;

use App\Badge;
use App\Models\Goal;
use App\Models\Habit;
use App\Models\Highlight;
use App\Models\User;
use App\Notifications\WeeklyRecapReady;
use App\RoadmapStatus;
use App\Services\WeeklyRecap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WeeklyRecapTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Habit $habit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-25 12:00', config('app.timezone'))); // Sunday.
        $this->user = User::factory()->create();
        $goal = Goal::factory()->for($this->user)->create(['title' => 'Launch the business', 'target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Prototype', 'milestone' => 'Done.']);
        $week->tasks()->create(['position' => 1, 'description' => 'Build it'])->forceFill(['completed_at' => '2026-10-21 10:00'])->saveQuietly();
        $week->forceFill(['milestone_hit_at' => '2026-10-24 10:00'])->save(); // 1 task + 3 runs + milestone: 6 of 6, in line.
        $this->habit = $goal->habits()->create(['title' => 'Run', 'times_per_week' => 3]);
        foreach (['2026-10-20', '2026-10-21', '2026-10-25'] as $day) {
            $this->habit->logs()->create(['logged_on' => $day]);
        }
        $this->habit->logs()->create(['logged_on' => '2026-10-15']); // Last week: not counted.
    }

    public function test_it_sums_up_the_week(): void
    {
        $highlight = Highlight::factory()->for($this->user)->create();
        $highlight->cheers()->create(['user_id' => User::factory()->create()->id]);

        $recap = new WeeklyRecap($this->user);

        $this->assertSame(4, $recap->checkIns());
        $this->assertSame(3, $recap->activeDays());
        $this->assertSame('2026-10-21', $recap->bestDay()['date']->toDateString());
        $this->assertSame(1, $recap->cheersReceived());
        $this->assertTrue($recap->badgesEarned()->contains('badge', Badge::FirstStep)); // Earned automatically by this week's first check-in.
        $this->assertSame('In line', $recap->goalWeeks()->first()['result']->label());
        $this->assertSame('Your week: 4 check-ins on 3 days, 1 point.', $recap->headline());
    }

    public function test_the_page_shows_this_week_and_last_week(): void
    {
        $this->actingAs($this->user)
            ->get(route('recap.week'))
            ->assertOk()
            ->assertSee('Your process this week')
            ->assertSee('Launch the business')
            ->assertSee('Plan my week');

        $this->actingAs($this->user)->get(route('recap.week', ['week' => 'last']))->assertSee('Oct 12');
    }

    public function test_the_recap_goes_out_sunday_evening_once(): void
    {
        Notification::fake();
        $this->user->forceFill(['notification_settings' => ['evening_at' => null]])->save();

        $this->travelTo(Carbon::parse('2026-10-25 17:45', config('app.timezone')));
        $this->artisan('goalline:send-reminders');
        Notification::assertNotSentTo($this->user, WeeklyRecapReady::class);

        $this->travelTo(Carbon::parse('2026-10-25 18:00', config('app.timezone')));
        $this->artisan('goalline:send-reminders');
        $this->travelTo(Carbon::parse('2026-10-25 18:15', config('app.timezone')));
        $this->artisan('goalline:send-reminders');

        Notification::assertSentToTimes($this->user, WeeklyRecapReady::class, 1);
        Notification::assertSentTo($this->user, WeeklyRecapReady::class, fn (WeeklyRecapReady $recap) => $recap->headline === 'Your week: 4 check-ins on 3 days, 1 point.');
    }

    public function test_a_quiet_week_gets_a_gentle_nudge(): void
    {
        $quiet = User::factory()->create();

        $this->assertSame('A quiet week. A fresh GoalWeek starts tomorrow: plan it and get back in line.', (new WeeklyRecap($quiet))->headline());
    }
}
