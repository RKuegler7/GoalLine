<?php

namespace Tests\Feature;

use App\Models\LeagueInvite;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\DailyReminder;
use App\Notifications\FriendRequested;
use App\Notifications\GoalLineNotification;
use App\Notifications\LeagueInviteEmail;
use App\Notifications\Nudged;
use App\Notifications\WeeklyRecapReady;
use App\Outbound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The outbound messaging switches (GOALLINE_EMAIL_*, GOALLINE_PUSH): a switch that's off stops the email but never the
 * in-app notification; transactional email always goes; demo accounts never get email.
 */
class MessagingFlagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array']);
        $this->allSwitches(true);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function streams(): array
    {
        return [
            'daily reminders' => [Outbound::REMINDERS, 'reminder'],
            'nudges' => [Outbound::NUDGES, 'nudge'],
            'Monday recaps' => [Outbound::RECAPS, 'recap'],
            'everything else' => [Outbound::NOTIFICATIONS, 'friend'],
        ];
    }

    #[DataProvider('streams')]
    public function test_a_switch_that_is_off_stops_the_email_but_keeps_the_inbox_notification(string $stream, string $kind): void
    {
        $user = $this->member();
        config(["goalline.messaging.{$stream}" => false]);

        $user->notify($this->notification($kind));

        $this->assertSame(0, $this->sentCount());
        $this->assertSame(1, $user->notifications()->count());
    }

    #[DataProvider('streams')]
    public function test_a_switch_that_is_on_sends_the_email(string $stream, string $kind): void
    {
        $user = $this->member();

        $user->notify($this->notification($kind));

        $this->assertSame(1, $this->sentCount());
    }

    public function test_each_switch_only_covers_its_own_kind(): void
    {
        $user = $this->member();
        config(['goalline.messaging.'.Outbound::NUDGES => false]);

        $user->notify($this->notification('nudge'));
        $user->notify($this->notification('recap'));

        $this->assertSame(1, $this->sentCount());
    }

    public function test_transactional_email_always_sends_with_every_switch_off(): void
    {
        $this->allSwitches(false);
        $user = User::factory()->unverified()->create();
        $inviter = User::factory()->create();

        $user->sendEmailVerificationNotification();
        $user->sendPasswordResetNotification('reset-token');
        Notification::route('mail', 'friend@example.com')->notify(new LeagueInviteEmail(LeagueInvite::factory()->create(), $inviter));

        $this->assertSame(3, $this->sentCount());
    }

    public function test_demo_accounts_never_get_email_whatever_the_switches_say(): void
    {
        $demo = $this->member(['demo_sandbox' => 'sandbox-1', 'email' => 'visitor@demo.goalline.invalid']);
        $demoFriend = User::factory()->create(['demo_sandbox' => 'sandbox-1']);

        foreach (['reminder', 'nudge', 'recap', 'friend'] as $kind) {
            $demo->notify($this->notification($kind));
        }

        $demo->forceFill(['email_verified_at' => null])->save();
        $demo->sendEmailVerificationNotification();
        $demo->sendPasswordResetNotification('reset-token');
        Notification::route('mail', 'someone@example.com')->notify(new LeagueInviteEmail(LeagueInvite::factory()->create(), $demoFriend));
        Notification::route('mail', 'pretend@demo.goalline.invalid')->notify(new LeagueInviteEmail(LeagueInvite::factory()->create(), User::factory()->create()));

        $this->assertSame(0, $this->sentCount());
        // Their inbox still works (reminders are email-only by design, so three of the four).
        $this->assertSame(3, $demo->notifications()->count());
    }

    public function test_the_push_switch_stops_push(): void
    {
        config(['services.webpush.public_key' => 'test-public-key', 'services.webpush.private_key' => 'test-private-key']);
        $user = $this->member();
        $this->actingAs($user)->postJson(route('push.subscribe'), [
            'endpoint' => 'https://push.example.com/send/device-1',
            'keys' => ['p256dh' => 'BPublicKeyFromTheBrowser', 'auth' => 'AuthSecret'],
            'contentEncoding' => 'aes128gcm',
        ]);
        $this->assertContains(WebPushChannel::class, (new FriendRequested(User::factory()->create()))->via($user));

        config(['goalline.messaging.push' => false]);

        $this->assertNotContains(WebPushChannel::class, (new FriendRequested(User::factory()->create()))->via($user));
    }

    public function test_production_defaults_every_switch_to_off(): void
    {
        foreach (['GOALLINE_EMAIL_REMINDERS', 'GOALLINE_EMAIL_NUDGES', 'GOALLINE_EMAIL_RECAPS', 'GOALLINE_EMAIL_NOTIFICATIONS', 'GOALLINE_PUSH'] as $name) {
            $this->assertArrayNotHasKey($name, $_ENV, "{$name} must not be set for this test.");
        }

        $original = $_SERVER['APP_ENV'] ?? null;
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'production';
        putenv('APP_ENV=production');

        try {
            $config = require config_path('goalline.php');
        } finally {
            $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $original ?? 'testing';
            putenv('APP_ENV='.($original ?? 'testing'));
        }

        $this->assertSame([
            'email_reminders' => false, 'email_nudges' => false, 'email_recaps' => false, 'email_notifications' => false, 'push' => false,
        ], $config['messaging']);
    }

    /**
     * A verified member who wants every kind of email.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function member(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['notification_settings' => ['email' => array_fill_keys(['reminders', 'recap', 'results', 'badges', 'leagues', 'mentions', 'nudges', 'social', 'news'], true)]])->save();

        return $user->fresh();
    }

    private function notification(string $kind): GoalLineNotification
    {
        return match ($kind) {
            'reminder' => new DailyReminder('morning', 'Your gameplan for today', []),
            'nudge' => new Nudged(User::factory()->create()),
            'recap' => new WeeklyRecapReady('Your week: 5 check-ins.'),
            'friend' => new FriendRequested(User::factory()->create()),
        };
    }

    private function allSwitches(bool $on): void
    {
        config(['goalline.messaging' => array_fill_keys(['email_reminders', 'email_nudges', 'email_recaps', 'email_notifications', 'push'], $on)]);
    }

    private function sentCount(): int
    {
        return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->count();
    }
}
