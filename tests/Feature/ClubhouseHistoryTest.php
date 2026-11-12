<?php

namespace Tests\Feature;

use App\GoalStatus;
use App\Models\Goal;
use App\Models\League;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClubhouseHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $rival;

    private League $league;

    private Goal $leagueGoal;

    private Goal $thesis;

    private RoadmapWeek $finalWeek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 10:00', config('app.timezone'))); // A Wednesday.
        $this->user = User::factory()->create(['name' => 'Ryan Kay']);
        $this->rival = User::factory()->create(['name' => 'Red Dufour']);

        // A three-week season that ended Oct 25, played with a goal whose date was the same day.
        $this->league = League::factory()->create(['commissioner_id' => $this->user->id, 'name' => 'Fall Grind', 'starts_on' => '2026-10-05', 'ends_on' => '2026-10-25']);
        $this->leagueGoal = $this->goal($this->user, 'Finish the CPA section', '2026-10-25');
        foreach ([['2026-10-05', '2026-10-11'], ['2026-10-12', '2026-10-18'], ['2026-10-19', '2026-10-25']] as $index => [$starts, $ends]) {
            $week = $this->leagueGoal->weeks()->create(['position' => $index + 1, 'starts_on' => $starts, 'ends_on' => $ends, 'focus' => 'Study', 'milestone' => 'Practice exam']);
            $week->tasks()->create(['position' => 1, 'description' => "Old league task {$index}"])->forceFill(['completed_at' => $starts.' 09:00'])->saveQuietly();
            $this->finalWeek = $week;
        }
        $rivalGoal = $this->goal($this->rival, 'Run a half', '2026-10-25');
        $this->league->members()->create(['user_id' => $this->user->id, 'goal_id' => $this->leagueGoal->id]);
        $this->league->members()->create(['user_id' => $this->rival->id, 'goal_id' => $rivalGoal->id]);
        $this->league->results()->create(['user_id' => $this->user->id, 'week' => 3, 'result' => 'in_line', 'result_points' => 1, 'opponent_id' => $this->rival->id, 'outcome' => 'close_win', 'matchup_points' => 2]);

        // A goal in play this week, outside any league.
        $this->thesis = $this->goal($this->user, 'Write the thesis', '2026-12-31');
        $current = $this->thesis->weeks()->create(['position' => 1, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-15', 'focus' => 'Draft', 'milestone' => 'Chapter two']);
        $current->tasks()->create(['position' => 1, 'description' => 'Draft chapter two', 'planned_on' => '2026-11-11']);
    }

    public function test_now_shows_only_goals_and_leagues_in_play(): void
    {
        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Draft chapter two')
            ->assertDontSee('Old league task')
            ->assertDontSee('Fall Grind')
            ->assertSee('1 goal reached its date');

        $this->actingAs($this->user)->get(route('today'))->assertOk()->assertSee('Draft chapter two')->assertDontSee('Old league task');
        $this->actingAs($this->user)->get(route('checkin.sheet'))->assertOk()->assertDontSee('Old league task');
    }

    public function test_a_goal_with_no_current_league_is_offered_play_it_with_friends_and_solo_is_remembered(): void
    {
        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertSee('Play it with friends?')
            ->assertSee(route('leagues.create', ['goal' => $this->thesis->id]), false)
            ->assertSee('Keep it solo against the line');

        $this->actingAs($this->user)->post(route('goals.next.choose', $this->thesis), ['choice' => 'solo', 'from' => 'clubhouse'])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->user)->get(route('dashboard'))->assertDontSee('Play it with friends?');
    }

    public function test_history_lists_past_seasons_goals_and_weeks_newest_first(): void
    {
        $response = $this->actingAs($this->user)->get(route('dashboard', ['tab' => 'history']))->assertOk();

        $response->assertSee('Fall Grind')
            ->assertSee('of 2')
            ->assertSee('Run it back')
            ->assertSee(route('leagues.recap', $this->league), false)
            ->assertSee('Finish the CPA section')
            ->assertSee('Date reached')
            ->assertSee('Close win vs Red')
            ->assertSeeInOrder(['GoalWeek 3', 'GoalWeek 2', 'GoalWeek 1'])
            ->assertDontSee('Write the thesis');
    }

    public function test_an_achieved_goal_reads_as_promoted(): void
    {
        $this->leagueGoal->forceFill(['status' => GoalStatus::Completed, 'completed_at' => '2026-10-25 18:00'])->save();

        $this->actingAs($this->user)->get(route('dashboard', ['tab' => 'history']))
            ->assertSee('Promoted')
            ->assertSee('Crossed the line')
            ->assertDontSee('Run it back');
    }

    public function test_a_past_week_opens_read_only_for_its_owner_only(): void
    {
        $this->actingAs($this->user)->get(route('history.weeks.show', $this->finalWeek))
            ->assertOk()
            ->assertSee('GoalWeek 3')
            ->assertSee('Close win vs Red')
            ->assertSee('Old league task 2')
            ->assertDontSee('name="_method"', false);

        $this->actingAs($this->rival)->get(route('history.weeks.show', $this->finalWeek))->assertNotFound();
        $this->actingAs($this->user)->get(route('history.weeks.show', $this->thesis->weeks()->first()))->assertNotFound(); // Not over yet.
    }

    public function test_history_shows_only_your_own_things(): void
    {
        $this->actingAs($this->rival)->get(route('dashboard', ['tab' => 'history']))
            ->assertOk()
            ->assertSee('Fall Grind')
            ->assertDontSee('Finish the CPA section')
            ->assertDontSee('Write the thesis');
    }

    public function test_history_has_a_friendly_empty_state_and_the_locker_links_to_it(): void
    {
        $newcomer = User::factory()->create();

        $this->actingAs($newcomer)->get(route('dashboard', ['tab' => 'history']))
            ->assertOk()
            ->assertSee('Your history starts here')
            ->assertSee('aria-selected="true"', false);

        $this->actingAs($this->user)->get(route('profile.show'))->assertSee(route('dashboard', ['tab' => 'history']), false);
    }

    private function goal(User $user, string $title, string $target): Goal
    {
        $goal = Goal::factory()->for($user)->create(['title' => $title, 'target_date' => $target]);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready, 'created_at' => '2026-10-01 09:00'])->save();

        return $goal;
    }
}
