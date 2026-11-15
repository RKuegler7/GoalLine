<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\ChallengeCheckIn;
use App\Models\ChallengeDispute;
use App\Models\League;
use App\Models\LeagueResult;
use App\Models\User;
use App\Notifications\ChallengeInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Challenge check-ins are enforced on the server for every type; disputes, proof, invites, the rules lock and side
 * Challenges.
 */
class ChallengeCheckInRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $sam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-14 10:00', 'America/New_York')); // A Saturday.
        $this->owner = User::factory()->create(['name' => 'Avery Lee', 'username' => 'avery']);
        $this->sam = User::factory()->create(['name' => 'Sam Okafor', 'username' => 'sam']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function challenge(array $attributes = []): Challenge
    {
        $challenge = Challenge::factory()->for($this->owner, 'creator')->create($attributes + ['starts_on' => '2026-11-09', 'ends_on' => '2026-11-22']);
        $challenge->members()->create(['user_id' => $this->owner->id]);
        $challenge->members()->create(['user_id' => $this->sam->id]);

        return $challenge;
    }

    public function test_no_check_ins_before_the_start_after_the_end_for_future_days_or_older_than_yesterday(): void
    {
        $upcoming = $this->challenge(['starts_on' => '2026-11-16', 'ends_on' => '2026-11-22']);
        $this->actingAs($this->sam)->post(route('challenges.check-in', $upcoming))->assertForbidden();
        $this->actingAs($this->sam)->post(route('challenges.check-in', $upcoming), ['date' => '2026-11-16'])->assertForbidden();

        $over = $this->challenge(['starts_on' => '2026-11-01', 'ends_on' => '2026-11-12']);
        $this->actingAs($this->sam)->post(route('challenges.check-in', $over))->assertForbidden();
        $this->actingAs($this->sam)->post(route('challenges.check-in', $over), ['date' => '2026-11-12'])->assertForbidden(); // Two days ago.

        $running = $this->challenge();
        $this->actingAs($this->sam)->post(route('challenges.check-in', $running), ['date' => '2026-11-15'])->assertForbidden(); // Tomorrow.
        $this->actingAs($this->sam)->post(route('challenges.check-in', $running), ['date' => '2026-11-12'])->assertForbidden(); // Too old.
        $this->actingAs($this->sam)->postJson(route('challenges.check-in', $running), ['date' => 'tomorrow'])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->post(route('challenges.check-in', $running))->assertForbidden(); // Not a member.

        $this->actingAs($this->sam)->post(route('challenges.check-in', $running), ['date' => '2026-11-13'])->assertRedirect(); // Yesterday.
        $this->actingAs($this->sam)->post(route('challenges.check-in', $running))->assertRedirect(); // Today.
        $this->assertSame(['2026-11-13', '2026-11-14'], $running->checkIns()->orderBy('checked_on')->get()->map(fn ($checkIn) => $checkIn->checked_on->toDateString())->all());
        $this->assertSame(0, ChallengeCheckIn::where('challenge_id', $upcoming->id)->orWhere('challenge_id', $over->id)->count());
    }

    public function test_a_daily_checklist_ticks_rule_by_rule(): void
    {
        $challenge = $this->challenge(['type' => 'daily', 'rules' => ['items' => ['Pray', 'Read']]]);

        $this->actingAs($this->sam)->post(route('challenges.check-in', $challenge), ['do' => 'rule', 'rule' => 0]);
        $this->assertFalse($challenge->fresh()->isCheckedIn($this->sam, Challenge::today()), 'One of two rules.');
        $this->actingAs($this->sam)->post(route('challenges.check-in', $challenge), ['do' => 'rule', 'rule' => 1]);
        $this->assertTrue($challenge->fresh()->load('checkIns')->isCheckedIn($this->sam, Challenge::today()));
        $this->actingAs($this->sam)->post(route('challenges.check-in', $challenge), ['do' => 'rule', 'rule' => 5])->assertStatus(422);

        $this->actingAs($this->sam)->get(route('challenges.show', $challenge))->assertOk()->assertSee('2 of 2 done today. This day counts.');
    }

    public function test_abstain_stayed_on_track_or_slipped_with_kind_words(): void
    {
        $challenge = $this->challenge(['type' => 'abstain', 'rules' => ['label' => 'No sweets']]);

        $this->actingAs($this->sam)->post(route('challenges.check-in', $challenge), ['do' => 'slipped'])
            ->assertSessionHas('status', 'Logged. One day doesn\'t undo the others. Back on track tomorrow.');
        $this->assertSame(ChallengeCheckIn::SLIPPED, $challenge->checkIns()->sole()->status);
        $this->actingAs($this->sam)->get(route('challenges.show', $challenge))->assertSee('one day, not the whole Challenge.');

        $this->actingAs($this->sam)->post(route('challenges.check-in', $challenge), ['do' => 'done']);
        $this->assertSame(ChallengeCheckIn::DONE, $challenge->checkIns()->sole()->status);
    }

    public function test_target_numbers_are_range_checked_and_unusual_ones_confirmed(): void
    {
        $challenge = $this->challenge(['type' => 'target', 'rules' => ['label' => 'Steps', 'threshold' => 10000, 'unit' => 'steps', 'per' => 'day', 'max' => 100000]]);

        $this->actingAs($this->sam)->post(route('challenges.check-in', $challenge), ['do' => 'value', 'value' => 250000])->assertSessionHasErrors('value');
        $this->actingAs($this->sam)->post(route('challenges.check-in', $challenge), ['do' => 'value', 'value' => -5])->assertSessionHasErrors('value');
        $this->actingAs($this->sam)->post(route('challenges.check-in', $challenge), ['do' => 'value', 'value' => 60000])->assertSessionHas('challenge_confirm');
        $this->assertSame(0, $challenge->checkIns()->count(), 'Unusual: asked first.');

        $this->actingAs($this->sam)->post(route('challenges.check-in', $challenge), ['do' => 'value', 'value' => 60000, 'confirmed' => 1]);
        $this->assertSame('60000.00', $challenge->checkIns()->sole()->value);
        $this->actingAs($this->sam)->post(route('challenges.check-in', $challenge), ['do' => 'value', 'value' => 8000]);
        $this->assertFalse($challenge->fresh()->load('checkIns')->isCheckedIn($this->sam, Challenge::today()), 'Under the target.');
        $this->actingAs($this->sam)->post(route('challenges.check-in', $challenge), ['do' => 'value', 'value' => 12000]);
        $this->assertTrue($challenge->fresh()->load('checkIns')->isCheckedIn($this->sam, Challenge::today()));

        // A number for a non-Target Challenge is refused.
        $daily = $this->challenge();
        $this->actingAs($this->sam)->post(route('challenges.check-in', $daily), ['do' => 'value', 'value' => 5])->assertStatus(422);
    }

    public function test_a_weekly_check_in_shows_progress_toward_the_week(): void
    {
        $challenge = $this->challenge(['type' => 'weekly', 'rules' => ['label' => 'Workout', 'times' => 4]]);

        $this->actingAs($this->sam)->post(route('challenges.check-in', $challenge))->assertSessionHas('status', '1 of 4 this week.');
        $this->actingAs($this->sam)->get(route('challenges.show', $challenge))->assertSee('1 of 4 this week');
    }

    public function test_milestones_are_celebrated(): void
    {
        $challenge = $this->challenge(['starts_on' => '2026-11-08', 'ends_on' => '2026-11-21']);
        foreach (['08', '09', '10', '11', '12', '13'] as $day) {
            $challenge->checkIns()->create(['user_id' => $this->sam->id, 'checked_on' => "2026-11-{$day}"]);
        }

        $this->actingAs($this->sam)->post(route('challenges.check-in', $challenge))
            ->assertSessionHas('band_up', fn (array $moment) => $moment['band'] === 'challenge' && $moment['title'] === 'Halfway there.');
    }

    public function test_proof_photos_are_cleaned_and_a_same_day_photo_is_verified(): void
    {
        Storage::fake('uploads');
        $challenge = $this->challenge();
        $photo = UploadedFile::fake()->createWithContent('proof.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        $this->actingAs($this->sam)->post(route('challenges.proof', $challenge), ['date' => '2026-11-14', 'photo' => $photo])->assertStatus(422); // Check in first.

        $this->actingAs($this->sam)->post(route('challenges.check-in', $challenge));
        $this->actingAs($this->sam)->post(route('challenges.proof', $challenge), ['date' => '2026-11-14', 'photo' => $photo])->assertSessionHas('status', 'Proof added. Verified.');
        $checkIn = $challenge->checkIns()->sole();
        $this->assertTrue($checkIn->isVerified());
        Storage::disk('uploads')->assertExists($checkIn->proof_path);

        $this->actingAs($this->owner)->get(route('challenges.proof.show', [$challenge, $checkIn]))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('challenges.proof.show', [$challenge, $checkIn]))->assertForbidden();
    }

    public function test_members_dispute_others_check_ins_and_the_owner_decides(): void
    {
        $challenge = $this->challenge();
        $checkIn = $challenge->checkIns()->create(['user_id' => $this->sam->id, 'checked_on' => '2026-11-13']);
        $mine = $challenge->checkIns()->create(['user_id' => $this->owner->id, 'checked_on' => '2026-11-13']);

        $this->actingAs($this->sam)->post(route('challenges.disputes.store', [$challenge, $mine]), ['reason' => 'Really?'])->assertRedirect();
        $this->actingAs($this->sam)->post(route('challenges.disputes.store', [$challenge, $checkIn]))->assertForbidden(); // Your own.

        $dispute = ChallengeDispute::sole();
        $this->actingAs($this->sam)->patch(route('challenges.disputes.resolve', [$challenge, $dispute]), ['resolution' => 'upheld'])->assertForbidden(); // Owner only.
        $this->actingAs($this->owner)->get(route('challenges.show', $challenge))->assertSee('Disputes to decide')->assertSee('Really?');
        $this->actingAs($this->owner)->patch(route('challenges.disputes.resolve', [$challenge, $dispute]), ['resolution' => 'upheld'])->assertRedirect();

        $this->assertSame(ChallengeCheckIn::VOIDED, $mine->fresh()->status);
        $this->assertSame(0, $challenge->fresh()->load('checkIns')->progressFor($this->owner->id)['completed']);
        // A voided day can't be checked in again.
        $this->actingAs($this->owner)->post(route('challenges.check-in', $challenge), ['date' => '2026-11-13'])->assertForbidden();
    }

    public function test_in_app_invites_land_in_the_invites_tab_and_can_be_declined(): void
    {
        Notification::fake();
        $challenge = Challenge::factory()->for($this->owner, 'creator')->create(['title' => 'December reset', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-31']);
        $challenge->members()->create(['user_id' => $this->owner->id]);

        $this->actingAs($this->owner)->post(route('challenges.invites.store', $challenge), ['who' => '@sam'])->assertRedirect();
        $this->actingAs($this->owner)->post(route('challenges.invites.store', $challenge), ['who' => 'nobody'])->assertSessionHasErrors('who', null, 'invite');
        Notification::assertSentTo($this->sam, ChallengeInvited::class);

        $this->actingAs($this->sam)->get(route('leagues.index', ['tab' => 'invites']))->assertSee('December reset')->assertSee('Not for me');
        $this->actingAs($this->sam)->delete(route('challenges.invites.decline', $challenge->invites()->sole()))->assertRedirect();
        $this->actingAs($this->sam)->get(route('leagues.index', ['tab' => 'invites']))->assertDontSee('December reset');
    }

    public function test_rules_lock_at_kickoff_and_only_the_name_description_and_visibility_change(): void
    {
        $challenge = $this->challenge(['title' => 'Steps', 'type' => 'daily']);
        $this->actingAs($this->sam)->get(route('challenges.edit', $challenge))->assertForbidden();

        $this->actingAs($this->owner)->get(route('challenges.edit', $challenge))->assertOk()->assertSee('its rules are locked');
        $this->actingAs($this->owner)->patch(route('challenges.update', $challenge), [
            'title' => 'More steps', 'visibility' => 'public', 'type' => 'abstain', 'duration' => '7', 'starts_on' => '2026-11-20', 'mode' => 'strict',
        ])->assertRedirect();

        $challenge->refresh();
        $this->assertSame(['More steps', true, 'daily', 'flexible', '2026-11-09'], [$challenge->title, $challenge->is_public, $challenge->type, $challenge->mode, $challenge->starts_on->toDateString()]);
    }

    public function test_a_member_cap_is_respected(): void
    {
        $challenge = $this->challenge(['max_members' => 2]);
        $third = User::factory()->create();

        $this->actingAs($third)->get(route('challenges.join', $challenge->invite_code))->assertSee('This Challenge is full.');
        $this->actingAs($third)->post(route('challenges.join.store', $challenge->invite_code), ['agree' => 1]);
        $this->assertSame(2, $challenge->members()->count());
    }

    public function test_a_side_challenge_is_for_league_members_and_never_changes_goalleague_stars(): void
    {
        $league = League::factory()->create(['commissioner_id' => $this->owner->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-13']);
        $league->members()->create(['user_id' => $this->owner->id]);
        $league->members()->create(['user_id' => $this->sam->id]);

        $this->actingAs($this->owner)->get(route('challenges.create', ['league' => $league->id]))->assertOk()->assertSee('A side Challenge for');
        $this->actingAs($this->sam)->get(route('challenges.create', ['league' => $league->id]))->assertForbidden();
        $this->actingAs($this->owner)->post(route('challenges.store'), [
            'title' => 'League water', 'type' => 'daily', 'rules' => ['items' => ['Drink water']], 'duration' => '7', 'starts_on' => '2026-11-14',
            'mode' => 'flexible', 'allowed_misses' => 0, 'visibility' => 'public', 'league_id' => $league->id,
        ])->assertRedirect();

        $side = Challenge::where('title', 'League water')->sole();
        $this->assertSame($league->id, $side->league_id);
        $this->assertFalse($side->is_public, 'Side Challenges are never public.');

        // It shows on the GoalLeague page for members to opt in.
        $this->actingAs($this->sam)->get(route('leagues.show', $league))->assertSee('Side Challenges')->assertSee('League water')->assertSee('Opt in');
        $this->actingAs($this->sam)->post(route('challenges.join.store', $side->invite_code), ['agree' => 1])->assertRedirect(route('challenges.show', $side));
        $this->actingAs($this->sam)->get(route('leagues.index', ['tab' => 'challenges']))->assertSee('League water');
        $this->actingAs(User::factory()->create())->get(route('challenges.join', $side->invite_code))->assertSee('This side Challenge is for members of');

        $results = LeagueResult::query()->get(['user_id', 'week', 'result_points', 'matchup_points'])->toArray();
        $this->actingAs($this->sam)->post(route('challenges.check-in', $side));
        $this->assertSame($results, LeagueResult::query()->get(['user_id', 'week', 'result_points', 'matchup_points'])->toArray(), 'Challenges never change GoalLeague results or stars.');
    }
}
