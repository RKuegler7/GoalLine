<?php

namespace Tests\Feature;

use App\GoalWeekResult;
use App\MatchupOutcome;
use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_scoring_sheet_numbers_match_the_scoring_code(): void
    {
        $page = $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->getContent();

        // Bands: each row's range starts at the scoring threshold, and its line points are the band's own points.
        $percent = fn (float $share) => (int) round($share * 100).'%';
        $row = fn (string $key) => $this->between($page, 'data-band="'.$key.'"', '</tr>');
        $this->assertStringContainsString('under '.$percent(GoalWeekResult::IN_LINE_AT), $row('short'));
        $this->assertStringContainsString($percent(GoalWeekResult::IN_LINE_AT).' to '.$percent(GoalWeekResult::PAST_ABOVE), $row('in_line'));
        $this->assertStringContainsString('over '.$percent(GoalWeekResult::PAST_ABOVE).', under '.$percent(GoalWeekResult::SMASHED_AT), $row('past'));
        $this->assertStringContainsString($percent(GoalWeekResult::SMASHED_AT).' and up', $row('smashed'));
        foreach (GoalWeekResult::cases() as $band) {
            $this->assertMatchesRegularExpression('/>'.$band->points().'<\/td>\s*$/', trim($row($band->value)), "{$band->value} line points");
        }

        // Match points, and the week's range, from the outcome points themselves.
        // Matchup stars as shown must equal the one star config the scoring code reads.
        $config = config('goalline.stars.matchup');
        foreach ([[MatchupOutcome::Win, 'win'], [MatchupOutcome::CloseWin, 'photo_finish_win'], [MatchupOutcome::Tie, 'tie'], [MatchupOutcome::Loss, 'loss']] as [$outcome, $key]) {
            $this->assertMatchesRegularExpression('/>'.$config[$key].'<\/td>\s*$/', trim($this->between($page, 'data-outcome="'.$outcome->value.'"', '</tr>')), "{$outcome->value} matchup stars");
        }
        foreach (config('goalline.stars.line') as $band => $stars) {
            $this->assertMatchesRegularExpression('/>'.$stars.'<\/td>\s*$/', trim($this->between($page, 'data-band="'.$band.'"', '</tr>')), "{$band} line stars");
        }
        $this->assertStringNotContainsString('data-outcome="close_loss"', $page, 'No "close loss" row.');
        $min = min(array_map(fn ($band) => $band->points(), GoalWeekResult::cases())) + min(array_map(fn ($outcome) => $outcome->points(), MatchupOutcome::cases()));
        $max = max(array_map(fn ($band) => $band->points(), GoalWeekResult::cases())) + max(array_map(fn ($outcome) => $outcome->points(), MatchupOutcome::cases()));
        $this->assertStringContainsString("Line stars plus matchup stars: {$min} to {$max} stars a week.", $page);
    }

    public function test_the_standings_stay_clean_with_one_button_and_one_sheet(): void
    {
        $user = User::factory()->create();
        $league = League::factory()->create(['commissioner_id' => $user->id]);
        $league->members()->create(['user_id' => $user->id]);
        $league->results()->create(['user_id' => $user->id, 'week' => 1, 'result' => 'in_line', 'result_points' => 1, 'outcome' => 'close_win', 'matchup_points' => 1]);

        foreach ([route('leagues.standings', $league), route('leagues.show', $league)] as $url) {
            $page = $this->actingAs($user)->get($url)->assertOk()
                ->assertSee('How is GoalLine scored?')
                ->assertDontSee('Points = line points for your band')
                ->assertDontSee('A week is worth 0 to 6 points: line points')
                ->getContent();
            $this->assertSame(1, substr_count($page, 'id="scoring-sheet"'), 'One sheet, however many buttons.');
        }

        // An example from a real league row.
        $this->actingAs($user)->get(route('leagues.standings', $league))
            ->assertSeeText('Your latest league week, week 1: In line (1 line star) + Photo finish win (1 matchup star) = 2 stars.');
    }

    public function test_the_button_is_on_the_matchup_and_goal_week_screens(): void
    {
        $user = User::factory()->create();
        $league = League::factory()->create(['commissioner_id' => $user->id, 'starts_on' => now()->subWeek()->startOfWeek(), 'ends_on' => now()->addWeeks(6)->endOfWeek()]);
        $league->members()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('leagues.matchups', $league))->assertOk()->assertSee('data-scoring-help', false)->assertDontSee('How a matchup is scored');
        $goal = Goal::factory()->for($user)->create();
        $this->actingAs($user)->get(route('goals.show', $goal))->assertOk()->assertSee('data-scoring-help', false);
    }

    private function between(string $haystack, string $from, string $to): string
    {
        $start = strpos($haystack, $from);
        $this->assertNotFalse($start, "Missing {$from}");

        return substr($haystack, $start, strpos($haystack, $to, $start) - $start);
    }
}
