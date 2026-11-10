<?php

namespace Tests\Feature;

use App\GoalWeekResult;
use App\Models\Goal;
use App\Models\RoadmapWeek;
use App\RoadmapStatus;
use App\Services\BandProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BandProgressTest extends TestCase
{
    use RefreshDatabase;

    private Goal $goal;

    private RoadmapWeek $week;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 10:00', config('app.timezone'))); // Wednesday.
        $this->goal = Goal::factory()->create(['target_date' => '2026-12-31']);
        $this->goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $this->week = $this->goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Build', 'milestone' => 'Done']);

        // 20 one-point tasks and the 2-point milestone: 22 planned points. 19 done: 19 of 22 is 86%, Short.
        foreach (range(1, 20) as $position) {
            $task = $this->week->tasks()->create(['position' => $position, 'description' => "Session {$position}"]);

            if ($position <= 19) {
                $task->forceFill(['completed_at' => now()])->save();
            }
        }
    }

    public function test_band_lines_are_crossed_exactly_at_the_scoring_thresholds(): void
    {
        $band = fn (float $share) => GoalWeekResult::score($share, smashVerified: true);

        $this->assertNull(BandProgress::crossed($band(0.88), $band(0.89)), '89% is still Short.');
        $this->assertSame(GoalWeekResult::InLine, BandProgress::crossed($band(0.89), $band(0.90)));
        $this->assertNull(BandProgress::crossed($band(0.99), $band(1.00)), '100% is still In line.');
        $this->assertSame(GoalWeekResult::Past, BandProgress::crossed($band(1.00), $band(1.01)));
        $this->assertNull(BandProgress::crossed($band(1.48), $band(1.49)), '149% is still Past.');
        $this->assertSame(GoalWeekResult::Smashed, BandProgress::crossed($band(1.49), $band(1.50)));

        // Skipping a band celebrates the band reached; going down never does.
        $this->assertSame(GoalWeekResult::Past, BandProgress::crossed($band(0.80), $band(1.05)));
        $this->assertNull(BandProgress::crossed($band(1.05), $band(0.95)));
        $this->assertNull(BandProgress::crossed($band(0.95), $band(0.80)));
    }

    public function test_checking_in_returns_the_new_band_and_celebrates_the_step_up(): void
    {
        $this->actingAs($this->goal->user)->get(route('today'))
            ->assertSee('data-band="short" data-percent="86"', false);

        $last = $this->task(20);
        $this->actingAs($this->goal->user)->from(route('dashboard'))->post(route('roadmap.tasks.check', $last), ['intent' => 'done'])
            ->assertSessionHas('band_up', fn (array $up) => $up['band'] === 'in_line' && $up['percent'] === 90 && $up['needsProof'] === false);

        // The page that comes back carries the server's new values for the track to slide to, and the celebration.
        $this->actingAs($this->goal->user)->get(route('dashboard'))
            ->assertSee('data-band="in_line" data-percent="90"', false)
            ->assertSee('data-band-up hidden data-band="in_line"', false)
            ->assertSee('data-band-celebration', false);
    }

    public function test_un_checking_changes_quietly_and_a_band_is_celebrated_once_a_week(): void
    {
        $last = $this->task(20);
        $this->actingAs($this->goal->user)->post(route('roadmap.tasks.check', $last), ['intent' => 'done'])->assertSessionHas('band_up');

        // Going back down: the values update, nothing is celebrated, nothing negative is said.
        $this->actingAs($this->goal->user)->post(route('roadmap.tasks.check', $last), ['intent' => 'open'])
            ->assertSessionMissing('band_up')->assertSessionHas('status', 'Check-in undone.');
        $this->actingAs($this->goal->user)->get(route('today'))->assertSee('data-band="short" data-percent="86"', false);

        // Back up again in the same week: no second celebration for the same band.
        $this->actingAs($this->goal->user)->post(route('roadmap.tasks.check', $last), ['intent' => 'done'])->assertSessionMissing('band_up');

        // The next band still gets its own moment: the milestone (2 points) takes it to 22 of 22 (still In line),
        // and a bonus task goes past the plan.
        $this->actingAs($this->goal->user)->post(route('goalweeks.milestone', $this->week), ['intent' => 'done'])->assertSessionMissing('band_up');
        $bonus = $this->week->tasks()->create(['position' => 30, 'description' => 'Extra session', 'is_bonus' => true]);
        $this->actingAs($this->goal->user)->post(route('roadmap.tasks.check', $bonus), ['intent' => 'done'])
            ->assertSessionHas('band_up', fn (array $up) => $up['band'] === 'past');
    }

    public function test_a_smashed_week_without_proof_is_celebrated_with_a_nudge_to_add_proof(): void
    {
        $this->week->forceFill(['milestone_hit_at' => now()])->save();
        $this->task(20)->forceFill(['completed_at' => now()])->save();

        // 22 of 22, then 10 bonus points: 32 of 22 is 145%, still Past. One more makes 150%.
        foreach (range(1, 10) as $extra) {
            $this->week->tasks()->create(['position' => 40 + $extra, 'description' => 'Extra', 'is_bonus' => true])->forceFill(['completed_at' => now()])->save();
        }

        $bonus = $this->week->tasks()->create(['position' => 60, 'description' => 'Extra', 'is_bonus' => true]);
        $this->actingAs($this->goal->user)->post(route('roadmap.tasks.check', $bonus), ['intent' => 'done'])
            ->assertSessionHas('band_up', fn (array $up) => $up['band'] === 'smashed' && $up['needsProof'] === true
                && str_contains($up['goalUrl'], '#goalweek-1'));
    }

    public function test_habit_check_ins_can_move_the_band_too(): void
    {
        $habit = $this->goal->habits()->create(['title' => 'Stretch', 'times_per_week' => 1]);
        $habit->forceFill(['created_at' => '2026-10-18'])->saveQuietly();
        // Planned is now 23 (one habit day): 19 of 23 is 82%. The last task and the habit make 21 of 23: 91%.
        $this->task(20)->forceFill(['completed_at' => now()])->save();

        $this->actingAs($this->goal->user)->post(route('habits.log', $habit), ['intent' => 'done'])
            ->assertSessionHas('band_up', fn (array $up) => $up['band'] === 'in_line');
    }

    private function task(int $position)
    {
        return $this->week->tasks()->where('position', $position)->sole();
    }
}
