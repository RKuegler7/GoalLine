<?php

namespace Tests\Feature;

use App\Exceptions\AiLimitReached;
use App\HighlightKind;
use App\Models\Goal;
use App\Models\Highlight;
use App\Models\League;
use App\Models\RoadmapTask;
use App\Models\User;
use App\Notifications\HighlightCheered;
use App\PostVisibility;
use App\RoadmapStatus;
use App\Services\CaptionWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class CheckInPostTest extends TestCase
{
    use RefreshDatabase;

    /** Posts the check-in. */
    private User $avery;

    /** League owner. */
    private User $sam;

    /** Another league member. */
    private User $lee;

    /** Avery's friend, not in the league. */
    private User $fran;

    /** Neither a league mate nor a friend. */
    private User $stranger;

    private League $league;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('uploads');

        $this->travelTo(Carbon::parse('2026-10-01 09:00', config('app.timezone')));
        $this->avery = User::factory()->create(['name' => 'Avery Lee']);
        $this->sam = User::factory()->create(['name' => 'Sam Okafor']);
        $this->lee = User::factory()->create(['name' => 'Lee Park']);
        $this->fran = User::factory()->create(['name' => 'Fran Diaz']);
        $this->stranger = User::factory()->create(['name' => 'Stan Ger']);
        $this->league = League::factory()->create(['name' => 'Morning Milers', 'commissioner_id' => $this->sam->id, 'starts_on' => '2026-10-19', 'ends_on' => '2026-11-15']);
        foreach ([$this->avery, $this->sam, $this->lee] as $member) {
            $this->league->members()->create(['user_id' => $member->id, 'goal_id' => $this->goalFor($member)->id]);
        }
        $this->makeFriends($this->avery, $this->fran);
        $this->travelTo(Carbon::parse('2026-10-21 09:00', config('app.timezone')));
    }

    public function test_a_photo_posts_to_the_league_by_default_with_caption_and_where(): void
    {
        $task = $this->checkedOffTask();

        $this->actingAs($this->avery)
            ->post(route('roadmap.tasks.proof.store', $task), ['proof' => $this->fakeImage('run.png'), 'caption' => 'Rainy, but done.', 'location' => 'Riverside trail'])
            ->assertSessionHas('status', 'Proof added and posted to Morning Milers. That check-in is Verified.');

        $post = Highlight::sole();
        $this->assertSame(HighlightKind::CheckIn, $post->kind);
        $this->assertSame(PostVisibility::League, $post->visibility);
        $this->assertSame($this->league->id, $post->league_id);
        $this->assertSame($task->id, $post->roadmap_task_id);
        Storage::disk('uploads')->assertExists($post->photo_path);

        $this->actingAs($this->lee)->get(route('leagues.feed', $this->league))->assertOk()->assertSeeInOrder([
            'Avery Lee', 'Morning Milers', 'Run for Avery Lee', 'Riverside trail', $post->photoUrl(), 'Cheer', 'Verified', 'Rainy, but done.', 'Build the prototype',
        ], false);
        $this->actingAs($this->lee)->get($post->photoUrl())->assertOk();
    }

    public function test_league_posts_are_hidden_from_non_members_and_non_friends_and_so_are_their_images(): void
    {
        $this->actingAs($this->avery)->post(route('roadmap.tasks.proof.store', $this->checkedOffTask()), ['proof' => $this->fakeImage(), 'visibility' => 'league']);
        $post = Highlight::sole();

        foreach ([$this->fran, $this->stranger] as $outsider) {
            $this->actingAs($outsider)->get($post->photoUrl())->assertForbidden();
            $this->actingAs($outsider)->post(route('highlights.cheer', $post))->assertForbidden();
            $this->actingAs($outsider)->get(route('highlights.index'))->assertDontSee($post->photoUrl());
            $this->actingAs($outsider)->get(route('dashboard'))->assertDontSee($post->photoUrl());
            $this->actingAs($outsider)->get(route('leagues.feed', $this->league))->assertForbidden();
        }

        $this->actingAs($this->sam)->get(route('highlights.index'))->assertSee($post->photoUrl());
    }

    public function test_league_and_friends_posts_reach_friends_but_never_strangers(): void
    {
        $this->actingAs($this->avery)
            ->post(route('roadmap.tasks.proof.store', $this->checkedOffTask()), ['proof' => $this->fakeImage(), 'visibility' => 'league_friends', 'caption' => 'Week one, day three.'])
            ->assertSessionHas('status');
        $post = Highlight::sole();

        $this->actingAs($this->fran)->get(route('dashboard'))->assertSee('Week one, day three.')->assertSee($post->photoUrl());
        $this->actingAs($this->fran)->get($post->photoUrl())->assertOk();
        $this->actingAs($this->lee)->get($post->photoUrl())->assertOk();

        $this->actingAs($this->stranger)->get($post->photoUrl())->assertForbidden();
        $this->actingAs($this->stranger)->get(route('dashboard'))->assertDontSee('Week one, day three.');
    }

    public function test_proof_only_makes_no_post_and_a_new_photo_replaces_the_post(): void
    {
        $task = $this->checkedOffTask();

        $this->actingAs($this->avery)->post(route('roadmap.tasks.proof.store', $task), ['proof' => $this->fakeImage(), 'visibility' => 'proof'])
            ->assertSessionHas('status', 'Proof added. That check-in is Verified.');
        $this->assertSame(0, Highlight::count());
        $this->assertTrue($task->fresh()->isVerified());

        // Proof can be replaced while the week is open: posting it then switching back to proof only.
        $task->forceFill(['proof_path' => null])->saveQuietly();
        $this->actingAs($this->avery)->post(route('roadmap.tasks.proof.store', $task), ['proof' => $this->fakeImage(), 'visibility' => 'league']);
        $this->assertSame(1, Highlight::count());
        $firstPhoto = Highlight::sole()->photo_path;

        $this->actingAs($this->avery)->post(route('roadmap.tasks.proof.store', $task), ['proof' => $this->fakeImage(), 'visibility' => 'league_friends']);
        $this->assertSame(PostVisibility::LeagueFriends, Highlight::sole()->visibility);
        Storage::disk('uploads')->assertMissing($firstPhoto);

        $this->actingAs($this->avery)->post(route('roadmap.tasks.proof.store', $task), ['proof' => $this->fakeImage(), 'visibility' => 'proof']);
        $this->assertSame(0, Highlight::count());
    }

    public function test_caption_and_where_have_limits(): void
    {
        $task = $this->checkedOffTask();

        $this->actingAs($this->avery)
            ->post(route('roadmap.tasks.proof.store', $task), ['proof' => $this->fakeImage(), 'caption' => str_repeat('a', 281), 'location' => str_repeat('b', 61), 'proof_task_id' => $task->id])
            ->assertSessionHasErrors(['caption' => 'Captions can be up to 280 characters.', 'location' => 'Keep "Where?" to 60 characters.']);
        $this->assertSame(0, Highlight::count());
        $this->assertFalse($task->fresh()->isVerified());

        $this->actingAs($this->avery)->post(route('roadmap.tasks.proof.store', $task), ['proof' => $this->fakeImage(), 'visibility' => 'everyone'])->assertSessionHasErrors('visibility');
    }

    public function test_the_proof_form_offers_the_choices_and_the_home_map_hint(): void
    {
        $task = $this->checkedOffTask();

        $this->actingAs($this->avery)->get(route('goals.show', $task->week->goal))
            ->assertSee('Who sees it?')->assertSee('Proof only')->assertSee('League + friends')
            ->assertSee('Where?')->assertSee('Crop out maps that show your home.');
    }

    public function test_reporting_hides_the_post_from_the_reporter_and_tells_the_league_owner(): void
    {
        $this->actingAs($this->avery)->post(route('roadmap.tasks.proof.store', $this->checkedOffTask()), ['proof' => $this->fakeImage(), 'caption' => 'Look at this']);
        $post = Highlight::sole();

        $this->actingAs($this->avery)->post(route('highlights.report', $post))->assertForbidden();
        $this->actingAs($this->stranger)->post(route('highlights.report', $post))->assertForbidden();

        $this->actingAs($this->lee)->post(route('highlights.report', $post), ['reason' => 'Shows a street sign near home'])->assertSessionHas('status');
        $this->actingAs($this->lee)->get(route('leagues.feed', $this->league))->assertDontSee('Look at this');
        $this->actingAs($this->lee)->get($post->photoUrl())->assertForbidden();

        $this->actingAs($this->sam)->get(route('leagues.show', $this->league))->assertSee('1 report to review');
        $this->actingAs($this->sam)->get(route('leagues.feed', $this->league))->assertSee('Reports to review')->assertSee('Shows a street sign near home')->assertSee('Look at this');
        $this->actingAs($this->lee)->get(route('leagues.feed', $this->league))->assertDontSee('Reports to review');

        // The owner can take it down; other members can't.
        $this->actingAs($this->lee)->delete(route('highlights.destroy', $post))->assertForbidden();
        $this->actingAs($this->sam)->delete(route('highlights.destroy', $post))->assertSessionHas('status', 'Post removed from the league.');
        $this->assertSame(0, Highlight::count());
    }

    public function test_cheers_count_on_a_post(): void
    {
        $this->actingAs($this->avery)->post(route('roadmap.tasks.proof.store', $this->checkedOffTask()), ['proof' => $this->fakeImage()]);
        $post = Highlight::sole();
        Notification::fake();

        $this->actingAs($this->lee)->post(route('highlights.cheer', $post))->assertRedirect();
        $this->actingAs($this->sam)->post(route('highlights.cheer', $post));

        $this->assertSame(2, $post->cheers()->count());
        $this->actingAs($this->avery)->get(route('leagues.feed', $this->league))->assertSee('Lee Park and Sam Okafor cheered');
        Notification::assertSentTo($this->avery, HighlightCheered::class, fn (HighlightCheered $cheer) => $cheer->fan->is($this->lee)
            && $cheer->toArray($this->avery)['text'] === 'Lee Park cheered your post: Build the prototype');
    }

    public function test_the_friends_feed_starts_with_an_encouraging_empty_state(): void
    {
        $this->actingAs($this->stranger)->get(route('dashboard'))->assertSee('Goals stick better with company.')->assertSee(route('friends.index'));
        $this->actingAs($this->fran)->get(route('dashboard'))->assertSee('Quiet for now.');
    }

    public function test_suggest_a_caption_fills_the_box_and_handles_failure(): void
    {
        $task = $this->checkedOffTask();
        $calls = 0;
        $this->mock(CaptionWriter::class, fn (MockInterface $mock) => $mock->shouldReceive('suggest')->andReturnUsing(function () use (&$calls) {
            return match (++$calls) {
                1 => 'Three miles in the rain. Showing up counts.',
                2 => null,
                default => throw new RuntimeException('API down'),
            };
        }));

        $this->actingAs($this->avery)->postJson(route('roadmap.tasks.caption', $task))->assertOk()->assertJson(['caption' => 'Three miles in the rain. Showing up counts.']);
        $this->actingAs($this->avery)->postJson(route('roadmap.tasks.caption', $task))->assertStatus(503)->assertJsonStructure(['message']);
        $this->actingAs($this->avery)->postJson(route('roadmap.tasks.caption', $task))->assertStatus(503)->assertJson(['message' => "Couldn't suggest one right now. Write your own!"]);
        $this->actingAs($this->lee)->postJson(route('roadmap.tasks.caption', $task))->assertForbidden();

        $this->assertSame(0, Highlight::count());
    }

    public function test_suggest_a_caption_respects_the_ai_limits(): void
    {
        $task = $this->checkedOffTask();
        $this->mock(CaptionWriter::class, fn (MockInterface $mock) => $mock->shouldReceive('suggest')->andThrow(new AiLimitReached("You've used today's AI coaching. It resets tomorrow.")));

        $this->actingAs($this->avery)->postJson(route('roadmap.tasks.caption', $task))
            ->assertStatus(429)->assertJson(['message' => "You've used today's AI coaching. It resets tomorrow. Write your own!"]);
    }

    private function goalFor(User $user): Goal
    {
        $goal = Goal::factory()->for($user)->create(['title' => "Run for {$user->name}", 'target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Base miles', 'milestone' => 'Run 10 miles.']);
        $week->tasks()->create(['position' => 1, 'description' => 'Build the prototype']);

        return $goal;
    }

    private function checkedOffTask(): RoadmapTask
    {
        $task = $this->avery->goals()->sole()->weeks()->sole()->tasks()->sole();
        $task->forceFill(['completed_at' => now()])->saveQuietly();

        return $task;
    }
}
