<?php

namespace Tests\Feature;

use App\GoalWeekResult;
use App\Models\Goal;
use App\Models\League;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Notifications\ProofRequested;
use App\RoadmapStatus;
use App\Services\ProofImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FairPlayTest extends TestCase
{
    use RefreshDatabase;

    private User $avery;

    private User $sam;

    private League $league;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('uploads');

        // A two-person GoalLeague whose season started Monday, Oct 19; today is Wednesday, Oct 21.
        $this->travelTo(Carbon::parse('2026-10-01 09:00', config('app.timezone')));
        $this->avery = User::factory()->create(['name' => 'Avery Lee']);
        $this->sam = User::factory()->create(['name' => 'Sam Okafor']);
        $this->league = League::factory()->create(['commissioner_id' => $this->sam->id, 'starts_on' => '2026-10-19', 'ends_on' => '2026-11-15']);
        $this->league->members()->create(['user_id' => $this->avery->id, 'goal_id' => $this->goalFor($this->avery)->id]);
        $this->league->members()->create(['user_id' => $this->sam->id, 'goal_id' => $this->goalFor($this->sam)->id]);
        $this->travelTo(Carbon::parse('2026-10-21 09:00', config('app.timezone')));
    }

    public function test_a_smashed_week_needs_proof_or_the_opponents_ok_to_count_in_full(): void
    {
        $week = $this->smashedWeekFor($this->avery);

        $this->assertTrue($week->needsProofForSmashed());
        $this->assertSame(GoalWeekResult::Past, $week->result());
        $this->actingAs($this->avery)->get(route('goals.show', $week->goal))->assertSee('Needs proof to count as Smashed');

        // Proof on any check-in that week makes it count.
        $week->tasks->first()->forceFill(['proof_path' => 'proofs/x.png'])->saveQuietly();
        $this->assertSame(GoalWeekResult::Smashed, $week->fresh('tasks')->result());
    }

    public function test_the_matchup_opponent_can_confirm_a_smashed_week_but_nobody_else_can(): void
    {
        $week = $this->smashedWeekFor($this->avery);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post(route('goalweeks.confirm-smashed', $week))->assertForbidden();
        $this->actingAs($this->avery)->post(route('goalweeks.confirm-smashed', $week))->assertForbidden();

        $this->actingAs($this->sam)->get(route('leagues.show', $this->league))->assertSee('Confirm their Smashed week');
        $this->actingAs($this->sam)->post(route('goalweeks.confirm-smashed', $week))->assertRedirect();

        $week = $week->fresh('tasks');
        $this->assertSame($this->sam->id, $week->smashed_confirmed_by);
        $this->assertSame(GoalWeekResult::Smashed, $week->result());
        $this->assertFalse($week->needsProofForSmashed());
    }

    public function test_numeric_results_must_be_in_range(): void
    {
        $task = $this->numericTask($this->avery, target: 5, unit: 'miles');

        $this->actingAs($this->avery)
            ->post(route('roadmap.tasks.check', $task), ['result' => 16, 'task_id' => $task->id])
            ->assertSessionHasErrors(['result' => "That's outside this task's range (0 miles to 15 miles). Check the number and try again."]);
        $this->actingAs($this->avery)->post(route('roadmap.tasks.check', $task), ['result' => -1])->assertSessionHasErrors('result');
        $this->actingAs($this->avery)->post(route('roadmap.tasks.check', $task), [])->assertSessionHasErrors('result');

        $this->assertNull($task->fresh()->completed_at);

        $this->actingAs($this->avery)->post(route('roadmap.tasks.check', $task), ['result' => 6])->assertSessionHasNoErrors();
        $task->refresh();
        $this->assertNotNull($task->completed_at);
        $this->assertSame(6.0, $task->result_value);
        $this->assertEqualsWithDelta(1.2, $task->earnedPoints(), 0.001);
    }

    public function test_an_unusually_high_number_asks_are_you_sure_once(): void
    {
        $task = $this->numericTask($this->avery, target: 5, unit: 'miles');

        // Normal entries never ask.
        $normal = $this->numericTask($this->avery, target: 5, unit: 'pages');
        $this->actingAs($this->avery)->post(route('roadmap.tasks.check', $normal), ['result' => 7])->assertSessionMissing('confirm_result');
        $this->assertNotNull($normal->fresh()->completed_at);

        // More than 1.5 times the plan: asks first.
        $this->actingAs($this->avery)->post(route('roadmap.tasks.check', $task), ['result' => 8])
            ->assertSessionHas('confirm_result', fn (array $confirm) => $confirm['task'] === $task->id && str_contains($confirm['reason'], 'more than 1.5 times your plan'));
        $this->assertNull($task->fresh()->completed_at);

        $this->actingAs($this->avery)->post(route('roadmap.tasks.check', $task), ['result' => 8, 'confirmed' => 1]);
        $this->assertSame(8.0, $task->fresh()->result_value);
    }

    public function test_a_new_personal_best_asks_are_you_sure(): void
    {
        $first = $this->numericTask($this->avery, target: 10, unit: 'pushups');
        $this->actingAs($this->avery)->post(route('roadmap.tasks.check', $first), ['result' => 10]);

        $second = $this->numericTask($this->avery, target: 10, unit: 'pushups');
        $this->actingAs($this->avery)->post(route('roadmap.tasks.check', $second), ['result' => 12])
            ->assertSessionHas('confirm_result', fn (array $confirm) => str_contains($confirm['reason'], 'new personal best'));
    }

    public function test_numbers_can_only_be_set_before_the_goalweek_starts(): void
    {
        $week = $this->weekFor($this->avery);
        $task = $week->tasks()->create(['position' => 9, 'description' => 'Run']);

        $this->actingAs($this->avery)->put(route('roadmap.tasks.update', $task), ['description' => 'Run', 'track_target' => 1, 'track_unit' => 'mile']);
        $this->assertFalse($task->fresh()->result_required); // This GoalWeek is underway.

        $next = $week->goal->weeks()->create(['position' => 2, 'starts_on' => '2026-10-26', 'ends_on' => '2026-11-01', 'focus' => 'Next', 'milestone' => 'Done.']);
        $upcoming = $next->tasks()->create(['position' => 1, 'description' => 'Run']);
        $this->actingAs($this->avery)->put(route('roadmap.tasks.update', $upcoming), ['description' => 'Run', 'track_target' => 5, 'track_unit' => 'miles']);

        $upcoming->refresh();
        $this->assertTrue($upcoming->result_required);
        $this->assertSame([5.0, 'miles', 0.0, 15.0], [$upcoming->result_target, $upcoming->result_unit, $upcoming->result_min, $upcoming->result_max]);
    }

    public function test_check_ins_are_for_today_or_yesterday_only(): void
    {
        $habit = $this->weekFor($this->avery)->goal->habits()->create(['title' => 'Run', 'times_per_week' => 7]);

        $this->actingAs($this->avery)->post(route('habits.log', $habit), ['date' => '2026-10-21']);
        $this->actingAs($this->avery)->post(route('habits.log', $habit), ['date' => '2026-10-20']);
        $this->actingAs($this->avery)->post(route('habits.log', $habit), ['date' => '2026-10-19'])
            ->assertSessionHas('status', 'Check-ins are for today or yesterday. Earlier days stay as they are: one more day on the line starts now.');

        $this->assertSame(['2026-10-20', '2026-10-21'], $habit->logs()->orderBy('logged_on')->get()->map(fn ($log) => $log->logged_on->toDateString())->all());
    }

    public function test_proof_uploads_strip_location_data_and_mark_the_check_in_verified(): void
    {
        $task = $this->weekFor($this->avery)->tasks->first();
        $task->forceFill(['completed_at' => now()])->save();

        $this->actingAs($this->avery)
            ->post(route('roadmap.tasks.proof.store', $task), ['proof' => $this->jpegWithGps()])
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertTrue($task->isVerified());
        $stored = Storage::disk('uploads')->get($task->proof_path);
        $this->assertStringStartsWith("\xFF\xD8", $stored);
        $this->assertStringNotContainsString('Exif', $stored);
        $this->assertStringNotContainsString('GPS-38.9072N', $stored);
    }

    public function test_proof_uploads_must_be_images_up_to_5_mb(): void
    {
        $task = $this->weekFor($this->avery)->tasks->first();
        $task->forceFill(['completed_at' => now()])->save();

        $this->actingAs($this->avery)
            ->post(route('roadmap.tasks.proof.store', $task), ['proof' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('proof');
        $this->actingAs($this->avery)
            ->post(route('roadmap.tasks.proof.store', $task), ['proof' => UploadedFile::fake()->createWithContent('big.png', str_repeat('x', 10))->size(6000)])
            ->assertSessionHasErrors('proof');

        $this->assertFalse($task->fresh()->isVerified());
    }

    public function test_proof_is_visible_to_the_member_their_opponent_and_the_league_owner_only(): void
    {
        $task = $this->weekFor($this->avery)->tasks->first();
        $task->forceFill(['completed_at' => now()])->save();
        $this->actingAs($this->avery)->post(route('roadmap.tasks.proof.store', $task), ['proof' => $this->fakeImage('run.png')]);

        $this->actingAs($this->avery)->get(route('roadmap.tasks.proof', $task))->assertOk();
        $this->actingAs($this->sam)->get(route('roadmap.tasks.proof', $task))->assertOk(); // Opponent and league owner.
        $this->actingAs(User::factory()->create())->get(route('roadmap.tasks.proof', $task))->assertNotFound();
    }

    public function test_league_mates_can_ask_to_see_the_work_once_without_changing_the_score(): void
    {
        Notification::fake();
        $week = $this->weekFor($this->avery);
        $task = $week->tasks->first();
        $task->forceFill(['completed_at' => now()])->save();
        $before = $week->fresh('tasks')->result();

        $this->actingAs($this->sam)->get(route('leagues.show', $this->league))->assertSee('Show your work?');
        $this->actingAs($this->sam)->post(route('roadmap.tasks.proof.request', $task))->assertSessionHas('status');
        $this->actingAs($this->sam)->post(route('roadmap.tasks.proof.request', $task));

        Notification::assertSentToTimes($this->avery, ProofRequested::class, 1);
        $this->assertSame($before, $week->fresh('tasks')->result());

        $this->actingAs(User::factory()->create())->post(route('roadmap.tasks.proof.request', $task))->assertForbidden();
        $this->actingAs($this->avery)->post(route('roadmap.tasks.proof.request', $task))->assertForbidden();
    }

    public function test_the_matchup_shows_both_sides_check_ins(): void
    {
        $task = $this->weekFor($this->avery)->tasks->first();
        $task->forceFill(['completed_at' => now()])->save();

        $this->actingAs($this->sam)
            ->get(route('leagues.show', $this->league))
            ->assertSee("This week's check-ins (1)", false)
            ->assertSee('Build the prototype');
    }

    public function test_checking_off_a_task_offers_show_your_work(): void
    {
        $task = $this->weekFor($this->avery)->tasks->first();

        $this->actingAs($this->avery)->post(route('roadmap.tasks.check', $task))->assertSessionHas('proof_prompt', $task->id);
        $this->actingAs($this->avery)
            ->get(route('goals.show', $task->week->goal))
            ->assertSee('Show your work (optional)')
            ->assertSee('It earns a Verified check, shows your league the process behind your goal');
    }

    public function test_the_jpeg_fallback_keeps_the_image_and_drops_metadata(): void
    {
        $clean = (new ProofImage)->stripJpeg($this->jpegBytesWithGps());

        $this->assertStringStartsWith("\xFF\xD8\xFF\xE0", $clean); // Starts with the JFIF block, EXIF gone.
        $this->assertStringNotContainsString('GPS-38.9072N', $clean);
        $this->assertStringEndsWith("\xFF\xD9", $clean);
    }

    private function goalFor(User $user): Goal
    {
        $goal = Goal::factory()->for($user)->create(['target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Prototype', 'milestone' => 'Show three customers.']);
        $week->tasks()->create(['position' => 1, 'description' => 'Build the prototype']);
        $week->tasks()->create(['position' => 2, 'description' => 'Book calls']);

        return $goal;
    }

    private function weekFor(User $user): RoadmapWeek
    {
        return $user->goals()->sole()->weeks()->where('position', 1)->sole()->load('tasks');
    }

    /**
     * Both planned tasks and the milestone (4 of 4) plus two 1-point bonus tasks: 6 of 4 is 150%.
     */
    private function smashedWeekFor(User $user): RoadmapWeek
    {
        $week = $this->weekFor($user);
        $week->tasks->each(fn (RoadmapTask $task) => $task->forceFill(['completed_at' => now()])->saveQuietly());
        $week->forceFill(['milestone_hit_at' => now()])->save();
        foreach ([3, 4] as $position) {
            $week->tasks()->create(['position' => $position, 'description' => "Bonus {$position}", 'is_bonus' => true])->forceFill(['completed_at' => now()])->saveQuietly();
        }

        return $week->fresh('tasks');
    }

    private function numericTask(User $user, float $target, string $unit): RoadmapTask
    {
        $task = $this->weekFor($user)->tasks()->create(['position' => 10 + RoadmapTask::count(), 'description' => "Track {$unit}"]);
        $task->trackNumber($target, $unit);
        $task->save();

        return $task;
    }

    private function jpegWithGps(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('run.jpg', $this->jpegBytesWithGps());
    }

    /**
     * A tiny JPEG-shaped file with a JFIF block, an EXIF block carrying a fake GPS tag, and image data.
     */
    private function jpegBytesWithGps(): string
    {
        $jfif = "JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";
        $exif = "Exif\x00\x00GPS-38.9072N-77.0369W";

        return "\xFF\xD8"
            ."\xFF\xE0".pack('n', strlen($jfif) + 2).$jfif
            ."\xFF\xE1".pack('n', strlen($exif) + 2).$exif
            ."\xFF\xDA".pack('n', 4)."\x00\x00"
            ."\x12\x34\x56"
            ."\xFF\xD9";
    }
}
