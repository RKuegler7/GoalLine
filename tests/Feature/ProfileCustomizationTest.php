<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use App\ProfileVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profiles_live_at_their_username(): void
    {
        $user = User::factory()->create(['name' => 'Jordan Rivera', 'profile_visibility' => ProfileVisibility::Everyone]);

        $this->assertStringEndsWith('/u/jordan.rivera', $user->profileUrl());
        $this->actingAs(User::factory()->create())->get($user->profileUrl())->assertOk()->assertSee('Jordan Rivera');
    }

    public function test_everyone_visibility_shows_the_profile_to_any_member(): void
    {
        $user = User::factory()->create(['profile_visibility' => ProfileVisibility::Everyone, 'bio' => 'Chasing the CPA.']);

        $this->actingAs(User::factory()->create())->get($user->profileUrl())->assertSee('Chasing the CPA.')->assertDontSee('Edit profile');
    }

    public function test_league_visibility_shows_the_profile_only_to_league_mates(): void
    {
        $user = User::factory()->create(['profile_visibility' => ProfileVisibility::Leagues, 'bio' => 'League eyes only.']);
        $mate = User::factory()->create();
        $stranger = User::factory()->create();
        $league = League::factory()->create(['commissioner_id' => $user->id]);
        $league->members()->create(['user_id' => $user->id]);
        $league->members()->create(['user_id' => $mate->id]);

        $this->actingAs($mate)->get($user->profileUrl())->assertSee('League eyes only.');
        $this->actingAs($stranger)->get($user->profileUrl())->assertSee('This profile is private')->assertDontSee('League eyes only.');
    }

    public function test_private_profiles_are_only_visible_to_their_owner(): void
    {
        $user = User::factory()->create(['profile_visibility' => ProfileVisibility::Private, 'bio' => 'Just for me.']);
        $mate = User::factory()->create();
        $league = League::factory()->create(['commissioner_id' => $user->id]);
        $league->members()->create(['user_id' => $user->id]);
        $league->members()->create(['user_id' => $mate->id]);

        $this->actingAs($mate)->get($user->profileUrl())->assertSee('This profile is private');
        $this->actingAs($user)->get($user->profileUrl())->assertSee('Just for me.')->assertSee('Edit profile');
    }

    public function test_member_can_set_a_cover_photo_and_go_back_to_the_field(): void
    {
        Storage::fake(User::AVATAR_DISK);
        $user = User::factory()->create();
        $fields = ['name' => $user->name, 'username' => $user->username];

        $this->actingAs($user)->put(route('profile.update'), $fields + ['cover' => UploadedFile::fake()->image('cover.png', 1200, 400)]);
        $path = $user->fresh()->cover_path;
        Storage::disk(User::AVATAR_DISK)->assertExists($path);
        $this->actingAs($user)->get(route('covers.show', $user))->assertOk();

        $this->actingAs($user)->put(route('profile.update'), $fields + ['remove_cover' => '1']);
        $this->assertNull($user->fresh()->cover_path);
        Storage::disk(User::AVATAR_DISK)->assertMissing($path);
    }

    public function test_member_can_feature_one_of_their_own_goals(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create(['title' => 'Run the Marine Corps Marathon']);
        $someoneElses = Goal::factory()->create();
        $fields = ['name' => $user->name, 'username' => $user->username];

        $this->actingAs($user)->put(route('profile.update'), $fields + ['featured_goal_id' => $someoneElses->id])->assertSessionHasErrors('featured_goal_id');

        $this->actingAs($user)->put(route('profile.update'), $fields + ['featured_goal_id' => $goal->id]);
        $this->actingAs($user)->get(route('profile.show'))->assertSee('Featured goal')->assertSee('Run the Marine Corps Marathon');
    }

    public function test_profile_visibility_can_be_changed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'profile_visibility' => 'everyone',
        ]);

        $this->assertSame(ProfileVisibility::Everyone, $user->fresh()->profile_visibility);
    }

    public function test_completion_photos_follow_profile_visibility(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['profile_visibility' => ProfileVisibility::Everyone]);
        $goal = Goal::factory()->for($owner)->create();
        $goal->complete('Done', UploadedFile::fake()->image('finish.png')->store('goal-photos', 'local'));
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get(route('goals.photo', $goal))->assertOk();

        $owner->update(['profile_visibility' => ProfileVisibility::Private]);
        $this->actingAs($viewer)->get(route('goals.photo', $goal))->assertForbidden();
    }
}
