<?php

namespace Tests\Feature;

use App\EmailVerificationLink;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationLinkTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_URL = 'https://goalline.example';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => self::PUBLIC_URL, 'goalline.behind_cloudfront' => true]);
    }

    public function test_the_email_links_to_the_public_https_address_with_the_signature_in_the_path(): void
    {
        $user = User::factory()->unverified()->create();
        $link = (new VerifyEmail)->toMail($user)->actionUrl;

        $this->assertStringStartsWith(self::PUBLIC_URL.'/email/verify/'.$user->id.'/', $link);
        $this->assertStringNotContainsString('?', $link, 'Nothing in the query string for a CDN or click-tracker to drop.');
    }

    public function test_a_link_forwarded_by_cloudfront_to_the_server_over_http_still_verifies(): void
    {
        $user = User::factory()->unverified()->create();
        $link = EmailVerificationLink::for($user);

        // CloudFront hands the request to Elastic Beanstalk over plain HTTP, under the server's own name.
        $atServer = 'http://goalline-env.elasticbeanstalk.example'.substr($link, strlen(self::PUBLIC_URL));

        $this->actingAs($user)->get($atServer, ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'goalline.example'])
            ->assertRedirect(route('dashboard'))->assertSessionHas('status');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_opening_the_link_signed_out_on_another_device_verifies_and_asks_to_log_in(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get(EmailVerificationLink::for($user))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', fn (string $status) => str_starts_with($status, 'Email verified.'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
    }

    public function test_a_tampered_link_shows_a_friendly_page_with_a_resend_not_a_raw_403(): void
    {
        $user = User::factory()->unverified()->create();
        $tampered = preg_replace('#/[0-9a-f]{64}$#', '/'.str_repeat('0', 64), EmailVerificationLink::for($user));

        $this->get($tampered)->assertForbidden()
            ->assertSee('That link didn&#039;t work', false)
            ->assertSee('It may have expired or been opened in a different place.')
            ->assertSee('Send me a new verification email')
            ->assertSee(route('login'))
            ->assertDontSee('Invalid signature');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_an_expired_link_offers_a_new_one(): void
    {
        $user = User::factory()->unverified()->create();
        $link = EmailVerificationLink::for($user);
        $this->travel(config('goalline.verification_link_hours') + 1)->hours();

        $this->get($link)->assertStatus(410)
            ->assertSee('This verification link has expired.')
            ->assertSee('value="'.$user->email.'"', false)
            ->assertSee('Send me a new verification email');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_an_already_verified_member_is_sent_on_with_a_friendly_note(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(EmailVerificationLink::for($user))
            ->assertRedirect(route('dashboard'))->assertSessionHas('status', "Your email is already verified. You're all set.");
    }

    public function test_resending_signed_out_is_rate_limited_and_never_says_who_has_an_account(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->post(route('verification.resend'), ['email' => $user->email])->assertRedirect(route('login'))->assertSessionHas('status');
        $this->post(route('verification.resend'), ['email' => 'nobody@example.com'])->assertRedirect(route('login'))->assertSessionHas('status');
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        $this->post(route('verification.resend'), ['email' => $user->email]);
        $this->post(route('verification.resend'), ['email' => $user->email])->assertStatus(429);
    }

    public function test_signed_in_unverified_members_can_resend_from_the_page(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $tampered = preg_replace('#/[0-9a-f]{64}$#', '/'.str_repeat('0', 64), EmailVerificationLink::for($user));

        $this->actingAs($user)->get($tampered)->assertSee(route('verification.send'));
        $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('status');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_links_sent_before_the_fix_still_work_and_a_stripped_one_gets_the_friendly_page(): void
    {
        $user = User::factory()->unverified()->create();
        // Built the way production built them: on the public https address.
        URL::forceRootUrl(self::PUBLIC_URL);
        URL::forceScheme('https');
        $old = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        URL::forceRootUrl(null);
        URL::forceScheme(null);

        // The query string dropped on the way (a CDN cache policy or a click-tracker): friendly page, no verify.
        $this->get(strtok($old, '?'))->assertForbidden()->assertSee('Send me a new verification email');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        // Intact, it verifies, even signed out.
        $this->get($old)->assertRedirect(route('login'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
