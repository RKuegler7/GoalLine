<?php

namespace Tests\Feature;

use App\Badge;
use App\Models\Challenge;
use App\Models\Goal;
use App\Models\User;
use App\Notifications\ChallengeFinished;
use App\Notifications\ChallengeJoined;
use App\RoadmapStatus;
use App\Services\ChallengeWrapUp;
use App\Services\DailyStreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ChallengeTest extends TestCase
{
    use RefreshDatabase;

    private User $avery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00', 'America/New_York')); // A Wednesday.
        $this->avery = User::factory()->create(['name' => 'Avery Lee']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_replace_recursive([
            'title' => 'Cold showers',
            'type' => 'daily',
            'rules' => ['items' => ['Take a cold shower']],
            'duration' => '7',
            'starts_on' => '2026-10-21',
            'mode' => 'flexible',
            'allowed_misses' => 1,
            'visibility' => 'private',
        ], $overrides);
    }

    public function test_anyone_can_start_a_challenge_and_is_its_first_member(): void
    {
        $this->actingAs($this->avery)->post(route('challenges.store'), $this->form())->assertRedirect();

        $challenge = Challenge::sole();
        $this->assertSame('2026-10-27', $challenge->ends_on->toDateString());
        $this->assertSame(6, $challenge->target_days, '7 days, 1 miss allowed.');
        $this->assertFalse($challenge->is_public, 'Private by default.');
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $challenge->invite_code);
        $this->assertTrue($challenge->members()->where('user_id', $this->avery->id)->exists());

        $this->actingAs($this->avery)->get(route('challenges.show', $challenge))->assertOk()
            ->assertSee('I did it today')->assertSee($challenge->invite_code)->assertSee('Daily: Take a cold shower, every day');
    }

    public function test_challenge_details_are_validated(): void
    {
        $this->actingAs($this->avery)
            ->post(route('challenges.store'), $this->form(['title' => '', 'starts_on' => '2026-10-01', 'duration' => '9']))
            ->assertSessionHasErrors(['title', 'duration']);

        $this->actingAs($this->avery)->post(route('challenges.store'), $this->form(['starts_on' => '2026-10-01']))->assertSessionHasErrors('starts_on');
        $this->actingAs($this->avery)->post(route('challenges.store'), $this->form(['title' => str_repeat('a', 61)]))->assertSessionHasErrors('title');
        $this->actingAs($this->avery)->post(route('challenges.store'), $this->form(['rules' => ['items' => ['', '']]]))->assertSessionHasErrors('rules.items');
        $this->assertSame(0, Challenge::count());
    }

    public function test_people_join_with_the_link_or_code_after_seeing_the_rules_and_the_creator_hears_about_it(): void
    {
        Notification::fake();
        $challenge = Challenge::factory()->for($this->avery, 'creator')->create();
        $challenge->members()->create(['user_id' => $this->avery->id]);
        $sam = User::factory()->create(['name' => 'Sam Okafor']);

        $this->actingAs($sam)->get(route('challenges.show', $challenge))->assertForbidden();

        $this->actingAs($sam)->post(route('challenges.find'), ['code' => strtolower(str_replace('-', ' ', $challenge->invite_code))])
            ->assertRedirect(route('challenges.join', $challenge->invite_code));
        $this->actingAs($sam)->get(route('challenges.join', $challenge->invite_code))
            ->assertSee('The rules')->assertSee('I&rsquo;m in for these rules', false);

        // The rules must be confirmed.
        $this->actingAs($sam)->post(route('challenges.join.store', $challenge->invite_code))->assertSessionHasErrors('agree');
        $this->actingAs($sam)->post(route('challenges.join.store', $challenge->invite_code), ['agree' => '1'])->assertRedirect(route('challenges.show', $challenge));

        $this->assertSame(2, $challenge->members()->count());
        Notification::assertSentTo($this->avery, ChallengeJoined::class);
    }

    public function test_check_ins_count_once_a_day_with_a_one_day_grace_and_rank_the_standings(): void
    {
        $challenge = Challenge::factory()->for($this->avery, 'creator')->create(['starts_on' => '2026-10-19', 'ends_on' => '2026-10-25']);
        $sam = User::factory()->create(['name' => 'Sam Okafor']);
        $challenge->members()->create(['user_id' => $this->avery->id]);
        $challenge->members()->create(['user_id' => $sam->id]);

        $this->actingAs($this->avery)->post(route('challenges.check-in', $challenge));
        $this->actingAs($this->avery)->post(route('challenges.check-in', $challenge), ['date' => '2026-10-20']);
        $this->actingAs($this->avery)->post(route('challenges.check-in', $challenge), ['date' => '2026-10-19'])->assertForbidden(); // Past the grace.
        $this->actingAs($this->avery)->post(route('challenges.check-in', $challenge), ['date' => '2026-10-22'])->assertForbidden(); // Tomorrow.
        $this->actingAs($sam)->post(route('challenges.check-in', $challenge));

        $standings = $challenge->fresh()->load('members.user', 'checkIns')->standings();
        $this->assertSame(['Avery Lee', 'Sam Okafor'], $standings->map(fn ($row) => $row['member']->user->name)->all());
        $this->assertSame([2, 1], $standings->pluck('completed')->all());
        $this->assertSame([2, 1], $standings->pluck('streak')->all());
        $this->assertSame([28, 14], $standings->pluck('percent')->all(), '2 and 1 of 7 days.');

        // Checking in again undoes it.
        $this->actingAs($sam)->post(route('challenges.check-in', $challenge));
        $this->assertSame(0, $challenge->checkIns()->where('user_id', $sam->id)->count());

        // The gold table, ranked by completion, with a text alternative for the last five days.
        $this->actingAs($this->avery)->get(route('challenges.show', $challenge))->assertOk()
            ->assertSee('data-challenge-table', false)
            ->assertSee('Official order: Completion %, then current streak, then days completed.')
            ->assertSee('Last 5: missed, done, done.')
            ->assertSee('2 of 7 days');
        $this->actingAs($this->avery)->get(route('challenges.show', [$challenge, 'col' => 'bestStreak']))->assertOk()
            ->assertSee('Sorted by Best, highest first.')->assertSee('Reset to official order');
    }

    public function test_challenges_show_on_todays_gameplan_and_count_toward_the_streak(): void
    {
        $goal = Goal::factory()->for($this->avery)->create(['target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Work', 'milestone' => 'Done.']);
        $challenge = Challenge::factory()->for($this->avery, 'creator')->create(['title' => 'Cold showers', 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25']);
        $challenge->members()->create(['user_id' => $this->avery->id]);

        $this->actingAs($this->avery)->get(route('dashboard'))->assertSee('Cold showers')->assertSee('day 3 of 7');

        $this->actingAs($this->avery)->post(route('challenges.check-in', $challenge), ['return' => 'gameplan'])->assertRedirect(route('dashboard').'#gameplan');
        $this->assertSame(1, (new DailyStreak($this->avery))->current());
        $this->actingAs($this->avery)->get(route('dashboard'))->assertSee('All 1 done. Day won.');
    }

    public function test_when_it_ends_everyone_hears_their_finish_and_finishers_earn_the_badge(): void
    {
        Notification::fake();
        // 7 days, 5 misses allowed: 2 days finish it.
        $challenge = Challenge::factory()->for($this->avery, 'creator')->create(['title' => 'No sugar', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18', 'allowed_misses' => 5]);
        $sam = User::factory()->create();
        $challenge->members()->create(['user_id' => $this->avery->id]);
        $challenge->members()->create(['user_id' => $sam->id]);
        $challenge->checkIns()->create(['user_id' => $this->avery->id, 'checked_on' => '2026-10-15']);
        $challenge->checkIns()->create(['user_id' => $this->avery->id, 'checked_on' => '2026-10-16']);
        $challenge->checkIns()->create(['user_id' => $sam->id, 'checked_on' => '2026-10-16']);

        $this->assertSame(1, app(ChallengeWrapUp::class)->run());
        $this->assertSame(0, app(ChallengeWrapUp::class)->run()); // Only once.

        Notification::assertSentTo($this->avery, ChallengeFinished::class, fn (ChallengeFinished $finished) => $finished->text === '“No sugar” is over. You finished #1 of 2 with 2 of 7 days. Challenge complete!');
        Notification::assertSentTo($sam, ChallengeFinished::class, fn (ChallengeFinished $finished) => $finished->text === '“No sugar” is over. You finished #2 of 2 with 1 of 7 days.');
        $this->assertTrue($this->avery->badges()->where('badge', Badge::ChallengeFinisher)->exists());
        $this->assertFalse($sam->badges()->where('badge', Badge::ChallengeFinisher)->exists());
    }

    public function test_the_creator_can_call_it_off_before_it_starts_and_members_can_leave(): void
    {
        $upcoming = Challenge::factory()->for($this->avery, 'creator')->create(['starts_on' => '2026-10-26', 'ends_on' => '2026-11-01']);
        $upcoming->members()->create(['user_id' => $this->avery->id]);
        $this->actingAs($this->avery)->delete(route('challenges.destroy', $upcoming))->assertRedirect(route('leagues.index', ['tab' => 'challenges']));
        $this->assertModelMissing($upcoming);

        $running = Challenge::factory()->for($this->avery, 'creator')->create();
        $sam = User::factory()->create();
        $running->members()->create(['user_id' => $this->avery->id]);
        $running->members()->create(['user_id' => $sam->id]);
        $this->actingAs($this->avery)->delete(route('challenges.destroy', $running))->assertForbidden();

        $this->actingAs($sam)->delete(route('challenges.leave', $running));
        $this->assertSame(1, $running->members()->count());
    }

    public function test_you_can_check_in_right_from_the_challenge_card(): void
    {
        $challenge = Challenge::factory()->for($this->avery, 'creator')->create(['title' => 'Cold showers', 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25']);
        $challenge->members()->create(['user_id' => $this->avery->id]);

        $this->actingAs($this->avery)->get(route('leagues.index'))->assertSee('I did it today')->assertSee('0/7');
        $this->actingAs($this->avery)->post(route('challenges.check-in', $challenge), ['return' => 'list'])->assertRedirect(route('leagues.index', ['tab' => 'challenges']));
        $this->actingAs($this->avery)->get(route('leagues.index'))->assertSee('Done today')->assertSee('1/7');
    }

    public function test_templates_fill_the_builder_with_their_own_duration_and_rules(): void
    {
        $this->actingAs($this->avery)->get(route('challenges.create', ['template' => 'workout-4']))->assertOk()
            ->assertSee('<option value="30" selected', false)
            ->assertDontSee('<option value="7" selected', false)
            ->assertSee('value="Workout 4 times a week"', false);

        $this->actingAs($this->avery)->get(route('challenges.create', ['template' => 'lent']))->assertOk()
            ->assertSee('<option value="lent" selected', false);

        $weekly = Challenge::factory()->for($this->avery, 'creator')->create(['type' => 'weekly', 'rules' => ['label' => 'Workout', 'times' => 4]]);
        $this->assertSame('Workout 4 times a week', $weekly->ruleSummary());
        $days = Challenge::factory()->for($this->avery, 'creator')->create(['type' => 'weekly', 'rules' => ['days' => [5, 1, 3]]]);
        $this->assertSame('on Mon, Wed, Fri', $days->ruleSummary());
    }

    public function test_the_help_center_explains_challenges_and_public_listings(): void
    {
        $this->actingAs($this->avery)->get(route('help'))->assertOk()
            ->assertSeeInOrder(['Challenges', 'What is a Challenge?', 'What are the four types?', 'How long can a Challenge be?', 'How does the Lent template work?', 'What are strict and flexible?', 'How are Challenges ranked?', 'How is a Challenge different from a GoalLeague?'])
            ->assertSee('How do public GoalLeagues and Challenges work?')
            ->assertSee('never change a GoalLeague');

        $this->actingAs($this->avery)->get(route('help', ['context' => 'challenge-ranking']))->assertOk()->assertSee('id="help-challenge-ranking"', false)->assertSee('data-help-focus', false);
    }

    public function test_the_goalleagues_page_lists_challenges_and_offers_templates_when_there_are_none(): void
    {
        $this->actingAs($this->avery)->get(route('leagues.index'))->assertSee('No Challenges yet.')->assertSee('Lent')->assertSee('Build your own');

        $challenge = Challenge::factory()->for($this->avery, 'creator')->create(['title' => 'Read 20 pages a day']);
        $challenge->members()->create(['user_id' => $this->avery->id]);

        $this->actingAs($this->avery)->get(route('leagues.index'))->assertSee('Challenges')->assertSee('Read 20 pages a day');
    }
}
