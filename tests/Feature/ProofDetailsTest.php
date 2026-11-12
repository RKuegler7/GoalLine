<?php

namespace Tests\Feature;

use App\GoalWeekResult;
use App\HighlightKind;
use App\Models\Goal;
use App\Models\League;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * "Show your work" with details: a photo, a caption, where and when, or any mix. Details are seen by the same people
 * as the proof, and only a photo (or a confirmation) earns Verified or lets a Smashed week count in full.
 */
class ProofDetailsTest extends TestCase
{
    use RefreshDatabase;

    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private User $avery;

    private User $sam;

    private League $league;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('uploads');

        // Avery and Sam's league kicked off Monday, Oct 19; today is Wednesday, Oct 21 at 9 a.m.
        $this->at('2026-10-01 09:00');
        $this->avery = User::factory()->create(['name' => 'Avery Lee']);
        $this->sam = User::factory()->create(['name' => 'Sam Okafor']);
        $this->league = League::factory()->create(['commissioner_id' => $this->sam->id, 'starts_on' => '2026-10-19', 'ends_on' => '2026-11-15']);

        foreach ([$this->avery, $this->sam] as $user) {
            $goal = Goal::factory()->for($user)->create(['target_date' => '2026-12-31']);
            $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
            $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'F', 'milestone' => 'M']);
            $week->tasks()->create(['position' => 1, 'description' => 'Morning run']);
            $week->tasks()->create(['position' => 2, 'description' => 'Strength']);
            $this->league->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);
        }

        $this->at('2026-10-21 09:00');
    }

    private function at(string $moment): void
    {
        $this->travelTo(Carbon::parse($moment, 'America/New_York'));
    }

    private function week(): RoadmapWeek
    {
        return $this->avery->goals()->sole()->weeks()->sole()->load('tasks');
    }

    private function checkedTask(): RoadmapTask
    {
        $task = $this->week()->tasks->first();
        $task->forceFill(['completed_at' => now()->subHour()])->save();

        return $task;
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('run.png', base64_decode(self::PIXEL));
    }

    private function prove(RoadmapTask $task, array $fields): TestResponse
    {
        return $this->actingAs($this->avery)->post(route('roadmap.tasks.proof.store', $task), $fields + ['visibility' => 'proof']);
    }

    public function test_details_only_are_saved_but_never_verified(): void
    {
        $task = $this->checkedTask();

        $this->prove($task, ['caption' => 'Easy miles', 'location' => 'Rock Creek Park', 'start_time' => '06:30', 'end_time' => '07:15'])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Details added. Add a photo any time to earn the Verified check.');

        $task->refresh();
        $this->assertTrue($task->hasDetails());
        $this->assertFalse($task->isVerified());
        $this->assertSame('6:30 to 7:15 a.m. at Rock Creek Park', $task->detailsLine());

        $this->get(route('goals.show', $task->week->goal))->assertSee('Details added')->assertSee('6:30 to 7:15 a.m. at Rock Creek Park')
            ->assertSee('Photos earn the Verified check. Details help your league see your process.');
    }

    public function test_a_photo_only_or_photo_and_details_earn_verified(): void
    {
        $task = $this->checkedTask();
        $this->prove($task, ['proof' => $this->photo()])->assertSessionHasNoErrors();
        $this->assertTrue($task->fresh()->isVerified());
        $this->assertFalse($task->fresh()->hasDetails());

        $other = $this->week()->tasks->last();
        $other->forceFill(['completed_at' => now()->subHour()])->save();
        $this->prove($other, ['proof' => $this->photo(), 'caption' => 'Squats', 'window' => 'morning'])->assertSessionHasNoErrors();
        $this->assertTrue($other->fresh()->isVerified());
        $this->assertSame('Morning', $other->fresh()->detailsLine());
    }

    public function test_nothing_at_all_is_a_friendly_error(): void
    {
        $task = $this->checkedTask();
        $this->prove($task, [])->assertSessionHasErrors(['proof' => 'Add a photo, or a few details: a caption, where, or when.']);
    }

    public function test_the_time_window_is_validated(): void
    {
        $task = $this->checkedTask();

        $this->prove($task, ['start_time' => '07:15', 'end_time' => '06:30'])->assertSessionHasErrors(['end_time' => 'The end time needs to be after the start time.']);
        $this->prove($task, ['start_time' => '08:30', 'end_time' => '10:00'])->assertSessionHasErrors(['end_time' => "That time hasn't happened yet."]);
        $this->prove($task, ['start_time' => '06:30'])->assertSessionHasErrors('end_time');
        $this->prove($task, ['start_time' => '6:30am', 'end_time' => '07:00'])->assertSessionHasErrors('start_time');
        $this->prove($task, ['caption' => str_repeat('x', 281)])->assertSessionHasErrors('caption');
        $this->prove($task, ['location' => str_repeat('x', 61)])->assertSessionHasErrors('location');

        $this->at('2026-10-21 23:00');
        $this->prove($task, ['start_time' => '06:00', 'end_time' => '19:00'])->assertSessionHasErrors(['end_time' => 'Keep it to 12 hours or less.']);
        $this->prove($task, ['start_time' => '06:00', 'end_time' => '18:00'])->assertSessionHasNoErrors();
        $this->assertSame('6 a.m. to 6 p.m.', $task->fresh()->detailsLine());
    }

    public function test_windows_on_daylight_saving_days_use_real_hours(): void
    {
        // Sunday, Nov 1, 2026: clocks fall back at 2 a.m., so 12:30 to 3:00 a.m. is three and a half real hours.
        $week = $this->week();
        $week->forceFill(['ends_on' => '2026-11-01'])->save();
        $task = $week->tasks->first();
        $this->at('2026-11-01 10:00');
        $task->forceFill(['completed_at' => now()->subHour()])->save();

        $this->prove($task, ['start_time' => '00:30', 'end_time' => '03:00'])->assertSessionHasNoErrors();
        $this->assertSame(210, (int) $task->fresh()->proof_starts_at->diffInMinutes($task->fresh()->proof_ends_at));
    }

    public function test_details_do_not_let_a_smashed_week_count_in_full_but_a_photo_does(): void
    {
        $week = $this->week();
        $week->tasks->each(fn (RoadmapTask $task) => $task->forceFill(['completed_at' => now()->subHour()])->saveQuietly());
        $week->forceFill(['milestone_hit_at' => now()->subHour()])->save();
        foreach ([3, 4] as $position) {
            $week->tasks()->create(['position' => $position, 'description' => "Bonus {$position}", 'is_bonus' => true])->forceFill(['completed_at' => now()->subHour()])->saveQuietly();
        }
        $task = $week->fresh('tasks')->tasks->first();

        $this->prove($task, ['caption' => 'Long day', 'window' => 'evening']);
        $this->assertSame(GoalWeekResult::Past, $week->fresh('tasks')->result());

        $this->prove($task, ['proof' => $this->photo()]);
        $this->assertSame(GoalWeekResult::Smashed, $week->fresh('tasks')->result());
    }

    public function test_details_are_seen_by_the_same_people_as_the_proof(): void
    {
        $task = $this->checkedTask();
        $this->prove($task, ['location' => 'Rock Creek Park', 'window' => 'morning', 'visibility' => 'proof']);
        $task->refresh();

        $this->assertTrue($task->detailsVisibleTo($this->avery));
        $this->assertTrue($task->detailsVisibleTo($this->sam), 'The opponent and league owner.');
        $stranger = User::factory()->create();
        $this->assertFalse($task->fresh()->detailsVisibleTo($stranger));
        $this->assertFalse($this->avery->highlights()->exists(), 'Proof only: nothing is posted.');

        // Posted to the league: the post carries the time and place to whoever can see the post.
        $this->prove($task, ['location' => 'Rock Creek Park', 'window' => 'morning', 'visibility' => 'league']);
        $post = $this->avery->highlights()->where('kind', HighlightKind::CheckIn)->sole();
        $this->assertSame('Rock Creek Park', $post->location);
        $this->actingAs($this->sam)->get(route('highlights.index'))->assertSee('Morning at Rock Creek Park');
    }

    public function test_answering_a_dispute_with_details_settles_it_like_an_explanation(): void
    {
        $task = $this->checkedTask();
        $this->actingAs($this->sam)->post(route('disputes.store', $task), ['reason' => 'Where was this?'])->assertRedirect();
        $this->assertSame(RoadmapTask::DISPUTE_OPEN, $task->fresh()->dispute_status);

        $this->prove($task, ['location' => 'Rock Creek Park', 'start_time' => '06:30', 'end_time' => '07:15']);

        $task->refresh();
        $this->assertNotSame(RoadmapTask::DISPUTE_OPEN, $task->dispute_status);
        $this->assertFalse($task->isVerified());
    }

    public function test_un_checking_clears_the_details(): void
    {
        $task = $this->checkedTask();
        $this->prove($task, ['caption' => 'Done', 'window' => 'morning']);

        $this->actingAs($this->avery)->post(route('roadmap.tasks.check', $task), ['intent' => 'open'])->assertRedirect();
        $this->assertFalse($task->fresh()->hasDetails());
        $this->assertNull($task->fresh()->proof_caption);
    }
}
