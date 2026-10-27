<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Announcement;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\DailyReminder;
use App\Notifications\FriendRequested;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Mockery\MockInterface;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://push.example.com/send/device-1';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webpush.public_key' => 'test-public-key', 'services.webpush.private_key' => 'test-private-key']);
    }

    public function test_a_device_can_turn_push_on_and_off(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push.subscribe'), $this->subscription())->assertNoContent();
        $this->actingAs($user)->postJson(route('push.subscribe'), $this->subscription())->assertNoContent(); // Same device again.
        $this->assertSame(1, $user->pushSubscriptions()->count());

        $this->actingAs($user)->deleteJson(route('push.unsubscribe'), ['endpoint' => self::ENDPOINT])->assertNoContent();
        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_a_shared_device_moves_to_whoever_turns_push_on_next(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->postJson(route('push.subscribe'), $this->subscription());
        $this->actingAs($second)->postJson(route('push.subscribe'), $this->subscription());

        $this->assertSame(0, $first->pushSubscriptions()->count());
        $this->assertSame(1, $second->pushSubscriptions()->count());
    }

    public function test_subscriptions_must_be_real_push_endpoints(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('push.subscribe'), ['endpoint' => 'http://insecure.example.com', 'keys' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }

    public function test_push_goes_to_subscribed_devices_for_the_kinds_people_want(): void
    {
        $user = User::factory()->create();
        $follower = User::factory()->create();

        // No device yet: no push.
        $this->assertNotContains(WebPushChannel::class, (new FriendRequested($follower))->via($user));

        $this->actingAs($user)->postJson(route('push.subscribe'), $this->subscription());
        $this->assertContains(WebPushChannel::class, (new FriendRequested($follower))->via($user));
        $this->assertNotContains(WebPushChannel::class, (new Announcement('News'))->via($user)); // News is off by default.

        // Turned off for kudos and friends.
        $this->actingAs($user)->put(route('settings.notifications.update'), ['push' => ['social' => '0', 'news' => '1']]);
        $user->refresh();
        $this->assertNotContains(WebPushChannel::class, (new FriendRequested($follower))->via($user));
        $this->assertContains(WebPushChannel::class, (new Announcement('News'))->via($user));

        // Without VAPID keys, push stays off entirely.
        config(['services.webpush.private_key' => null]);
        $this->assertNotContains(WebPushChannel::class, (new Announcement('News'))->via($user));
    }

    public function test_the_channel_sends_to_every_device_and_forgets_ones_that_are_gone(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('push.subscribe'), $this->subscription());
        $this->actingAs($user)->postJson(route('push.subscribe'), $this->subscription('https://push.example.com/send/device-2'));

        $this->mock(WebPush::class, function (MockInterface $mock) {
            $mock->shouldReceive('queueNotification')->twice()->withArgs(fn (Subscription $subscription, string $payload) => str_starts_with(json_decode($payload, true)['body'], 'Sam Okafor sent you a friend request.'));
            $mock->shouldReceive('flush')->once()->andReturnUsing(function () {
                yield new MessageSentReport(new Request('POST', self::ENDPOINT), new Response(201));
                yield new MessageSentReport(new Request('POST', 'https://push.example.com/send/device-2'), new Response(410), false, 'Gone');
            });
        });

        $user->notify(new FriendRequested(User::factory()->create(['name' => 'Sam Okafor'])));

        $this->assertSame([hash('sha256', self::ENDPOINT)], $user->pushSubscriptions()->pluck('endpoint_hash')->all());
    }

    public function test_reminder_pushes_preview_todays_gameplan(): void
    {
        $user = User::factory()->create();
        $reminder = new DailyReminder('morning', "Today's Gameplan: 4 things on deck", ['Run', 'Read chapter 3', 'Call Sam', 'Meal prep']);

        $this->assertSame([
            'title' => "Today's Gameplan: 4 things on deck",
            'body' => 'Run · Read chapter 3 · Call Sam · +1 more',
            'url' => route('dashboard').'#gameplan',
            'tag' => 'gameplan-morning',
        ], $reminder->toWebPush($user));
    }

    public function test_the_settings_page_has_the_push_controls(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('settings.notifications'))
            ->assertSee('Phone notifications')
            ->assertSee('data-vapid-key="test-public-key"', false)
            ->assertSee('Push: Daily reminders');
    }

    /**
     * @return array{endpoint: string, keys: array{p256dh: string, auth: string}, contentEncoding: string}
     */
    private function subscription(string $endpoint = self::ENDPOINT): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPublicKeyFromTheBrowser', 'auth' => 'AuthSecret'], 'contentEncoding' => 'aes128gcm'];
    }
}
