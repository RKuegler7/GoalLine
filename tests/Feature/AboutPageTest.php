<?php

namespace Tests\Feature;

use App\GoalWeekResult;
use App\MatchupOutcome;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\Services\ScoringRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_about_page_loads_signed_out_with_every_section(): void
    {
        $response = $this->get(route('about'))->assertOk();

        foreach (['What GoalLine is', 'The goal of GoalLine', 'Why it exists', 'How it was created', 'Why use it', 'How to play', 'Scoring', 'Fair play', 'The AI coach', 'Privacy and safety', 'FAQ', 'Contact and feedback'] as $section) {
            $response->assertSee($section);
        }

        foreach (['what', 'mission', 'why', 'made', 'impact', 'how-to-play', 'scoring', 'fair-play', 'ai', 'privacy', 'faq', 'contact'] as $anchor) {
            $response->assertSee('href="#'.$anchor.'"', false)->assertSee('id="'.$anchor.'"', false);
        }

        $response->assertSee('Get in line with your goals.')
            ->assertSee(route('demo'))
            ->assertSee('Ryan Kuegler')
            ->assertSee('CUA AI Vibe Coding Contest')
            ->assertSee('<meta property="og:title" content="About GoalLine', false)
            ->assertSee('<meta property="og:url" content="'.url('/about').'">', false)
            ->assertSee(route('privacy'));
    }

    public function test_the_scoring_section_shows_the_numbers_the_scoring_code_uses(): void
    {
        $response = $this->get(route('about'))->assertOk();
        $percent = fn (float $share) => (int) round($share * 100);

        $response->assertSee('under '.$percent(GoalWeekResult::IN_LINE_AT).'%')
            ->assertSee($percent(GoalWeekResult::IN_LINE_AT).'% to '.$percent(GoalWeekResult::PAST_ABOVE).'%')
            ->assertSee($percent(GoalWeekResult::SMASHED_AT).'% and up')
            ->assertSee('close call', false)
            ->assertSee('capped at '.($percent(GoalWeekResult::SMASHED_AT) - 1).'%')
            ->assertSee('the milestone ('.RoadmapWeek::MILESTONE_POINTS.')', false);

        foreach (GoalWeekResult::cases() as $result) {
            $this->assertMatchesRegularExpression('/data-band="'.$result->value.'".*?>'.$result->points().'<\/td>/s', $response->getContent());
        }

        foreach ([MatchupOutcome::Win, MatchupOutcome::CloseWin, MatchupOutcome::Tie, MatchupOutcome::Loss] as $outcome) {
            $this->assertMatchesRegularExpression('/data-outcome="'.$outcome->value.'".*?'.$outcome->points().' stars?</s', $response->getContent());
        }

        $max = max(array_map(fn (GoalWeekResult $result) => $result->points(), GoalWeekResult::cases()))
            + max(array_map(fn (MatchupOutcome $outcome) => $outcome->points(), MatchupOutcome::cases()));
        $response->assertSee('0 to '.$max.' stars');
    }

    public function test_changing_a_scoring_rule_changes_the_page(): void
    {
        $this->app->instance(ScoringRules::class, new class extends ScoringRules
        {
            protected function thresholds(): array
            {
                return [...parent::thresholds(), 'in_line' => 0.85];
            }

            protected function linePoints(GoalWeekResult $result): int
            {
                return $result === GoalWeekResult::Smashed ? 4 : parent::linePoints($result);
            }
        });

        $this->get(route('about'))->assertOk()
            ->assertSee('under 85%')
            ->assertSee('85% to 100%')
            ->assertDontSee('under 90%')
            ->assertSee('0 to 6 stars');
    }

    public function test_the_worked_example_is_scored_by_the_real_rules(): void
    {
        $example = app(ScoringRules::class)->example();
        [$avery, $sam] = $example['players'];

        $this->assertSame(GoalWeekResult::score($avery['earned'] / $avery['planned']), $avery['result']);
        $this->assertSame(MatchupOutcome::compare($avery['linePoints'], $sam['linePoints'], $avery['percent'], $sam['percent']), $avery['outcome']);
        $this->assertSame($avery['linePoints'] + $avery['outcome']->points(), $avery['total']);
        $this->assertTrue($example['photoFinish'], 'The example shows a photo finish.');

        $this->get(route('about'))->assertSee('Weekly stars explained')
            ->assertSee($avery['earned'].' &divide; '.$avery['planned'].' = <strong>'.$avery['percent'].'%</strong>', false)
            ->assertSee('Week total: '.$sam['linePoints'].' + '.$sam['matchPoints'].' = '.$sam['total']);
    }

    public function test_about_links_are_on_the_home_login_and_signed_in_pages(): void
    {
        $about = route('about');

        $this->get(route('home'))->assertOk()->assertSee('href="'.$about.'"', false);
        $this->get(route('login'))->assertOk()->assertSee('About GoalLine and how to play')->assertSee('href="'.$about.'"', false);

        $member = User::factory()->create();
        $this->actingAs($member)->get(route('dashboard'))->assertOk()->assertSee('About GoalLine')->assertSee('href="'.$about.'"', false);
        $this->actingAs($member)->get($about)->assertOk()->assertSee('Back to your Clubhouse')->assertSee('Send feedback');

        // Someone mid-sign-up can read it too, without being sent back to onboarding.
        $this->actingAs(User::factory()->newcomer()->create())->get($about)->assertOk();
    }

    public function test_signed_in_feedback_from_the_about_page_is_stored(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->from(route('about'))->post(route('feedback.store'), ['body' => 'Love the scoring card.', 'page' => '/about'])
            ->assertRedirect(route('about'));

        $this->assertDatabaseHas('feedback', ['user_id' => $member->id, 'body' => 'Love the scoring card.', 'page' => '/about']);
        $this->assertSame(1, DB::table('feedback')->count());
    }

    public function test_signed_out_visitors_get_an_email_link(): void
    {
        config(['goalline.contact_email' => 'hello@goalline.app']);

        $this->get(route('about'))->assertSee('mailto:hello@goalline.app', false)->assertDontSee('Send feedback');
    }

    public function test_no_personal_address_is_published_without_a_contact_email(): void
    {
        config(['goalline.contact_email' => null, 'mail.from.address' => 'someone.personal@gmail.com']);

        $this->get(route('about'))->assertOk()
            ->assertDontSee('someone.personal@gmail.com')
            ->assertDontSee('mailto:', false);
    }
}
