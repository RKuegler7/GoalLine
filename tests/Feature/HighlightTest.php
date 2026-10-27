<?php

namespace Tests\Feature;

use App\HighlightKind;
use App\Models\Goal;
use App\Models\Highlight;
use App\Models\League;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\ProfileVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HighlightTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_completed_goal_can_be_shared_with_its_photo(): void
    {
        Storage::fake('uploads');
        $goal = Goal::factory()->create(['title' => 'Run a half marathon']);
        $goal->complete('Hardest thing I have trained for.', $this->fakeImage('finish.png')->store('goal-photos', 'uploads'));

        $this->actingAs($goal->user)
            ->get(route('highlights.create', ['goal' => $goal->id]))
            ->assertOk()
            ->assertSee('Crossed the GoalLine')
            ->assertSee('Hardest thing I have trained for.');

        $this->actingAs($goal->user)->post(route('highlights.store'), [
            'goal' => $goal->id,
            'caption' => 'Thank you to everyone who ran with me.',
            'use_goal_photo' => '1',
        ]);

        $highlight = Highlight::sole();
        $this->assertSame(HighlightKind::GoalCompleted, $highlight->kind);
        $this->assertSame('Run a half marathon', $highlight->headline);
        Storage::disk('uploads')->assertExists($highlight->photo_path);
        $this->assertNotSame($goal->completion_photo_path, $highlight->photo_path, 'The highlight keeps its own copy of the photo.');
    }

    public function test_only_finished_goals_great_weeks_and_hit_milestones_can_be_shared(): void
    {
        $goal = Goal::factory()->create();
        $week = $this->weekWith($goal, done: 1, of: 2, milestone: false);

        $this->actingAs($goal->user)->get(route('highlights.create', ['goal' => $goal->id]))->assertForbidden();
        $this->actingAs($goal->user)->get(route('highlights.create', ['week' => $week->id]))->assertForbidden();
        $this->actingAs($goal->user)->get(route('highlights.create', ['milestone' => $week->id]))->assertForbidden();

        $great = $this->weekWith($goal, done: 2, of: 2, milestone: true, position: 2, bonus: 1);
        $this->actingAs($goal->user)->get(route('highlights.create', ['week' => $great->id]))->assertOk()->assertSee('Past the line');
        $this->actingAs($goal->user)->get(route('highlights.create', ['milestone' => $great->id]))->assertOk()->assertSee('Hit a milestone');
    }

    public function test_you_cannot_share_someone_elses_goal(): void
    {
        $goal = Goal::factory()->create();
        $goal->complete();

        $this->actingAs(User::factory()->create())->get(route('highlights.create', ['goal' => $goal->id]))->assertForbidden();
    }

    public function test_sharing_the_same_moment_twice_points_to_the_first_highlight(): void
    {
        $goal = Goal::factory()->create();
        $goal->complete();
        $this->actingAs($goal->user)->post(route('highlights.store'), ['goal' => $goal->id]);
        $this->actingAs($goal->user)->post(route('highlights.store'), ['goal' => $goal->id]);

        $this->assertSame(1, Highlight::count());
    }

    public function test_the_feed_shows_friends_and_league_mates_but_not_strangers(): void
    {
        $viewer = User::factory()->create();
        $followed = User::factory()->create(['profile_visibility' => ProfileVisibility::Everyone]);
        $mate = User::factory()->create();
        $stranger = User::factory()->create(['profile_visibility' => ProfileVisibility::Everyone]);
        $this->makeFriends($viewer, $followed);
        $league = League::factory()->create(['commissioner_id' => $viewer->id]);
        $league->members()->create(['user_id' => $viewer->id]);
        $league->members()->create(['user_id' => $mate->id]);

        Highlight::factory()->for($followed)->create(['headline' => 'Followed win']);
        Highlight::factory()->for($mate)->create(['headline' => 'League mate win']);
        Highlight::factory()->for($stranger)->create(['headline' => 'Stranger win']);
        Highlight::factory()->for($viewer)->create(['headline' => 'My own win']);

        $this->actingAs($viewer)
            ->get(route('highlights.index'))
            ->assertSee('Followed win')
            ->assertSee('League mate win')
            ->assertSee('My own win')
            ->assertDontSee('Stranger win');
    }

    public function test_private_lockers_keep_highlights_out_of_the_feed(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create(['profile_visibility' => ProfileVisibility::Everyone]);
        $this->makeFriends($viewer, $author);
        $highlight = Highlight::factory()->for($author)->create(['headline' => 'Hidden win']);

        $author->update(['profile_visibility' => ProfileVisibility::Private]);

        $this->actingAs($viewer)->get(route('highlights.index'))->assertDontSee('Hidden win');
        $this->actingAs($viewer)->post(route('highlights.cheer', $highlight))->assertForbidden();
    }

    public function test_kudos_toggle_and_name_who_gave_them(): void
    {
        $author = User::factory()->create(['name' => 'Jordan Rivera', 'profile_visibility' => ProfileVisibility::Everyone]);
        $fan = User::factory()->create(['name' => 'Sam Okafor']);
        $this->makeFriends($fan, $author);
        $highlight = Highlight::factory()->for($author)->create();

        $this->actingAs($fan)->post(route('highlights.cheer', $highlight));
        $this->assertSame(1, $highlight->cheers()->count());

        $this->actingAs($author)->get(route('highlights.index'))->assertSee('Sam Okafor gave kudos');
        $this->actingAs($fan)->get(route('highlights.index'))->assertSee('You gave kudos')->assertSee('Kudos given');

        $this->actingAs($fan)->post(route('highlights.cheer', $highlight));
        $this->assertSame(0, $highlight->cheers()->count());
    }

    public function test_kudos_summary_names_two_people_then_counts_the_rest(): void
    {
        $highlight = Highlight::factory()->create();
        foreach (['Ava', 'Ben', 'Cal', 'Dee'] as $name) {
            $highlight->cheers()->create(['user_id' => User::factory()->create(['name' => $name])->id]);
        }

        $this->assertSame('Ava, Ben, and 2 others gave kudos', $highlight->load('cheers.user')->cheerSummary($highlight->user));
    }

    public function test_authors_can_remove_highlights_and_get_a_share_card(): void
    {
        $highlight = Highlight::factory()->create(['headline' => 'Pass the CPA FAR exam']);
        $other = User::factory()->create();

        $this->actingAs($highlight->user)->get(route('highlights.card', $highlight))->assertOk()->assertSee('data-share-card', false)->assertSee('Pass the CPA');
        $this->actingAs($other)->get(route('highlights.card', $highlight))->assertForbidden();
        $this->actingAs($other)->delete(route('highlights.destroy', $highlight))->assertForbidden();

        $this->actingAs($highlight->user)->delete(route('highlights.destroy', $highlight))->assertRedirect(route('highlights.index'));
        $this->assertModelMissing($highlight);
    }

    public function test_the_completion_page_offers_to_share(): void
    {
        $goal = Goal::factory()->create();
        $goal->complete();

        $this->actingAs($goal->user)->get(route('goals.show', $goal))->assertSee('Share to Highlights');
    }

    private function weekWith(Goal $goal, int $done, int $of, bool $milestone, int $position = 1, int $bonus = 0): RoadmapWeek
    {
        $week = $goal->weeks()->create([
            'position' => $position,
            'starts_on' => today()->subDays(2),
            'ends_on' => today()->addDays(4),
            'focus' => 'Focus',
            'milestone' => 'Finish the first module.',
        ]);
        $week->forceFill(['milestone_hit_at' => $milestone ? now() : null])->save();

        foreach (range(1, $of) as $index) {
            $week->tasks()->create(['position' => $index, 'description' => "Task {$index}"])
                ->forceFill(['completed_at' => $index <= $done ? now() : null])->save();
        }

        foreach (range(1, $bonus) as $index) {
            if ($bonus > 0) {
                $week->tasks()->create(['position' => $of + $index, 'description' => "Bonus {$index}", 'is_bonus' => true])
                    ->forceFill(['completed_at' => now()])->save();
            }
        }

        return $week;
    }
}
