<?php

namespace Tests\Feature;

use App\BuildStamp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class BuildStampTest extends TestCase
{
    use RefreshDatabase;

    private string $stamp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stamp = base_path(BuildStamp::FILE);
        $this->assertFileDoesNotExist($this->stamp, 'A real build.json would be overwritten by this test.');
        BuildStamp::flush();
    }

    protected function tearDown(): void
    {
        @unlink($this->stamp);
        BuildStamp::flush();

        parent::tearDown();
    }

    public function test_without_a_stamp_health_and_about_say_so(): void
    {
        $this->getJson('/health')->assertJsonPath('build', null);
        $this->get(route('about'))->assertOk()->assertSee('Build: running from source (no build stamp)');
    }

    public function test_a_stamped_build_shows_its_commit_and_date_on_about_and_health(): void
    {
        file_put_contents($this->stamp, json_encode(['commit' => 'a46ad58', 'built_at' => '2026-11-14T20:30:00+00:00']));

        $this->getJson('/health')
            ->assertJsonPath('build.commit', 'a46ad58')
            ->assertJsonPath('build.built_at', '2026-11-14T20:30:00+00:00');

        $this->get(route('about'))->assertOk()
            ->assertSee('<code>a46ad58</code>', false)
            ->assertSee(rtrim(config('goalline.repository_url'), '/').'/commit/a46ad58', false)
            ->assertSee('Nov 14, 2026');
    }

    public function test_a_broken_or_odd_stamp_is_ignored(): void
    {
        foreach (['not json', json_encode(['commit' => '<script>', 'built_at' => 'now']), json_encode(['commit' => 'abc1234', 'built_at' => 'whenever'])] as $content) {
            file_put_contents($this->stamp, $content);
            BuildStamp::flush();
            $this->assertNull(BuildStamp::current(), $content);
        }
    }

    public function test_the_command_builds_a_zip_from_a_commit_with_the_stamp_inside(): void
    {
        $zip = storage_path('framework/testing-deploy.zip');
        @unlink($zip);

        $this->artisan('goalline:deploy-zip', ['--output' => $zip])->assertSuccessful();

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($zip) === true);
        $stamp = json_decode((string) $archive->getFromName('build.json'), true);
        $hasArtisan = $archive->locateName('artisan') !== false;
        $hasEnv = $archive->locateName('.env') !== false;
        $archive->close();
        @unlink($zip);

        $this->assertSame(trim((string) shell_exec('git -C '.escapeshellarg(base_path()).' rev-parse --short HEAD')), $stamp['commit']);
        $this->assertNotFalse(strtotime($stamp['built_at']));
        $this->assertSame(['commit', 'built_at'], array_keys($stamp), 'Nothing but the hash and the time.');
        $this->assertTrue($hasArtisan);
        $this->assertFalse($hasEnv, 'Only committed files: never the .env.');

        $this->artisan('goalline:deploy-zip', ['ref' => 'no-such-ref-xyz', '--output' => $zip])->assertFailed();
    }
}
