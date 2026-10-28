<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoStorageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['goalline.admin_emails' => ['admin@goalline.app']]);
        $this->admin = User::factory()->create(['email' => 'admin@goalline.app']);
    }

    public function test_the_admin_page_warns_when_photos_are_on_the_servers_own_disk(): void
    {
        Storage::fake('uploads');

        $this->actingAs($this->admin)->get(route('admin.index'))
            ->assertSee('Photo storage')
            ->assertSee('Uploaded photos vanish every time you deploy.');
    }

    public function test_the_admin_page_confirms_s3_is_working(): void
    {
        config(['filesystems.disks.uploads' => ['driver' => 's3', 'bucket' => 'goalline-uploads', 'region' => 'us-east-1', 'visibility' => 'private']]);
        Storage::fake('uploads');

        $this->actingAs($this->admin)->get(route('admin.index'))
            ->assertSee('Working.')
            ->assertSee('goalline-uploads');
        Storage::disk('uploads')->assertMissing('health/admin-check.txt');
    }
}
