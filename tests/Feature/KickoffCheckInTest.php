<?php

namespace Tests\Feature;

use App\HighlightKind;
use App\Http\Controllers\LeagueController;
use App\Models\Goal;
use App\Models\League;
use App\Models\RoadmapTask;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\PostVisibility;
use App\RoadmapStatus;
use App\Services\EarlyCheckIns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * No check-ins before kickoff: every check-in path asks the same server-side rule (RoadmapWeek::checkInBlocker and
 * Goal::checkInSeason), so the UI can't be bypassed.
 */
class KickoffCheckInTest extends TestCase
{
    use RefreshDatabase;

    private const ZONE = 'America/New_York';

    private function at(string $moment): void
    {
        $this->travelTo(Carbon::parse($moment, self::ZONE));
    }

    /**
     * A league kicking off on $kickoff with two members, each with a goal whose first GoalWeek began $weekStart (the
     * day they made their plan) and a second week after it.
     *
     * @return array{0: League, 1: User, 2: User, 3: RoadmapWeek, 4: RoadmapWeek}
     */
    private function league(string $kickoff = '2026-11-16', string $weekStart = '2026-11-11', string $endsOn = '2026-12-20'): array
    {
        $league = League::factory()->create(['starts_on' => $kickoff, 'ends_on' => $endsOn]);
        $people = [];
        $weeks = [];

        foreach (['Alex', 'Blair'] as $name) {
            $user = User::factory()->create(['name' => $name]);
            $goal = Goal::factory()->for($user)->create(['target_date' => $endsOn]);
            $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
            $league->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);
            $first = $goal->weeks()->create(['position' => 1, 'starts_on' => $weekStart, 'ends_on' => '2026-11-22', 'focus' => 'F', 'milestone' => 'M']);
            $second = $goal->weeks()->create(['position' => 2, 'starts_on' => '2026-11-23', 'ends_on' => '2026-11-29', 'focus' => 'F', 'milestone' => 'M']);

            foreach ([$first, $second] as $week) {
                $week->tasks()->create(['position' => 1, 'description' => 'Run']);
            }

            $people[] = $user;
            $weeks[] = [$first, $second];
        }

