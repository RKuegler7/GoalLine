<?php

namespace Tests\Feature;

use App\Models\User;
use App\ProfileVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_you_can_follow_and_unfollow_people_whose_locker_you_can_see(): void
    {
        $viewer = User::factory()->create();
        $person = User::factory()->create(['profile_visibility' => ProfileVisibility::Everyone]);

        $this->actingAs($viewer)->post(route('follows.store', $person));
        $this->assertTrue($viewer->follows($person));

        $this->actingAs($viewer)->get($person->profileUrl())->assertSee('Following');

        $this->actingAs($viewer)->delete(route('follows.destroy', $person));
        $this->assertFalse($viewer->follows($person));
    }

    public function test_you_cannot_follow_private_lockers_or_yourself(): void
    {
        $viewer = User::factory()->create();
        $private = User::factory()->create(['profile_visibility' => ProfileVisibility::Private]);

        $this->actingAs($viewer)->post(route('follows.store', $private))->assertForbidden();
        $this->actingAs($viewer)->post(route('follows.store', $viewer))->assertStatus(422);
    }

    public function test_friends_are_mutual_follows(): void
    {
        $jordan = User::factory()->create(['name' => 'Jordan Rivera', 'profile_visibility' => ProfileVisibility::Everyone]);
        $sam = User::factory()->create(['name' => 'Sam Okafor', 'profile_visibility' => ProfileVisibility::Everyone]);
        $fan = User::factory()->create(['name' => 'Fan Only']);

        $jordan->following()->attach($sam);
        $fan->following()->attach($jordan);
        $this->assertTrue($jordan->friends()->isEmpty());

        $sam->following()->attach($jordan);
        $this->assertSame(['Sam Okafor'], $jordan->friends()->pluck('name')->all());

        $this->actingAs($jordan)->get(route('profile.show'))->assertSee('Sam Okafor')->assertDontSee('Fan Only');
        $this->actingAs($jordan)->get($sam->profileUrl())->assertSee('Friends');
    }

    public function test_people_search_only_finds_lockers_you_can_see(): void
    {
        $viewer = User::factory()->create();
        User::factory()->create(['name' => 'Priya Public', 'profile_visibility' => ProfileVisibility::Everyone]);
        User::factory()->create(['name' => 'Priya Private', 'profile_visibility' => ProfileVisibility::Private]);
        User::factory()->create(['name' => 'Priya Leagues', 'profile_visibility' => ProfileVisibility::Leagues]);

        $this->actingAs($viewer)
            ->get(route('people.index', ['q' => 'priya']))
            ->assertSee('Priya Public')
            ->assertDontSee('Priya Private')
            ->assertDontSee('Priya Leagues');
    }
}
