<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manifest_opens_the_installed_app_on_the_clubhouse_with_shortcuts(): void
    {
        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertStringStartsWith('/dashboard', $manifest['start_url']);
        $this->assertSame(["Today's Gameplan", 'Plan my week', 'Inbox'], array_column($manifest['shortcuts'], 'name'));

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    public function test_the_service_worker_and_offline_screen_are_in_place(): void
    {
        $this->assertFileExists(public_path('sw.js'));
        $this->assertStringContainsString("const OFFLINE_URL = '/offline.html'", file_get_contents(public_path('sw.js')));
        $this->assertStringContainsString('You&rsquo;re offline', file_get_contents(public_path('offline.html')));
    }

    public function test_the_clubhouse_offers_to_install_the_app(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertSee('data-install-card', false)
            ->assertSee('Put GoalLine on your home screen')
            ->assertSee('apple-mobile-web-app-capable', false);
    }
}
