<?php

namespace Tests\Feature;

use App\Badge;
use App\Jobs\AwardBadges;
use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use App\Notifications\BadgeEarned;
use App\ProfileVisibility;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00', config('app.timezone'))); // A Wednesday morning.
    }

    public function test_the_first_check_in_earns_first_step_and_lands_in_the_inbox(): void
    {
        $goal = $this->goalWithWeek();
        $task = $goal->weeks[0]->tasks[0];

        $this->actingAs($goal->user)->post(route('roadmap.tasks.check', $task));
        $this->actingAs($goal->user)->post(route('roadmap.tasks.check', $task)); // Undo.
        $this->actingAs($goal->user)->post(route('roadmap.tasks.check', $task)); // And again.

        $this->assertSame([Badge::FirstStep], $goal->user->badges()->get()->pluck('badge')->all());
        $notification = $goal->user->notifications()->where('type', BadgeEarned::class)->sole();
        $this->assertSame('Badge earned: First Step. Checked in for the first time.', $notification->data['text']);
    }

    public function test_joining_a_goalleague_earns_team_player(): void
    {
        $league = League::factory()->create();
        $member = User::factory()->create();

        $league->members()->create(['user_id' => $member->id]);

        $this->assertTrue($member->badges()->where('badge', Badge::TeamPlayer)->exists());
    }

    public function test_thirty_habit_check_ins_earn_habit_hero(): void
    {
        $goal = $this->goalWithWeek();
        $habit = $goal->habits()->create(['title' => 'Run', 'times_per_week' => 7]);

        foreach (range(1, 29) as $daysAgo) {
            $habit->logs()->create(['logged_on' => today()->subDays($daysAgo)]);
        }
        $this->assertFalse($goal->user->badges()->where('badge', Badge::HabitHero)->exists());

        $habit->logs()->create(['logged_on' => today()]);
        $this->assertTrue($goal->user->badges()->where('badge', Badge::HabitHero)->exists());
    }

    public function test_finished_goalweeks_earn_result_and_streak_badges(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create(['target_date' => '2026-12-31']);

        // Three locked GoalWeeks, the task and milestone done each time (in line); the last with bonus work too (past).
        foreach ([['2026-09-28', '2026-10-04'], ['2026-10-05', '2026-10-11'], ['2026-10-12', '2026-10-18']] as $index => [$starts, $ends]) {
            $week = $goal->weeks()->create(['position' => $index + 1, 'starts_on' => $starts, 'ends_on' => $ends, 'focus' => 'Work', 'milestone' => 'Done.']);
            $week->tasks()->create(['position' => 1, 'description' => 'Task'])->forceFill(['completed_at' => $starts])->saveQuietly();
            $week->forceFill(['milestone_hit_at' => $ends])->save();

            if ($index === 2) {
                $week->tasks()->create(['position' => 2, 'description' => 'Bonus', 'is_bonus' => true])->forceFill(['completed_at' => $starts])->saveQuietly();
            }
        }

        AwardBadges::dispatchSync($user);

        $earned = $user->badges()->get()->pluck('badge');
        $this->assertTrue($earned->contains(Badge::InLine));
        $this->assertTrue($earned->contains(Badge::PastTheLine));
        $this->assertTrue($earned->contains(Badge::OnARoll));
        $this->assertFalse($earned->contains(Badge::Smashed));
        $this->assertFalse($earned->contains(Badge::IronWill));

        // Checking again never awards a badge twice.
        AwardBadges::dispatchSync($user);
        $this->assertSame($earned->count(), $user->badges()->count());
    }

    public function test_your_locker_shows_badges_to_earn_and_others_only_see_earned_ones(): void
    {
        $owner = User::factory()->create(['profile_visibility' => ProfileVisibility::Everyone]);
        $owner->badges()->create(['badge' => Badge::FirstStep, 'earned_at' => now()]);

        $this->actingAs($owner)
            ->get(route('profile.show'))
            ->assertSee('Badges &amp; awards', false)
            ->assertSee('First Step')
            ->assertSee('Iron Will')
            ->assertSee('1 of 13 badges');

        $this->actingAs(User::factory()->create())
            ->get(route('profiles.show', $owner))
            ->assertSee('First Step')
            ->assertDontSee('Iron Will');
    }

    private function goalWithWeek(): Goal
    {
        $goal = Goal::factory()->create(['title' => 'Launch the business', 'target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Prototype', 'milestone' => 'Done.']);
        $week->tasks()->create(['position' => 1, 'description' => 'Build the first prototype']);

        return $goal->load('weeks.tasks');
    }
}
