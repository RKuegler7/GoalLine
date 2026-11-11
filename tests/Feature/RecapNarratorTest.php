<?php

namespace Tests\Feature;

use App\Jobs\NarrateRecap;
use App\Models\Goal;
use App\Models\RecapNarration;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\RecapNarrator;
use App\Services\WeeklyRecap;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class RecapNarratorTest extends TestCase
{
    use RefreshDatabase;

    private const AI_SUMMARY = 'You logged 4 check-ins on 3 of 7 days, and Wednesday carried the load. That steady rhythm earned 1 line point, so keep stacking days.';

    private User $user;

    private RoadmapWeek $week;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anthropic.key' => 'test-key', 'goalline.demo.live_ai' => false]);
        $this->travelTo(Carbon::parse('2026-10-26 12:00', config('app.timezone'))); // Monday: last week is finished.
        $this->user = User::factory()->create();
        $goal = Goal::factory()->for($this->user)->create(['title' => 'Run a 10K', 'target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $this->week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Base', 'milestone' => 'Done.']);
        $this->week->tasks()->create(['position' => 1, 'description' => 'Easy run'])->forceFill(['completed_at' => '2026-10-21 10:00'])->saveQuietly();
        $this->week->forceFill(['milestone_hit_at' => '2026-10-24 10:00'])->save();
        $habit = $goal->habits()->create(['title' => 'Stretch', 'times_per_week' => 3]);
        foreach (['2026-10-20', '2026-10-21', '2026-10-25'] as $day) {
            $habit->logs()->create(['logged_on' => $day]);
        }
    }

    public function test_the_facts_are_the_numbers_code_computed(): void
    {
        $facts = RecapNarrator::facts($this->lastWeek());

        $this->assertSame(4, $facts['check_ins']);
        $this->assertSame(3, $facts['active_days']);
        $this->assertSame('Wednesday', $facts['best_day']);
        $this->assertSame(1, $facts['line_points']);
        $this->assertSame([['goal' => 'Run a 10K', 'result' => 'In line', 'line_points' => 1, 'percent_of_plan' => 100]], $facts['goals']);
        $this->assertStringContainsString('<week>', RecapNarrator::brief($facts));
    }

    public function test_the_template_only_uses_given_numbers_and_passes_its_own_checks(): void
    {
        $facts = RecapNarrator::facts($this->lastWeek());
        $template = RecapNarrator::template($facts);

        $this->assertSame('You logged 4 check-ins on 3 of 7 days, and Wednesday was your strongest day. Your GoalWeeks earned 1 line point. One more day on the line starts today.', $template);
        $this->assertTrue(RecapNarrator::isValid($template, $facts));

        $quiet = [...$facts, 'check_ins' => 0, 'active_days' => 0, 'best_day' => null, 'best_day_check_ins' => null, 'line_points' => 0, 'goals' => []];
        $this->assertTrue(RecapNarrator::isValid(RecapNarrator::template($quiet), $quiet));
    }

    public function test_validation_rejects_invented_numbers_and_bad_shapes(): void
    {
        $facts = RecapNarrator::facts($this->lastWeek());

        $this->assertTrue(RecapNarrator::isValid(self::AI_SUMMARY, $facts));
        $this->assertTrue(RecapNarrator::isValid('Your 10K plan stayed in line at 100% of plan. Keep showing up.', $facts), 'Numbers from the goal title and percent are allowed.');

        $this->assertFalse(RecapNarrator::isValid('You logged 5 check-ins this week. Great work, keep going.', $facts), 'A number code never gave.');
        $this->assertFalse(RecapNarrator::isValid('You logged six check-ins across 3 days. Keep stacking days.', $facts), 'A number word code never gave.');
        $this->assertFalse(RecapNarrator::isValid('You checked in 4 times and that makes 8 points total. Nice.', $facts), 'Arithmetic on the numbers.');
        $this->assertFalse(RecapNarrator::isValid('You logged 4 check-ins on 3 days and kept the line alive all week long.', $facts), 'One sentence.');
        $this->assertFalse(RecapNarrator::isValid('Keep going. You showed up. Nice work. See you Monday.', $facts), 'Four sentences.');
        $this->assertFalse(RecapNarrator::isValid(str_repeat('Keep going strong. ', 30), $facts), 'Too long.');
    }

    public function test_a_finished_week_shows_the_ai_summary_and_caches_it(): void
    {
        $narrator = $this->narratorAnswering(self::AI_SUMMARY, times: 1);

        $this->actingAs($this->user)->get(route('recap.week', ['week' => 'last']))
            ->assertOk()
            ->assertSee(self::AI_SUMMARY)
            ->assertSee('Written by the AI coach from numbers');

        $this->actingAs($this->user)->get(route('recap.week', ['week' => 'last']))->assertSee(self::AI_SUMMARY);

        $saved = RecapNarration::sole();
        $this->assertSame(RecapNarration::AI, $saved->source);
        $this->assertSame('2026-10-19', $saved->week_starts_on->toDateString());
        $narrator->shouldHaveReceived('ask')->once();
    }

    public function test_an_answer_with_invented_numbers_falls_back_to_the_template(): void
    {
        $this->narratorAnswering('You smashed 12 sessions and 9 runs. Incredible week.');

        $this->actingAs($this->user)->get(route('recap.week', ['week' => 'last']))
            ->assertSee('You logged 4 check-ins on 3 of 7 days')
            ->assertSee('A plain summary written by code')
            ->assertDontSee('12 sessions');

        $this->assertSame(RecapNarration::TEMPLATE, RecapNarration::sole()->source);
    }

    public function test_a_failed_call_falls_back_to_the_template_and_is_not_retried_every_visit(): void
    {
        $this->partialMock(RecapNarrator::class, fn (MockInterface $mock) => $mock->shouldAllowMockingProtectedMethods()
            ->shouldReceive('ask')->once()->andThrow(new RuntimeException('Overloaded')));

        $this->actingAs($this->user)->get(route('recap.week', ['week' => 'last']))->assertOk()->assertSee('You logged 4 check-ins');
        $this->actingAs($this->user)->get(route('recap.week', ['week' => 'last']))->assertOk();

        $this->assertSame(RecapNarration::TEMPLATE, RecapNarration::sole()->source);
    }

    public function test_the_summary_is_rewritten_only_when_the_numbers_change(): void
    {
        $narrator = $this->narratorAnswering(self::AI_SUMMARY);

        $this->actingAs($this->user)->get(route('recap.week', ['week' => 'last']));
        $this->week->tasks()->create(['position' => 2, 'description' => 'Long run'])->forceFill(['completed_at' => '2026-10-24 09:00'])->saveQuietly();
        $this->actingAs($this->user)->get(route('recap.week', ['week' => 'last']));
        $this->actingAs($this->user)->get(route('recap.week', ['week' => 'last']));

        $narrator->shouldHaveReceived('ask')->twice();
        $this->assertSame(1, RecapNarration::count());
    }

    public function test_the_live_call_is_queued_and_the_template_shows_meanwhile(): void
    {
        Queue::fake();

        $this->actingAs($this->user)->get(route('recap.week', ['week' => 'last']))
            ->assertSee('You logged 4 check-ins')
            ->assertSee('A plain summary written by code');

        Queue::assertPushed(NarrateRecap::class, fn (NarrateRecap $job) => $job->user->is($this->user) && $job->monday === '2026-10-19');
        $this->assertSame(0, RecapNarration::count());
    }

    public function test_demo_accounts_get_a_saved_sample_and_never_call_the_ai(): void
    {
        $this->user->forceFill(['demo_sandbox' => 'test-sandbox'])->save();
        $this->partialMock(RecapNarrator::class, fn (MockInterface $mock) => $mock->shouldAllowMockingProtectedMethods()->shouldNotReceive('ask'));

        $this->actingAs($this->user)->get(route('recap.week', ['week' => 'last']))
            ->assertSee('Demo coach: 4 check-ins across 3 days')
            ->assertSee('A saved demo sample');

        $saved = RecapNarration::sole();
        $this->assertSame(RecapNarration::SAMPLE, $saved->source);
        $this->assertTrue(RecapNarrator::isValid($saved->body, RecapNarrator::facts($this->lastWeek())));
        $this->assertDatabaseCount('ai_requests', 0);
    }

    public function test_without_an_api_key_the_template_is_saved_without_a_call(): void
    {
        config(['services.anthropic.key' => null]);
        Queue::fake();

        $this->actingAs($this->user)->get(route('recap.week', ['week' => 'last']))->assertSee('A plain summary written by code');

        Queue::assertNothingPushed();
        $this->assertSame(RecapNarration::TEMPLATE, RecapNarration::sole()->source);
    }

    public function test_this_week_has_no_summary_yet(): void
    {
        Queue::fake();

        $this->actingAs($this->user)->get(route('recap.week'))->assertOk()->assertDontSee('Your week in a sentence or three');

        Queue::assertNothingPushed();
    }

    private function lastWeek(): WeeklyRecap
    {
        return new WeeklyRecap($this->user, CarbonImmutable::parse('2026-10-19'));
    }

    private function narratorAnswering(string $answer, ?int $times = null): MockInterface
    {
        return $this->partialMock(RecapNarrator::class, function (MockInterface $mock) use ($answer, $times) {
            $expectation = $mock->shouldAllowMockingProtectedMethods()->shouldReceive('ask')->andReturn($answer);
            if ($times !== null) {
                $expectation->times($times);
            }
        });
    }
}
