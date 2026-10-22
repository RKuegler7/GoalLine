<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use App\Notifications\Nudged;
use App\ProfileVisibility;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NudgeTest extends TestCase
{
    use RefreshDatabase;

    private User $sam;

    private User $jordan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 18:00', config('app.timezone')));
        $this->sam = User::factory()->create(['name' => 'Sam Okafor', 'profile_visibility' => ProfileVisibility::Everyone]);
        $this->jordan = User::factory()->create(['name' => 'Jordan Rivera', 'profile_visibility' => ProfileVisibility::Everyone]);
    }

    public function test_friends_can_nudge_once_a_day_with_a_message_about_what_is_left(): void
    {
        Notification::fake();
        $this->makeFriends();
        $goal = Goal::factory()->for($this->jordan)->create(['target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Work', 'milestone' => 'Done.']);
        $week->tasks()->create(['position' => 1, 'description' => 'Ship it', 'planned_on' => '2026-10-21']);

        $this->actingAs($this->sam)->from(route('profiles.show', $this->jordan))
            ->post(route('nudges.store', $this->jordan))
            ->assertSessionHas('status', 'Nudged Jordan. Nice teammate move.');
        $this->actingAs($this->sam)->post(route('nudges.store', $this->jordan))
            ->assertSessionHas('status', 'You already nudged Jordan today.');

        Notification::assertSentToTimes($this->jordan, Nudged::class, 1);
        $this->assertSame("Sam nudged you: 1 thing left on today's gameplan. You've got this.", (new Nudged($this->sam))->toArray($this->jordan)['text']);

        $this->travel(21)->hours();
        $this->actingAs($this->sam)->post(route('nudges.store', $this->jordan));
        Notification::assertSentToTimes($this->jordan, Nudged::class, 2);
    }

    public function test_league_mates_can_nudge_but_strangers_and_one_way_follows_cannot(): void
    {
        Notification::fake();
        $this->sam->following()->attach($this->jordan); // Sam follows Jordan, not mutual.

        $this->actingAs($this->sam)->post(route('nudges.store', $this->jordan))->assertForbidden();
        $this->actingAs($this->sam)->post(route('nudges.store', $this->sam))->assertForbidden();

        $league = League::factory()->create();
        $league->members()->create(['user_id' => $this->sam->id]);
        $league->members()->create(['user_id' => $this->jordan->id]);

        $this->actingAs($this->sam)->post(route('nudges.store', $this->jordan))->assertRedirect();
        Notification::assertSentTo($this->jordan, Nudged::class);
    }

    public function test_the_profile_shows_the_nudge_button_to_friends(): void
    {
        $this->actingAs($this->sam)->get(route('profiles.show', $this->jordan))->assertDontSee('Nudge Jordan');

        $this->makeFriends();
        $this->actingAs($this->sam)->get(route('profiles.show', $this->jordan))->assertSee('Nudge Jordan');

        $this->actingAs($this->sam)->post(route('nudges.store', $this->jordan));
        $this->actingAs($this->sam)->get(route('profiles.show', $this->jordan))->assertSee('Nudged today');
    }

    private function makeFriends(): void
    {
        $this->sam->following()->attach($this->jordan);
        $this->jordan->following()->attach($this->sam);
    }
}
