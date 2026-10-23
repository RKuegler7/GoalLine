<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Notifications\DailyReminder;
use App\Notifications\GoalWeeksFinal;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_the_morning_gameplan_arrives_at_the_chosen_time_on_the_members_clock(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Los_Angeles']);
        $this->goalFor($user, ['Build the first prototype' => '2026-10-21', 'Book calls' => '2026-10-22']);

        // 7:15 AM in Los Angeles: too early for the default 7:30.
        $this->at('2026-10-21 07:15', 'America/Los_Angeles');
        Notification::assertNothingSentTo($user);

        $this->at('2026-10-21 07:30', 'America/Los_Angeles');
        Notification::assertSentTo($user, DailyReminder::class, fn (DailyReminder $reminder) => $reminder->moment === 'morning'
            && $reminder->headline === "Today's Gameplan: 1 thing on deck"
            && $reminder->items === ['Build the first prototype']);

        // Only once a day.
        $this->at('2026-10-21 07:45', 'America/Los_Angeles');
        Notification::assertSentToTimes($user, DailyReminder::class, 1);
    }

    public function test_the_evening_nudge_only_comes_when_work_is_left(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Chicago']);
        $week = $this->goalFor($user, ['Build the first prototype' => '2026-10-21', 'Book calls' => '2026-10-21']);

        $this->travelTo(Carbon::parse('2026-10-21 12:00', 'America/Chicago'));
        $week->tasks[0]->forceFill(['completed_at' => now()])->save();
        $this->at('2026-10-21 20:00', 'America/Chicago');
        Notification::assertSentTo($user, DailyReminder::class, fn (DailyReminder $reminder) => $reminder->moment === 'evening'
            && $reminder->headline === "1 thing left on today's gameplan"
            && $reminder->items === ['Book calls']);

        // Thursday: nothing planned, and an early check-in keeps the streak safe, so there's nothing to nudge about.
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-22 12:00', 'America/Chicago'));
        $week->tasks[1]->forceFill(['completed_at' => now()])->save();
        $this->at('2026-10-22 20:00', 'America/Chicago');
        Notification::assertNothingSentTo($user);
    }

    public function test_reminders_can_be_turned_off(): void
    {
        $user = User::factory()->create(['notification_settings' => ['morning_at' => null, 'evening_at' => null]]);
        $this->goalFor($user, ['Build the first prototype' => '2026-10-21']);

        $this->at('2026-10-21 07:30');
        $this->at('2026-10-21 20:00');

        Notification::assertNothingSentTo($user);
    }

    public function test_monday_mornings_prompt_planning_and_flag_check_ins_closing_tonight(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create(['title' => 'Launch the business', 'target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $last = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'A', 'milestone' => 'Done.']);
        $last->tasks()->create(['position' => 1, 'description' => 'Unfinished']);
        $this_ = $goal->weeks()->create(['position' => 2, 'starts_on' => '2026-10-26', 'ends_on' => '2026-11-01', 'focus' => 'B', 'milestone' => 'Done.']);
        $this_->tasks()->create(['position' => 1, 'description' => 'Kick off', 'planned_on' => '2026-10-26']);

        $this->at('2026-10-26 07:30');

        Notification::assertSentTo($user, DailyReminder::class, fn (DailyReminder $reminder) => str_contains($reminder->notes[0], 'A new GoalWeek starts today')
            && str_contains($reminder->notes[1], 'check-ins for GoalWeek 1 of “Launch the business” close tonight'));
    }

    public function test_final_results_arrive_the_morning_after_check_ins_close(): void
    {
        $user = User::factory()->create();
        $week = $this->goalFor($user, ['Build the first prototype' => '2026-10-21']);
        $week->tasks[0]->forceFill(['completed_at' => Carbon::parse('2026-10-21 12:00')])->save();

        $this->at('2026-10-26 07:30'); // Monday: still open.
        Notification::assertNotSentTo($user, GoalWeeksFinal::class);

        $this->at('2026-10-27 07:30'); // Tuesday: final.
        Notification::assertSentTo($user, GoalWeeksFinal::class, fn (GoalWeeksFinal $final) => $final->results[0]['result'] === 'In line' && $final->results[0]['points'] === 1);
    }

    public function test_members_who_have_not_finished_signing_up_get_nothing(): void
    {
        $user = User::factory()->unverified()->create();
        $this->goalFor($user, ['Build the first prototype' => '2026-10-21']);

        $this->at('2026-10-21 07:30');

        Notification::assertNothingSentTo($user);
    }

    /**
     * Run the reminders as if it were this time on the given clock.
     */
    private function at(string $time, string $timezone = 'America/New_York'): void
    {
        $this->travelTo(Carbon::parse($time, $timezone));
        $this->artisan('goalline:send-reminders')->assertSuccessful();
    }

    /**
     * @param  array<string, string|null>  $tasks
     */
    private function goalFor(User $user, array $tasks): RoadmapWeek
    {
        $goal = Goal::factory()->for($user)->create(['title' => 'Launch the business', 'target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Prototype', 'milestone' => 'Show three customers.']);

        $position = 0;
        foreach ($tasks as $description => $day) {
            $week->tasks()->create(['position' => ++$position, 'description' => $description, 'planned_on' => $day]);
        }

        return $week->load('tasks');
    }
}
