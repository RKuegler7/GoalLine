<?php

namespace Tests\Feature;

use App\Exceptions\RoadmapPlanningException;
use App\GoalCategory;
use App\Jobs\GenerateIntakeQuestions;
use App\Jobs\GenerateRoadmap;
use App\Models\Goal;
use App\Models\GoalIntake;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\IntakeQuestions;
use App\Services\RoadmapPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class GoalIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_the_coachs_questions_are_cleaned_capped_and_given_safe_ids(): void
    {
        $question = fn (array $overrides = []) => ['id' => 'weekly_mileage', 'text' => 'How many miles a week do you run now?', 'type' => 'number', 'options' => [], 'unit' => 'miles', 'why' => '', ...$overrides];

        $questions = IntakeQuestions::validated(['questions' => [
            $question(),
            $question(['id' => 'weekly_mileage', 'text' => '<b>Longest</b>   recent run?']), // Duplicate id, markup.
            $question(['type' => 'essay']), // Unknown type: dropped.
            $question(['type' => 'single_choice', 'options' => ['Only one']]), // Too few choices: dropped.
            $question(['text' => '   ']), // Empty: dropped.
            $question(['id' => '', 'type' => 'single_choice', 'options' => ['3', '4', '4', '5'], 'unit' => 'ignored']),
            $question(['type' => 'scale', 'text' => str_repeat('a', 400)]),
            ...array_fill(0, 10, $question(['type' => 'short_text', 'id' => 'extra'])),
        ]], physical: false);

        $this->assertCount(IntakeQuestions::MAX_QUESTIONS, $questions);
        $this->assertSame('weekly_mileage', $questions[0]['id']);
        $this->assertSame('miles', $questions[0]['unit']);
        $this->assertSame('Longest recent run?', $questions[1]['text']);
        $this->assertSame(['3', '4', '5'], $questions[2]['options']);
        $this->assertNull($questions[2]['unit']);
        $this->assertSame(200, mb_strlen($questions[3]['text']));
        $this->assertSame(count($questions), count(array_unique(array_column($questions, 'id'))));
        $this->assertNull($questions[0]['why']);
    }

    public function test_too_few_usable_questions_are_rejected(): void
    {
        $this->expectException(RoadmapPlanningException::class);

        IntakeQuestions::validated(['questions' => [['id' => 'a', 'text' => 'Only one?', 'type' => 'short_text', 'options' => [], 'unit' => '', 'why' => '']]], physical: false);
    }

    public function test_physical_goals_always_get_a_health_question(): void
    {
        $five = array_fill(0, 8, ['id' => 'q', 'text' => 'How many days a week can you run?', 'type' => 'short_text', 'options' => [], 'unit' => '', 'why' => '']);

        $questions = IntakeQuestions::validated(['questions' => $five], physical: true);

        $this->assertCount(8, $questions);
        $this->assertTrue(IntakeQuestions::asksAboutHealth($questions));
        $this->assertTrue(IntakeQuestions::asksAboutHealth(IntakeQuestions::fallback(GoalCategory::Personal, physical: true)));
        $this->assertFalse(IntakeQuestions::asksAboutHealth(IntakeQuestions::fallback(GoalCategory::Academic, physical: false)));

        foreach (GoalCategory::cases() as $category) {
            $this->assertGreaterThanOrEqual(IntakeQuestions::MIN_QUESTIONS, count(IntakeQuestions::fallback($category, physical: false)));
        }
    }

    public function test_setting_a_goal_asks_the_coach_for_questions_when_it_can(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('goals.store'), ['title' => 'Run the Marine Corps Marathon', 'category' => 'fitness', 'target_date' => today()->addMonths(5)->toDateString()]);

        $intake = $user->goals()->sole()->intake;
        $this->assertSame(GoalIntake::GENERATING, $intake->status);
        Queue::assertPushed(GenerateIntakeQuestions::class);
        $this->actingAs($user)->get(route('goals.intake', $intake->goal))->assertOk()->assertSee('Writing your questions')->assertSee('Skip the questions');
    }

    public function test_the_coachs_questions_are_saved(): void
    {
        $intake = GoalIntake::factory()->generating()->create();
        $this->mock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('intakeQuestions')->andReturn(IntakeQuestions::fallback(GoalCategory::Creative, false)));

        (new GenerateIntakeQuestions($intake))->handle(app(RoadmapPlanner::class));

        $intake->refresh();
        $this->assertSame(GoalIntake::READY, $intake->status);
        $this->assertSame(GoalIntake::SOURCE_AI, $intake->source);
    }

    public function test_when_the_coach_fails_the_built_in_questions_take_over(): void
    {
        $intake = GoalIntake::factory()->generating()->for(Goal::factory()->state(['category' => GoalCategory::Academic]))->create();
        $this->mock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('intakeQuestions')->andThrow(new RoadmapPlanningException('Timed out.')));

        (new GenerateIntakeQuestions($intake))->handle(app(RoadmapPlanner::class));

        $intake->refresh();
        $this->assertSame(GoalIntake::SOURCE_FALLBACK, $intake->source);
        $this->assertEquals(IntakeQuestions::fallback(GoalCategory::Academic, false), $intake->questions);
        $this->actingAs($intake->goal->user)->get(route('goals.intake', $intake->goal))->assertOk()->assertSee('When do you study best?')->assertDontSee('Timed out');
    }

    public function test_without_the_coach_or_after_a_short_wait_the_built_in_questions_are_used(): void
    {
        config(['services.anthropic.key' => null]);
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('goals.store'), ['title' => 'Pass the bar', 'category' => 'academic', 'target_date' => today()->addMonths(5)->toDateString()]);
        $this->assertSame(GoalIntake::SOURCE_FALLBACK, $user->goals()->sole()->intake->source);
        Queue::assertNotPushed(GenerateIntakeQuestions::class);

        $slow = GoalIntake::factory()->generating()->create();
        $this->travel(GoalIntake::WAIT_SECONDS + 1)->seconds();
        $this->actingAs($slow->goal->user)->get(route('goals.intake', $slow->goal))->assertOk()->assertSee('Build my plan');
        $this->assertSame(GoalIntake::SOURCE_FALLBACK, $slow->fresh()->source);

        // The coach's late answer doesn't replace questions the person is already looking at.
        $slow->fresh()->useQuestions([['id' => 'late']], GoalIntake::SOURCE_AI);
        $this->assertSame(GoalIntake::SOURCE_FALLBACK, $slow->fresh()->source);
    }

    public function test_answers_are_saved_with_not_sure_and_skips_then_the_plan_is_built(): void
    {
        $intake = GoalIntake::factory()->for(Goal::factory()->state(['category' => GoalCategory::Fitness]))->create();
        $goal = $intake->goal;

        $this->actingAs($goal->user)->get(route('goals.intake', ['goal' => $goal, 'from' => 'new']))
            ->assertOk()->assertSee('A few questions first')->assertSee("I'm not sure", false)->assertSee('Skip all and build my plan')
            ->assertSee('not medical advice');

        $this->actingAs($goal->user)->post(route('goals.intake.store', $goal), ['from' => 'new', 'answers' => [
            'experience' => ['value' => 'Some experience'],
            'starting_point' => ['value' => '  15 miles a week, <i>longest</i> 8  '],
            'days' => ['value' => '4', 'status' => IntakeQuestions::NOT_SURE],
            'health' => ['value' => ''],
            'access' => ['value' => ['A pool', 'A track or trails']],
            'confidence' => ['value' => '4'],
        ]])->assertRedirect(route('goals.next', $goal));

        $answers = collect($intake->fresh()->answers)->keyBy('id');
        $this->assertSame(GoalIntake::ANSWERED, $intake->fresh()->status);
        $this->assertSame('15 miles a week, longest 8', $answers['starting_point']['answer']);
        $this->assertSame(IntakeQuestions::NOT_SURE, $answers['days']['status']);
        $this->assertNull($answers['days']['answer']);
        $this->assertSame(IntakeQuestions::SKIPPED, $answers['health']['status']);
        $this->assertSame('A track or trails, A pool', $answers['access']['answer']);
        $this->assertSame('4 of 5', $answers['confidence']['answer']);

        $this->assertSame(RoadmapStatus::Pending, $goal->fresh()->roadmap_status);
        Queue::assertPushed(GenerateRoadmap::class, fn (GenerateRoadmap $job) => $job->goal->is($goal) && $job->fromPosition === null);
    }

    public function test_answers_that_dont_fit_the_question_are_turned_back(): void
    {
        $goal = GoalIntake::factory()->create()->goal;

        $this->actingAs($goal->user)->post(route('goals.intake.store', $goal), ['answers' => [
            'experience' => ['value' => 'Olympian'],
            'confidence' => ['value' => '9'],
            'access' => ['value' => ['A spaceship']],
        ]])->assertSessionHasErrors(['answers.experience.value', 'answers.confidence.value', 'answers.access.value.0']);

        $this->assertTrue($goal->intake->fresh()->isOpen());
    }

    public function test_skipping_builds_the_plan_from_the_goal_alone(): void
    {
        $goal = GoalIntake::factory()->create()->goal;

        $this->actingAs($goal->user)->post(route('goals.intake.store', $goal), ['skip' => 1])->assertRedirect(route('goals.show', $goal));

        $this->assertSame(GoalIntake::SKIPPED, $goal->intake->fresh()->status);
        Queue::assertPushed(GenerateRoadmap::class);
    }

    public function test_building_the_roadmap_directly_skips_open_questions(): void
    {
        $goal = GoalIntake::factory()->create()->goal;

        $this->actingAs($goal->user)->get(route('goals.show', $goal))->assertSee('A few questions first');
        $this->actingAs($goal->user)->post(route('goals.roadmap.store', $goal));

        $this->assertSame(GoalIntake::SKIPPED, $goal->intake->fresh()->status);
        Queue::assertPushed(GenerateRoadmap::class);
    }

    public function test_answers_are_passed_to_the_goal_decomposer(): void
    {
        $intake = GoalIntake::factory()->create();
        $intake->answer(IntakeQuestions::answers($intake->questions, [
            'starting_point' => ['value' => '15 miles a week'],
            'health' => ['value' => 'Sore left knee last month'],
            'days' => ['status' => IntakeQuestions::NOT_SURE],
        ]));
        $goal = $intake->goal->fresh();
        $planner = $this->partialMock(RoadmapPlanner::class);

        $brief = $planner->planBrief($goal, $planner->periods($goal));

        $this->assertStringContainsString('<intake>', $brief);
        $this->assertStringContainsString('Where are you starting from? For example, your weekly mileage and longest recent run, or your current max. 15 miles a week', $brief);
        $this->assertStringContainsString('Sore left knee last month', $brief);
        $this->assertStringContainsString('How many days a week can you train? (not sure)', $brief);
    }

    public function test_the_goal_page_shows_what_the_plan_is_built_from_to_its_owner_only(): void
    {
        $intake = GoalIntake::factory()->for(Goal::factory()->state(['category' => GoalCategory::Fitness, 'title' => 'Run a half marathon']))->create();
        $intake->answer(IntakeQuestions::answers($intake->questions, ['health' => ['value' => 'Sore left knee']]));
        $goal = $intake->goal;
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();

        $this->actingAs($goal->user)->get(route('goals.show', $goal))
            ->assertSee('Your plan is built from')->assertSee('Sore left knee')->assertSee('Edit answers')
            ->assertSee('GoalLine plans are general guidance, not medical advice. Check with a doctor if you have health concerns.');

        $this->actingAs(User::factory()->create())->get(route('goals.intake', $goal))->assertForbidden();
    }

    public function test_editing_answers_replans_only_the_goalweeks_that_havent_started(): void
    {
        $this->travelTo('2026-11-04'); // A Wednesday.
        $goal = Goal::factory()->create(['target_date' => today()->addDays(20)]);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        foreach ([1 => '2026-11-02', 2 => '2026-11-09', 3 => '2026-11-16'] as $position => $monday) {
            $goal->weeks()->create(['position' => $position, 'starts_on' => $monday, 'ends_on' => now()->parse($monday)->addDays(6), 'focus' => 'Week', 'milestone' => 'Done']);
        }
        $intake = GoalIntake::factory()->for($goal)->create();
        $intake->answer([]);

        $this->actingAs($goal->user)->get(route('goals.intake', $goal))->assertSee('Update your answers')->assertSee('Save and re-plan')->assertDontSee('Skip all');
        $this->actingAs($goal->user)->post(route('goals.intake.store', $goal), ['answers' => ['days' => ['value' => '3']]])
            ->assertRedirect(route('goals.show', $goal))
            ->assertSessionHas('status', 'Answers saved. Your upcoming GoalWeeks are being re-planned around them. This week stays as it is.');

        $this->assertSame('3', collect($intake->fresh()->answers)->firstWhere('id', 'days')['answer']);
        Queue::assertPushed(GenerateRoadmap::class, fn (GenerateRoadmap $job) => $job->fromPosition === 2);
    }
}
