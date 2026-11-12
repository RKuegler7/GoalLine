<?php

namespace Tests\Feature;

use App\HighlightKind;
use App\Models\Goal;
use App\Models\Highlight;
use App\Models\League;
use App\Models\ResultCard;
use App\Models\RoadmapWeek;
use App\Models\User;
use App\PostVisibility;
use App\RoadmapStatus;
use App\Services\ResultCardData;
use App\Services\ResultCards\GdCanvas;
use App\Services\ResultCards\ResultCardImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

/**
 * Phase Q: shareable weekly result cards, made from the scoring code's numbers.
 */
class ResultCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday of the week Mon Oct 19 to Sun Oct 25.
        $this->travelTo('2026-10-22 10:00');
        Notification::fake();
        Storage::fake('local');
        Storage::fake(Highlight::PHOTO_DISK);
    }

    /**
     * Planned: 8 one-point tasks plus the 2-point milestone = 10 points.
     *
     * @return array<string, array{int, bool, int, bool, string, string, string}>
     */
    public static function bands(): array
    {
        return [
            'short of the line: calm, never red' => [5, false, 0, false, 'short', 'Showed up', '#fafaf5'],
            'in line: green' => [8, true, 0, false, 'in_line', 'In line', '#43b96a'],
            'past the line: gold' => [8, true, 2, false, 'past', 'Past the line', '#d4ac58'],
            'smashed with proof: blue diamond' => [8, true, 5, true, 'smashed', 'Smashed the line', '#0369a1'],
        ];
    }

    #[DataProvider('bands')]
    public function test_each_band_gets_its_own_card(int $done, bool $milestone, int $bonus, bool $proof, string $band, string $chip, string $color): void
    {
        $week = $this->week(User::factory()->create(['name' => 'Sam Rivera']), $done, $milestone, $bonus, $proof);

        $payload = ResultCardData::for($week);
        $this->assertSame($band, $payload['band']);
        $this->assertSame($week->creditedPercent(), $payload['percent']);
        $this->assertSame($week->result()->points(), $payload['linePoints']);
        $this->assertSame('Sam', $payload['name']);
        $this->assertSame('SR', $payload['initials']);

        $svg = app(ResultCardImage::class)->svg($payload);
        $this->assertStringContainsString('>'.$chip.'</text>', $svg);
        $this->assertStringContainsString($color, $svg);
        $this->assertStringContainsString('Get in line with your goals.', $svg);
        $this->assertStringContainsString('>'.$payload['percent'].'%</text>', $svg);
        $this->assertStringNotContainsString('#dc2626', $svg);

        if ($band === 'short') {
            $this->assertStringContainsString('Back on the line next week.', $svg);
            $this->assertStringNotContainsString('Short of the line', $svg);
        }
    }

    public function test_a_smashed_week_without_proof_is_credited_as_past_with_a_proof_nudge(): void
    {
        $week = $this->week(User::factory()->create(), 8, true, 5, false);

        $payload = ResultCardData::for($week);

        $this->assertSame('past', $payload['band']);
        $this->assertTrue($payload['needsProof']);
        $this->assertSame(149, $payload['percent']);
        $this->assertStringContainsString('Proof locks it in.', app(ResultCardImage::class)->svg($payload));
    }

    /**
     * @return array<string, array{string, string, int, bool}>
     */
    public static function matchResults(): array
    {
        return [
            'win' => ['win', 'Win', 3, false],
            'close win' => ['close_win', 'Close win', 2, true],
            'tie' => ['tie', 'Tie', 1, true],
            'close loss' => ['close_loss', 'Close loss', 1, true],
            'loss' => ['loss', 'Loss', 0, false],
        ];
    }

    #[DataProvider('matchResults')]
    public function test_each_match_result_shows_its_points_and_photo_finish(string $outcome, string $label, int $points, bool $photoFinish): void
    {
        $payload = ResultCard::factory()->make()->payload;
        $payload['match'] = ['outcome' => $outcome, 'label' => $label, 'points' => $points, 'photoFinish' => $photoFinish, 'bye' => false];

        $svg = app(ResultCardImage::class)->svg($payload);

        $this->assertStringContainsString('>'.$label.'</text>', $svg);
        $this->assertStringContainsString('· +'.$points.'</text>', $svg);
        $this->assertSame($photoFinish, str_contains($svg, 'Photo finish'));
        $this->assertStringContainsString('of 5', $svg);
    }

    public function test_a_league_week_carries_the_real_matchup_result_from_the_league_table(): void
    {
        $league = League::factory()->create(['starts_on' => '2026-10-19', 'ends_on' => '2026-11-15', 'name' => 'Run Club']);
        $home = $this->week($league->commissioner, 8, true, 0, false);
        $away = $this->week(User::factory()->create(['name' => 'Rival Person']), 7, true, 0, false);
        $league->members()->create(['user_id' => $home->goal->user_id, 'goal_id' => $home->goal_id]);
        $league->members()->create(['user_id' => $away->goal->user_id, 'goal_id' => $away->goal_id]);

        $payload = ResultCardData::for($home);

        // Both In line (100% and 90%): a photo finish, and the higher percent takes the close win.
        $this->assertSame('Run Club', $payload['league']);
        $this->assertSame('Week 1', $payload['weekLabel']);
        $this->assertSame(['outcome' => 'close_win', 'label' => 'Photo finish win', 'points' => 1, 'photoFinish' => true, 'bye' => false], $payload['match']);
        $this->assertSame(2, $payload['weeklyPoints']);
        $this->assertStringNotContainsString('Rival', json_encode($payload));
    }

    public function test_the_share_page_makes_a_card_for_the_owner_only(): void
    {
        $week = $this->week($owner = User::factory()->create(), 8, true, 2, false);

        $this->actingAs($owner)->get(route('cards.share', $week))->assertOk()
            ->assertSee('Share your week')
            ->assertSee('Post to Highlights')
            ->assertSee('Download image')
            ->assertSee('Make a link');
        $this->assertSame(1, $owner->resultCards()->count());

        // Same result, same card.
        $this->actingAs($owner)->get(route('cards.share', $week))->assertOk();
        $this->assertSame(1, $owner->resultCards()->count());

        $stranger = User::factory()->create();
        $card = $owner->resultCards()->sole();
        $this->actingAs($stranger)->get(route('cards.share', $week))->assertForbidden();
        $this->actingAs($stranger)->get(route('cards.image', [$card, 'feed', 'svg']))->assertForbidden();
        $this->actingAs($stranger)->post(route('cards.publish', $card))->assertForbidden();
        $this->actingAs($stranger)->post(route('cards.post', $card), ['visibility' => 'league_friends'])->assertForbidden();
        $this->actingAs($stranger)->delete(route('cards.revoke', $card))->assertForbidden();
        $this->assertNull($card->fresh()->public_at);
    }

    public function test_the_recap_and_matchup_offer_share_your_week(): void
    {
        $week = $this->week($owner = User::factory()->create(), 8, true, 0, false);

        $this->actingAs($owner)->get(route('recap.week'))->assertOk()->assertSee(route('cards.share', $week), false);
    }

    public function test_cards_come_in_feed_and_story_sizes(): void
    {
        $card = ResultCard::factory()->create();

        $feed = $this->actingAs($card->user)->get(route('cards.image', [$card, 'feed', 'svg']))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('width="1080" height="1350"', $feed->getContent());

        $story = $this->actingAs($card->user)->get(route('cards.image', [$card, 'story', 'svg']))->assertOk();
        $this->assertStringContainsString('width="1080" height="1920"', $story->getContent());
        $this->assertStringContainsString('sandbox', $story->headers->get('Content-Security-Policy'));
    }

    #[RequiresPhpExtension('gd')]
    public function test_png_cards_are_drawn_at_both_sizes_and_cached(): void
    {
        $this->assertTrue(GdCanvas::isAvailable());
        $card = ResultCard::factory()->create();

        foreach (ResultCardImage::SIZES as $size => [$width, $height]) {
            $image = app(ResultCardImage::class)->render($card, $size, 'png');
            $this->assertSame('image/png', $image['type']);
            $this->assertSame([$width, $height], array_slice(getimagesizefromstring($image['body']), 0, 2));
            Storage::disk('local')->assertExists("result-cards/{$card->content_hash}-{$size}-v1.png");
        }
    }

    public function test_without_gd_png_requests_fall_back_gracefully(): void
    {
        if (ResultCardImage::canMakePng()) {
            $this->markTestSkipped('This server can draw PNGs.');
        }

        $card = ResultCard::factory()->create();

        $this->actingAs($card->user)->get(route('cards.image', [$card, 'feed', 'png']))->assertNotFound();
        // The share page still works: the browser draws the PNG from the SVG.
        $this->actingAs($card->user)->get(route('cards.share', $card->week))->assertOk()->assertSee('data-card-png=""', false);
    }

    public function test_public_links_are_opt_in_unguessable_and_revocable(): void
    {
        $card = ResultCard::factory()->create();
        $other = ResultCard::factory()->create();

        $this->assertGreaterThanOrEqual(48, strlen($card->token));
        $this->assertNotSame($card->token, $other->token);

        // Not public until the owner opts in.
        $this->get(route('cards.public', $card->token))->assertNotFound();

        $this->actingAs($card->user)->post(route('cards.publish', $card))->assertRedirect();
        auth()->logout();
        $this->get(route('cards.public', $card->token))->assertOk()->assertSee('Join GoalLine');
        $this->get(route('cards.public.image', [$card->token, 'feed', 'svg']))->assertOk();

        // A wrong or shortened token never works.
        $this->get(route('cards.public', substr($card->token, 0, 20)))->assertNotFound();
        $this->get(route('cards.public', strrev($card->token)))->assertNotFound();

        // Revoked: gone.
        $this->actingAs($card->user)->delete(route('cards.revoke', $card))->assertRedirect();
        auth()->logout();
        $this->get(route('cards.public', $card->token))->assertNotFound();
        $this->get(route('cards.public.image', [$card->token, 'feed', 'svg']))->assertNotFound();
    }

    public function test_turning_public_cards_off_stops_every_link(): void
    {
        $card = ResultCard::factory()->public()->create();
        $this->get(route('cards.public', $card->token))->assertOk();

        $card->user->forceFill(['public_cards' => false])->save();

        $this->get(route('cards.public', $card->token))->assertNotFound();
        $this->actingAs($card->user)->post(route('cards.publish', $card->fresh()))->assertRedirect()->assertSessionHas('status');
    }

    public function test_the_public_page_shows_only_the_owners_own_summary_with_link_previews(): void
    {
        $owner = User::factory()->create(['name' => 'Jordan Private-Lastname', 'email' => 'jordan.secret@example.com', 'bio' => 'My private bio']);
        $week = $this->week($owner, 8, true, 2, true);
        $week->forceFill(['focus' => 'Secret focus note'])->save();

        $this->actingAs($owner)->get(route('cards.share', $week));
        $card = $owner->resultCards()->sole();
        $card->forceFill(['public_at' => now()])->save();
        auth()->logout();

        $this->get(route('cards.public', $card->token))->assertOk()
            ->assertSee('Jordan')
            ->assertSee('property="og:title"', false)
            ->assertSee('name="twitter:card"', false)
            ->assertSee('name="robots" content="noindex"', false)
            ->assertDontSee('Private-Lastname')
            ->assertDontSee('jordan.secret@example.com')
            ->assertDontSee('My private bio')
            ->assertDontSee('Secret focus note')
            ->assertDontSee('proofs/');
    }

    public function test_posting_to_highlights_follows_the_audience_choice(): void
    {
        $league = League::factory()->create(['starts_on' => '2026-10-19', 'ends_on' => '2026-11-15']);
        $week = $this->week($owner = $league->commissioner, 8, true, 2, false);
        $mate = $this->week(User::factory()->create(), 8, true, 0, false)->goal->user;
        $league->members()->create(['user_id' => $owner->id, 'goal_id' => $week->goal_id]);
        $league->members()->create(['user_id' => $mate->id, 'goal_id' => $mate->goals()->first()->id]);
        $friend = User::factory()->create();
        $this->makeFriends($owner, $friend);
        $stranger = User::factory()->create();

        $this->actingAs($owner)->get(route('cards.share', $week));
        $card = $owner->resultCards()->sole();

        $this->actingAs($owner)->post(route('cards.post', $card), ['visibility' => 'league', 'caption' => 'Good week'])
            ->assertRedirect();
        $post = $owner->highlights()->sole();
        $this->assertSame(HighlightKind::WeekCard, $post->kind);
        $this->assertSame(PostVisibility::League, $post->visibility);
        $this->assertSame($league->id, $post->league_id);
        $this->assertNotNull($post->photo_path);
        Storage::disk(Highlight::PHOTO_DISK)->assertExists($post->photo_path);

        $this->assertTrue($mate->can('view', $post));
        $this->assertFalse($friend->can('view', $post));
        $this->assertFalse($stranger->can('view', $post));

        // League + friends reaches the friend too.
        $post->forceFill(['visibility' => PostVisibility::LeagueFriends])->save();
        $this->assertTrue($friend->can('view', $post));
        $this->assertFalse($stranger->can('view', $post));

        // Posting again doesn't duplicate it.
        $this->actingAs($owner)->post(route('cards.post', $card), ['visibility' => 'league'])->assertRedirect();
        $this->assertSame(1, $owner->highlights()->count());

        $this->actingAs($mate)->get(route('highlights.index'))->assertOk()->assertSee('Weekly result')->assertSee('Good week');
    }

    public function test_making_cards_is_rate_limited(): void
    {
        $card = ResultCard::factory()->create();

        foreach (range(1, 10) as $attempt) {
            $this->actingAs($card->user)->post(route('cards.publish', $card))->assertRedirect();
        }

        $this->actingAs($card->user)->post(route('cards.publish', $card))->assertTooManyRequests();
    }

    public function test_demo_players_can_make_and_open_a_card(): void
    {
        $this->travelBack();
        $this->get(route('demo'))->assertRedirect(route('dashboard'));
        $visitor = auth()->user();
        $week = $visitor->goals()->with('weeks')->get()->flatMap->weeks->first(fn (RoadmapWeek $week) => $week->isStarted());

        $this->get(route('cards.share', $week))->assertOk()->assertSee('<svg', false);
        $card = $visitor->resultCards()->sole();
        $this->post(route('cards.publish', $card))->assertRedirect();
        $this->get(route('cards.public', $card->token))->assertOk();
    }

    /**
     * A started GoalWeek (Mon Oct 19 to Sun Oct 25) with 8 one-point tasks: $done checked off, the milestone, extra
     * bonus tasks done, and proof on one check-in.
     */
    private function week(User $user, int $done, bool $milestone, int $bonus, bool $proof): RoadmapWeek
    {
        $goal = Goal::factory()->for($user)->create(['title' => 'Run a half marathon', 'target_date' => '2027-01-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Base miles', 'milestone' => 'Run 10 km']);

        foreach (range(1, 8 + $bonus) as $index) {
            $week->tasks()->create(['position' => $index, 'description' => "Task {$index}", 'planned_on' => '2026-10-20'])
                ->forceFill([
                    'is_bonus' => $index > 8,
                    'completed_at' => ($index <= $done || $index > 8) ? now() : null,
                    'proof_path' => ($proof && $index === 1) ? 'proofs/test.png' : null,
                ])->save();
        }

        $week->forceFill(['milestone_hit_at' => $milestone ? now() : null])->save();

        return $week->fresh(['tasks', 'goal.weeks.tasks', 'goal.habits.logs']);
    }
}
