<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Habit;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\DailyStreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TodayAndHabitRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Goal $goal;

    private RoadmapWeek $week;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-04 10:00', config('app.timezone'))); // A Wednesday.
        $this->user = User::factory()->create();
        $this->goal = Goal::factory()->for($this->user)->create(['title' => 'Run a half marathon', 'target_date' => '2026-12-31']);
        $this->goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'created_at' => '2026-10-20'])->save();
        $this->week = $this->goal->weeks()->create(['position' => 1, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-08', 'focus' => 'Base', 'milestone' => 'Long run']);
        $this->week->tasks()->create(['position' => 1, 'description' => 'Foam roll', 'planned_on' => '2026-11-04']);
        $tempo = $this->week->tasks()->create(['position' => 2, 'description' => 'Tempo run', 'planned_on' => '2026-11-04']);
        $tempo->trackNumber(4, 'miles');
        $tempo->save();
    }

    public function test_a_number_habit_counts_only_at_or_above_its_threshold(): void
    {
        $run = $this->habit(['title' => 'Run', 'cadence' => Habit::DAYS, 'days' => [1, 3, 5], 'times_per_week' => 3, 'rule' => Habit::NUMBER, 'threshold' => 2, 'unit' => 'miles']);
        $this->assertSame('Run · 2+ miles', $run->label());
        $this->assertSame('Mon, Wed, Fri', $run->cadence());

        $this->log($run, ['value' => 1.5])->assertSessionHas('status', "Logged 1.5 miles. Your rule is 2+ miles, so it doesn't count yet. Update it if you do more today.");
        $this->assertSame(0, $run->fresh('logs')->doneIn($this->week));
        $this->assertTrue($run->fresh('logs')->isPartialOn(today()));

        $this->log($run, ['value' => 2])->assertSessionHas('status', 'Logged 2 miles. It counts: one more day on the line.');
        $this->assertSame(1, $run->fresh('logs')->doneIn($this->week));
        $this->assertSame(1, $run->logs()->count(), 'Updating the number keeps one log for the day.');

        $this->log($run, ['value' => 6.2]);
        $this->assertSame(1, $run->fresh('logs')->doneIn($this->week), 'Going over still counts once: a habit day is one point.');

        $this->log($run, ['value' => -1])->assertSessionHasErrors('value');
    }

    public function test_a_number_habit_needs_its_number_and_an_empty_one_clears_the_day(): void
    {
        $study = $this->habit(['title' => 'Study', 'rule' => Habit::MINUTES, 'threshold' => 45, 'cadence' => Habit::DAILY]);

        $this->log($study)->assertSessionHas('status', 'Log a number for "Study": it counts at 45+ minutes.');
        $this->assertSame(0, $study->logs()->count());

        $this->log($study, ['value' => 50]);
        $this->log($study, ['value' => '']);
        $this->assertSame(0, $study->logs()->count());
    }

    public function test_changing_the_rule_never_rewrites_past_days(): void
    {
        $run = $this->habit(['title' => 'Run', 'rule' => Habit::NUMBER, 'threshold' => 2, 'unit' => 'miles', 'times_per_week' => 4]);
        $this->log($run, ['value' => 1.5]);

        $this->actingAs($this->user)->put(route('habits.update', $run), ['title' => 'Run', 'cadence' => 'weekly', 'times_per_week' => 4, 'rule' => 'number', 'threshold' => 1, 'unit' => 'miles']);

        $this->assertSame(0, $run->fresh('logs')->doneIn($this->week));
        $this->assertSame('Run · 1+ miles', $run->fresh()->label());
    }

    public function test_existing_check_off_habits_and_logs_score_exactly_as_before(): void
    {
        $legacy = Habit::factory()->for($this->goal)->create(['title' => 'Stretch', 'times_per_week' => 3]);
        $legacy->forceFill(['created_at' => '2026-10-20'])->save();
        $legacy->logs()->create(['logged_on' => '2026-11-02']);
        $legacy->logs()->create(['logged_on' => '2026-11-03']);

        $legacy = $legacy->fresh('logs');
        $this->assertSame(Habit::CHECK, $legacy->rule);
        $this->assertSame(2, $legacy->doneIn($this->week));
        $this->assertSame(3, $legacy->targetFor($this->week));
        $this->assertSame('3× a week', $legacy->cadence());
        $this->assertSame('Stretch', $legacy->label());
    }

    public function test_habits_are_set_up_with_a_cadence_and_rule(): void
    {
        $this->actingAs($this->user)->post(route('habits.store', $this->goal), ['title' => 'Run', 'cadence' => 'days', 'rule' => 'number', 'threshold' => 2])
            ->assertSessionHasErrors(['days', 'unit']);
        $this->actingAs($this->user)->post(route('habits.store', $this->goal), ['title' => 'Read', 'cadence' => 'weekly', 'times_per_week' => 4, 'rule' => 'minutes'])
            ->assertSessionHasErrors('threshold');

        $this->actingAs($this->user)->post(route('habits.store', $this->goal), ['title' => 'Run', 'cadence' => 'days', 'days' => [5, 1, 3], 'rule' => 'number', 'threshold' => 2, 'unit' => 'miles']);
        $run = $this->goal->habits()->where('title', 'Run')->sole();
        $this->assertSame([Habit::DAYS, 3, [1, 3, 5], Habit::NUMBER, 2.0, 'miles'], [$run->cadence, $run->times_per_week, $run->days, $run->rule, $run->threshold, $run->unit]);

        $this->actingAs($this->user)->post(route('habits.store', $this->goal), ['title' => 'Stretch', 'cadence' => 'daily', 'rule' => 'check', 'days' => [2]]);
        $stretch = $this->goal->habits()->where('title', 'Stretch')->sole();
        $this->assertSame([Habit::DAILY, 7, null, null], [$stretch->cadence, $stretch->times_per_week, $stretch->days, $stretch->threshold]);

        $this->actingAs($this->user)->get(route('goals.show', $this->goal))->assertSee('Run · 2+ miles')->assertSee('Mon, Wed, Fri')->assertSee('What counts');
    }

    public function test_weekly_target_habits_show_progress_instead_of_misses(): void
    {
        $gym = $this->habit(['title' => 'Gym', 'times_per_week' => 4, 'cadence' => Habit::WEEKLY]);
        $gym->logs()->create(['logged_on' => '2026-11-02']);
        $gym->logs()->create(['logged_on' => '2026-11-03']);
        $gym->logs()->create(['logged_on' => '2026-11-04']);

        $this->actingAs($this->user)->get(route('today'))->assertOk()
            ->assertSeeInOrder(['This week&rsquo;s habits', 'Gym', '3 of 4', 'this week'], false);

        $gym->logs()->create(['logged_on' => '2026-11-01']); // Outside this GoalWeek: doesn't count.
        $this->assertSame(3, $gym->fresh('logs')->doneIn($this->week));
    }

    public function test_no_check_ins_beyond_today_or_yesterday(): void
    {
        $run = $this->habit(['title' => 'Run', 'rule' => Habit::NUMBER, 'threshold' => 2, 'unit' => 'miles', 'cadence' => Habit::DAILY]);

        $this->log($run, ['value' => 3, 'date' => '2026-11-03']);
        $this->assertTrue($run->fresh('logs')->isDoneOn(Carbon::parse('2026-11-03')));

        $this->log($run, ['value' => 3, 'date' => '2026-11-02'])->assertSessionHas('status', fn ($status) => str_contains($status, 'today or yesterday'));
        $this->log($run, ['value' => 3, 'date' => '2026-11-05'])->assertForbidden();
        $this->assertSame(1, $run->logs()->count());
    }

    public function test_the_today_view_lists_what_is_due_and_the_grid_shows_each_day(): void
    {
        $this->habit(['title' => 'Run', 'cadence' => Habit::DAYS, 'days' => [1, 3, 5], 'times_per_week' => 3, 'rule' => Habit::NUMBER, 'threshold' => 2, 'unit' => 'miles']);
        $this->habit(['title' => 'Swim', 'cadence' => Habit::DAYS, 'days' => [2, 4], 'times_per_week' => 2]);
        $stretch = $this->habit(['title' => 'Stretch', 'cadence' => Habit::DAILY]);
        $stretch->logs()->create(['logged_on' => '2026-11-03']);

        $response = $this->actingAs($this->user)->get(route('today'))->assertOk()
            ->assertSee('Today')
            ->assertSee('1 day on the line this week')
            ->assertSee('Foam roll')->assertSee('Tempo run')
            ->assertSee('Run · 2+ miles')->assertSee('Stretch');

        // Swim is Tuesday and Thursday: not due on a Wednesday, and its Wednesday is a rest day in the grid.
        $gameplan = $response->viewData('gameplan');
        $due = $gameplan->entries->first()['habits']->pluck('title')->all();
        $this->assertEqualsCanonicalizing(['Run', 'Stretch'], $due);
        $response->assertSee('Rest');
        $response->assertSee('Mark all 2 done');

        $this->actingAs($this->user)->get(route('dashboard'))->assertSee('Week grid')->assertSee(route('today'));
    }

    public function test_mark_all_done_checks_off_simple_items_and_leaves_numbers(): void
    {
        $run = $this->habit(['title' => 'Run', 'rule' => Habit::NUMBER, 'threshold' => 2, 'unit' => 'miles', 'cadence' => Habit::DAILY]);
        $stretch = $this->habit(['title' => 'Stretch', 'cadence' => Habit::DAILY]);

        $this->actingAs($this->user)->post(route('today.done'))
            ->assertRedirect(route('today'))->assertSessionHas('status', 'Marked 2 things done. One more day on the line.');

        $this->assertNotNull($this->week->tasks()->where('description', 'Foam roll')->sole()->completed_at);
        $this->assertNull($this->week->tasks()->where('description', 'Tempo run')->sole()->completed_at);
        $this->assertTrue($stretch->fresh('logs')->isDoneOn(today()));
        $this->assertSame(0, $run->logs()->count());

        $this->actingAs($this->user)->post(route('today.done'))->assertSessionHas('status', 'Everything simple is already done. Anything with a number needs its number.');
        $this->assertSame(1, (new DailyStreak($this->user))->daysOnTheLineThisWeek());
    }

    public function test_a_number_under_the_rule_doesnt_keep_the_streak(): void
    {
        $run = $this->habit(['title' => 'Run', 'rule' => Habit::NUMBER, 'threshold' => 2, 'unit' => 'miles', 'cadence' => Habit::DAILY]);
        $this->log($run, ['value' => 1]);

        $this->assertFalse((new DailyStreak($this->user))->checkedInToday());

        $this->log($run, ['value' => 2]);
        $this->assertTrue((new DailyStreak($this->user))->checkedInToday());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function habit(array $attributes): Habit
    {
        $habit = $this->goal->habits()->create(['times_per_week' => 7, ...$attributes]);
        $habit->forceFill(['created_at' => '2026-10-20'])->save();

        return $habit;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function log(Habit $habit, array $data = []): TestResponse
    {
        return $this->actingAs($this->user)->post(route('habits.log', $habit), $data);
    }
}
