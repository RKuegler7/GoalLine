<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use App\ProfileVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendshipTest extends TestCase
{
    use RefreshDatabase;

    private User $jordan;

    private User $sam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jordan = User::factory()->create(['name' => 'Jordan Rivera', 'username' => 'jordan.rivera', 'email' => 'jordan@example.com']);
        $this->sam = User::factory()->create(['name' => 'Sam Okafor', 'username' => 'sam.okafor', 'email' => 'sam@example.com']);
    }

    public function test_you_can_send_a_request_by_exact_username_or_email(): void
    {
        $this->actingAs($this->jordan)->post(route('friends.store'), ['who' => '@Sam.Okafor'])->assertSessionHas('status', 'Friend request sent to Sam.');
        $this->assertSame(Friendship::PENDING, $this->jordan->friendshipWith($this->sam)->status);

        $priya = User::factory()->create(['name' => 'Priya Shah', 'email' => 'Priya@Example.com']);
        $this->actingAs($this->jordan)->post(route('friends.store'), ['who' => 'priya@example.com'])->assertSessionHas('status', 'Friend request sent to Priya.');
        $this->assertNotNull($this->jordan->friendshipWith($priya));
    }

    public function test_there_is_no_search_only_exact_matches(): void
    {
        foreach (['sam', 'Sam Okafor', 'okafor', 'sam@example', ''] as $guess) {
            $this->actingAs($this->jordan)->post(route('friends.store'), ['who' => $guess])->assertSessionHasErrors('who');
        }

        $this->actingAs($this->jordan)->post(route('friends.store'), ['who' => 'jordan.rivera'])->assertSessionHasErrors('who');
        $this->assertSame(0, Friendship::count());
        $this->actingAs($this->jordan)->get('/people')->assertRedirect('/friends');
    }

    public function test_only_the_person_asked_can_accept_and_either_side_can_decline_or_remove(): void
    {
        $this->actingAs($this->jordan)->post(route('friends.request', $this->sam));
        $request = Friendship::sole();

        $this->actingAs($this->jordan)->post(route('friends.accept', $request))->assertForbidden();
        $this->actingAs(User::factory()->create())->delete(route('friends.destroy', $request))->assertForbidden();

        $this->actingAs($this->sam)->get(route('friends.index'))->assertSee('Waiting on you')->assertSee('Jordan Rivera');
        $this->actingAs($this->jordan)->get(route('friends.index'))->assertSee('Requests you sent')->assertSee('Sam Okafor');

        $this->actingAs($this->sam)->post(route('friends.accept', $request))->assertSessionHas('status', 'You and Jordan Rivera are friends now.');
        $this->assertTrue($this->jordan->isFriendsWith($this->sam));
        $this->assertTrue($this->sam->isFriendsWith($this->jordan));
        $this->assertSame(['Sam Okafor'], $this->jordan->friends()->pluck('name')->all());

        $this->actingAs($this->jordan)->delete(route('friends.destroy', $request))->assertSessionHas('status', 'Removed Sam Okafor from your friends.');
        $this->assertFalse($this->jordan->isFriendsWith($this->sam));

        $this->actingAs($this->jordan)->post(route('friends.request', $this->sam));
        $this->actingAs($this->sam)->delete(route('friends.destroy', Friendship::sole()))->assertSessionHas('status', 'Request declined.');
        $this->assertSame(0, Friendship::count());
    }

    public function test_the_locker_button_follows_the_request(): void
    {
        $this->sam->forceFill(['profile_visibility' => ProfileVisibility::Everyone])->save();
        $this->jordan->forceFill(['profile_visibility' => ProfileVisibility::Everyone])->save();

        $this->actingAs($this->jordan)->get($this->sam->profileUrl())->assertSee('Add friend');
        $this->actingAs($this->jordan)->post(route('friends.request', $this->sam));
        $this->actingAs($this->jordan)->get($this->sam->profileUrl())->assertSee('Request sent');
        $this->actingAs($this->sam)->get($this->jordan->profileUrl())->assertSee('Accept friend request');

        $this->actingAs($this->sam)->post(route('friends.request', $this->jordan)); // Asking back accepts.
        $this->actingAs($this->jordan)->get($this->sam->profileUrl())->assertSee('Friends');
        $this->assertTrue($this->jordan->isFriendsWith($this->sam));
        $this->actingAs($this->jordan)->post(route('friends.request', $this->jordan))->assertStatus(422);
    }

    public function test_friends_can_see_a_friends_and_league_mates_locker_but_strangers_cannot(): void
    {
        $this->sam->forceFill(['profile_visibility' => ProfileVisibility::Leagues])->save();

        $this->actingAs($this->jordan)->get($this->sam->profileUrl())->assertViewIs('profile.private');

        $this->makeFriends($this->jordan, $this->sam);
        $this->actingAs($this->jordan)->get($this->sam->profileUrl())->assertViewIs('profile.show');
    }
}
