<?php

namespace Tests\Feature;

use App\Models\League;
use App\Models\User;
use App\ProfileVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_signing_up_starts_the_walkthrough(): void
    {
        $this->post(route('register'), [
            'name' => 'Casey West',
            'email' => 'casey@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('welcome.show'));
    }

    public function test_newcomers_are_sent_to_the_walkthrough_until_they_finish(): void
    {
        $newcomer = User::factory()->newcomer()->create();

        $this->actingAs($newcomer)->get(route('dashboard'))->assertRedirect(route('welcome.show'));
        $this->actingAs($newcomer)->get(route('welcome.show'))->assertOk()->assertSee('Step 1 of 4');
    }

    public function test_members_who_finished_are_not_redirected(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
    }

    public function test_step_one_saves_username_photo_time_zone_and_visibility(): void
    {
        $newcomer = User::factory()->newcomer()->create();
        User::factory()->create(['username' => 'taken']);

        $this->actingAs($newcomer)->put(route('welcome.you'), [
            'name' => 'Casey West', 'username' => 'taken', 'timezone' => 'America/Chicago', 'profile_visibility' => 'everyone',
        ])->assertSessionHasErrors('username');

        $this->actingAs($newcomer)->put(route('welcome.you'), [
            'name' => 'Casey West', 'username' => '@Casey.West', 'timezone' => 'America/Chicago', 'profile_visibility' => 'everyone',
        ])->assertRedirect(route('welcome.goal'));

        $newcomer->refresh();
        $this->assertSame('casey.west', $newcomer->username);
        $this->assertSame('America/Chicago', $newcomer->timezone);
        $this->assertSame(ProfileVisibility::Everyone, $newcomer->profile_visibility);
    }

    public function test_setting_a_goal_in_the_walkthrough_continues_to_the_next_step(): void
    {
        Queue::fake();
        $newcomer = User::factory()->newcomer()->create();

        $this->actingAs($newcomer)->get(route('welcome.goal'))->assertOk()->assertSee('Skip for now');

        $this->actingAs($newcomer)->post(route('goals.store'), [
            'title' => 'Pass the CPA FAR exam',
            'category' => 'career',
            'target_date' => today()->addWeeks(10)->format('Y-m-d'),
            'from' => 'welcome',
        ])->assertRedirect(route('welcome.crew'));
    }

    public function test_joining_a_goalleague_in_the_walkthrough_returns_to_it(): void
    {
        $newcomer = User::factory()->newcomer()->create();
        $goal = $newcomer->goals()->create(['title' => 'Ship it', 'category' => 'career', 'target_date' => today()->addWeeks(8)]);
        $league = League::factory()->create();

        $this->actingAs($newcomer)
            ->post(route('leagues.join.store', $league->invite_code), ['goal_id' => $goal->id])
            ->assertRedirect(route('welcome.crew'));
    }

    public function test_finishing_enters_the_clubhouse(): void
    {
        $newcomer = User::factory()->newcomer()->create();

        $this->actingAs($newcomer)->get(route('welcome.rules'))->assertOk()->assertSee('How the game works');
        $this->actingAs($newcomer)->post(route('welcome.finish'))->assertRedirect(route('dashboard'));

        $this->assertNotNull($newcomer->fresh()->onboarded_at);
    }
}
