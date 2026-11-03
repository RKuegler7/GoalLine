<?php

namespace Tests\Feature;

use App\GoalWeekResult;
use App\Models\Dispute;
use App\Models\Goal;
use App\Models\League;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Notifications\DisputeOpened;
use App\Notifications\DisputeResolved;
use App\RoadmapStatus;
use App\Services\LeagueTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DisputeTest extends TestCase
{
    use RefreshDatabase;

    private League $league;

    private User $avery;

    private User $sam;

    private User $owner;

    private RoadmapWeek $week;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('uploads');
        $this->travelTo(Carbon::parse('2026-10-21 10:00', config('app.timezone'))); // Wednesday of week 1.

        // Three members: Avery and Sam face each other; the league owner plays the median.
        $this->owner = User::factory()->create(['name' => 'Olivia Owner']);
        $this->avery = User::factory()->create(['name' => 'Avery Lane']);
        $this->sam = User::factory()->create(['name' => 'Sam Park']);
        $this->league = League::factory()->create(['commissioner_id' => $this->owner->id, 'starts_on' => '2026-10-19', 'ends_on' => '2026-11-15']);

        foreach ([$this->owner, $this->avery, $this->sam] as $user) {
            $goal = Goal::factory()->for($user)->create(['target_date' => '2026-12-31']);
            $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
            $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Build', 'milestone' => 'Done']);
            foreach (range(1, 4) as $position) {
                $week->tasks()->create(['position' => $position, 'description' => "Session {$position}"])->forceFill(['completed_at' => '2026-10-20 09:00'])->save();
            }
            $this->league->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);

            if ($user->is($this->avery)) {
                $this->week = $week;
            }
        }

        // Make sure the pairing is Avery vs Sam, with the owner on the bye.
        $pairs = (new LeagueTable($this->league->fresh()))->pairings(1);
        $this->assertTrue(collect($pairs)->contains(fn (array $pair) => $pair[1] !== null && collect([$pair[0]->user_id, $pair[1]->user_id])->sort()->values()->all() === collect([$this->avery->id, $this->sam->id])->sort()->values()->all()));
    }

    public function test_the_opponent_or_league_owner_can_dispute_and_the_owner_is_told_kindly(): void
    {
        $task = $this->task(1);

        $this->actingAs($this->sam)->get(route('leagues.matchups', $this->league))->assertSee('Dispute');
        $this->actingAs($this->sam)->post(route('disputes.store', $task), ['reason' => 'Can you show the run?'])->assertSessionHas('status');

        $this->assertSame(RoadmapTask::DISPUTE_OPEN, $task->fresh()->dispute_status);
        Notification::assertSentTo($this->avery, DisputeOpened::class);
        $this->actingAs($this->sam)->get(route('leagues.matchups', $this->league))->assertSee('Disputed')->assertSee('Can you show the run?');

        // The league owner can dispute too; Avery can't dispute their own; outsiders can't at all.
        $this->actingAs($this->owner)->post(route('disputes.store', $this->task(2)))->assertSessionHas('status');
        $this->actingAs($this->avery)->post(route('disputes.store', $task))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('disputes.store', $task))->assertForbidden();
    }

    public function test_each_player_has_at_most_three_open_disputes_per_matchup_per_week(): void
    {
        foreach (range(1, 3) as $position) {
            $this->actingAs($this->sam)->post(route('disputes.store', $this->task($position)))->assertSessionHasNoErrors();
        }

        $this->actingAs($this->sam)->post(route('disputes.store', $this->task(4)))->assertSessionHasErrorsIn('dispute', 'reason');
        $this->actingAs($this->sam)->post(route('disputes.store', $this->task(1)))->assertSessionHasErrorsIn('dispute', 'reason');
        $this->assertSame(3, Dispute::count());
    }

    public function test_disputes_settle_by_explanation_withdrawal_proof_or_the_league_owners_ruling(): void
    {
        $explained = $this->dispute(1);
        $this->actingAs($this->avery)->post(route('disputes.explain', $explained), ['note' => 'Ran the river loop at 7am'])->assertSessionHas('status');
        $this->assertSame([Dispute::RESOLVED, 'note', 'Ran the river loop at 7am'], [$explained->fresh()->status, $explained->fresh()->resolution, $explained->fresh()->owner_note]);
        Notification::assertSentTo($this->sam, DisputeResolved::class);

        $withdrawn = $this->dispute(2);
        $this->actingAs($this->avery)->post(route('disputes.withdraw', $withdrawn))->assertForbidden();
        $this->actingAs($this->sam)->post(route('disputes.withdraw', $withdrawn));
        $this->assertSame(Dispute::WITHDRAWN, $withdrawn->fresh()->status);

        $proved = $this->dispute(3);
        $this->actingAs($this->avery)->post(route('roadmap.tasks.proof.store', $this->task(3)), ['proof' => $this->fakeImage(), 'visibility' => 'proof']);
        $this->assertSame('proof', $proved->fresh()->resolution);

        $ruled = $this->dispute(4);
        $this->actingAs($this->sam)->post(route('disputes.rule', $ruled), ['outcome' => 'out'])->assertForbidden();
        $this->actingAs($this->owner)->post(route('disputes.rule', $ruled), ['outcome' => 'out']);
        $this->assertSame(Dispute::UPHELD, $ruled->fresh()->status);
        $this->assertSame(RoadmapTask::DISPUTE_UPHELD, $this->task(4)->dispute_status);
        $this->assertSame(0.0, $this->task(4)->earnedPoints(), 'A check-in ruled out earns nothing.');

        foreach ([1, 2, 3] as $position) {
            $this->assertNull($this->task($position)->dispute_status);
        }
    }

    public function test_open_disputes_hold_points_at_week_close_and_the_week_rescores_when_settled(): void
    {
        $dispute = $this->dispute(1);
        $this->assertSame(1.0, $this->task(1)->earnedPoints(), 'Points stand while the week is still open.');

        $this->travelTo(Carbon::parse('2026-10-28 10:00', config('app.timezone'))); // Week 1 is final.
        $week = $this->week->fresh('tasks');
        $this->assertSame(1.0, $week->heldPoints());
        $this->assertSame(0.0, $week->tasks->firstWhere('position', 1)->earnedPoints());

        (new LeagueTable($this->league->fresh()))->settle();
        $stored = $this->league->results()->where('user_id', $this->avery->id)->where('week', 1)->sole();
        // 3 of 4 tasks (the milestone wasn't hit): 3 of 6 planned points is short of the line.
        $this->assertSame(GoalWeekResult::Short, $stored->result);

        $this->actingAs($this->avery)->get(route('leagues.matchups', [$this->league, 1]))->assertSee('1 point held while a dispute is settled');

        // Proof after the week closed still settles it, and the stored week re-scores on its own.
        $this->week->forceFill(['milestone_hit_at' => '2026-10-24 09:00'])->save();
        $this->actingAs($this->avery)->post(route('disputes.explain', $dispute), ['note' => 'Here is the route']);

        $this->assertSame(0.0, $this->week->fresh('tasks')->heldPoints());
        $this->assertSame(GoalWeekResult::InLine, $stored->fresh()->result, '6 of 6 once the held point is back.');
    }

    public function test_the_recap_mentions_disputes_kindly(): void
    {
        $this->dispute(1);

        $this->actingAs($this->avery)->get(route('recap.week'))->assertOk()
            ->assertSee('Fair play')->assertSee('League mates asked about 1 of your check-ins');
        $this->actingAs($this->sam)->get(route('recap.week'))->assertSee('You asked about 1 check-in. Thanks for keeping the league honest.');
    }

    private function task(int $position): RoadmapTask
    {
        return $this->week->tasks()->where('position', $position)->sole();
    }

    private function dispute(int $position): Dispute
    {
        $this->actingAs($this->sam)->post(route('disputes.store', $this->task($position)));

        return Dispute::latest('id')->first();
    }
}
