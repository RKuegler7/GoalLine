<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Highlight;
use App\Models\League;
use App\Models\User;
use App\Notifications\Announcement;
use App\Notifications\FriendHighlight;
use App\Notifications\HighlightCheered;
use App\Notifications\LeagueMemberJoined;
use App\Notifications\NewFollower;
use App\ProfileVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_following_someone_lands_in_their_inbox(): void
    {
        $sam = User::factory()->create(['name' => 'Sam Okafor', 'profile_visibility' => ProfileVisibility::Everyone]);
        $jordan = User::factory()->create(['name' => 'Jordan Rivera', 'profile_visibility' => ProfileVisibility::Everyone]);

        $this->actingAs($jordan)->post(route('follows.store', $sam));
        $this->actingAs($jordan)->post(route('follows.store', $sam)); // Already following: no second notification.
        $this->actingAs($sam)->post(route('follows.store', $jordan));

        $this->assertSame(['Jordan Rivera started following you.'], $sam->notifications()->pluck('data')->pluck('text')->all());
        $this->assertSame('Sam Okafor followed you back. You\'re friends now.', $jordan->notifications()->sole()->data['text']);
    }

    public function test_cheers_notify_the_owner_once_and_never_for_your_own_highlight(): void
    {
        $highlight = Highlight::factory()->create(['headline' => 'Ran a half marathon']);
        $owner = $highlight->user;
        $owner->forceFill(['profile_visibility' => ProfileVisibility::Everyone])->save();
        $fan = User::factory()->create(['name' => 'Sam Okafor']);
        $fan->following()->attach($owner);

        $this->actingAs($fan)->post(route('highlights.cheer', $highlight))->assertRedirect();
        $this->actingAs($fan)->post(route('highlights.cheer', $highlight)); // Un-cheer.
        $this->actingAs($fan)->post(route('highlights.cheer', $highlight)); // Cheer again.
        $this->actingAs($owner)->post(route('highlights.cheer', $highlight));

        $notification = $owner->notifications()->sole();
        $this->assertSame(HighlightCheered::class, $notification->type);
        $this->assertSame('Sam Okafor cheered your highlight: Ran a half marathon', $notification->data['text']);
    }

    public function test_sharing_a_highlight_tells_followers_who_can_see_it(): void
    {
        Notification::fake();
        $goal = Goal::factory()->create();
        $goal->complete(null, null);
        $me = $goal->user;
        $me->forceFill(['profile_visibility' => ProfileVisibility::Everyone])->save();
        $fan = User::factory()->create();
        $fan->following()->attach($me);

        $this->actingAs($me)->post(route('highlights.store'), ['goal' => $goal->id]);
        Notification::assertSentTo($fan, FriendHighlight::class);

        // A private locker keeps highlights to yourself.
        Notification::fake();
        $me->forceFill(['profile_visibility' => ProfileVisibility::Private])->save();
        Highlight::query()->delete();
        $this->actingAs($me)->post(route('highlights.store'), ['goal' => $goal->id]);
        Notification::assertNothingSentTo($fan);
    }

    public function test_the_commissioner_hears_when_someone_joins(): void
    {
        Notification::fake();
        $league = League::factory()->create(['invite_code' => 'KICK-OFF1']);
        $member = User::factory()->create();
        $goal = Goal::factory()->for($member)->create();

        $this->actingAs($member)->post(route('leagues.join.store', 'KICKOFF1'), ['goal_id' => $goal->id]);

        Notification::assertSentTo($league->commissioner, LeagueMemberJoined::class);
    }

    public function test_the_inbox_lists_notifications_and_marks_them_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new Announcement('Today’s Gameplan is here.'));

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Inbox, 1 new');

        $this->actingAs($user)
            ->get(route('inbox'))
            ->assertOk()
            ->assertSee('Today’s Gameplan is here.')
            ->assertSee('New');

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->actingAs($user)->get(route('inbox'))->assertDontSee('<span class="sr-only">New</span>', false);
    }

    public function test_email_follows_each_persons_settings(): void
    {
        $user = User::factory()->create();
        $follower = User::factory()->create();

        // Social is inbox-only by default; news also goes to email.
        $this->assertSame(['database'], (new NewFollower($follower))->via($user));
        $this->assertSame(['database', 'mail'], (new Announcement('Hi'))->via($user));

        $this->actingAs($user)
            ->put(route('settings.notifications.update'), [
                'email' => ['social' => '1', 'news' => '0'],
                'morning_at' => '06:30',
                'evening_at' => '',
            ])
            ->assertRedirect(route('settings.notifications'));

        $user->refresh();
        $this->assertSame(['database', 'mail'], (new NewFollower($follower))->via($user));
        $this->assertSame(['database'], (new Announcement('Hi'))->via($user));
        $this->assertSame('06:30', $user->notificationSettings()->morningAt);
        $this->assertNull($user->notificationSettings()->eveningAt);

        // No email until the address is verified.
        $user->forceFill(['email_verified_at' => null])->save();
        $this->assertSame(['database'], (new NewFollower($follower))->via($user));
    }

    public function test_reminder_times_must_be_from_the_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('settings.notifications.update'), ['morning_at' => '03:17'])
            ->assertSessionHasErrors('morning_at');

        $this->actingAs($user)->get(route('settings.notifications'))->assertOk()->assertSee('7:30 AM');
    }

    public function test_announcements_reach_every_verified_member(): void
    {
        Notification::fake();
        $members = User::factory()->count(2)->create();
        $unverified = User::factory()->unverified()->create();

        $this->artisan('goalline:announce', ['message' => 'Habits are here.'])->assertSuccessful();

        Notification::assertSentTo($members, Announcement::class);
        Notification::assertNothingSentTo($unverified);
    }
}
