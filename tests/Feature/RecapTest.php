<?php

namespace Tests\Feature;

use App\Badge;
use App\GoalWeekResult;
use App\MatchupOutcome;
use App\Models\League;
use App\Models\User;
use App\Notifications\LeagueRecap;
use App\Services\LeagueTable;
use App\Services\SeasonRecap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RecapTest extends TestCase
{
    use RefreshDatabase;

    private League $league;

    /** @var array<string, User> */
    private array $people;

    protected function setUp(): void
    {
        parent::setUp();

        // A four-week season, September 28 to October 25, now over.
        $this->travelTo(Carbon::parse('2026-09-01 09:00', config('app.timezone')));
        $this->league = League::factory()->create(['name' => 'Launch Crew', 'starts_on' => '2026-09-28', 'ends_on' => '2026-10-25']);

        foreach (['Avery', 'Blake', 'Casey', 'Drew'] as $name) {
            $this->people[$name] = User::factory()->create(['name' => $name]);
            $this->league->members()->create(['user_id' => $this->people[$name]->id]);
        }

        $this->travelTo(Carbon::parse('2026-11-02 09:00', config('app.timezone')));

        $I = GoalWeekResult::InLine;
        $P = GoalWeekResult::Past;
        $S = GoalWeekResult::Smashed;
        $X = GoalWeekResult::Short;
        $W = MatchupOutcome::Win;
        $T = MatchupOutcome::Tie;
        $L = MatchupOutcome::Loss;

        // Avery wins every week. Blake smashes early then fades. Casey starts last and finishes second.
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

    public function test_season_awards_go_to_the_champion_mvp_comeback_player_iron_streak_and_smash_machine(): void
    {
        $awards = collect((new SeasonRecap($this->league, new LeagueTable($this->league)))->awards())
            ->mapWithKeys(fn (array $award) => [$award['badge']->value => [$award['member']->user->name, $award['detail']]]);

        $this->assertSame(['Avery', '12 points, 4–0–0'], $awards[Badge::Champion->value]);
        $this->assertSame(['Blake', '6 GoalWeek points'], $awards[Badge::Mvp->value]);
        $this->assertSame(['Casey', 'Climbed from 4th to 2nd'], $awards[Badge::ComebackPlayer->value]);
        $this->assertSame(['Avery', '4 GoalWeeks in line in a row'], $awards[Badge::IronStreak->value]);
        $this->assertSame(['Blake', '2 smashed GoalWeeks'], $awards[Badge::SmashMachine->value]);
    }

    public function test_finishing_the_season_hands_out_awards_once_and_tells_everyone(): void
    {
        Notification::fake();

        (new LeagueTable($this->league))->settle();
        (new LeagueTable($this->league->fresh()))->settle();

        $this->assertSame(5, $this->league->awards()->count());
        $this->assertTrue($this->people['Avery']->badges()->where('badge', Badge::Champion)->where('league_id', $this->league->id)->exists());
        $this->assertStringContainsString('The season is over. GoalLeague Champion: Avery. MVP: Blake.', $this->league->messages()->latest('id')->first()->body);
        Notification::assertSentTo(array_values($this->people), LeagueRecap::class);
        Notification::assertSentToTimes($this->people['Drew'], LeagueRecap::class, 1);
    }

    public function test_the_recap_page_shows_the_podium_awards_and_each_week(): void
    {
        $this->actingAs($this->people['Drew'])
            ->get(route('leagues.recap', $this->league))
            ->assertOk()
            ->assertSee('Season recap')
            ->assertSee('Season awards')
            ->assertSee('Climbed from 4th to 2nd')
            ->assertSee('Final table')
            ->assertSeeInOrder(['Week 4', 'Week 3', 'Week 2', 'Week 1'])
            ->assertSee('Player of the Week');

        $this->actingAs($this->people['Drew'])->get(route('leagues.show', $this->league))->assertSee('The season recap is in');
        $this->actingAs(User::factory()->create())->get(route('leagues.recap', $this->league))->assertForbidden();
    }

    public function test_awards_show_on_the_winners_locker(): void
    {
        $this->actingAs($this->people['Avery'])->get(route('leagues.recap', $this->league));

        $this->actingAs($this->people['Avery'])
            ->get(route('profile.show'))
            ->assertSee('GoalLeague Champion')
            ->assertSee('Launch Crew');
    }

    public function test_each_member_hears_how_their_week_went(): void
    {
        Notification::fake();

        (new SeasonRecap($this->league, new LeagueTable($this->league)))->notifyWeek(4);

        Notification::assertSentTo($this->people['Casey'], LeagueRecap::class, fn (LeagueRecap $recap) => $recap->text === 'Launch Crew week 4 is final: you beat Avery, +4 points. Player of the Week: you!');
        Notification::assertSentTo($this->people['Drew'], LeagueRecap::class, fn (LeagueRecap $recap) => $recap->text === 'Launch Crew week 4 is final: you lost to Avery, +0 points. Player of the Week: Casey.');
    }
}
