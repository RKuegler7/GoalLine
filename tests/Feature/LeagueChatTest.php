<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\League;
use App\Models\LeagueMessage;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\LeagueTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeagueChatTest extends TestCase
{
    use RefreshDatabase;

    private League $league;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->league = League::factory()->create();
        $this->league->members()->create(['user_id' => $this->league->commissioner_id]);
        $this->member = User::factory()->create(['name' => 'Sam Okafor']);
        $this->league->members()->create(['user_id' => $this->member->id]);
    }

    public function test_members_can_post_and_see_messages(): void
    {
        $this->actingAs($this->member)
            ->post(route('leagues.messages.store', $this->league), ['body' => 'Who is ready for kickoff?'])
            ->assertRedirect(route('leagues.show', $this->league).'#chat');

        $this->actingAs($this->league->commissioner)
            ->get(route('leagues.show', $this->league))
            ->assertSee('League chat')
            ->assertSee('Who is ready for kickoff?')
            ->assertSee('Sam Okafor');
    }

    public function test_sending_from_the_page_returns_the_new_messages_as_html(): void
    {
        $earlier = $this->league->messages()->create(['user_id' => $this->member->id, 'body' => 'Earlier message']);

        $this->actingAs($this->member)
            ->post(route('leagues.messages.store', $this->league).'?after='.$earlier->id, ['body' => 'Fresh one'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Fresh one')
            ->assertDontSee('Earlier message')
            ->assertHeader('X-Last-Message-Id', (string) LeagueMessage::max('id'));
    }

    public function test_polling_returns_only_newer_messages(): void
    {
        $first = $this->league->messages()->create(['user_id' => $this->member->id, 'body' => 'First']);
        $this->league->messages()->create(['user_id' => $this->member->id, 'body' => 'Second']);

        $this->actingAs($this->league->commissioner)
            ->get(route('leagues.messages.index', $this->league).'?after='.$first->id)
            ->assertOk()
            ->assertSee('Second')
            ->assertDontSee('First');
    }

    public function test_non_members_cannot_read_or_post(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('leagues.messages.index', $this->league))->assertForbidden();
        $this->actingAs($outsider)->post(route('leagues.messages.store', $this->league), ['body' => 'Let me in'])->assertForbidden();
    }

    public function test_messages_must_have_text_and_stay_under_the_limit(): void
    {
        $this->actingAs($this->member)
            ->post(route('leagues.messages.store', $this->league), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($this->member)
            ->post(route('leagues.messages.store', $this->league), ['body' => str_repeat('a', LeagueMessage::MAX_LENGTH + 1)])
            ->assertSessionHasErrors('body');
    }

    public function test_authors_and_the_commissioner_can_delete_messages_but_others_cannot(): void
    {
        $third = User::factory()->create();
        $this->league->members()->create(['user_id' => $third->id]);
        $mine = $this->league->messages()->create(['user_id' => $this->member->id, 'body' => 'Mine']);
        $another = $this->league->messages()->create(['user_id' => $this->member->id, 'body' => 'Another']);

        $this->actingAs($third)->delete(route('leagues.messages.destroy', $mine))->assertForbidden();

        $this->actingAs($this->member)->delete(route('leagues.messages.destroy', $mine));
        $this->assertModelMissing($mine);

        $this->actingAs($this->league->commissioner)->delete(route('leagues.messages.destroy', $another));
        $this->assertModelMissing($another);
    }

    public function test_joining_posts_a_league_update(): void
    {
        $joiner = Goal::factory()->create();
        $joiner->user->update(['name' => 'Priya Shah']);

        $this->actingAs($joiner->user)->post(route('leagues.join.store', $this->league->invite_code), ['goal_id' => $joiner->id]);

        $update = $this->league->messages()->where('is_system', true)->latest('id')->first();
        $this->assertSame('Priya Shah joined the league.', $update->body);
    }

    public function test_a_week_going_final_posts_the_top_scorer(): void
    {
        $this->travelTo('2026-10-01');
        $league = League::factory()->create(['starts_on' => '2026-10-05', 'ends_on' => '2026-10-11']);

        foreach (['Jordan Rivera' => true, 'Sam Okafor' => false] as $name => $hitMilestone) {
            $user = User::factory()->create(['name' => $name]);
            $goal = Goal::factory()->for($user)->create(['target_date' => '2026-12-31']);
            $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
            $league->members()->create(['user_id' => $user->id, 'goal_id' => $goal->id]);

            $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-05', 'ends_on' => '2026-10-11', 'focus' => 'F', 'milestone' => 'M']);
            $week->forceFill(['milestone_hit_at' => $hitMilestone ? now() : null])->save(); // With it: 3 of 3, in line (1) + win (2).
            $week->tasks()->create(['position' => 1, 'description' => 'T'])->forceFill(['completed_at' => now()])->save();
        }

        $this->travelTo('2026-10-20');
        (new LeagueTable($league->fresh()))->settle();

        $this->assertSame('Week 1 is final. Top score: Jordan Rivera with 3 points.', $league->messages()->where('is_system', true)->latest('id')->value('body'));
    }

    public function test_unread_counts_others_messages_since_the_last_visit(): void
    {
        $this->actingAs($this->league->commissioner)->get(route('leagues.show', $this->league));
        $this->league->messages()->create(['user_id' => $this->member->id, 'body' => 'You up?']);
        $this->league->messages()->create(['user_id' => $this->league->commissioner_id, 'body' => 'My own message']);

        $membership = $this->league->fresh()->memberFor($this->league->commissioner);
        $this->assertSame(1, $membership->unreadCount());

        $this->actingAs($this->league->commissioner)->get(route('leagues.index'))->assertSee('1 new');

        $this->actingAs($this->league->commissioner)->get(route('leagues.show', $this->league));
        $this->assertSame(0, $this->league->fresh()->memberFor($this->league->commissioner)->unreadCount());
    }
}
