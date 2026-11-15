<?php

namespace Tests\Feature;

use App\Lent;
use App\Models\Challenge;
use App\Models\ChallengeCheckIn;
use App\Models\User;
use App\Services\ChallengeBuilder;
use App\Services\ChallengeProgress;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Challenge completion rules (ChallengeProgress), Lent dates, and the builder's durations.
 */
class ChallengeRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-14 10:00', 'America/New_York'));
        $this->user = User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function challenge(array $attributes): Challenge
    {
        return Challenge::factory()->for($this->user, 'creator')->create($attributes + ['starts_on' => '2026-11-02', 'ends_on' => '2026-11-08']);
    }

    /**
     * @param  array<string, array<string, mixed>>  $days  date => check-in attributes
     * @return array<string, mixed>
     */
    private function progress(Challenge $challenge, array $days, string $today = '2026-11-14'): array
    {
        foreach ($days as $date => $attributes) {
            $challenge->checkIns()->create(['user_id' => $this->user->id, 'checked_on' => $date] + $attributes);
        }

        return ChallengeProgress::for($challenge->fresh(), $challenge->checkIns()->get(), CarbonImmutable::parse($today));
    }

    private function statuses(array $progress): string
    {
        return implode(' ', array_map(fn ($period) => $period['forgiven'] ? 'forgiven' : $period['status'], $progress['periods']));
    }

    public function test_lent_dates_come_from_easter_for_any_year(): void
    {
        foreach ([
            2025 => ['2025-04-20', '2025-03-05', '2025-04-19'],
            2026 => ['2026-04-05', '2026-02-18', '2026-04-04'],
            2027 => ['2027-03-28', '2027-02-10', '2027-03-27'],
            2028 => ['2028-04-16', '2028-03-01', '2028-04-15'],
            2029 => ['2029-04-01', '2029-02-14', '2029-03-31'],
            2030 => ['2030-04-21', '2030-03-06', '2030-04-20'],
            2038 => ['2038-04-25', '2038-03-10', '2038-04-24'],
        ] as $year => [$easter, $ash, $holySaturday]) {
            $this->assertSame($easter, Lent::easter($year)->toDateString(), "Easter {$year}");
            $this->assertSame($ash, Lent::ashWednesday($year)->toDateString(), "Ash Wednesday {$year}");
            $this->assertSame($holySaturday, Lent::holySaturday($year)->toDateString(), "Holy Saturday {$year}");
            $this->assertTrue(Lent::ashWednesday($year)->isWednesday());
        }

        $this->assertSame(2027, Lent::yearFor(CarbonImmutable::parse('2026-11-14')), 'After this year\'s Lent, the next one.');
        $this->assertSame(2027, Lent::yearFor(CarbonImmutable::parse('2027-01-05')));
    }

    public function test_daily_counts_a_day_only_when_every_rule_is_checked(): void
    {
        $challenge = $this->challenge(['type' => 'daily', 'rules' => ['items' => ['Pray', 'Read', 'Walk']]]);
        $progress = $this->progress($challenge, [
            '2026-11-02' => ['rules_done' => [0, 1, 2]],
            '2026-11-03' => ['rules_done' => [0, 2]],
        ]);

        $this->assertSame('done missed missed missed missed missed missed', $this->statuses($progress));
        $this->assertSame([1, 7, 14], [$progress['completed'], $progress['total'], $progress['percent']]);
    }

    public function test_weekly_counts_x_times_or_every_chosen_day(): void
    {
        // Two 7-day blocks: Nov 2-8 and Nov 9-15 (today, Nov 14, is in the second).
        $times = $this->challenge(['type' => 'weekly', 'rules' => ['times' => 3], 'ends_on' => '2026-11-15']);
        $progress = $this->progress($times, ['2026-11-02' => [], '2026-11-04' => [], '2026-11-07' => [], '2026-11-10' => []]);
        $this->assertSame('done open', $this->statuses($progress));
        $this->assertSame(['count' => 1, 'needed' => 3, 'status' => 'open'], $progress['thisPeriod']);
        $this->assertSame('week', $progress['unit']);

        $days = $this->challenge(['type' => 'weekly', 'rules' => ['days' => [1, 3, 5]], 'ends_on' => '2026-11-08']);
        // Monday and Friday, but not Wednesday: the week isn't met.
        $this->assertSame('missed', $this->statuses($this->progress($days, ['2026-11-02' => [], '2026-11-06' => [], '2026-11-07' => []])));

        $short = $this->challenge(['type' => 'weekly', 'rules' => ['times' => 4], 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-10']);
        // The last block is 2 days long, so its target is capped at 2.
        $this->assertSame([7, 2], array_column($this->progress($short, [])['periods'], 'needed') === [4, 2] ? [7, 2] : [0, 0]);
    }

    public function test_abstain_counts_on_track_days_and_slips_are_kind_but_count_as_misses(): void
    {
        $challenge = $this->challenge(['type' => 'abstain', 'rules' => ['label' => 'No sweets'], 'allowed_misses' => 1]);
        $progress = $this->progress($challenge, [
            '2026-11-02' => [],
            '2026-11-03' => ['status' => ChallengeCheckIn::SLIPPED],
            '2026-11-04' => [],
            '2026-11-05' => ['status' => ChallengeCheckIn::SLIPPED],
        ]);

        $this->assertStringStartsWith('done forgiven done slipped', $this->statuses($progress), 'The first slip is forgiven (1 allowed).');
        $this->assertSame(2, $progress['completed']);
        $this->assertSame(1, $progress['missesUsed']);
        $this->assertSame(['done', 'slipped', 'missed', 'missed', 'missed'], array_column($progress['last5'], 'status'), 'The last five days, oldest first.');
    }

    public function test_target_needs_the_number_and_a_weekly_target_adds_up(): void
    {
        $daily = $this->challenge(['type' => 'target', 'rules' => ['threshold' => 10000, 'unit' => 'steps', 'per' => 'day', 'max' => 100000]]);
        $progress = $this->progress($daily, ['2026-11-02' => ['value' => 10000], '2026-11-03' => ['value' => 9999.5]]);
        $this->assertStringStartsWith('done missed', $this->statuses($progress));

        $weekly = $this->challenge(['type' => 'target', 'rules' => ['threshold' => 100, 'unit' => 'pages', 'per' => 'week', 'max' => 500]]);
        $progress = $this->progress($weekly, ['2026-11-02' => ['value' => 40], '2026-11-05' => ['value' => 60]]);
        $this->assertSame('done', $this->statuses($progress));
        $this->assertSame(100, $progress['periods'][0]['count']);
    }

    public function test_strict_restarts_and_flexible_forgives_up_to_the_allowance(): void
    {
        $done = ['2026-11-02' => [], '2026-11-03' => [], '2026-11-05' => [], '2026-11-06' => [], '2026-11-07' => [], '2026-11-08' => []];

        $strict = $this->progress($this->challenge(['mode' => 'strict']), $done);
        $this->assertSame([4, 2, 1], [$strict['streak'], $strict['bestStreak'] === 4 ? 2 : 0, $strict['restarts']], 'A miss on the 4th restarts the streak.');
        $this->assertFalse($strict['finished']);

        $flexible = $this->progress($this->challenge(['mode' => 'flexible', 'allowed_misses' => 1]), $done);
        $this->assertSame([6, 6, 0, 1], [$flexible['streak'], $flexible['bestStreak'], $flexible['restarts'], $flexible['missesUsed']], 'The miss is forgiven: no break.');
        $this->assertTrue($flexible['finished'], '6 of 7 with 1 miss allowed.');

        $tooMany = $this->progress($this->challenge(['mode' => 'flexible', 'allowed_misses' => 0]), $done);
        $this->assertSame(4, $tooMany['streak']);
    }

    public function test_sundays_off_are_rest_days_that_never_break_a_streak(): void
    {
        // Nov 2-9: Monday to Monday, with Sunday the 8th off.
        $challenge = $this->challenge(['sundays_off' => true, 'ends_on' => '2026-11-09']);
        $days = collect(['02', '03', '04', '05', '06', '07', '09'])->mapWithKeys(fn ($day) => ["2026-11-{$day}" => []])->all();
        $progress = $this->progress($challenge, $days);

        $this->assertSame('done done done done done done rest done', $this->statuses($progress));
        $this->assertSame([7, 7, 100, 7], [$progress['completed'], $progress['total'], $progress['percent'], $progress['streak']]);
    }

    public function test_today_and_yesterday_stay_open_and_never_break_a_streak(): void
    {
        $challenge = $this->challenge(['starts_on' => '2026-11-10', 'ends_on' => '2026-11-20']);
        $progress = $this->progress($challenge, ['2026-11-10' => [], '2026-11-11' => [], '2026-11-12' => []]);

        $this->assertSame('done done done open open upcoming', implode(' ', array_slice(explode(' ', $this->statuses($progress)), 0, 6)));
        $this->assertSame(3, $progress['streak']);
    }

    public function test_voided_check_ins_do_not_count(): void
    {
        $progress = $this->progress($this->challenge([]), ['2026-11-02' => ['status' => ChallengeCheckIn::VOIDED]]);

        $this->assertSame(0, $progress['completed']);
    }

    public function test_durations_calendar_months_leap_years_lent_and_custom_dates(): void
    {
        $builder = new ChallengeBuilder;
        $today = CarbonImmutable::parse('2026-11-14');
        $range = fn (array $input) => array_map(fn ($date) => $date->toDateString(), $builder->dates($input, $today));

        $this->assertSame(['2026-11-16', '2026-11-22'], $range(['duration' => '7', 'starts_on' => '2026-11-16']));
        $this->assertSame(['2026-11-16', '2027-11-15'], $range(['duration' => '365', 'starts_on' => '2026-11-16']));
        $this->assertSame(['2026-11-16', '2027-01-29'], $range(['duration' => '75', 'starts_on' => '2026-11-16']));
        $this->assertSame(['2027-02-01', '2027-02-28'], $range(['duration' => 'month', 'month' => '2027-02']));
        $leapYear = array_map(fn ($date) => $date->toDateString(), $builder->dates(['duration' => 'month', 'month' => '2028-02'], CarbonImmutable::parse('2027-11-14')));
        $this->assertSame(['2028-02-01', '2028-02-29'], $leapYear, 'Leap year.');
        $this->assertSame(['2026-12-01', '2026-12-31'], $range(['duration' => 'month', 'month' => '2026-12']));
        $this->assertSame(['2027-02-10', '2027-03-27'], $range(['duration' => 'lent', 'lent_year' => 2027]));
        $this->assertSame(['2026-11-20', '2027-12-24'], $range(['duration' => 'custom', 'starts_on' => '2026-11-20', 'ends_on' => '2027-12-24']), 'Exactly 400 days.');

        foreach ([
            ['duration' => 'custom', 'starts_on' => '2026-11-20', 'ends_on' => '2027-12-25'],
            ['duration' => 'custom', 'starts_on' => '2026-11-20', 'ends_on' => '2026-11-19'],
            ['duration' => '30', 'starts_on' => '2026-11-13'],
            ['duration' => 'month', 'month' => '2026-10'],
            ['duration' => 'lent', 'lent_year' => 2026],
        ] as $input) {
            try {
                $builder->dates($input, $today);
                $this->fail('Expected a validation error for '.json_encode($input));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_default_start_is_the_next_monday_and_kickoff_follows_new_york_time(): void
    {
        $this->assertSame('2026-11-16', ChallengeBuilder::defaultStart()->toDateString());

        // 11:30 p.m. Sunday in New York is already Monday in UTC: still Sunday for Challenges.
        $this->travelTo(Carbon::parse('2026-11-15 23:30', 'America/New_York'));
        $this->assertSame('2026-11-15', Challenge::today()->toDateString());
        $challenge = $this->challenge(['starts_on' => '2026-11-16', 'ends_on' => '2026-11-22']);
        $this->assertFalse($challenge->hasStarted());

        $this->travelTo(Carbon::parse('2026-11-16 00:00', 'America/New_York'));
        $this->assertTrue($challenge->fresh()->hasStarted(), 'Kickoff at 12:00 a.m. New York time.');

        // Daylight saving ends Sunday, Nov 1, 2026: midnight on Nov 2 is still the start of Nov 2.
        $this->travelTo(Carbon::parse('2026-11-02 00:00', 'America/New_York'));
        $this->assertSame('2026-11-02', Challenge::today()->toDateString());
        $this->travelTo(Carbon::parse('2026-03-08 00:30', 'America/New_York')); // The night daylight saving starts.
        $this->assertSame('2026-03-08', Challenge::today()->toDateString());
    }
}
