<?php

namespace Tests\Feature;

use App\Clock;
use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use App\Services\RoadmapPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_up_saves_the_browsers_time_zone(): void
    {
        $this->post(route('register'), [
            'name' => 'Casey West',
            'email' => 'casey@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'timezone' => 'America/Los_Angeles',
        ]);

        $this->assertSame('America/Los_Angeles', User::firstWhere('email', 'casey@example.com')->timezone);
    }

    public function test_people_can_pick_a_time_zone_and_bad_ones_are_rejected(): void
    {
        $user = User::factory()->create();
        $fields = ['name' => $user->name, 'username' => $user->username];

        $this->actingAs($user)->put(route('profile.update'), $fields + ['timezone' => 'Mars/Olympus_Mons'])->assertSessionHasErrors('timezone');

        $this->actingAs($user)->put(route('profile.update'), $fields + ['timezone' => 'Europe/London'])->assertSessionHasNoErrors();
        $this->assertSame('Europe/London', $user->fresh()->timezoneName());
    }

    public function test_people_without_a_time_zone_use_the_app_clock(): void
    {
        $this->assertSame(config('app.timezone'), User::factory()->make(['timezone' => null])->timezoneName());
    }

    public function test_goalweeks_follow_each_persons_own_sunday(): void
    {
        // 1:30 am Monday in New York is still 10:30 pm Sunday in Los Angeles.
        $this->travelTo(Carbon::parse('2026-10-26 01:30', 'America/New_York'));
        $goal = Goal::factory()->create(['target_date' => '2026-12-31']);
        $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'F', 'milestone' => 'M']);

        $newYork = User::factory()->make(['timezone' => 'America/New_York']);
        $losAngeles = User::factory()->make(['timezone' => 'America/Los_Angeles']);

        $this->assertFalse(Clock::for($newYork, fn () => $week->isCurrent()));
        $this->assertTrue(Clock::for($losAngeles, fn () => $week->isCurrent()));
    }

    public function test_new_roadmaps_start_on_the_owners_today(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 01:30', 'America/New_York'));
        $owner = User::factory()->create(['timezone' => 'America/Los_Angeles']);
        $goal = Goal::factory()->for($owner)->create(['target_date' => '2026-11-30']);

        $periods = Clock::for($owner, fn () => Mockery::mock(RoadmapPlanner::class)->makePartial()->periods($goal));

        $this->assertSame('2026-10-25', $periods[0]['starts_on']->toDateString(), 'It is still Sunday in Los Angeles.');
    }

    public function test_chat_times_show_on_the_viewers_clock(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 20:00', 'America/New_York'));
        $viewer = User::factory()->create(['timezone' => 'America/Los_Angeles']);
        $league = League::factory()->create(['commissioner_id' => $viewer->id]);
        $league->members()->create(['user_id' => $viewer->id]);
        $league->messages()->create(['user_id' => $viewer->id, 'body' => 'Evening check-in']);

        $this->actingAs($viewer)->get(route('leagues.show', $league))->assertSee('5:00 pm');
    }
}
