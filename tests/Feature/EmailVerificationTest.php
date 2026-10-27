<?php

namespace Tests\Feature;

use App\Models\League;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signing_up_sends_a_verification_email(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'Casey West',
            'email' => 'casey@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        Notification::assertSentTo(User::firstWhere('email', 'casey@example.com'), VerifyEmail::class);
    }

    public function test_the_signed_link_verifies_the_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user)->get($url)->assertRedirect(route('dashboard'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_members_can_use_goals_but_not_social_or_league_features(): void
    {
        $user = User::factory()->unverified()->create();
        $league = League::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Verify your email');
        $this->actingAs($user)->get(route('goals.create'))->assertOk();

        $this->actingAs($user)->post(route('leagues.join.store', $league->invite_code))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->post(route('friends.request', User::factory()->create()))->assertRedirect(route('verification.notice'));
    }

    public function test_verification_can_be_resent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_changing_your_email_requires_verifying_it_again(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => 'new-address@example.com',
        ]);

        $user->refresh();
        $this->assertSame('new-address@example.com', $user->email);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
