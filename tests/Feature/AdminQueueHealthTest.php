<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminQueueHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_page_shows_whether_the_queue_worker_is_keeping_up(): void
    {
        config(['goalline.admin_emails' => ['admin@goalline.app'], 'queue.default' => 'database']);
        $admin = User::factory()->create(['email' => 'admin@goalline.app']);

        $this->actingAs($admin)->get(route('admin.index'))->assertOk()->assertSee('Worker running.')->assertSee('0 waiting');

        // A job nobody has picked up for ten minutes: the worker isn't running.
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subMinutes(10)->getTimestamp(), 'created_at' => now()->subMinutes(10)->getTimestamp()]);

        $this->actingAs($admin)->get(route('admin.index'))->assertSee('Jobs are piling up:')->assertSee('the oldest for 10 minutes');
    }
}
