<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Highlight;
use App\Models\RoadmapTask;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Services\ErrorAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToRetrieveMetadata;
use RuntimeException;
use Tests\TestCase;

class MissingUploadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Like S3 in production: a missing file throws instead of returning null.
        Storage::fake('uploads', ['throw' => true]);
        Exceptions::fake();
    }

    public function test_a_missing_avatar_shows_initials_and_clears_the_stale_reference(): void
    {
        $user = User::factory()->create(['name' => 'Avery Brooks']);
        $user->forceFill(['avatar_path' => 'avatars/gone.jpg', 'avatar_mime_type' => 'image/jpeg'])->save();

        $this->actingAs($user)->get(route('avatars.show', $user))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('AB', false);

        $this->assertNull($user->fresh()->avatar_path);
        $this->assertNull($user->fresh()->avatar_mime_type);
        $this->actingAs($user->fresh())->get(route('profile.show'))->assertOk()->assertDontSee('avatars/', false);
        Exceptions::assertNothingReported();
    }

    public function test_an_avatar_link_from_an_old_page_still_shows_initials(): void
    {
        $user = User::factory()->create(['name' => 'Avery Brooks']);

        $this->actingAs($user)->get(route('avatars.show', $user))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('AB', false);
    }

    public function test_avatars_are_served_with_the_type_saved_at_upload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'avatar' => $this->fakeImage('me.png'),
        ]);

        $this->assertSame('image/png', $user->fresh()->avatar_mime_type);
        $this->actingAs($user)->get(route('avatars.show', $user))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_a_missing_post_image_shows_a_placeholder_and_the_post_goes_on_without_it(): void
    {
        $goal = Goal::factory()->create();
        $post = Highlight::factory()->create(['user_id' => $goal->user_id, 'goal_id' => $goal->id]);
        $post->forceFill(['photo_path' => "highlights/{$post->id}/gone.jpg", 'photo_mime_type' => 'image/jpeg'])->save();

        $this->actingAs($goal->user)->get(route('highlights.photo', $post))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('Photo unavailable', false);

        $this->assertNull($post->fresh()->photo_path);
        $this->actingAs($goal->user)->get(route('highlights.index'))->assertOk()->assertDontSee(route('highlights.photo', $post));
        Exceptions::assertNothingReported();
    }

    public function test_a_share_card_with_a_missing_photo_still_opens(): void
    {
        $goal = Goal::factory()->create();
        $post = Highlight::factory()->create(['user_id' => $goal->user_id, 'goal_id' => $goal->id]);
        $post->forceFill(['photo_path' => "highlights/{$post->id}/gone.jpg"])->save();

        $this->actingAs($goal->user)->get(route('highlights.card', $post))->assertOk();

        $this->assertNull($post->fresh()->photo_path);
        Exceptions::assertNothingReported();
    }

    public function test_a_missing_proof_photo_shows_a_placeholder_but_keeps_the_check_in_verified(): void
    {
        $task = RoadmapTask::factory()->create();
        $task->forceFill(['completed_at' => now(), 'proof_path' => 'proofs/1/gone.jpg', 'proof_mime_type' => 'image/jpeg', 'proof_uploaded_at' => now()])->save();

        $this->actingAs($task->week->goal->user)->get(route('roadmap.tasks.proof', $task))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $this->assertSame('proofs/1/gone.jpg', $task->fresh()->proof_path, 'Clearing proof would change Verified and the score.');
        Exceptions::assertNothingReported();
    }

    public function test_the_same_error_is_emailed_at_most_once_an_hour(): void
    {
        Notification::fake();
        config(['goalline.admin_emails' => ['admin@goalline.app']]);
        $alerts = app(ErrorAlerts::class);

        $alerts->report(UnableToRetrieveMetadata::mimeType('avatars/abc123.jpg', 'HeadObject NotFound 404 (request id 7F3A9C21B04D)'));
        $alerts->report(UnableToRetrieveMetadata::mimeType('avatars/def456.jpg', 'HeadObject NotFound 404 (request id 0B19E77D5A2C)'));
        $alerts->report(UnableToRetrieveMetadata::mimeType('avatars/ghi789.jpg', 'HeadObject NotFound 404 (request id 55AA0C3D9E1F)'));
        Notification::assertSentOnDemandTimes(AdminAlert::class, 1);

        // A different error still gets through.
        $alerts->report(new RuntimeException('The coach timed out.'));
        Notification::assertSentOnDemandTimes(AdminAlert::class, 2);

        // An hour later, the first one can be sent again.
        $this->travel(61)->minutes();
        $alerts->report(UnableToRetrieveMetadata::mimeType('avatars/xyz000.jpg', 'HeadObject NotFound 404 (request id 1234ABCD5678)'));
        Notification::assertSentOnDemandTimes(AdminAlert::class, 3);
    }
}
