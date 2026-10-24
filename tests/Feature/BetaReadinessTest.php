<?php

namespace Tests\Feature;

use App\Exceptions\AiLimitReached;
use App\Models\Goal;
use App\Models\Invite;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\RoadmapStatus;
use App\Services\AiBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BetaReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'goalline.admin_emails' => ['admin@goalline.app'],
            'goalline.ai.daily_requests_per_member' => 3,
            'goalline.ai.daily_budget_usd' => 1.0,
            'goalline.ai.monthly_budget_usd' => 10.0,
            'goalline.ai.input_usd_per_million_tokens' => 5.0,
            'goalline.ai.output_usd_per_million_tokens' => 25.0,
        ]);
    }

    public function test_each_member_has_a_daily_ai_cap(): void
    {
        $budget = app(AiBudget::class);
        $member = User::factory()->create();

        foreach (range(1, 3) as $call) {
            $budget->ensureAvailable($member);
            $budget->record($member, 'coach-check', 1_000, 100);
        }

        $this->expectException(AiLimitReached::class);
        $this->expectExceptionMessage("You've used today's AI coaching. It resets tomorrow.");
        $budget->ensureAvailable($member);
    }

    public function test_the_daily_budget_pauses_ai_for_everyone_and_alerts_the_admins_once(): void
    {
        Notification::fake();
        $budget = app(AiBudget::class);

        // 100k input + 10k output tokens = $0.50 + $0.25 = $0.75: under the $0.80 alert line.
        $budget->record(null, 'roadmap', 100_000, 10_000);
        $this->assertEqualsWithDelta(0.75, $budget->spentToday(), 0.0001);
        Notification::assertNothingSent();

        // $0.85: past 80%, so the admins hear about it, but AI keeps working.
        $budget->record(null, 'roadmap', 10_000, 0);
        $this->assertNull($budget->blockedReason(User::factory()->create()));
        Notification::assertSentOnDemand(AdminAlert::class, fn (AdminAlert $alert, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === ['admin@goalline.app']);

        // $1.10: AI pauses for everyone, and there's no second alert today.
        $budget->record(null, 'roadmap', 50_000, 0);
        $this->assertSame('GoalLine has reached its AI budget for today.', $budget->blockedReason(User::factory()->create()));
        Notification::assertSentOnDemandTimes(AdminAlert::class, 1);
    }

    public function test_a_paused_roadmap_says_why(): void
    {
        $goal = Goal::factory()->create();
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Failed])->save();
        DB::table('ai_requests')->insert(['user_id' => null, 'kind' => 'roadmap', 'cost_usd' => 5, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($goal->user)->get(route('goals.show', $goal))->assertSee('GoalLine has reached its AI budget for today.');
    }

    public function test_signup_needs_a_valid_invite_during_the_beta(): void
    {
        config(['goalline.beta.invite_only' => true]);
        $invite = Invite::create(['note' => 'Sam', 'max_uses' => 1]);

        $this->get(route('register', ['invite' => $invite->code]))->assertSee('Invite code')->assertSee($invite->code);

        $this->post(route('register'), $this->signup('nobody@example.com'))->assertSessionHasErrors('invite');
        $this->post(route('register'), $this->signup('typo@example.com', 'WRONG123'))->assertSessionHasErrors('invite');

        $this->post(route('register'), $this->signup('sam@example.com', strtolower($invite->code)))->assertRedirect(route('welcome.show'));
        $this->assertSame($invite->id, User::where('email', 'sam@example.com')->sole()->invite_id);
        $this->assertSame(1, $invite->fresh()->uses);

        // Used up.
        auth()->logout();
        $this->post(route('register'), $this->signup('second@example.com', $invite->code))->assertSessionHasErrors('invite');
    }

    public function test_admins_can_sign_up_without_an_invite(): void
    {
        config(['goalline.beta.invite_only' => true]);

        $this->post(route('register'), $this->signup('admin@goalline.app'))->assertRedirect(route('welcome.show'));
    }

    public function test_only_admins_see_the_admin_page_and_can_make_and_revoke_invites(): void
    {
        $admin = User::factory()->create(['email' => 'admin@goalline.app']);

        $this->actingAs(User::factory()->create())->get(route('admin.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.index'))->assertOk()->assertSee('Beta invites')->assertSee('AI spend');

        $this->actingAs($admin)->post(route('admin.invites.store'), ['note' => 'Run club', 'max_uses' => 5]);
        $invite = Invite::sole();
        $this->assertSame(5, $invite->max_uses);
        $this->actingAs($admin)->get(route('admin.index'))->assertSee($invite->code)->assertSee($invite->signupUrl());

        $this->actingAs($admin)->delete(route('admin.invites.revoke', $invite));
        $this->assertNull(Invite::usable($invite->code));
    }

    public function test_verified_admins_get_an_admin_link_in_the_top_bar(): void
    {
        $admin = User::factory()->create(['email' => 'admin@goalline.app']);
        $this->actingAs($admin)->get(route('dashboard'))->assertSee('href="'.route('admin.index').'"', false);

        // Until the email is verified, it isn't an admin account yet.
        $admin->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($admin)->get(route('dashboard'))->assertDontSee('href="'.route('admin.index').'"', false);

        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertDontSee('href="'.route('admin.index').'"', false);
    }

    public function test_usage_is_tracked_and_shown_on_the_admin_page(): void
    {
        $admin = User::factory()->create(['email' => 'admin@goalline.app']);
        $member = User::factory()->create();
        $goal = Goal::factory()->for($member)->create();
        $habit = $goal->habits()->create(['title' => 'Run', 'times_per_week' => 3]);

        $this->actingAs($member)->get(route('dashboard'));
        $this->actingAs($member)->get(route('dashboard')); // Still one "active" for today.
        $habit->logs()->create(['logged_on' => today()]);

        $this->assertSame(1, DB::table('usage_events')->where('user_id', $member->id)->where('name', 'active')->count());
        $this->assertSame(1, DB::table('usage_events')->where('name', 'habit-log')->count());
        $this->assertSame(1, DB::table('usage_events')->where('name', 'goal-created')->count());

        $this->actingAs($admin)->get(route('admin.index'))->assertSee('Logged a habit');
    }

    public function test_the_privacy_policy_and_terms_are_public(): void
    {
        $this->get(route('privacy'))->assertOk()->assertSee('AI coaching');
        $this->get(route('terms'))->assertOk()->assertSee('isn&rsquo;t professional advice', false);
        $this->get(route('register'))->assertSee(route('terms'))->assertSee(route('privacy'));
    }

    /**
     * @return array<string, string>
     */
    private function signup(string $email, ?string $invite = null): array
    {
        return array_filter([
            'name' => 'Sam Okafor',
            'email' => $email,
            'password' => 'a-strong-password-123',
            'password_confirmation' => 'a-strong-password-123',
            'invite' => $invite,
        ]);
    }
}
