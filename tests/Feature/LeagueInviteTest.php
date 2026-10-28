<?php

namespace Tests\Feature;

use App\GoalCategory;
use App\Models\Goal;
use App\Models\League;
use App\Models\LeagueInvite;
use App\Models\User;
use App\Notifications\LeagueInvited;
use App\Notifications\LeagueInviteEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeagueInviteTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private League $league;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->travelTo(Carbon::parse('2026-10-28 12:00', config('app.timezone')));
        $this->owner = User::factory()->create(['name' => 'Priya Shah']);
        $this->league = League::factory()->create(['name' => 'CPA Prep League', 'category' => GoalCategory::Academic, 'commissioner_id' => $this->owner->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-27']);
        $this->league->members()->create(['user_id' => $this->owner->id, 'goal_id' => $this->goalFor($this->owner)->id]);
    }

    public function test_every_league_has_an_unguessable_shareable_link_that_expires_in_14_days(): void
    {
        $this->actingAs($this->owner)->get(route('leagues.show', $this->league))
            ->assertSee('Invite your people')->assertSee($this->league->invite_code)->assertSee('Invite friends');

        $invite = LeagueInvite::sole();
        $this->assertSame(40, strlen($invite->token));
        $this->assertSame(LeagueInvite::LINK, $invite->kind);
        $this->assertTrue($invite->expires_at->equalTo(now()->addDays(14)));
        $this->assertSame($invite->id, $this->league->shareableInvite()->id); // Reused, not made again.

        $this->get(route('leagues.invite.show', 'not-a-real-token'))->assertNotFound();
    }

    public function test_joining_through_a_link(): void
    {
        $sam = User::factory()->create();
        $goal = $this->goalFor($sam);
        $invite = $this->league->shareableInvite();

        $this->actingAs($sam)->get($invite->url())->assertOk()->assertSee('CPA Prep League')->assertSee('Academic')->assertSee('Run by Priya Shah')->assertSee('1 member');
        $this->actingAs($sam)->post(route('leagues.invite.join', $invite->token), ['goal_id' => $goal->id])->assertRedirect(route('leagues.show', $this->league));

        $this->assertNotNull($this->league->memberFor($sam));
        $this->assertSame(1, $invite->fresh()->uses);
        $this->actingAs($sam)->get($invite->url())->assertRedirect(route('leagues.show', $this->league));
    }

    public function test_joining_while_signed_out_signs_up_through_the_private_beta_then_sets_a_goal(): void
    {
        config(['goalline.beta.invite_only' => true]);
        $invite = $this->league->shareableInvite();

        $this->get($invite->url())->assertOk()->assertSee('Sign in to join')->assertSee('Create an account');

        // The league invite stands in for a beta invite code.
        $this->post(route('register'), [
            'name' => 'Dave Kim', 'email' => 'dave@example.com', 'password' => 'a-strong-password-123', 'password_confirmation' => 'a-strong-password-123',
        ])->assertRedirect($invite->url());
        $dave = User::where('email', 'dave@example.com')->sole();
        $dave->forceFill(['email_verified_at' => now()])->save();

        // No goal yet: set one right on the invite page, then come back to join with it.
        $this->actingAs($dave)->get($invite->url())->assertSee("Set the goal you'll play", false)->assertSee('name="from" value="league"', false);
        $response = $this->actingAs($dave)->post(route('goals.store'), ['from' => 'league', 'title' => 'Pass FAR', 'category' => 'academic', 'target_date' => '2026-12-15']);
        $goal = $dave->goals()->sole();
        $response->assertRedirect($invite->url().'?goal='.$goal->id);

        $this->actingAs($dave)->post(route('leagues.invite.join', $invite->token), ['goal_id' => $goal->id])->assertRedirect(route('welcome.crew'));
        $this->assertNotNull($this->league->memberFor($dave));
    }

    public function test_signing_in_from_an_invite_comes_back_to_it(): void
    {
        $sam = User::factory()->create(['email' => 'sam@example.com', 'password' => 'a-strong-password-123']);
        $invite = $this->league->shareableInvite();

        $this->get($invite->url());
        $this->post(route('login'), ['email' => 'sam@example.com', 'password' => 'a-strong-password-123'])->assertRedirect($invite->url());
    }

    public function test_expired_revoked_and_used_up_invites_stop_working(): void
    {
        $sam = User::factory()->create();
        $samGoal = $this->goalFor($sam);
        $invite = $this->league->shareableInvite();

        // Expired.
        $this->travel(15)->days();
        $this->assertFalse($invite->fresh()->isUsable());
        $this->actingAs($sam)->get($invite->url())->assertSee('This invite has expired or was turned off.');
        $this->actingAs($sam)->post(route('leagues.invite.join', $invite->token), ['goal_id' => $samGoal->id])->assertStatus(410);
        $this->travelBack();
        $this->travelTo(Carbon::parse('2026-10-28 12:00', config('app.timezone')));

        // Revoked.
        $revoked = $this->league->invites()->create(['kind' => LeagueInvite::LINK]);
        $this->actingAs($this->owner)->delete(route('leagues.invites.revoke', $revoked))->assertRedirect();
        $this->actingAs($sam)->post(route('leagues.invite.join', $revoked->token), ['goal_id' => $samGoal->id])->assertStatus(410);

        // Capped at one use.
        $capped = $this->league->invites()->create(['kind' => LeagueInvite::LINK, 'max_uses' => 1]);
        $this->actingAs($sam)->post(route('leagues.invite.join', $capped->token), ['goal_id' => $samGoal->id])->assertRedirect();
        $dave = User::factory()->create();
        $this->actingAs($dave)->post(route('leagues.invite.join', $capped->token), ['goal_id' => $this->goalFor($dave)->id])->assertStatus(410);
        $this->assertNull($this->league->memberFor($dave));
    }

    public function test_the_owner_can_make_a_new_link_with_expiry_and_cap_and_the_old_one_stops(): void
    {
        $old = $this->league->shareableInvite();
        $oldCode = $this->league->invite_code;

        $this->actingAs($this->owner)->post(route('leagues.invites.regenerate', $this->league), ['expires_in_days' => 7, 'max_uses' => 5, 'new_code' => 1])->assertRedirect();

        $this->assertFalse($old->fresh()->isUsable());
        $new = $this->league->fresh()->shareableInvite();
        $this->assertNotSame($old->token, $new->token);
        $this->assertSame(5, $new->max_uses);
        $this->assertTrue($new->expires_at->equalTo(now()->addDays(7)));
        $this->assertNotSame($oldCode, $this->league->fresh()->invite_code);
    }

    public function test_only_the_owner_manages_invites_and_members(): void
    {
        $sam = User::factory()->create();
        $member = $this->league->members()->create(['user_id' => $sam->id, 'goal_id' => $this->goalFor($sam)->id]);
        $invite = $this->league->shareableInvite();

        $this->actingAs($sam)->post(route('leagues.invites.regenerate', $this->league))->assertForbidden();
        $this->actingAs($sam)->delete(route('leagues.invites.revoke', $invite))->assertForbidden();
        $ownerMembership = $this->league->memberFor($this->owner);
        $this->actingAs($sam)->delete(route('leagues.members.destroy', [$this->league, $ownerMembership]))->assertForbidden();
        $this->actingAs($sam)->get(route('leagues.show', $this->league))->assertDontSee('Manage invites and members');

        $this->actingAs($this->owner)->get(route('leagues.show', $this->league))->assertSee('Manage invites and members');
        $this->actingAs($this->owner)->delete(route('leagues.members.destroy', [$this->league, $member]))->assertRedirect();
        $this->assertNull($this->league->fresh()->memberFor($sam));
        $this->actingAs($this->owner)->delete(route('leagues.members.destroy', [$this->league, $ownerMembership]))->assertStatus(422);
    }

    public function test_invites_by_email_and_by_username(): void
    {
        Notification::fake();
        $sam = User::factory()->create(['name' => 'Sam Okafor', 'username' => 'sam.okafor']);

        $this->actingAs($this->owner)->post(route('leagues.invites.store', $this->league), ['who' => 'friend@example.com'])
            ->assertSessionHas('status', 'Invite emailed to friend@example.com.');
        Notification::assertSentOnDemand(LeagueInviteEmail::class, fn (LeagueInviteEmail $mail, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'friend@example.com');

        $this->actingAs($this->owner)->post(route('leagues.invites.store', $this->league), ['who' => '@sam.okafor'])
            ->assertSessionHas('status', "Invited Sam Okafor. They'll see it in their inbox.");
        Notification::assertSentTo($sam, LeagueInvited::class);

        $this->actingAs($this->owner)->post(route('leagues.invites.store', $this->league), ['who' => '@nobody.here'])->assertSessionHasErrorsIn('invite', 'who');

        $personal = LeagueInvite::where('kind', '!=', LeagueInvite::LINK)->get();
        $this->assertCount(2, $personal);
        $this->assertTrue($personal->every(fn (LeagueInvite $invite) => $invite->max_uses === 1 && $invite->expires_at->equalTo(now()->addDays(14))));
        $this->actingAs($this->owner)->get(route('leagues.show', $this->league))->assertSee('Pending invites')->assertSee('friend@example.com')->assertSee('@sam.okafor');
    }

    private function goalFor(User $user): Goal
    {
        return Goal::factory()->for($user)->create(['target_date' => '2026-12-31']);
    }
}
