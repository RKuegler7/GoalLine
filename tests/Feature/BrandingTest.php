<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    public function test_pages_link_the_favicon_app_icon_and_manifest(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="/favicon.svg"', false)
            ->assertSee('href="/apple-touch-icon.png"', false)
            ->assertSee('href="/site.webmanifest"', false)
            ->assertSee('aria-label="GoalLine"', false);
    }

    public function test_icon_files_exist(): void
    {
        foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'site.webmanifest', 'icons/icon-192.png', 'icons/icon-512.png', 'icons/app-icon.svg'] as $file) {
            $this->assertFileExists(public_path($file));
        }
    }

    public function test_goal_progress_marker_follows_the_given_percent(): void
    {
        $html = Blade::render('<x-goal-progress percent="62" />');

        $this->assertStringContainsString('aria-valuenow="62"', $html);
        $this->assertStringContainsString('left: 62%', $html);
    }

    public function test_goal_progress_is_clamped_between_zero_and_one_hundred(): void
    {
        $this->assertStringContainsString('aria-valuenow="100"', Blade::render('<x-goal-progress percent="140" />'));
        $this->assertStringContainsString('aria-valuenow="0"', Blade::render('<x-goal-progress percent="-5" />'));
    }
}
