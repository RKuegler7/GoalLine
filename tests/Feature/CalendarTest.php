<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00', config('app.timezone'))); // A Wednesday morning.
    }

    public function test_the_feed_has_planned_tasks_habits_milestones_and_target_dates(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create(['title' => 'Launch the business', 'target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Prototype', 'milestone' => 'Show three customers, and listen.']);
        $week->tasks()->create(['position' => 1, 'description' => 'Build the first prototype', 'planned_on' => '2026-10-22']);
        $done = $week->tasks()->create(['position' => 2, 'description' => 'Book calls', 'planned_on' => '2026-10-20']);
        $done->forceFill(['completed_at' => now()])->saveQuietly();
        $week->tasks()->create(['position' => 3, 'description' => 'Not on a day yet']);
        $goal->habits()->create(['title' => 'Run', 'times_per_week' => 3, 'days' => [1, 3, 5]]);

        $response = $this->get($user->calendarUrl())
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

        $ics = str_replace("\r\n ", '', $response->getContent()); // Unfold long lines.

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("DTSTART;VALUE=DATE:20261022\r\nDTEND;VALUE=DATE:20261023\r\nSUMMARY:Build the first prototype", $ics);
        $this->assertStringContainsString('SUMMARY:✓ Book calls', $ics);
        $this->assertStringNotContainsString('Not on a day yet', $ics);
        $this->assertStringContainsString('SUMMARY:Milestone: Show three customers\, and listen.', $ics);
        $this->assertStringContainsString('SUMMARY:GoalLine: Launch the business', $ics);
        $this->assertStringContainsString('RRULE:FREQ=WEEKLY;BYDAY=MO,WE,FR;UNTIL=20261231', $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);

        foreach (explode("\r\n", $response->getContent()) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
        }
    }

    public function test_the_feed_needs_the_right_token_and_a_new_link_retires_the_old_one(): void
    {
        $user = User::factory()->create();
        $oldUrl = $user->calendarUrl();

        $this->get(route('calendar.feed', 'not-the-token'))->assertNotFound();

        $this->actingAs($user)->post(route('calendar.reset'))->assertRedirect(route('settings.notifications').'#calendar');

        $this->get($oldUrl)->assertNotFound();
        $this->get($user->fresh()->calendarUrl())->assertOk();
    }

    public function test_the_settings_page_offers_one_tap_subscribe_links(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.notifications'))
            ->assertSee('Add GoalLine to your calendar')
            ->assertSee('https://calendar.google.com/calendar/render?cid=webcal', false)
            ->assertSee($user->fresh()->calendarUrl());

        $this->assertArrayNotHasKey('calendar_token', $user->fresh()->toArray());
    }
}
