<?php

namespace Tests\Feature;

use App\GoalOutcome;
use App\GoalWeekResult;
use App\HighlightKind;
use App\MatchupOutcome;
use App\MedalType;
use App\Models\Goal;
use App\Models\League;
use App\Models\Medal;
use App\Models\User;
use App\Notifications\LeagueInvited;
use App\Services\GoalRecommender;
use App\Services\LeagueTable;
use App\Services\MedalAwarder;
use App\Services\ResultCards\SeasonCardImage;
use App\Services\SeasonFinale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SeasonFinaleTest extends TestCase
{
    use RefreshDatabase;

    private League $league;

    /** @var array<string, User> */
    private array $people;

    /** @var array<string, Goal> */
    private array $goals;

    /**
     * A one-pixel PNG, so proof uploads work without GD.
     */
    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');

        // A four-week season, September 28 to October 25, now over. Goal Day for everyone was October 25.
        $this->travelTo(Carbon::parse('2026-09-01 09:00', config('app.timezone')));
        $this->league = League::factory()->create(['name' => 'Launch Crew', 'starts_on' => '2026-09-28', 'ends_on' => '2026-10-25']);

        foreach (['Avery', 'Blake', 'Casey', 'Drew'] as $name) {
            $this->people[$name] = User::factory()->create(['name' => $name.' Lane']);
            $this->goals[$name] = Goal::factory()->for($this->people[$name])->create(['title' => "{$name}'s goal", 'category' => 'fitness', 'target_date' => '2026-10-25']);
            $this->league->members()->create(['user_id' => $this->people[$name]->id, 'goal_id' => $this->goals[$name]->id]);
        }

        $this->travelTo(Carbon::parse('2026-11-02 09:00', config('app.timezone')));

        [$I, $P, $S, $X] = [GoalWeekResult::InLine, GoalWeekResult::Past, GoalWeekResult::Smashed, GoalWeekResult::Short];
        [$W, $T, $L] = [MatchupOutcome::Win, MatchupOutcome::Tie, MatchupOutcome::Loss];

        $season = [
            'Avery' => [[$I, $W], [$I, $W], [$I, $W], [$I, $W]],
            'Blake' => [[$S, $T], [$S, $L], [$X, $L], [$X, $L]],
            'Casey' => [[$X, $L], [$X, $L], [$P, $W], [$P, $W]],
            'Drew' => [[$X, $L], [$I, $L], [$X, $L], [$X, $L]],
        ];

        foreach ($season as $name => $weeks) {
            foreach ($weeks as $index => [$result, $outcome]) {
                $this->league->results()->create([
                    'user_id' => $this->people[$name]->id,
                    'week' => $index + 1,
                    'result' => $result,
                    'result_points' => $result->points(),
                    'opponent_id' => $this->people[$name === 'Avery' ? 'Blake' : 'Avery']->id,
                    'vs_median' => false,
                    'outcome' => $outcome,
                    'matchup_points' => $outcome->points(),
                ]);
            }
        }
    }

    private function checkIn(string $name, array $answer = []): TestResponse
    {
        return $this->actingAs($this->people[$name])->post(route('goal-day.store', $this->goals[$name]), $answer + ['outcome' => 'achieved', 'shared' => '1']);
    }

    private function proof(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('proof.png', base64_decode(self::PIXEL));
    }

    public function test_the_goal_day_check_in_waits_on_the_clubhouse_and_in_history_until_answered(): void
    {
        $this->actingAs($this->people['Casey'])->get(route('dashboard'))
            ->assertOk()->assertSee('data-goal-day-prompt', false)->assertSee('Did you reach');
        $this->get(route('dashboard', ['tab' => 'history']))->assertOk()->assertSee('data-history-goal-day', false);
        $this->get(route('goal-day.show', $this->goals['Casey']))->assertOk()->assertSee('Did you reach your goal?')
            ->assertSee('I did it')->assertSee('Partly')->assertSee('I came up short');

        $this->checkIn('Casey', ['outcome' => 'partly']);

        $this->get(route('dashboard'))->assertDontSee('data-goal-day-prompt', false);
    }

    public function test_the_finale_opens_with_the_check_in_when_it_is_unanswered(): void
    {
        $this->actingAs($this->people['Drew'])->get(route('finale.show', $this->goals['Drew']))
            ->assertRedirect(route('goal-day.show', $this->goals['Drew']));

        // Unanswered and skipped: "Season complete", no judgment.
        $this->get(route('finale.show', ['goal' => $this->goals['Drew'], 'skip' => 1]))
            ->assertOk()->assertSee('Season complete.')->assertSee('Answer the Goal Day check-in');
    }

    public function test_the_banner_counts_down_to_goal_day_in_the_final_two_weeks(): void
    {
        $user = User::factory()->create();
        Goal::factory()->for($user)->create(['title' => 'Run a 10K', 'target_date' => Carbon::today()->addDays(10)]);
        $later = User::factory()->create();
        Goal::factory()->for($later)->create(['target_date' => Carbon::today()->addDays(30)]);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Goal Day is in 10 days');
        $this->actingAs($later)->get(route('dashboard'))->assertDontSee('data-goal-day-banner', false);
    }

    public function test_answering_never_changes_stars_or_standings(): void
    {
        $before = $this->league->results()->orderBy('id')->get(['result_points', 'matchup_points', 'outcome'])->toArray();
        $table = (new LeagueTable($this->league->fresh()))->standings()->map(fn ($row) => [$row['member']->user_id, $row['total']])->all();

        $this->checkIn('Drew', ['actual_result' => 'Finished in 52:10', 'hardest_part' => 'Hills', 'helped_most' => 'Saturday long runs'])
            ->assertRedirect(route('finale.show', $this->goals['Drew']));

        $this->assertSame($before, $this->league->results()->orderBy('id')->get(['result_points', 'matchup_points', 'outcome'])->toArray());
        $this->assertSame($table, (new LeagueTable($this->league->fresh()))->standings()->map(fn ($row) => [$row['member']->user_id, $row['total']])->all());

        $result = $this->goals['Drew']->fresh()->result;
        $this->assertSame(GoalOutcome::Achieved, $result->outcome);
        $this->assertSame('Finished in 52:10', $result->actual_result);
        $this->assertSame($this->league->id, $result->league_id);
        $this->assertTrue($this->goals['Drew']->fresh()->isCompleted());
        $this->assertFalse($result->isVerified());
    }

    public function test_the_actual_result_and_notes_are_validated(): void
    {
        $this->checkIn('Drew', ['outcome' => 'nope'])->assertSessionHasErrors('outcome');
        $this->checkIn('Drew', ['actual_result' => str_repeat('x', 81)])->assertSessionHasErrors('actual_result');
        $this->checkIn('Drew', ['hardest_part' => str_repeat('x', 281)])->assertSessionHasErrors('hardest_part');
        $this->assertNull($this->goals['Drew']->fresh()->result);
    }

    public function test_finishing_early_is_only_for_a_reached_goal(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create(['target_date' => Carbon::today()->addMonth()]);

        $this->actingAs($user)->get(route('goal-day.show', $goal))->assertOk()->assertSee('I finished early')->assertDontSee('I came up short');
        $this->post(route('goal-day.store', $goal), ['outcome' => 'short', 'shared' => '1'])->assertSessionHasErrors('outcome');
        $this->post(route('goal-day.store', $goal), ['outcome' => 'achieved', 'shared' => '0'])->assertRedirect(route('finale.show', $goal));

        $this->assertTrue($goal->fresh()->result->finished_early);
    }

    public function test_an_answer_can_be_changed_for_seven_days_then_it_is_locked(): void
    {
        $this->checkIn('Blake', ['outcome' => 'short']);
        $this->travel(6)->days();
        $this->checkIn('Blake', ['outcome' => 'partly'])->assertRedirect();
        $this->assertSame(GoalOutcome::Partly, $this->goals['Blake']->fresh()->result->outcome);

        $this->travel(2)->days();
        $this->checkIn('Blake', ['outcome' => 'achieved'])->assertForbidden();
        $this->get(route('goal-day.show', $this->goals['Blake']))->assertRedirect(route('finale.show', $this->goals['Blake']));
    }

    public function test_proof_makes_an_achieved_result_verified(): void
    {
        $this->checkIn('Avery', ['proof' => $this->proof()])->assertRedirect();

        $result = $this->goals['Avery']->fresh()->result;
        $this->assertTrue($result->isVerified());
        Storage::disk('uploads')->assertExists($result->proof_path);
        $this->assertSame('verified', Medal::where('award_key', 'goal_achieved:G'.$this->goals['Avery']->id)->value('tier'));
    }

    public function test_a_league_mate_can_confirm_a_result_but_never_their_own(): void
    {
        $this->checkIn('Casey');
        $result = $this->goals['Casey']->fresh()->result;

        $this->actingAs($this->people['Casey'])->post(route('goal-day.confirm', $result))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('goal-day.confirm', $result))->assertForbidden();

        $this->actingAs($this->people['Avery'])->get(route('finale.show', ['goal' => $this->goals['Avery'], 'skip' => 1]))->assertSee('Confirm it');
        $this->post(route('goal-day.confirm', $result))->assertRedirect();

        $this->assertTrue($result->fresh()->isVerified());
        $this->assertSame($this->people['Avery']->id, $result->fresh()->verified_by);
    }

    public function test_a_private_result_never_reaches_league_news(): void
    {
        $this->checkIn('Blake', ['outcome' => 'short', 'shared' => '0']);
        $this->checkIn('Casey', ['outcome' => 'achieved', 'actual_result' => 'Sub-25 5K']);

        $news = (new SeasonFinale($this->goals['Avery']))->leagueNews();
        $this->assertSame([$this->people['Casey']->id], $news->pluck('user_id')->all());
        $this->assertFalse($this->people['Blake']->highlights()->where('kind', HighlightKind::GoalDay)->exists());

        $this->actingAs($this->people['Avery'])->get(route('leagues.show', $this->league))
            ->assertSee('Goal Day news')->assertSee('Sub-25 5K')->assertDontSee("Blake's goal");

        // Keeping it private later takes the post away.
        $this->checkIn('Casey', ['outcome' => 'achieved', 'shared' => '0']);
        $this->assertTrue(SeasonFinale::newsFor($this->league)->isEmpty());
        $this->assertFalse($this->people['Casey']->highlights()->where('kind', HighlightKind::GoalDay)->exists());

        // Someone outside the league never sees it.
        $this->checkIn('Casey', ['outcome' => 'achieved', 'shared' => '1']);
        $this->actingAs(User::factory()->create())->get(route('leagues.show', $this->league))->assertForbidden();
    }

    public function test_medals_are_awarded_once_from_the_settled_season(): void
    {
        Notification::fake();

        (new LeagueTable($this->league))->settle();
        $count = Medal::count();
        (new LeagueTable($this->league->fresh()))->settle();
        app(MedalAwarder::class)->forLeague($this->league->fresh());

        $this->assertSame($count, Medal::count());

        $medals = fn (string $name) => Medal::where('user_id', $this->people[$name]->id)->get()->mapWithKeys(fn (Medal $medal) => [$medal->type->value => $medal->tier])->all();

        $this->assertSame('gold', $medals('Avery')[MedalType::TableChampion->value]);
        $this->assertSame('gold', $medals('Avery')[MedalType::Podium->value]);
        $this->assertSame('bronze', $medals('Avery')[MedalType::Streak->value]);
        $this->assertArrayHasKey(MedalType::Consistency->value, $medals('Avery'));
        $this->assertSame('silver', $medals('Casey')[MedalType::Podium->value]);
        $this->assertArrayHasKey(MedalType::Comeback->value, $medals('Casey'));
        $this->assertArrayHasKey(MedalType::BestWeek->value, $medals('Casey'));
        $this->assertSame('first', $medals('Blake')[MedalType::SmashedIt->value]);
        $this->assertArrayHasKey(MedalType::BestWeek->value, $medals('Blake'));
        $this->assertArrayNotHasKey(MedalType::TableChampion->value, $medals('Drew'));
    }

    public function test_medals_are_re_evaluated_when_a_dispute_changes_a_week(): void
    {
        app(MedalAwarder::class)->forLeague($this->league);
        $this->assertTrue(Medal::where('user_id', $this->people['Blake']->id)->where('type', MedalType::SmashedIt)->exists());

        // A settled dispute takes Blake's Smashed weeks down to In line.
        $this->league->results()->where('user_id', $this->people['Blake']->id)->where('result', GoalWeekResult::Smashed)
            ->update(['result' => GoalWeekResult::InLine, 'result_points' => GoalWeekResult::InLine->points()]);
        app(MedalAwarder::class)->forLeague($this->league->fresh());

        $this->assertFalse(Medal::where('user_id', $this->people['Blake']->id)->where('type', MedalType::SmashedIt)->exists());
    }

    public function test_medal_rules_come_from_one_config(): void
    {
        config(['medals.streak_tiers' => [2 => 'bronze', 3 => 'silver', 4 => 'gold']]);
        app(MedalAwarder::class)->forLeague($this->league);

        $this->assertSame('gold', Medal::where('user_id', $this->people['Avery']->id)->where('type', MedalType::Streak)->value('tier'));
    }

    public function test_the_finale_shows_every_card_for_an_achieved_goal(): void
    {
        $this->checkIn('Avery', ['actual_result' => '1:58 half', 'helped_most' => 'My league']);
        app(MedalAwarder::class)->forLeague($this->league);

        $this->get(route('finale.show', $this->goals['Avery']))->assertOk()
            ->assertSee('You did it.')
            ->assertSee('Two results')
            ->assertSee('Finishing last while hitting your goal is still a win.')
            ->assertSee('1st')
            ->assertSee('Medals')->assertSee('Table Champion')
            ->assertSee('Share your season')
            ->assertSee("What's next?", false)
            ->assertSee('Step it up')
            ->assertSee('Run it back with the same friends');
    }

    public function test_a_goal_that_fell_short_gets_a_warm_finale_and_a_card_that_never_says_missed(): void
    {
        $this->checkIn('Drew', ['outcome' => 'short', 'shared' => '0']);

        $this->get(route('finale.show', $this->goals['Drew']))->assertOk()
            ->assertSee('You showed up for')->assertSee('Run it back')->assertDontSee('You did it.');

        $card = SeasonCardImage::payload(new SeasonFinale($this->goals['Drew']->fresh()));
        $svg = app(SeasonCardImage::class)->render($card, 'story')['body'];
        $this->assertStringContainsString('Season complete', $svg);
        $this->assertStringContainsString('Get in line with your goals.', $svg);
        $this->assertStringNotContainsStringIgnoringCase('short', $svg);
        $this->assertStringNotContainsStringIgnoringCase('missed', $svg);
    }

    public function test_the_season_card_renders_in_both_sizes_for_its_owner_only(): void
    {
        $this->checkIn('Avery');

        $this->get(route('finale.card', [$this->goals['Avery'], 'feed', 'svg']))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('width="1080" height="1350"', false);
        $this->get(route('finale.card', [$this->goals['Avery'], 'story', 'svg']))->assertOk()->assertSee('width="1080" height="1920"', false);

        $this->actingAs($this->people['Blake'])->get(route('finale.card', [$this->goals['Avery'], 'feed', 'svg']))->assertForbidden();
    }

    public function test_the_public_season_link_is_opt_in_and_revocable(): void
    {
        $this->checkIn('Avery');
        $this->people['Avery']->forceFill(['public_cards' => true])->save();

        $this->post(route('finale.publish', $this->goals['Avery']))->assertRedirect();
        $token = $this->goals['Avery']->fresh()->result->share_token;

        auth()->logout();
        $this->get(route('finale.public', $token))->assertOk()->assertSee('og:title', false)->assertSee('Goal achieved');

        $this->actingAs($this->people['Avery'])->delete(route('finale.revoke', $this->goals['Avery']));
        auth()->logout();
        $this->get(route('finale.public', $token))->assertNotFound();
    }

    public function test_posting_the_season_card_to_highlights_is_never_automatic(): void
    {
        $this->checkIn('Avery', ['shared' => '0']);
        $this->assertFalse($this->people['Avery']->highlights()->where('kind', HighlightKind::SeasonCard)->exists());

        $this->post(route('finale.post', $this->goals['Avery']), ['visibility' => 'league_friends'])->assertRedirect();
        $this->post(route('finale.post', $this->goals['Avery']), ['visibility' => 'league_friends']);

        $this->assertSame(1, $this->people['Avery']->highlights()->where('kind', HighlightKind::SeasonCard)->count());
    }

    public function test_next_goal_ideas_promote_after_an_achieved_goal_and_never_demote(): void
    {
        $this->checkIn('Avery');
        $ideas = app(GoalRecommender::class)->for($this->goals['Avery']->fresh(), $this->goals['Avery']->fresh()->result);

        $this->assertCount(3, $ideas);
        $this->assertSame('harder', $ideas[0]['kind']);
        $this->assertStringStartsWith('Next level:', $ideas[0]['title']);

        $this->checkIn('Drew', ['outcome' => 'short']);
        $this->assertSame('again', app(GoalRecommender::class)->for($this->goals['Drew']->fresh(), $this->goals['Drew']->fresh()->result)[0]['kind']);
    }

    public function test_ai_ideas_are_validated_and_fall_back_to_templates(): void
    {
        $recommender = app(GoalRecommender::class);

        $this->assertNull($recommender->validate([['title' => 'x', 'category' => 'fitness', 'weeks' => 8, 'why' => 'ok']]));
        $this->assertNull($recommender->validate([['title' => 'Run a 10K', 'category' => 'cooking', 'weeks' => 8, 'why' => 'ok']]));
        $this->assertNull($recommender->validate([['title' => 'Run a 10K', 'category' => 'fitness', 'weeks' => 200, 'why' => 'ok']]));
        $this->assertCount(1, $recommender->validate([['title' => 'Run a 10K', 'category' => 'fitness', 'weeks' => 8, 'why' => 'A classic.'], 'nonsense']));

        $this->checkIn('Avery');
        $result = $this->goals['Avery']->fresh()->result;
        $result->forceFill(['recommendations' => [['title' => '', 'category' => 'x']]])->save();

        $this->assertSame('harder', $recommender->for($this->goals['Avery']->fresh(), $result->fresh())[0]['kind']);
    }

    public function test_a_recommended_goal_opens_the_goal_form_filled_in(): void
    {
        $this->actingAs($this->people['Avery'])
            ->get(route('goals.create', ['title' => 'Run a 10K', 'category' => 'fitness', 'target_date' => Carbon::today()->addWeeks(10)->toDateString()]))
            ->assertOk()->assertSee('value="Run a 10K"', false)->assertSee('value="'.Carbon::today()->addWeeks(10)->toDateString().'"', false);

        // A date in the past is ignored.
        $this->get(route('goals.create', ['target_date' => '2020-01-01']))->assertDontSee('value="2020-01-01"', false);
    }

    public function test_run_it_back_with_the_same_friends_invites_last_season_in_the_app(): void
    {
        Notification::fake();

        $this->actingAs($this->people['Avery'])->get(route('leagues.create', ['rematch' => $this->league->id]))
            ->assertOk()->assertSee('Launch Crew · Season 2')->assertSee('data-rematch-note', false);

        $monday = Carbon::today()->next(Carbon::MONDAY)->toDateString();
        $this->post(route('leagues.store'), [
            'name' => 'Launch Crew · Season 2',
            'starts_on' => $monday,
            'ends_on' => Carbon::parse($monday)->addWeeks(6)->toDateString(),
            'goal_id' => $this->goals['Avery']->id,
            'rematch_of' => $this->league->id,
        ])->assertRedirect();

        Notification::assertSentTo([$this->people['Blake'], $this->people['Casey'], $this->people['Drew']], LeagueInvited::class);
        Notification::assertNotSentTo($this->people['Avery'], LeagueInvited::class);
    }

    public function test_the_finale_is_only_for_the_goal_owner(): void
    {
        $this->checkIn('Avery');

        $this->actingAs($this->people['Blake'])->get(route('finale.show', $this->goals['Avery']))->assertForbidden();
        $this->post(route('goal-day.store', $this->goals['Avery']), ['outcome' => 'short'])->assertForbidden();
    }
}
