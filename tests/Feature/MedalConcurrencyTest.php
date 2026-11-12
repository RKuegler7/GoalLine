<?php

namespace Tests\Feature;

use App\Atomic;
use App\GoalWeekResult;
use App\MatchupOutcome;
use App\Models\League;
use App\Models\Medal;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Services\ErrorAlerts;
use App\Services\LeagueTable;
use App\Services\MedalAwarder;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Medals and the season finale under concurrency: many members open the league at the same moment the season ends.
 * One request finishes it, the rest skip and read the result; awarding is one idempotent insert; deadlocks are retried
 * quietly.
 */
class MedalConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private League $league;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->travelTo(Carbon::parse('2026-09-01 09:00', 'America/New_York'));
        $this->league = League::factory()->create(['starts_on' => '2026-09-28', 'ends_on' => '2026-10-11']);
        $people = User::factory()->count(3)->create();
        foreach ($people as $person) {
            $this->league->members()->create(['user_id' => $person->id]);
        }
        $this->travelTo(Carbon::parse('2026-10-20 09:00', 'America/New_York'));

        foreach ([1, 2] as $week) {
            foreach ($people as $index => $person) {
                $band = [GoalWeekResult::Past, GoalWeekResult::InLine, GoalWeekResult::Short][$index];
                $outcome = [MatchupOutcome::Win, MatchupOutcome::Loss, MatchupOutcome::Loss][$index];
                $this->league->results()->create(['user_id' => $person->id, 'week' => $week, 'result' => $band, 'result_points' => $band->points(), 'opponent_id' => $people[($index + 1) % 3]->id, 'vs_median' => false, 'outcome' => $outcome, 'matchup_points' => $outcome->points()]);
            }
        }
    }

    private function settle(): void
    {
        (new LeagueTable(League::find($this->league->id)))->settle();
    }

    public function test_the_unique_index_on_award_key_exists_in_the_database(): void
    {
        $this->assertTrue(collect(Schema::getIndexes('medals'))->contains(fn (array $index) => $index['unique'] && $index['columns'] === ['award_key']));
    }

    public function test_the_loser_of_a_race_skips_cleanly_and_reads_the_finished_result(): void
    {
        // Another request is finishing the season right now: this one skips without an exception or a duplicate.
        $lock = Cache::lock('exclusive:league-season:'.$this->league->id, 60);
        $this->assertTrue($lock->get());
        $this->settle();
        $this->assertSame(0, Medal::count());
        $this->assertNull($this->league->fresh()->finalized_at);
        $lock->release();

        // The winner finishes; everyone after reads the same medals, and nothing runs twice.
        $this->settle();
        $count = Medal::count();
        $this->assertGreaterThan(0, $count);
        $this->settle();
        $this->settle();
        $this->assertSame($count, Medal::count());
        $this->assertSame(1, $this->league->messages()->where('body', 'like', 'The season is over.%')->count());
    }

    public function test_awarding_twice_or_racing_an_insert_gives_the_same_result(): void
    {
        $awarder = app(MedalAwarder::class);
        $awarder->forLeague($this->league);
        $first = Medal::orderBy('award_key')->get(['award_key', 'tier', 'user_id'])->toArray();

        // As if another request inserted the same medal a moment earlier: the single INSERT skips it.
        Medal::query()->delete();
        $winner = Medal::make(['user_id' => $first[0]['user_id'], 'type' => 'table_champion', 'tier' => 'gold', 'league_id' => $this->league->id, 'award_key' => 'table_champion:L'.$this->league->id.':U'.$first[0]['user_id'], 'season' => 1, 'awarded_at' => now()]);
        $winner->save();

        $awarder->forLeague($this->league->fresh());
        $awarder->forLeague($this->league->fresh());

        $this->assertSame($first, Medal::orderBy('award_key')->get(['award_key', 'tier', 'user_id'])->toArray());
    }

    public function test_lock_timeouts_never_email_but_real_errors_do_once_per_15_minutes(): void
    {
        config(['goalline.admin_emails' => ['admin@example.test']]);
        $alerts = app(ErrorAlerts::class);

        $alerts->report(new LockTimeoutException);
        Notification::assertSentOnDemandTimes(AdminAlert::class, 0);

        $alerts->report(new RuntimeException('Something broke'));
        $alerts->report(new RuntimeException('Something broke'));
        Notification::assertSentOnDemandTimes(AdminAlert::class, 1);

        $this->travel(16)->minutes();
        $alerts->report(new RuntimeException('Something broke'));
        Notification::assertSentOnDemandTimes(AdminAlert::class, 2);
    }

    public function test_a_waiting_request_gives_up_quietly_when_the_work_stays_busy(): void
    {
        // Waiting counts real seconds, so let the clock run.
        $this->travelBack();
        $lock = Cache::lock('exclusive:busy-work', 60);
        $lock->get();

        $this->assertNull(Atomic::exclusively('busy-work', fn () => 'ran', wait: 1));
        $lock->release();
        $this->assertSame('ran', Atomic::exclusively('busy-work', fn () => 'ran'));
    }

    public function test_a_season_where_nobody_scored_is_finished_once(): void
    {
        $this->league->results()->update(['result' => GoalWeekResult::Short, 'result_points' => 0, 'matchup_points' => 0, 'outcome' => MatchupOutcome::Loss]);

        foreach (range(1, 3) as $visit) {
            $this->settle();
        }

        $this->assertNotNull($this->league->fresh()->finalized_at);
        $this->assertSame(1, $this->league->messages()->where('body', 'like', 'The season is over.%')->count());
    }

    public function test_check_medals_reports_and_repairs_a_finished_season_missing_its_medals(): void
    {
        $this->league->forceFill(['finalized_at' => now()])->save();

        $this->artisan('goalline:check-medals')
            ->expectsOutputToContain('Unique index on medals.award_key: yes')
            ->expectsOutputToContain('Finished seasons missing medals: 1')
            ->assertSuccessful();
        $this->assertSame(0, Medal::count());

        $this->artisan('goalline:check-medals', ['--fix' => true])->expectsOutputToContain('awarded medals in 1 seasons')->assertSuccessful();
        $count = Medal::count();
        $this->assertGreaterThan(0, $count);

        $this->artisan('goalline:check-medals', ['--fix' => true])->expectsOutputToContain('Finished seasons missing medals: 0')->assertSuccessful();
        $this->assertSame($count, Medal::count());
    }
}