        return [$league, $people[0], $people[1], $weeks[0][0], $weeks[0][1]];
    }

    private function check(RoadmapTask $task, string $intent = 'done'): TestResponse
    {
        return $this->post(route('roadmap.tasks.check', $task), ['intent' => $intent]);
    }

    public function test_no_check_ins_before_kickoff_on_any_path(): void
    {
        $this->at('2026-11-11 10:00');
        [$league, $alex, $blair, $week] = $this->league();
        $task = $week->tasks->first();

        $this->actingAs($alex);
        $this->check($task)->assertForbidden();
        $this->postJson(route('roadmap.tasks.check', $task), ['intent' => 'done'])->assertForbidden();
        $this->post(route('goalweeks.milestone', $week))->assertForbidden();
        $this->assertNull($task->fresh()->completed_at);
        $this->assertNull($week->fresh()->milestone_hit_at);

        // A check-in that slipped in before the fix can't be undone, proven, cheered, asked about or disputed either.
        $task->forceFill(['completed_at' => now()])->save();
        $this->check($task, 'open')->assertForbidden();
        $this->post(route('roadmap.tasks.proof.store', $task), ['proof' => UploadedFile::fake()->createWithContent('p.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='))])->assertForbidden();

        $post = $alex->highlights()->create(['kind' => HighlightKind::CheckIn, 'goal_id' => $week->goal_id, 'roadmap_week_id' => $week->id, 'roadmap_task_id' => $task->id, 'league_id' => $league->id, 'visibility' => PostVisibility::League, 'headline' => 'Run']);
        $this->actingAs($blair);
        $this->post(route('highlights.cheer', $post), ['intent' => 'add'])->assertForbidden();
        $this->post(route('roadmap.tasks.proof.request', $task))->assertForbidden();
        $this->post(route('disputes.store', $task), ['reason' => 'Hm'])->assertForbidden();
        $this->assertSame(0, $post->cheers()->count());
    }

    public function test_a_numeric_result_cant_be_entered_before_kickoff(): void
    {
        $this->at('2026-11-12 10:00');
        [, $alex, , $week] = $this->league();
        $task = $week->tasks->first();
        $task->trackNumber(5, 'miles');
        $task->save();

        $this->actingAs($alex)->post(route('roadmap.tasks.check', $task), ['intent' => 'done', 'result' => 5])->assertForbidden();
        $this->assertNull($task->fresh()->result_value);
    }

    public function test_the_plan_can_still_be_seen_and_edited_before_kickoff(): void
    {
        Queue::fake();
        $this->at('2026-11-11 10:00');
        [, $alex, , $week] = $this->league();
        $task = $week->tasks->first();

        $this->actingAs($alex)->get(route('goals.show', $week->goal))->assertOk()
            ->assertSee('Your season kicks off Monday, Nov 16')->assertSee('Opens Mon, Nov 16')->assertSee('Refine your plan');
        $this->put(route('roadmap.tasks.update', $task), ['description' => 'Easy run, 3 miles'])->assertRedirect();
        $this->assertSame('Easy run, 3 miles', $task->fresh()->description);

        $this->get(route('dashboard'))->assertSee('Your season kicks off Monday, Nov 16')->assertSee('data-countdown', false);
        $this->get(route('today'))->assertSee('Your season kicks off Monday, Nov 16');
    }

    public function test_check_ins_open_at_12_am_eastern_on_kickoff_monday(): void
    {
        $this->at('2026-11-15 23:59');
        [, $alex] = $this->league(weekStart: '2026-11-16');
        $task = RoadmapWeek::whereDate('starts_on', '2026-11-16')->whereHas('goal', fn ($goals) => $goals->where('user_id', $alex->id))->sole()->tasks->first();

        // Sunday 11:59 p.m.: the week hasn't started.
        $this->actingAs($alex);
        $this->check($task)->assertForbidden();

        // Monday 12:00 a.m.: kickoff.
        $this->at('2026-11-16 00:00');
        $this->check($task)->assertRedirect();
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertTrue($task->fresh()->countsAsCheckIn());
    }

    public function test_yesterday_on_kickoff_day_is_before_the_season(): void
    {
        // The goal's first stretch ran Wed Nov 11 to Sun Nov 15. On Monday (its grace day) a check-in would count for
        // Sunday, which is before kickoff.
        $this->at('2026-11-11 09:00');
        $league = League::factory()->create(['starts_on' => '2026-11-16', 'ends_on' => '2026-12-20']);
        $alex = User::factory()->create();
        $goal = Goal::factory()->for($alex)->create(['target_date' => '2026-12-20']);
        $league->members()->create(['user_id' => $alex->id, 'goal_id' => $goal->id]);
        $stub = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-11-11', 'ends_on' => '2026-11-15', 'focus' => 'F', 'milestone' => 'M']);
        $task = $stub->tasks()->create(['position' => 1, 'description' => 'Run']);

        $this->at('2026-11-16 08:00');
        $this->actingAs($alex);
        $this->check($task)->assertForbidden();
    }

    public function test_later_days_and_next_weeks_tasks_stay_upcoming(): void
    {
        $this->at('2026-11-17 09:00');
        [, $alex, , $week, $next] = $this->league(weekStart: '2026-11-16');
        $tomorrow = $week->tasks()->create(['position' => 2, 'description' => 'Long run', 'planned_on' => '2026-11-18']);
        $today = $week->tasks()->create(['position' => 3, 'description' => 'Stretch', 'planned_on' => '2026-11-17']);

        $this->actingAs($alex);
        $this->check($tomorrow)->assertForbidden();
        $this->check($next->tasks->first())->assertForbidden();
        $this->check($today)->assertRedirect();
        $this->assertNotNull($today->fresh()->completed_at);

        $this->get(route('goals.show', $week->goal))->assertSee('Opens Wed, Nov 18');
    }

    public function test_no_check_ins_after_the_season_ends(): void
    {
        $this->at('2026-11-16 09:00');
        $alex = User::factory()->create();
        $goal = Goal::factory()->for($alex)->create(['target_date' => '2026-11-18']);
        $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-11-16', 'ends_on' => '2026-11-22', 'focus' => 'F', 'milestone' => 'M']);
        $task = $week->tasks()->create(['position' => 1, 'description' => 'Run']);

        $this->at('2026-11-20 09:00');
        $this->actingAs($alex);
        $this->check($task)->assertForbidden();
    }

    public function test_a_late_joiner_starts_on_the_monday_of_the_week_they_joined(): void
    {
        $this->at('2026-11-04 09:00');
        $league = League::factory()->create(['starts_on' => '2026-11-02', 'ends_on' => '2026-12-20']);
        $late = User::factory()->create();
        $goal = Goal::factory()->for($late)->create(['target_date' => '2026-12-20']);
        $league->members()->create(['user_id' => $late->id, 'goal_id' => $goal->id])->forceFill(['joined_week' => 3])->save();

        $this->assertSame('2026-11-16', $goal->fresh()->checkInSeason()['starts']->toDateString());
        $this->assertTrue($goal->fresh()->isBeforeKickoff());
    }

    public function test_a_solo_goal_opens_on_its_own_start_date(): void
    {
        $this->at('2026-11-11 09:00');
        $solo = User::factory()->create();
        $goal = Goal::factory()->for($solo)->create(['target_date' => '2026-12-20']);
        $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-11-11', 'ends_on' => '2026-11-22', 'focus' => 'F', 'milestone' => 'M']);
        $task = $week->tasks()->create(['position' => 1, 'description' => 'Run']);

        $this->actingAs($solo);
        $this->check($task)->assertRedirect();
        $this->assertTrue($task->fresh()->countsAsCheckIn());
    }

    public function test_habits_cant_be_logged_before_kickoff(): void
    {
        $this->at('2026-11-12 09:00');
        [, $alex, , $week] = $this->league();
        $habit = $week->goal->habits()->create(['title' => 'Stretch', 'times_per_week' => 3]);

        $this->actingAs($alex)->post(route('habits.log', $habit))->assertForbidden();
        $this->assertSame(0, $habit->logs()->count());

        // Kickoff Monday: today counts, but yesterday (Sunday) is still before the season.
        $this->at('2026-11-16 09:00');
        $this->post(route('habits.log', $habit), ['date' => '2026-11-15'])->assertForbidden();
        $this->post(route('habits.log', $habit))->assertRedirect();
        $this->assertSame(1, $habit->fresh()->logs()->count());
    }

    public function test_early_check_ins_never_count_even_if_a_void_was_missed(): void
    {
        $this->at('2026-11-12 09:00');
        [, , , $week] = $this->league();
        $week->tasks->first()->forceFill(['completed_at' => now()])->save();
        $week->forceFill(['milestone_hit_at' => now()])->save();

        $this->at('2026-11-17 09:00');
        $fresh = $week->fresh(['tasks', 'goal.habits.logs']);
        $this->assertSame(0.0, (float) $fresh->earnedPoints());
    }

    public function test_the_cleanup_voids_early_check_ins_once_and_keeps_them_for_history(): void
    {
        $this->at('2026-11-11 09:00');
        [, $alex, , $week] = $this->league();
        $task = $week->tasks->first();
        $task->forceFill(['completed_at' => now(), 'proof_path' => 'proofs/early.png'])->save();
        $week->forceFill(['milestone_hit_at' => now()])->save();
        $habit = $week->goal->habits()->create(['title' => 'Stretch', 'times_per_week' => 3]);
        $habit->logs()->create(['logged_on' => '2026-11-11']);

        $this->at('2026-11-17 09:00');
        $legit = $week->tasks()->create(['position' => 2, 'description' => 'Tempo']);
        $legit->forceFill(['completed_at' => now()])->save();

        $counts = app(EarlyCheckIns::class)->void();
        $this->assertSame(['tasks' => 1, 'proofs' => 1, 'milestones' => 1, 'habit_logs' => 1], array_intersect_key($counts, array_flip(['tasks', 'proofs', 'milestones', 'habit_logs'])));

        $task->refresh();
        $this->assertNull($task->completed_at);
        $this->assertNotNull($task->voided_completed_at);
        $this->assertSame('before kickoff', $task->void_reason);
        $this->assertSame('proofs/early.png', $task->voided_proof_path);
        $this->assertNull($week->fresh()->milestone_hit_at);
        $this->assertSame(0, $habit->fresh()->logs()->count());
        $this->assertNotNull($legit->fresh()->completed_at, 'A check-in after kickoff is untouched.');

        // Running it again changes nothing.
        $again = app(EarlyCheckIns::class)->void();
        $this->assertSame(0, $again['tasks'] + $again['milestones'] + $again['habit_logs']);
        $this->artisan('goalline:early-check-ins', ['--dry-run' => true])->expectsOutputToContain('Would void: 0 task check-ins')->assertSuccessful();
    }

    public function test_kickoff_is_always_a_monday(): void
    {
        $this->at('2026-11-11 09:00');
        $options = LeagueController::kickoffOptions();
        $this->assertTrue(collect($options)->every->isMonday());
        $this->assertSame('2026-11-16', $options[0]->toDateString());

        $this->at('2026-11-16 09:00');
        $this->assertSame('2026-11-16', LeagueController::kickoffOptions()[0]->toDateString(), 'On a Monday, today can be kickoff.');

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('leagues.store'), ['name' => 'X', 'starts_on' => '2026-11-18', 'ends_on' => '2026-12-20', 'goal_id' => 'new', 'goal_title' => 'Run', 'goal_target_date' => '2026-12-20'])
            ->assertSessionHasErrors('starts_on');
        $this->get(route('leagues.create'))->assertSee('Week 1 starts Monday at 12:00 a.m. Eastern.');
    }

    public function test_standings_before_kickoff_show_the_start_date_not_zeros(): void
    {
        $this->at('2026-11-11 09:00');
        [$league, $alex] = $this->league();

        $this->actingAs($alex)->get(route('leagues.show', $league))->assertOk()->assertSee('Season starts Nov 16');
        $this->get(route('leagues.matchups', $league))->assertRedirect(route('leagues.show', $league));
    }

    public function test_the_clubhouse_offers_a_goalleague_and_a_challenge_side_by_side(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Start a GoalLeague')->assertSee('Start a challenge')->assertSee('data-start-choices', false);
        $this->get(route('leagues.index'))->assertSee('Start a challenge');
    }

    public function test_kickoff_respects_the_end_of_daylight_saving_time(): void
    {
        // Daylight saving time ends Sunday, Nov 1, 2026. Kickoff is Monday, Nov 2.
        $this->at('2026-10-30 09:00');
        [, $alex] = $this->league(kickoff: '2026-11-02', weekStart: '2026-11-02');
        $task = RoadmapWeek::whereDate('starts_on', '2026-11-02')->whereHas('goal', fn ($goals) => $goals->where('user_id', $alex->id))->sole()->tasks->first();
        $this->actingAs($alex);

        $this->at('2026-11-01 23:59');
        $this->check($task)->assertForbidden();
        $this->at('2026-11-02 00:00');
        $this->check($task)->assertRedirect();
    }

    public function test_kickoff_respects_the_start_of_daylight_saving_time(): void
    {
        // Daylight saving time starts Sunday, Mar 14, 2027. Kickoff is Monday, Mar 15.
        $this->at('2027-03-10 09:00');
        $league = League::factory()->create(['starts_on' => '2027-03-15', 'ends_on' => '2027-04-25']);
        $alex = User::factory()->create();
        $goal = Goal::factory()->for($alex)->create(['target_date' => '2027-04-25']);
        $league->members()->create(['user_id' => $alex->id, 'goal_id' => $goal->id]);
        $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2027-03-15', 'ends_on' => '2027-03-21', 'focus' => 'F', 'milestone' => 'M']);
        $task = $week->tasks()->create(['position' => 1, 'description' => 'Run']);
        $this->actingAs($alex);

        $this->at('2027-03-14 23:59');
        $this->check($task)->assertForbidden();
        $this->at('2027-03-15 00:00');
        $this->check($task)->assertRedirect();
        $this->assertNotNull($task->fresh()->completed_at);
    }
}
