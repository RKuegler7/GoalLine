<?php

namespace Tests\Feature;

use App\Models\User;
use App\ShareLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_leads_with_the_demo(): void
    {
        config(['goalline.demo.enabled' => true]);

        $this->get(route('home'))->assertOk()
            ->assertSee('Get in line with your goals.')
            ->assertSee('Compete weekly with friends, each against your own AI-built plan.')
            ->assertSeeInOrder(['Try the demo', 'No sign-up needed', 'Log in'])
            ->assertSee('gl-logo-animated', false)
            ->assertSeeInOrder(['Set a goal', 'Get your plan', 'Play your week'])
            ->assertSee('AI builds the plan. Code keeps score.')
            ->assertSee('AI never scores your week or decides disputes.');
    }

    public function test_the_screens_are_real_files_with_sizes_and_alt_text(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        foreach (['matchup', 'standings', 'plan'] as $screen) {
            $this->assertFileExists(public_path("images/landing/{$screen}.webp"));
            $this->assertMatchesRegularExpression('#src="[^"]*/images/landing/'.$screen.'\.webp" width="600" height="1298" alt="[^"]{20,}"#', $html);
        }
    }

    public function test_public_pages_have_titles_descriptions_and_link_previews(): void
    {
        $this->assertFileExists(public_path('images/og-goalline.jpg'));

        foreach ([route('home'), route('login'), route('register'), route('privacy'), route('terms')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('#<title>[^<]*GoalLine[^<]*</title>#', $html, $url);
            $this->assertMatchesRegularExpression('#<meta name="description" content="[^"]{40,}">#', $html, $url);
            $this->assertStringContainsString('<meta property="og:image" content="'.ShareLink::to('/images/og-goalline.jpg').'">', $html, $url);
            $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html, $url);
        }
    }

    public function test_the_footer_links_about_privacy_help_and_the_repository(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('Privacy and safety')
            ->assertSee(route('about').'#faq', false)
            ->assertSee(config('goalline.repository_url'), false);

        $this->actingAs(User::factory()->create())->get(route('privacy'))->assertOk()->assertSee(route('help'), false);
    }

    public function test_signed_in_visitors_go_to_their_clubhouse(): void
    {
        $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()
            ->assertSee('Go to your Clubhouse')
            ->assertDontSee('data-try-demo', false);
    }
}
