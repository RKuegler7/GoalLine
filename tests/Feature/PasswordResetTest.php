<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_links_to_password_reset(): void
    {
        $this->get(route('login'))->assertSee(route('password.request'));
        $this->get(route('password.request'))->assertOk()->assertSee('Reset your password');
    }

    public function test_a_reset_link_is_emailed_and_resets_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))->assertOk();

            $this->post(route('password.store'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'brand-new-pass',
                'password_confirmation' => 'brand-new-pass',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_unknown_emails_get_the_same_answer(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_bad_tokens_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.store'), [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('email');
    }
}
