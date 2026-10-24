<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_links_work_behind_cloudfront(): void
    {
        config(['app.url' => 'https://goalline.example', 'goalline.behind_cloudfront' => true]);
        $user = User::factory()->unverified()->create();

        // The email links to the public https address...
        URL::forceRootUrl('https://goalline.example');
        URL::forceScheme('https');
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        URL::forceRootUrl(null);
        URL::forceScheme(null);

        // ...but CloudFront hands it to the server over plain HTTP under the server's own name.
        $atServer = 'http://goalline-env.elasticbeanstalk.example'.substr($link, strlen('https://goalline.example'));

        $this->actingAs($user)->get($atServer)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_without_cloudfront_requests_keep_their_own_address(): void
    {
        config(['goalline.behind_cloudfront' => false]);

        $this->get('http://localhost/privacy')->assertOk();
        $this->assertSame('http://localhost/privacy', request()->url());
    }
}
