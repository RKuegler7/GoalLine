<?php

namespace Tests\Feature;

use App\GoalVisibility;
use App\Models\Challenge;
use App\Models\Friendship;
use App\Models\Goal;
use App\Models\League;
use App\Models\Medal;
use App\Models\User;
use App\RoadmapStatus;
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
        // Goals counts what they're on now: current goals (each goal's own visibility) plus current Challenges.
        $current = Goal::factory()->for($owner)->create(['title' => 'Run a half marathon', 'target_date' => now()->addMonths(3)]);
        $current->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $challenge = Challenge::factory()->for($owner, 'creator')->create(['starts_on' => now()->subDay(), 'ends_on' => now()->addDays(20)]);
        $challenge->members()->create(['user_id' => $owner->id]);
        $over = Challenge::factory()->for($owner, 'creator')->create(['starts_on' => now()->subDays(40), 'ends_on' => now()->subDays(10)]);
        $over->members()->create(['user_id' => $owner->id]);
        Medal::create(['user_id' => $owner->id, 'type' => 'perfect_week', 'award_key' => 'perfect_week:test', 'awarded_at' => now()]);

        $page = $this->actingAs($viewer)->get($owner->profileUrl())->assertOk();
        $page->assertSee('data-profile-counts', false)
            ->assertSee('data-profile-header', false)
            ->assertSeeInOrder(['data-count="friends"', '2', 'Friends', 'data-count="goals"', '2', 'Goals', 'data-count="medals"', '1', 'Medals'], false)
            ->assertDontSee('data-count="weeks-on-the-line"', false)
            ->assertSee('1 mutual friend')
            // Photo and counts, then the name, @username, the whole bio, location and "Why I'm here", then the button.
            ->assertSeeInOrder(['data-profile-counts', 'Corey Magro', '@'.$owner->username, 'Chasing a sub-4 marathon.', 'Washington, DC', "Why I'm here:", 'My first marathon', 'Add friend', 'Running', 'Coding'], false)
            ->assertSee('gl-field-rows relative overflow-hidden rounded-2xl', false)
            ->assertSee('text-gold-light tabular-nums', false)
            ->assertDontSee('Goals done')
            ->assertSeeInOrder(['About', 'Goals', 'Highlights', 'Stats', 'Trophy case', 'History'])
            ->assertSee('Joined '.$owner->created_at->format('F Y'));
    }

    public function test_long_names_wrap_instead_of_being_cut_off(): void
    {
        $owner = User::factory()->create(['name' => 'Bartholomew-Alexander Montgomery-Featherstonehaugh Ó Súilleabháin 王小明', 'profile_visibility' => 'everyone']);

        $html = $this->actingAs(User::factory()->create())->get($owner->profileUrl())->assertOk()->getContent();

        preg_match('/<h2[^>]*data-profile-name[^>]*>/', $html, $heading);
        $this->assertStringNotContainsString('line-clamp', $heading[0]);
        $this->assertStringContainsString('[overflow-wrap:anywhere]', $heading[0]);
        $this->assertStringNotContainsString('truncate', $heading[0]);
        $this->assertStringContainsString('王小明', $html);
        // The Add friend button sits on its own row under the name, never beside it.
        $this->assertGreaterThan(strpos($html, 'data-profile-name'), strpos($html, 'data-profile-actions'));
    }

    public function test_a_full_length_bio_shows_whole_and_profiles_without_a_bio_or_photo_still_read_well(): void
    {
        $bio = str_repeat('Training for my first marathon while finishing my thesis, one page and one mile a day. ', 2);
        $bio = substr($bio, 0, 160);
        $owner = User::factory()->create(['bio' => $bio, 'profile_visibility' => 'everyone']);

        $html = $this->actingAs(User::factory()->create())->get($owner->profileUrl())->assertOk()->getContent();
        preg_match('/<p[^>]*data-profile-bio[^>]*>(.*?)<\/p>/s', $html, $shown);
        $this->assertSame($bio, html_entity_decode(trim($shown[1]), ENT_QUOTES));
        $this->assertStringNotContainsString('line-clamp', $shown[0]);
        $this->assertStringNotContainsString('truncate', $shown[0]);

        // No bio and no photo: initials in the avatar, no empty bio line for visitors.
        $plain = User::factory()->create(['bio' => null, 'avatar_path' => null, 'profile_visibility' => 'everyone']);
        $this->actingAs(User::factory()->create())->get($plain->profileUrl())->assertOk()
            ->assertDontSee('data-profile-bio', false)
            ->assertDontSee('Add a short bio')
            ->assertSee('data-profile-header', false);
        $this->actingAs($plain)->get(route('profile.show'))->assertSee('Add a short bio')->assertSee('My Locker');
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

    public function test_avatar_uploads_are_validated_and_stripped_and_cover_photos_are_no_longer_shown(): void
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

        // A stored cover stays stored, but no profile shows it any more, and Edit profile has no cover upload.
        $this->actingAs(User::factory()->create())->get($user->profileUrl())->assertDontSee($user->coverUrl(), false)->assertDontSee('data-profile-cover', false)->assertDontSee($user->cover_path);
        $this->actingAs($user)->get(route('profile.show'))->assertDontSee($user->coverUrl(), false);
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertDontSee('name="cover"', false)->assertDontSee('Cover photo');
    }

    public function test_private_accounts_show_who_they_are_and_a_friend_button_but_nothing_more(): void
    {
        $private = User::factory()->create(['name' => 'Priya Shah', 'profile_visibility' => 'private', 'bio' => 'Thesis by May.', 'location' => 'Hidden town']);
        $private->forceFill(['why_here' => 'Hidden reason'])->save();
        Goal::factory()->for($private)->create(['title' => 'Secret goal title']);
        $viewer = User::factory()->create();

        // Instagram style: the header (photo, name, @username, bio, counts) and a way to add them, then a lock.
        $this->actingAs($viewer)->get($private->profileUrl())->assertOk()
            ->assertSee('data-profile-header', false)
            ->assertSee('Priya Shah')->assertSee('@'.$private->username)->assertSee('Thesis by May.')
            ->assertSeeInOrder(['data-count="friends"', 'Friends', 'data-count="goals"', 'Goals', 'data-count="medals"', 'Medals'], false)
            ->assertSee('Add friend')
            ->assertSee('This account is private')->assertSee('(private account)')
            ->assertDontSee('Hidden reason')->assertDontSee('Hidden town')->assertDontSee('Secret goal title')
            ->assertDontSee('data-profile-chips', false)->assertDontSee($private->email);

        // A friend request can be sent from there.
        $this->actingAs($viewer)->post(route('friends.request', $private))->assertRedirect();
        $this->actingAs($viewer)->get($private->profileUrl())->assertSee('Request sent');

        // Another GoalLine world (a demo visitor) can't look a real member up at all.
        $this->actingAs(User::factory()->create(['demo_sandbox' => 'world-9']))->get($private->profileUrl())->assertNotFound();
    }

    public function test_the_demo_people_have_rich_profiles(): void
    {
        Storage::fake('uploads');
        $this->get(route('demo'));
        $peter = User::where('demo_sandbox', auth()->user()->demo_sandbox)->where('name', 'Peter Kilpatrick')->sole();

        $this->get($peter->profileUrl())->assertOk()
            ->assertSee('Early miles, then the day')->assertSee('Reading')->assertDontSee('data-profile-cover', false)
            ->assertSee('Finish a spring marathon');

        // The visitor is Ryan, with his own photo (from resources/demo), bio and location.
        $this->assertStringStartsWith('avatars/demo-', auth()->user()->avatar_path);
        Storage::disk(User::AVATAR_DISK)->assertExists(auth()->user()->avatar_path);
        $this->get(route('profile.show'))->assertOk()
            ->assertSee('Ryan Kuegler')->assertSee('Finance and Sales at the Busch School of Business')->assertSee('Washington, DC')
            ->assertSee('Building GoalLine, one GoalWeek at a time');
    }
}
