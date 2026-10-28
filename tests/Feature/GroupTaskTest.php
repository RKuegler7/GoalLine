<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\GroupTask;
use App\Models\League;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Notifications\GroupTaskDecided;
use App\Notifications\GroupTaskProposed;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GroupTaskTest extends TestCase
{
    use RefreshDatabase;

    private League $league;

    /** @var Collection<int, User> */
    private Collection $members;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        // Wednesday, Oct 28. The season starts Monday, Nov 2.
        $this->travelTo(Carbon::parse('2026-10-28 12:00', 'America/New_York'));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function leagueSizes(): array
    {
        return ['2 members' => [2, 2], '3 members' => [3, 2], '4 members' => [4, 3], '5 members' => [5, 4], '10 members' => [10, 7]];
    }

    #[DataProvider('leagueSizes')]
    public function test_it_passes_at_two_thirds_of_the_league_rounded_up(int $size, int $needed): void
    {
        $this->assertSame($needed, GroupTask::requiredYes($size));

        $this->makeLeague($size);
        $groupTask = $this->propose();

        // The proposer's yes counts; everyone else but the last needed voter says yes.
        foreach ($this->members->slice(1, $needed - 2) as $member) {
            $this->vote($member, $groupTask, true);
            $this->assertSame(GroupTask::VOTING, $groupTask->fresh()->status);
        }

        $this->vote($this->members[$needed - 1], $groupTask, true);
        $this->assertSame(GroupTask::PASSED, $groupTask->fresh()->status);
        $this->assertSame($size, $groupTask->tasks()->count());
    }

    public function test_votes_can_change_until_voting_closes(): void
    {
        $this->makeLeague(4);
        $groupTask = $this->propose();

        $this->vote($this->members[1], $groupTask, false);
        $this->vote($this->members[1], $groupTask, true);
        $this->assertSame(2, $groupTask->load('votes')->yesCount());
        $this->assertSame(0, $groupTask->noCount());
        $this->vote($this->members[1], $groupTask, false);
        $this->assertSame(1, $groupTask->load('votes')->yesCount());

        $this->travel(25)->hours();
        $this->actingAs($this->members[1])->post(route('group-tasks.vote', $groupTask), ['approve' => 1])->assertStatus(422);
    }

    public function test_without_enough_yes_votes_in_24_hours_there_is_no_task(): void
    {
        Notification::fake();
        $this->makeLeague(3);
        $groupTask = $this->propose();
        Notification::assertSentTo([$this->members[1], $this->members[2]], GroupTaskProposed::class);
        Notification::assertNotSentTo($this->members[0], GroupTaskProposed::class);

        $this->travel(23)->hours();
        $this->artisan('goalline:close-group-votes')->assertSuccessful();
        $this->assertSame(GroupTask::VOTING, $groupTask->fresh()->status);

        $this->travel(2)->hours();
        $this->artisan('goalline:close-group-votes')->assertSuccessful();
        $groupTask->refresh();
        $this->assertSame(GroupTask::FAILED, $groupTask->status);
        $this->assertSame('Not enough yes votes in 24 hours.', $groupTask->outcome_note);
        $this->assertSame(0, $groupTask->tasks()->count());
        Notification::assertSentTo($this->members, GroupTaskDecided::class);

        $this->actingAs($this->members[0])->get(route('leagues.show', $this->league))->assertSee('Did not pass');
    }

    public function test_a_passed_task_joins_every_members_goalweek_and_their_planned_points(): void
    {
        $this->makeLeague(3);
        $before = $this->members->map(fn (User $member) => $this->goalWeekOf($member)->plannedPoints());

        $groupTask = $this->propose(['title' => 'Walk 10,000 steps', 'points' => 7]);
        $this->vote($this->members[1], $groupTask, true);

        foreach ($this->members as $index => $member) {
            $week = $this->goalWeekOf($member);
            $task = $week->tasks->firstWhere('group_task_id', $groupTask->id);
            $this->assertSame('Walk 10,000 steps', $task->description);
            $this->assertSame(7, $task->points);
            $this->assertFalse($task->is_bonus);
            $this->assertSame($before[$index] + 7, $week->plannedPoints());
        }

        // It's set by the vote: nobody can reword or remove it.
        $task = $this->goalWeekOf($this->members[2])->tasks->firstWhere('group_task_id', $groupTask->id);
        $this->actingAs($this->members[2])->delete(route('roadmap.tasks.destroy', $task))->assertForbidden();
        $this->actingAs($this->members[2])->put(route('roadmap.tasks.update', $task), ['description' => 'Walk 10 steps'])->assertForbidden();

        $this->actingAs($this->members[2])->get(route('goals.show', $task->week->goal))->assertSee('Group task');
    }

    public function test_two_group_tasks_a_week_at_most_and_no_duplicates(): void
    {
        $this->makeLeague(2);
        $this->vote($this->members[1], $this->propose(['title' => 'Read 20 minutes']), true);
        $this->actingAs($this->members[0])->post(route('leagues.group-tasks.store', $this->league), ['title' => 'read 20 minutes', 'points' => 5, 'week' => 1])
            ->assertSessionHasErrorsIn('groupTask', ['title' => 'That task is already proposed for that week.']);

        $this->actingAs($this->members[0])->get(route('leagues.show', $this->league))->assertSee('Week 1 &middot; starts Nov 2 &middot; 1 slot left', false);

        $this->vote($this->members[1], $this->propose(['title' => 'No phone after 10']), true);
        $this->actingAs($this->members[0])->post(route('leagues.group-tasks.store', $this->league), ['title' => 'Stretch', 'points' => 5, 'week' => 1])
            ->assertSessionHasErrorsIn('groupTask', ['title' => 'Week 1 already has 2 group tasks.']);
        $this->assertSame(2, GroupTask::where('status', GroupTask::PASSED)->count());
    }

    public function test_the_week_must_start_after_the_vote_ends_and_points_are_1_to_10(): void
    {
        $this->makeLeague(2);
        $this->travelTo(Carbon::parse('2026-11-01 09:00', 'America/New_York')); // Sunday: week 1 starts in 15 hours.

        $this->actingAs($this->members[0])->post(route('leagues.group-tasks.store', $this->league), ['title' => 'Stretch', 'points' => 5, 'week' => 1])
            ->assertSessionHasErrorsIn('groupTask', ['title' => 'Pick a week that starts after the 24-hour vote ends.']);
        $this->actingAs($this->members[0])->post(route('leagues.group-tasks.store', $this->league), ['title' => 'Stretch', 'points' => 11, 'week' => 2])
            ->assertSessionHasErrorsIn('groupTask', 'points');
        $this->actingAs($this->members[0])->post(route('leagues.group-tasks.store', $this->league), ['title' => 'Stretch', 'points' => 5, 'week' => 2])
            ->assertSessionHasNoErrors();
    }

    public function test_solo_leagues_and_outsiders_cannot_use_group_tasks(): void
    {
        $this->makeLeague(1);
        $this->actingAs($this->members[0])->get(route('leagues.show', $this->league))->assertSee('Group tasks open up once a second member joins.');
        $this->actingAs($this->members[0])->post(route('leagues.group-tasks.store', $this->league), ['title' => 'Stretch', 'points' => 5, 'week' => 1])
            ->assertSessionHasErrorsIn('groupTask', ['title' => 'Group tasks need at least two members in the league.']);

        $this->makeLeague(3);
        $groupTask = $this->propose();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->post(route('group-tasks.vote', $groupTask), ['approve' => 1])->assertForbidden();
        $this->actingAs($outsider)->post(route('leagues.group-tasks.store', $this->league), ['title' => 'Mine', 'points' => 5, 'week' => 1])->assertForbidden();
    }

    private function makeLeague(int $size): void
    {
        $this->members = User::factory()->count($size)->create()->values();
        $this->league = League::factory()->create(['commissioner_id' => $this->members[0]->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-29']);

        foreach ($this->members as $member) {
            $goal = Goal::factory()->for($member)->create(['target_date' => '2026-12-31']);
            $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
            $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-08', 'focus' => 'Base', 'milestone' => 'Done.']);
            $week->tasks()->create(['position' => 1, 'description' => 'Plan the week']);
            $this->league->members()->create(['user_id' => $member->id, 'goal_id' => $goal->id]);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function propose(array $overrides = []): GroupTask
    {
        $this->actingAs($this->members[0])
            ->post(route('leagues.group-tasks.store', $this->league), [...['title' => 'Read 20 minutes', 'points' => 5, 'week' => 1], ...$overrides])
            ->assertSessionHasNoErrors();

        return GroupTask::latest('id')->first();
    }

    private function vote(User $member, GroupTask $groupTask, bool $approve): void
    {
        $this->actingAs($member)->post(route('group-tasks.vote', $groupTask), ['approve' => $approve ? 1 : 0])->assertRedirect();
    }

    private function goalWeekOf(User $member): RoadmapWeek
    {
        return $member->goals()->sole()->weeks()->sole()->load('tasks');
    }
}
