<?php

namespace Tests\Feature;

use App\Http\Controllers\DemoController;
use App\Http\Middleware\TrustCloudFront;
use App\Models\User;
use App\Services\DemoWorld;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * "Try the demo" behind CloudFront: the real visitor's address, limits that don't trip shared networks, returning to
 * your own demo, a total cap for cost, and a friendly page (never an error email) when a limit is reached.
 */
class DemoRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const CLOUDFRONT = '130.176.12.34';

    protected function setUp(): void
    {
        parent::setUp();

        // Building a real demo world is slow; a stand-in visitor is enough to test the limits.
        $this->mock(DemoWorld::class, fn ($mock) => $mock->shouldReceive('create')->andReturnUsing(
            fn () => User::factory()->create(['name' => DemoWorld::VISITOR_NAME, 'demo_sandbox' => (string) Str::uuid()]),
        ));

        Route::get('/_ip', fn () => request()->ip())->middleware('web');
    }

    /**
     * A visit through CloudFront from $viewer, with this browser's cookies.
     *
     * @param  array<string, string>  $cookies
     */
    private function visit(string $viewer, array $cookies = [], ?string $spoof = null): TestResponse
    {
        $this->flushHeaders();
        auth()->logout();

        return $this->withServerVariables(['REMOTE_ADDR' => self::CLOUDFRONT])
            ->withHeader('X-Forwarded-For', $spoof ? "{$spoof}, {$viewer}" : $viewer)
            ->withCookies($cookies)
            ->get('/demo');
    }

    /**
     * @return array<string, string>
     */
    private function cookiesFrom(TestResponse $response): array
    {
        return collect([DemoController::COOKIE, DemoController::BROWSER_COOKIE])
            ->mapWithKeys(fn ($name) => [$name => $response->getCookie($name)?->getValue()])
            ->filter()
            ->all();
    }

    public function test_the_visitor_address_is_the_one_cloudfront_saw_and_cant_be_faked(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::CLOUDFRONT])->withHeader('X-Forwarded-For', '203.0.113.5')
            ->get('/_ip')->assertSee('203.0.113.5');

        // A visitor's own X-Forwarded-For sits to the left of CloudFront's entry and is ignored.
        $this->withServerVariables(['REMOTE_ADDR' => self::CLOUDFRONT])->withHeader('X-Forwarded-For', '1.1.1.1, 203.0.113.5')
            ->get('/_ip')->assertSee('203.0.113.5')->assertDontSee('1.1.1.1');

        $this->assertSame('2001:db8::1', TrustCloudFront::viewerIp('2001:db8::1:46532'));
        $this->assertSame('203.0.113.7', TrustCloudFront::viewerIp('203.0.113.7:443'));
        $this->assertNull(TrustCloudFront::viewerIp('nonsense'));
    }

    public function test_cloudfront_viewer_address_is_used_when_turned_on(): void
    {
        config(['goalline.proxies.cloudfront_viewer_address' => true]);

        $this->withServerVariables(['REMOTE_ADDR' => self::CLOUDFRONT])
            ->withHeaders(['X-Forwarded-For' => '10.0.0.9', 'CloudFront-Viewer-Address' => '198.51.100.20:51234'])
            ->get('/_ip')->assertSee('198.51.100.20');
    }

    public function test_a_returning_visitor_opens_the_same_demo_and_is_never_blocked(): void
    {
        config(['goalline.demo.starts_per_hour_per_browser' => 1, 'goalline.demo.starts_per_hour' => 1]);

        $first = $this->visit('203.0.113.5')->assertRedirect(route('dashboard'));
        $visitor = auth()->user();
        $cookies = $this->cookiesFrom($first);

        foreach (range(1, 5) as $tap) {
            $this->visit('203.0.113.5', $cookies)->assertRedirect(route('dashboard'));
            $this->assertTrue(auth()->user()->is($visitor), "Tap {$tap} opens the same demo.");
        }

        $this->assertSame(1, User::whereNotNull('demo_sandbox')->count());

        // Tapping Try the demo while already in it keeps the demo too.
        $this->actingAs($visitor)->get('/demo')->assertRedirect(route('dashboard'));
        $this->assertSame(1, User::whereNotNull('demo_sandbox')->count());
    }

    public function test_two_people_on_one_network_dont_block_each_other(): void
    {
        config(['goalline.demo.starts_per_hour_per_browser' => 2, 'goalline.demo.starts_per_hour' => 30]);
        $shared = '198.51.100.77';

        // Alex keeps starting brand-new demos (no demo cookie), from one browser, until their browser's limit.
        $browser = ['goalline_browser' => 'alex-browser-0000000000000000000000'];
        $this->visit($shared, $browser)->assertRedirect(route('dashboard'));
        $this->visit($shared, $browser)->assertRedirect(route('dashboard'));
        $this->visit($shared, $browser)->assertStatus(429);

        // Sam, on the same network, still gets in.
        $this->visit($shared, ['goalline_browser' => 'sam-browser-00000000000000000000000'])->assertRedirect(route('dashboard'));
    }

    public function test_the_network_and_total_caps_protect_cost(): void
    {
        config(['goalline.demo.starts_per_hour' => 2, 'goalline.demo.starts_per_hour_total' => 3]);

        $this->visit('203.0.113.1')->assertRedirect();
        $this->visit('203.0.113.1')->assertRedirect();
        $this->visit('203.0.113.1')->assertStatus(429);

        $this->visit('203.0.113.2')->assertRedirect();
        $this->visit('203.0.113.3')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_the_429_page_is_friendly_with_a_countdown_and_never_emails_the_admins(): void
    {
        config(['goalline.demo.starts_per_hour_total' => 1]);
        $this->visit('203.0.113.8');

        $this->visit('203.0.113.9')->assertStatus(429)
            ->assertSee('Lots of people are trying GoalLine right now')
            ->assertSee('Give it a moment, then try again.')
            ->assertSee('data-retry-seconds', false)
            ->assertSee('Try again')
            ->assertDontSee('Too many tries');

        $this->assertFalse(app(ExceptionHandler::class)->shouldReport(new ThrottleRequestsException));
    }

    public function test_sign_in_limits_are_per_account_so_a_shared_network_isnt_fragile(): void
    {
        $server = ['REMOTE_ADDR' => self::CLOUDFRONT];

        foreach (range(1, 10) as $try) {
            $this->withServerVariables($server)->withHeader('X-Forwarded-For', '198.51.100.8')
                ->post('/login', ['email' => 'alex@example.test', 'password' => 'wrong-password']);
        }
        $this->withServerVariables($server)->withHeader('X-Forwarded-For', '198.51.100.8')
            ->post('/login', ['email' => 'alex@example.test', 'password' => 'wrong-password'])->assertStatus(429);

        // Someone else on the same network can still sign in.
        $this->withServerVariables($server)->withHeader('X-Forwarded-For', '198.51.100.8')
            ->post('/login', ['email' => 'sam@example.test', 'password' => 'wrong-password'])->assertStatus(302);
    }

    public function test_signed_in_limits_like_search_are_per_member(): void
    {
        [$alex, $sam] = User::factory()->count(2)->create();
        $server = ['REMOTE_ADDR' => self::CLOUDFRONT];

        foreach (range(1, 30) as $try) {
            $this->actingAs($alex)->withServerVariables($server)->withHeader('X-Forwarded-For', '198.51.100.8')->get(route('people.search', ['q' => 'a']));
        }
        $this->actingAs($alex)->get(route('people.search', ['q' => 'a']))->assertStatus(429);
        $this->actingAs($sam)->withServerVariables($server)->withHeader('X-Forwarded-For', '198.51.100.8')->get(route('people.search', ['q' => 'a']))->assertOk();
    }
}
