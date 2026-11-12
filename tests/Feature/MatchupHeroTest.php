<?php

namespace Tests\Feature;

use App\GoalWeekResult;
use App\Models\Goal;
use App\Models\League;
use App\Models\User;
use App\RoadmapStatus;
use App\Rules\CleanName;
use App\Services\MatchupLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MatchupHeroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-22 10:00'); // Thursday of league week 1 (Mon Oct 19 to Sun Oct 25).
    }

    public function test_the_scoreboard_shows_each_players_name_then_record_then_percent(): void
    {
        $league = $this->league(['Avery Lane', 'Sam Park']);
        [$avery, $sam] = $league->members;
        $avery->forceFill(['team_name' => 'Lane Changers'])->save();
        $this->week($avery, 4, 3);
        $this->week($sam, 4, 1);

        $page = $this->actingAs($avery->user)->get(route('leagues.matchups', $league))->assertOk()->getContent();
        $hero = $this->between($page, 'data-pager-hero="0"', 'data-matchup-line');

        $this->assertMatchesRegularExpression('/data-hero-name[^>]*>Lane Changers<.*?data-hero-record[^>]*>0&ndash;0&ndash;0<.*?data-hero-percent>\d+%</s', $hero);
        $this->assertMatchesRegularExpression('/data-hero-name[^>]*>Sam Park<.*?data-hero-record[^>]*>0&ndash;0&ndash;0<.*?data-hero-percent>\d+%</s', $hero);
        $this->assertStringContainsString('if it ended now', $hero);
        $this->assertStringContainsString('WK 1', $hero);
        $this->assertStringContainsString('Ends Sunday 11:59 p.m.', $page);
        // No photos: initials, on the light tone so they show on the green field.
        $this->assertStringContainsString('bg-ivory text-club', $hero);
        $this->assertStringContainsString('>AL<', str_replace(["\n", ' '], '', $hero));
    }

    public function test_long_names_are_kept_whole_for_screen_readers_and_clamped_on_screen(): void
    {
        $league = $this->league(['Maximiliana Alexandrova-Whitfield', 'Bo']);

        $this->actingAs($league->members[1]->user)->get(route('leagues.matchups', $league))
            ->assertOk()
            ->assertSee('title="Maximiliana Alexandrova-Whitfield"', false)
            ->assertSee('line-clamp-2', false);
    }

    public function test_bye_and_solo_pages_have_their_own_scoreboards(): void
    {
        $bye = $this->league(['Ann One', 'Bea Two', 'Cy Three']);
        $page = $this->actingAs($bye->commissioner)->get(route('leagues.matchups', $bye))->assertOk()->getContent();
        $this->assertStringContainsString('Line stars only', $page);
        $this->assertStringContainsString('Bye week', $page);

        $solo = $this->league(['Dee Solo']);
        $this->actingAs($solo->commissioner)->get(route('leagues.matchups', $solo))->assertOk()
            ->assertSee('The line')
            ->assertSee('Solo season');
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function boundaries(): array
    {
        return [
            'just short of In line' => [89, 90],
            'In line to the plan' => [99, 100],
            'the plan to Past' => [100, 101],
            'Past to Smashed' => [149, 150],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_pucks_sit_on_the_right_side_of_each_threshold(int $below, int $at): void
    {
        $this->assertLessThan(MatchupLine::position($at), MatchupLine::position($below));
        $this->assertGreaterThan(0, MatchupLine::position($below));
    }

    public function test_the_thresholds_and_track_ends_come_from_the_scoring_code(): void
    {
        $markers = collect(MatchupLine::markers())->keyBy('key');

        $this->assertSame((int) round(GoalWeekResult::IN_LINE_AT * 100), $markers['in_line']['percent']);
        $this->assertSame(100, $markers['plan']['percent']);
        $this->assertSame((int) round(GoalWeekResult::SMASHED_AT * 100), $markers['smashed']['percent']);
        $this->assertSame(MatchupLine::position(90), $markers['in_line']['position']);

        $this->assertSame(0.0, MatchupLine::position(null));
        $this->assertSame(0.0, MatchupLine::position(-5));
        $this->assertSame(100.0, MatchupLine::position(MatchupLine::max()));
        $this->assertSame(100.0, MatchupLine::position(240), 'Far past Smashed still sits at the end of the track.');
        $this->assertTrue(MatchupLine::isOffTheEnd(240));
        $this->assertLessThan(100.0, MatchupLine::position(160), 'A Smashed puck still has room past the Smashed line.');
        $this->assertEqualsWithDelta(100.0, collect(MatchupLine::zones())->sum(fn ($zone) => $zone['to'] - $zone['from']), 0.01);
    }

    public function test_the_line_has_a_text_alternative_for_each_player(): void
    {
        $this->assertSame(
            'Ryan is at 82%, Short of the line; needs 2 more points to reach In line.',
            MatchupLine::describe('Ryan', ['percent' => 82, 'result' => GoalWeekResult::Short, 'next' => ['band' => GoalWeekResult::InLine, 'points' => 2]]),
        );
        $this->assertSame('Ryan is at 160%, Smashed the line.', MatchupLine::describe('Ryan', ['percent' => 160, 'result' => GoalWeekResult::Smashed, 'next' => null]));

        $league = $this->league(['Avery Lane', 'Sam Park']);
        $this->week($league->members[0], 4, 1);
        $this->week($league->members[1], 4, 4);

        $this->actingAs($league->members[0]->user)->get(route('leagues.matchups', $league))
            ->assertSee('data-line-text', false)
            ->assertSeeText('Avery is at')
            ->assertSee('data-line-puck="0-0"', false)
            ->assertSee('aria-controls="line-detail-0-0"', false);
    }

    public function test_members_set_a_friendly_team_name_of_up_to_24_characters(): void
    {
        $league = $this->league(['Avery Lane', 'Sam Park']);
        $avery = $league->members[0];

        $this->actingAs($avery->user)->put(route('leagues.team-name.update', $league), ['team_name' => '  Lane Changers '])
            ->assertRedirect(route('leagues.show', $league));
        $this->assertSame('Lane Changers', $avery->fresh()->team_name);
        $this->actingAs($avery->user)->get(route('leagues.show', $league))->assertSee('Lane Changers');

        $this->actingAs($avery->user)->put(route('leagues.team-name.update', $league), ['team_name' => str_repeat('a', 25)])
            ->assertSessionHasErrorsIn('teamName', ['team_name' => 'Team names can be up to 24 characters.']);
        $this->actingAs($avery->user)->put(route('leagues.team-name.update', $league), ['team_name' => 'Sh1t Kickers'])
            ->assertSessionHasErrorsIn('teamName', 'team_name');
        $this->assertSame('Lane Changers', $avery->fresh()->team_name);

        $this->actingAs($avery->user)->put(route('leagues.team-name.update', $league), ['team_name' => '']);
        $this->assertNull($avery->fresh()->team_name);

        $this->actingAs(User::factory()->create())->put(route('leagues.team-name.update', $league), ['team_name' => 'Crashers'])->assertForbidden();
    }

    public function test_the_name_check_blocks_profanity_but_not_innocent_words(): void
    {
        foreach (['F.U.C.K Mondays', 'sh!t happens', 'B1tch please', 'Big Ass Squad'] as $bad) {
            $this->assertTrue(CleanName::isOffensive($bad), $bad);
        }

        foreach (['Scunthorpe Runners', 'Class Act', 'Dickens Readers', 'Peacock Pace', 'Grape Escape', 'Hellen Keller Fans'] as $fine) {
            $this->assertFalse(CleanName::isOffensive($fine), $fine);
        }
    }

    /**
     * @param  list<string>  $names
     */
    private function league(array $names): League
    {
        $league = League::factory()->create(['starts_on' => '2026-10-19', 'ends_on' => '2026-11-15']);

        foreach ($names as $index => $name) {
            $user = $index === 0 ? $league->commissioner : User::factory()->create();
            $user->forceFill(['name' => $name])->save();
            $goal = Goal::factory()->for($user)->create(['target_date' => '2027-01-31']);
            $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
            $league->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);
        }

        return $league->load('members.user');
    }

    private function week($member, int $tasks, int $done): void
    {
        $week = $member->goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Focus', 'milestone' => 'Milestone']);

        foreach (range(1, $tasks) as $index) {
            $week->tasks()->create(['position' => $index, 'description' => "Task {$index}", 'planned_on' => '2026-10-2'.$index])
                ->forceFill(['completed_at' => $index <= $done ? now() : null])->save();
        }
    }

    private function between(string $haystack, string $from, string $to): string
    {
        $start = strpos($haystack, $from);
        $this->assertNotFalse($start, "Missing {$from}");

        return substr($haystack, $start, strpos($haystack, $to, $start) - $start);
    }
}
