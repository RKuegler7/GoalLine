<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MobileReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_has_the_iphone_viewport_theme_color_and_home_screen_icons(): void
    {
        $pages = [
            [null, route('login')],
            [null, route('privacy')],
            [User::factory()->create(), route('dashboard')],
        ];

        foreach ($pages as [$user, $url]) {
            $response = ($user ? $this->actingAs($user) : $this)->get($url)->assertOk();

            $response->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">', false)
                ->assertSee('<meta name="theme-color" content="#176b45">', false)
                ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', false)
                ->assertSee('<link rel="manifest" href="/site.webmanifest">', false);
        }
    }

    public function test_the_manifest_icons_exist_at_the_sizes_it_names(): void
    {
        $manifest = json_decode(File::get(public_path('site.webmanifest')), true);

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));

            if ($icon['type'] === 'image/png') {
                [$width, $height] = getimagesize(public_path(ltrim($icon['src'], '/')));
                $this->assertSame($icon['sizes'], "{$width}x{$height}");
            }
        }

        [$width, $height] = getimagesize(public_path('apple-touch-icon.png'));
        $this->assertSame([180, 180], [$width, $height]);
    }

    public function test_layouts_use_dynamic_viewport_units_and_keep_clear_of_the_home_indicator(): void
    {
        foreach (File::files(resource_path('views/components/layouts')) as $layout) {
            $html = $layout->getContents();

            $this->assertStringNotContainsString('min-h-screen', $html, "{$layout->getFilename()} should use min-h-dvh.");
            $this->assertStringContainsString('min-h-dvh', $html, $layout->getFilename());
            $this->assertStringContainsString('env(safe-area-inset-bottom)', $html, $layout->getFilename());
        }

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertSee('pb-[calc(6rem+env(safe-area-inset-bottom))]', false);
    }

    public function test_every_photo_picker_asks_for_formats_ios_converts_to(): void
    {
        $pickers = collect(File::allFiles(resource_path('views')))
            ->flatMap(fn ($file) => preg_match_all('/<input[^>]*type="file"[^>]*>/s', $file->getContents(), $matches) ? $matches[0] : [])
            ->reject(fn (string $input) => str_contains($input, 'application/pdf'));

        $this->assertNotEmpty($pickers);

        foreach ($pickers as $input) {
            $this->assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $input);
        }
    }

    public function test_main_grids_let_their_column_shrink_on_phones(): void
    {
        foreach (['dashboard', 'highlights/index', 'challenges/show', 'admin/index'] as $view) {
            $html = File::get(resource_path("views/{$view}.blade.php"));

            $this->assertStringContainsString('grid-cols-[minmax(0,1fr)]', $html, "{$view}: a bare grid column grows to its widest child and overflows the phone.");
        }
    }
}
