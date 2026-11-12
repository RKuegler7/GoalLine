<?php

namespace Tests\Feature;

use App\GoalCategory;
use App\Models\Goal;
use App\Models\GoalIntake;
use App\Models\League;
use App\Models\User;
use App\Notifications\LeagueGoalReminder;
use App\RoadmapStatus;
use App\ShareLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeagueOwnerViewTest extends TestCase
{
    use RefreshDatabase;

    private League $league;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 10:00', config('app.timezone')));
        $this->owner = User::factory()->create(['name' => 'Corey Dojo']);
        $this->league = League::factory()->create([
            'commissioner_id' => $this->owner->id, 'name' => 'Dojo Go Running', 'category' => GoalCategory::Fitness,
            'starts_on' => '2026-11-16', 'ends_on' => '2027-03-21', 'goal_title' => 'Run a marathon', 'goal_target_date' => '2027-03-20',
        ]);
        $this->league->members()->create(['user_id' => $this->owner->id, 'goal_id' => $this->goal($this->owner, ready: true)->id]);
    }

    public function test_the_owner_sees_each_members_goal_status(): void
    {
        $setUp = User::factory()->create(['name' => 'Sam Set']);
        $this->league->members()->create(['user_id' => $setUp->id, 'goal_id' => $this->goal($setUp, ready: true)->id]);
        $pending = User::factory()->create(['name' => 'Pat Pending']);
        $pendingGoal = $this->goal($pending, ready: false);
        GoalIntake::factory()->for($pendingGoal)->create();
        $this->league->members()->create(['user_id' => $pending->id, 'goal_id' => $pendingGoal->id]);
        $none = User::factory()->create(['name' => 'Nia None']);
        $this->league->members()->create(['user_id' => $none->id]);

        $this->actingAs($this->owner)->get(route('leagues.show', $this->league))->assertOk()
            ->assertSeeInOrder(['Sam Set', 'Goal set', 'Pat Pending', 'Setting up', 'Remind', 'Nia None', 'Not started', 'Remind']);
    }

    public function test_remind_is_in_app_once_a_day_and_owner_only(): void
    {
        Notification::fake();
        $member = User::factory()->create(['name' => 'Nia None']);
        $membership = $this->league->members()->create(['user_id' => $member->id]);

        $this->actingAs($this->owner)->post(route('leagues.members.remind', [$this->league, $membership]))->assertSessionHas('status', 'Reminder sent to Nia None.');
        $this->actingAs($this->owner)->post(route('leagues.members.remind', [$this->league, $membership]))->assertSessionHas('status', 'You already reminded Nia None today.');
        Notification::assertSentToTimes($member, LeagueGoalReminder::class, 1);

        $this->actingAs($member)->post(route('leagues.members.remind', [$this->league, $membership]))->assertForbidden();
    }

    public function test_the_share_text_includes_the_league_goal(): void
    {
        $url = $this->league->shareableInvite()->url();

        $this->get($url)->assertSee('Corey invited you')->assertSee('1 member')->assertSee('18-week season')->assertSee('Goal: Run a marathon by March 20');
        $this->assertSame("Join me: Run a marathon by March 20. Dojo Go Running on GoalLine: {$url}", ShareLink::leagueMessage('Dojo Go Running', $url, $this->league->goalSummary()));
        $this->actingAs($this->owner)->get(route('leagues.show', $this->league))
            ->assertSee('data-share-message="'.e(ShareLink::leagueMessage('Dojo Go Running', $url, 'Run a marathon by March 20')).'"', false);
    }

    private function goal(User $user, bool $ready): Goal
    {
        $goal = Goal::factory()->for($user)->create(['target_date' => '2027-03-20']);
        $goal->forceFill(['roadmap_status' => $ready ? RoadmapStatus::Ready : RoadmapStatus::None])->save();

        return $goal;
    }
}
