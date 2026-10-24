<?php

namespace Tests\Feature;

use App\Badge;
use App\Models\Challenge;
use App\Models\Goal;
use App\Models\User;
use App\Notifications\ChallengeFinished;
use App\Notifications\ChallengeJoined;
use App\RoadmapStatus;
use App\Services\ChallengeWrapUp;
use App\Services\DailyStreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ChallengeTest extends TestCase
{
    use RefreshDatabase;

    private User $avery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00', config('app.timezone'))); // A Wednesday.
        $this->avery = User::factory()->create(['name' => 'Avery Lee']);
    }

    public function test_anyone_can_start_a_challenge_and_is_its_first_member(): void
    {
        $this->actingAs($this->avery)->post(route('challenges.store'), [
            'title' => 'No sugar for a week',
            'starts_on' => '2026-10-21',
            'length' => 7,
            'target_days' => 6,
        ])->assertRedirect();

        $challenge = Challenge::sole();
        $this->assertSame('2026-10-27', $challenge->ends_on->toDateString());
        $this->assertSame(6, $challenge->target_days);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $challenge->invite_code);
        $this->assertTrue($challenge->members()->where('user_id', $this->avery->id)->exists());

        $this->actingAs($this->avery)->get(route('challenges.show', $challenge))->assertOk()->assertSee('I did it today')->assertSee($challenge->invite_code);
    }

    public function test_challenge_details_are_validated(): void
    {
        $this->actingAs($this->avery)
            ->post(route('challenges.store'), ['title' => '', 'starts_on' => '2026-10-01', 'length' => 9, 'target_days' => 40])
            ->assertSessionHasErrors(['title', 'starts_on', 'length']);

        $this->actingAs($this->avery)
            ->post(route('challenges.store'), ['title' => 'Run', 'starts_on' => '2026-10-21', 'length' => 7, 'target_days' => 8])
            ->assertSessionHasErrors('target_days');
    }

    public function test_people_join_with_the_link_or_code_and_the_creator_hears_about_it(): void
    {
        Notification::fake();
        $challenge = Challenge::factory()->for($this->avery, 'creator')->create();
        $challenge->members()->create(['user_id' => $this->avery->id]);
        $sam = User::factory()->create(['name' => 'Sam Okafor']);

        $this->actingAs($sam)->get(route('challenges.show', $challenge))->assertForbidden();

        $this->actingAs($sam)->post(route('challenges.find'), ['code' => strtolower(str_replace('-', ' ', $challenge->invite_code))])
            ->assertRedirect(route('challenges.join', $challenge->invite_code));
        $this->actingAs($sam)->get(route('challenges.join', $challenge->invite_code))->assertSee('I&rsquo;m in', false);
        $this->actingAs($sam)->post(route('challenges.join.store', $challenge->invite_code))->assertRedirect(route('challenges.show', $challenge));

        $this->assertSame(2, $challenge->members()->count());
        Notification::assertSentTo($this->avery, ChallengeJoined::class);
    }

    public function test_check_ins_count_once_a_day_with_a_one_day_grace_and_rank_the_leaderboard(): void
    {
        $challenge = Challenge::factory()->for($this->avery, 'creator')->create(['starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'target_days' => 3]);
        $sam = User::factory()->create(['name' => 'Sam Okafor']);
        $challenge->members()->create(['user_id' => $this->avery->id]);
        $challenge->members()->create(['user_id' => $sam->id]);

        $this->actingAs($this->avery)->post(route('challenges.check-in', $challenge));
        $this->actingAs($this->avery)->post(route('challenges.check-in', $challenge), ['date' => '2026-10-20']);
        $this->actingAs($this->avery)->post(route('challenges.check-in', $challenge), ['date' => '2026-10-19'])->assertForbidden(); // Past the grace.
        $this->actingAs($this->avery)->post(route('challenges.check-in', $challenge), ['date' => '2026-10-22'])->assertForbidden(); // Tomorrow.
        $this->actingAs($sam)->post(route('challenges.check-in', $challenge));

        $standings = $challenge->fresh()->load('members.user', 'checkIns')->standings();
        $this->assertSame(['Avery Lee', 'Sam Okafor'], $standings->map(fn ($row) => $row['member']->user->name)->all());
        $this->assertSame([2, 1], $standings->pluck('days')->all());
        $this->assertSame([2, 1], $standings->pluck('run')->all());

        // Checking in again undoes it.
        $this->actingAs($sam)->post(route('challenges.check-in', $challenge));
        $this->assertSame(0, $challenge->checkIns()->where('user_id', $sam->id)->count());
    }

    public function test_challenges_show_on_todays_gameplan_and_count_toward_the_streak(): void
    {
        $goal = Goal::factory()->for($this->avery)->create(['target_date' => '2026-12-31']);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Work', 'milestone' => 'Done.']);
        $challenge = Challenge::factory()->for($this->avery, 'creator')->create(['title' => 'Cold showers', 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25']);
        $challenge->members()->create(['user_id' => $this->avery->id]);

        $this->actingAs($this->avery)->get(route('dashboard'))->assertSee('Cold showers')->assertSee('day 3 of 7');

        $this->actingAs($this->avery)->post(route('challenges.check-in', $challenge), ['return' => 'gameplan'])->assertRedirect(route('dashboard').'#gameplan');
        $this->assertSame(1, (new DailyStreak($this->avery))->current());
        $this->actingAs($this->avery)->get(route('dashboard'))->assertSee('All 1 done. Day won.');
    }

    public function test_when_it_ends_everyone_hears_their_finish_and_finishers_earn_the_badge(): void
    {
        Notification::fake();
        $challenge = Challenge::factory()->for($this->avery, 'creator')->create(['title' => 'No sugar', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-18', 'target_days' => 2]);
        $sam = User::factory()->create();
        $challenge->members()->create(['user_id' => $this->avery->id]);
        $challenge->members()->create(['user_id' => $sam->id]);
        $challenge->checkIns()->create(['user_id' => $this->avery->id, 'checked_on' => '2026-10-15']);
        $challenge->checkIns()->create(['user_id' => $this->avery->id, 'checked_on' => '2026-10-16']);
        $challenge->checkIns()->create(['user_id' => $sam->id, 'checked_on' => '2026-10-16']);

        $this->assertSame(1, app(ChallengeWrapUp::class)->run());
        $this->assertSame(0, app(ChallengeWrapUp::class)->run()); // Only once.

        Notification::assertSentTo($this->avery, ChallengeFinished::class, fn (ChallengeFinished $finished) => $finished->text === '“No sugar” is over. You finished #1 of 2 with 2 of 2 days. Challenge complete!');
        Notification::assertSentTo($sam, ChallengeFinished::class, fn (ChallengeFinished $finished) => $finished->text === '“No sugar” is over. You finished #2 of 2 with 1 of 2 days.');
        $this->assertTrue($this->avery->badges()->where('badge', Badge::ChallengeFinisher)->exists());
        $this->assertFalse($sam->badges()->where('badge', Badge::ChallengeFinisher)->exists());
    }

    public function test_the_creator_can_call_it_off_before_it_starts_and_members_can_leave(): void
    {
        $upcoming = Challenge::factory()->for($this->avery, 'creator')->create(['starts_on' => '2026-10-26', 'ends_on' => '2026-11-01']);
        $upcoming->members()->create(['user_id' => $this->avery->id]);
        $this->actingAs($this->avery)->delete(route('challenges.destroy', $upcoming))->assertRedirect(route('leagues.index'));
        $this->assertModelMissing($upcoming);

        $running = Challenge::factory()->for($this->avery, 'creator')->create();
        $sam = User::factory()->create();
        $running->members()->create(['user_id' => $this->avery->id]);
        $running->members()->create(['user_id' => $sam->id]);
        $this->actingAs($this->avery)->delete(route('challenges.destroy', $running))->assertForbidden();

        $this->actingAs($sam)->delete(route('challenges.leave', $running));
        $this->assertSame(1, $running->members()->count());
    }

    public function test_you_can_check_in_right_from_the_challenge_card(): void
    {
        $challenge = Challenge::factory()->for($this->avery, 'creator')->create(['title' => 'Cold showers', 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25']);
        $challenge->members()->create(['user_id' => $this->avery->id]);

        $this->actingAs($this->avery)->get(route('leagues.index'))->assertSee('I did it today')->assertSee('0/7');
        $this->actingAs($this->avery)->post(route('challenges.check-in', $challenge), ['return' => 'list'])->assertRedirect(route('leagues.index'));
        $this->actingAs($this->avery)->get(route('leagues.index'))->assertSee('Done today')->assertSee('1/7');
    }

    public function test_the_goalleagues_page_lists_challenges(): void
    {
        $challenge = Challenge::factory()->for($this->avery, 'creator')->create(['title' => 'Read 20 pages a day']);
        $challenge->members()->create(['user_id' => $this->avery->id]);

        $this->actingAs($this->avery)->get(route('leagues.index'))->assertSee('Challenges')->assertSee('Read 20 pages a day');
    }
}
