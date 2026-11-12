<?php

namespace Tests\Feature;

use App\GoalVisibility;
use App\Models\Friendship;
use App\Models\Goal;
use App\Models\League;
use App\Models\Medal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The profile: identity on top (cover, photo, a name that never gets cut off, counts, bio), then Goals, Highlights,
 * Stats, Trophy case and History, with each goal's visibility and the profile-details setting respected.
 */
class ProfileRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function friends(User $a, User $b): void
    {
        Friendship::create(['requester_id' => $a->id, 'addressee_id' => $b->id])->forceFill(['status' => Friendship::ACCEPTED])->save();
    }

    public function test_the_top_is_identity_with_a_counts_row_and_sections(): void
    {
        $owner = User::factory()->create(['name' => 'Corey Magro', 'bio' => 'Chasing a sub-4 marathon.', 'profile_visibility' => 'everyone']);
        $owner->forceFill(['why_here' => 'My first marathon', 'interests' => ['Running', 'Coding'], 'location' => 'Washington, DC'])->save();
        $viewer = User::factory()->create();
        $mutual = User::factory()->create();
        $this->friends($owner, User::factory()->create());
        $this->friends($owner, $mutual);
        $this->friends($viewer, $mutual);
        Goal::factory()->for($owner)->create(['title' => 'Learn to swim'])->complete();
        Medal::create(['user_id' => $owner->id, 'type' => 'perfect_week', 'award_key' => 'perfect_week:test', 'awarded_at' => now()]);

        $page = $this->actingAs($viewer)->get($owner->profileUrl())->assertOk();
        $page->assertSee('data-profile-counts', false)
            ->assertSeeInOrder(['data-count="friends"', '2', 'Friends', 'data-count="goals-completed"', '1', 'Goals done', 'data-count="medals"', '1', 'Medals', 'data-count="weeks-on-the-line"', 'On the line'], false)
            ->assertSee('1 mutual friend')
            ->assertSeeInOrder(['Corey Magro', 'Add friend', 'Chasing a sub-4 marathon.', "Why I'm here:", 'My first marathon', 'Running', 'Coding'], false)
            ->assertSeeInOrder(['About', 'Goals', 'Highlights', 'Stats', 'Trophy case', 'History'])
            ->assertSee('Joined '.$owner->created_at->format('F Y'));
    }

    public function test_long_names_wrap_instead_of_being_cut_off(): void
    {
        $owner = User::factory()->create(['name' => 'Bartholomew-Alexander Montgomery-Featherstonehaugh Ó Súilleabháin 王小明', 'profile_visibility' => 'everyone']);

        $html = $this->actingAs(User::factory()->create())->get($owner->profileUrl())->assertOk()->getContent();

        preg_match('/<h1[^>]*data-profile-name[^>]*>/', $html, $heading);
        $this->assertStringContainsString('line-clamp-3', $heading[0]);
        $this->assertStringContainsString('[overflow-wrap:anywhere]', $heading[0]);
        $this->assertStringNotContainsString('truncate', $heading[0]);
        $this->assertStringContainsString('王小明', $html);
        // The Add friend button sits on its own row under the name, never beside it.
        $this->assertGreaterThan(strpos($html, 'data-profile-name'), strpos($html, 'data-profile-actions'));
    }

    public function test_each_goal_shows_only_to_who_its_owner_chose(): void
    {
        $owner = User::factory()->create(['profile_visibility' => 'everyone']);
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        $this->friends($owner, $friend);
        Goal::factory()->for($owner)->create(['title' => 'Public half marathon', 'visibility' => GoalVisibility::Everyone]);
        Goal::factory()->for($owner)->create(['title' => 'Circle-only CPA', 'visibility' => GoalVisibility::FriendsAndLeagues]);
        Goal::factory()->for($owner)->create(['title' => 'Private weight goal', 'visibility' => GoalVisibility::Private, 'why' => 'Secret reason']);

        $this->actingAs($stranger)->get($owner->profileUrl())->assertSee('Public half marathon')->assertDontSee('Circle-only CPA')->assertDontSee('Private weight goal');
        $this->actingAs($friend)->get($owner->profileUrl())->assertSee('Public half marathon')->assertSee('Circle-only CPA')->assertDontSee('Private weight goal')->assertDontSee('Secret reason');
        $this->actingAs($owner)->get($owner->profileUrl())->assertSee('Private weight goal')->assertSee('Only me')->assertDontSee('Secret reason');

        // A league mate counts as the circle too.
        $mate = User::factory()->create();
        $league = League::factory()->create();
        $league->members()->create(['user_id' => $owner->id]);
        $league->members()->create(['user_id' => $mate->id]);
        $this->actingAs($mate)->get($owner->profileUrl())->assertSee('Circle-only CPA');
    }

    public function test_goal_visibility_is_chosen_on_the_goal_form(): void
    {
        $owner = User::factory()->create();
        $goal = Goal::factory()->for($owner)->create();

        $this->actingAs($owner)->get(route('goals.edit', $goal))->assertSee('Who can see this goal on your profile');
        $this->put(route('goals.update', $goal), ['title' => $goal->title, 'category' => $goal->category->value, 'target_date' => $goal->target_date->toDateString(), 'visibility' => 'private'])->assertRedirect();
        $this->assertSame(GoalVisibility::Private, $goal->fresh()->visibility);
    }

    public function test_bio_why_and_interests_are_checked_for_length_and_language(): void
    {
        $user = User::factory()->create();
        $base = ['name' => $user->name, 'username' => $user->username, 'email' => $user->email];

        $this->actingAs($user)->put(route('profile.update'), $base + ['bio' => str_repeat('x', 161)])->assertSessionHasErrors('bio');
        $this->put(route('profile.update'), $base + ['why_here' => str_repeat('x', 81)])->assertSessionHasErrors('why_here');
        $this->put(route('profile.update'), $base + ['why_here' => 'f.u.c.k this'])->assertSessionHasErrors('why_here');
        $this->put(route('profile.update'), $base + ['interests' => ['Running', 'Coding', 'Art', 'Music', 'Yoga', 'Chess']])->assertSessionHasErrors('interests');
        $this->put(route('profile.update'), $base + ['interests_other' => str_repeat('y', 25)])->assertSessionHasErrors('interests.0');

        $this->put(route('profile.update'), $base + ['why_here' => 'First marathon', 'interests' => ['Running'], 'interests_other' => 'Chess, running, Gardening'])->assertSessionHasNoErrors();
        $this->assertSame(['Running', 'Chess', 'Gardening'], $user->fresh()->interests);
        $this->assertSame('First marathon', $user->fresh()->why_here);

        // Unchecking every interest clears them.
        $this->put(route('profile.update'), $base + ['interests_present' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->interests);
    }

    public function test_avatar_and_cover_uploads_are_validated_stripped_and_served_through_the_app(): void
    {
        Storage::fake('uploads');
        $user = User::factory()->create(['profile_visibility' => 'everyone']);
        $base = ['name' => $user->name, 'username' => $user->username, 'email' => $user->email];
        $jpeg = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xE1\x00\x1DExif\x00\x00GPS-38.9072N-77.0369W\xFF\xDA\x00\x08\x01\x01\x00\x00?\x00\xD2\xCF \xFF\xD9";

        $this->actingAs($user)->put(route('profile.update'), $base + ['cover' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertSessionHasErrors('cover');
        $this->put(route('profile.update'), $base + ['avatar' => UploadedFile::fake()->createWithContent('big.jpg', $jpeg)->size(6000)])->assertSessionHasErrors('avatar');

        $this->put(route('profile.update'), $base + ['cover' => UploadedFile::fake()->createWithContent('run.jpg', $jpeg), 'avatar' => UploadedFile::fake()->createWithContent('me.jpg', $jpeg)])->assertSessionHasNoErrors();
        $user->refresh();

        foreach ([$user->cover_path, $user->avatar_path] as $path) {
            $stored = Storage::disk('uploads')->get($path);
            $this->assertStringStartsWith("\xFF\xD8", $stored);
            $this->assertStringNotContainsString('GPS-38.9072N', $stored);
        }

        $this->get($user->coverUrl())->assertOk();
        $this->actingAs(User::factory()->create())->get($user->profileUrl())->assertSee($user->coverUrl(), false)->assertDontSee($user->cover_path);
    }

    public function test_private_profiles_stay_calm_and_limited(): void
    {
        $private = User::factory()->create(['profile_visibility' => 'private', 'bio' => 'Hidden bio']);
        $private->forceFill(['why_here' => 'Hidden reason'])->save();

        $this->actingAs(User::factory()->create())->get($private->profileUrl())->assertOk()
            ->assertSee('This profile is private')->assertDontSee('Hidden bio')->assertDontSee('Hidden reason')->assertDontSee($private->email);
    }

    public function test_the_demo_people_have_rich_profiles(): void
    {
        $this->get(route('demo'));
        $red = User::where('demo_sandbox', auth()->user()->demo_sandbox)->where('name', 'Red Dufour')->sole();

        $this->get($red->profileUrl())->assertOk()
            ->assertSee('Sub-3:45 or bust')->assertSee('Outdoors')->assertSee($red->coverUrl(), false)
            ->assertSee('Run a marathon under 3:45 by March');
    }
}
