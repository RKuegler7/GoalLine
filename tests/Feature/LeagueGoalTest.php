<?php

namespace Tests\Feature;

use App\GoalCategory;
use App\Models\Goal;
use App\Models\GoalIntake;
use App\Models\League;
use App\Models\User;
use App\Notifications\LeagueGoalChanged;
use App\RoadmapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeagueGoalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-11 10:00', config('app.timezone'))); // Wednesday.
    }

    public function test_a_new_league_copies_its_owners_goal_as_a_snapshot(): void
    {
        $owner = User::factory()->create(['name' => 'Corey Dojo']);
        $goal = Goal::factory()->for($owner)->create(['title' => 'Run a marathon', 'category' => GoalCategory::Fitness, 'target_date' => '2027-03-20', 'why' => 'For my dad.']);

        $this->actingAs($owner)->post(route('leagues.store'), [
            'name' => 'Dojo Go Running', 'starts_on' => '2026-11-16', 'ends_on' => '2027-03-21', 'goal_id' => $goal->id,
        ])->assertRedirect();

        $league = League::sole();
        $this->assertSame('Run a marathon', $league->goal_title);
        $this->assertSame('2027-03-20', $league->goal_target_date->toDateString());
        $this->assertSame(GoalCategory::Fitness, $league->category);
        $this->assertSame('Run a marathon by March 20', $league->goalSummary());

        // A later edit to the owner's own goal doesn't change the league's copy.
        $goal->update(['title' => 'Run a marathon under 4 hours']);
        $this->assertSame('Run a marathon', $league->fresh()->goal_title);
    }

    public function test_the_invite_and_league_pages_show_the_league_goal_and_nothing_private(): void
    {
        [$league, $owner, $goal] = $this->league();
        GoalIntake::factory()->for($goal)->create(['answers' => [['id' => 'health', 'question' => 'Any injuries?', 'value' => 'Bad left knee']]]);
        $invite = $league->shareableInvite();

        $page = $this->get($invite->url())->assertOk()
            ->assertSee('Goal: Run a marathon by March 20')
            ->assertSee('Corey invited you');
        foreach (['For my dad.', 'Bad left knee', $owner->email, 'Easy run, 30 minutes'] as $private) {
            $page->assertDontSee($private);
        }

        $this->actingAs($owner)->get(route('leagues.show', $league))->assertOk()->assertSeeText('Goal: Run a marathon by March 20');
    }

    public function test_the_owner_edits_the_league_goal_and_members_are_told(): void
    {
        Notification::fake();
        [$league, $owner] = $this->league();
        $member = User::factory()->create();
        $league->members()->create(['user_id' => $member->id]);

        $this->actingAs($owner)->put(route('leagues.goal.update', $league), [
            'goal_title' => 'Run a spring marathon', 'goal_target_date' => '2027-03-27', 'goal_description' => 'Any 26.2.', 'goal_target_result' => 'Under 4:30',
        ])->assertRedirect(route('leagues.show', $league));

        $league->refresh();
        $this->assertSame(['Run a spring marathon', '2027-03-27', 'Any 26.2.', 'Under 4:30'], [$league->goal_title, $league->goal_target_date->toDateString(), $league->goal_description, $league->goal_target_result]);
        $this->assertNotNull($league->goal_updated_at);
        Notification::assertSentTo($member, LeagueGoalChanged::class);
        Notification::assertNotSentTo($owner, LeagueGoalChanged::class);
        $this->assertTrue($league->messages()->where('body', 'like', '%updated the league goal%')->exists());

        // Only the owner can change it.
        $this->actingAs($member)->put(route('leagues.goal.update', $league), ['goal_title' => 'Nap', 'goal_target_date' => '2027-01-01'])->assertForbidden();
    }

    public function test_existing_leagues_get_their_owners_goal_as_the_template_when_migrating(): void
    {
        [$league] = $this->league();
        $league->forceFill(['goal_title' => null, 'goal_target_date' => null])->save();

        $migration = require database_path('migrations/2026_11_11_211415_add_league_goal_to_leagues_table.php');
        $migration->up();
        $migration->up(); // Safe to run twice.

        $this->assertSame('Run a marathon', $league->fresh()->goal_title);
    }

    /**
     * @return array{League, User, Goal}
     */
    private function league(): array
    {
        $owner = User::factory()->create(['name' => 'Corey Dojo']);
        $goal = Goal::factory()->for($owner)->create(['title' => 'Run a marathon', 'category' => GoalCategory::Fitness, 'target_date' => '2027-03-20', 'why' => 'For my dad.']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-15', 'focus' => 'Base', 'milestone' => 'Done'])
            ->tasks()->create(['position' => 1, 'description' => 'Easy run, 30 minutes']);
        $league = League::factory()->create(['commissioner_id' => $owner->id, 'name' => 'Dojo Go Running', 'category' => GoalCategory::Fitness, 'starts_on' => '2026-11-16', 'ends_on' => '2027-03-21']);
        $league->members()->create(['user_id' => $owner->id, 'goal_id' => $goal->id]);
        $league->useGoalAsTemplate($goal);
        $league->save();

        return [$league, $owner, $goal];
    }
}
