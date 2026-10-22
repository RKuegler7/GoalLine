<?php

namespace Tests\Feature;

use App\Mentions;
use App\Models\Highlight;
use App\Models\League;
use App\Models\User;
use App\Notifications\HighlightCommented;
use App\Notifications\Mentioned;
use App\ProfileVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    private Highlight $highlight;

    private User $owner;

    private User $fan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Jordan Rivera', 'username' => 'jordan', 'profile_visibility' => ProfileVisibility::Everyone]);
        $this->highlight = Highlight::factory()->for($this->owner)->create(['headline' => 'Ran a half marathon']);
        $this->fan = User::factory()->create(['name' => 'Sam Okafor', 'username' => 'sam']);
        $this->fan->following()->attach($this->owner);
    }

    public function test_followers_can_comment_and_the_author_hears_about_it(): void
    {
        Notification::fake();

        $this->actingAs($this->fan)
            ->from(route('highlights.index'))
            ->post(route('highlights.comments.store', $this->highlight), ['body' => 'Huge. What was your pace?'])
            ->assertRedirect(route('highlights.index').'#highlight-'.$this->highlight->id);

        Notification::assertSentTo($this->owner, HighlightCommented::class);
        $this->actingAs($this->fan)->get(route('highlights.index'))->assertSee('Huge. What was your pace?');
    }

    public function test_people_who_cannot_see_a_highlight_cannot_comment(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('highlights.comments.store', $this->highlight), ['body' => 'Hi'])
            ->assertForbidden();

        $this->assertSame(0, $this->highlight->comments()->count());
    }

    public function test_mentions_in_comments_notify_people_who_can_see_the_highlight(): void
    {
        Notification::fake();
        $friend = User::factory()->create(['username' => 'avery']);
        $friend->following()->attach($this->owner);
        $stranger = User::factory()->create(['username' => 'drew']);

        $this->actingAs($this->fan)->post(route('highlights.comments.store', $this->highlight), ['body' => '@avery you have to see this. @drew too.']);

        Notification::assertSentTo($friend, Mentioned::class, fn (Mentioned $mention) => $mention->where === 'in a comment');
        Notification::assertNothingSentTo($stranger);
    }

    public function test_comments_can_be_removed_by_their_author_or_the_highlights_author_only(): void
    {
        $comment = $this->highlight->comments()->create(['user_id' => $this->fan->id, 'body' => 'Nice']);
        $another = $this->highlight->comments()->create(['user_id' => $this->fan->id, 'body' => 'Again']);

        $this->actingAs(User::factory()->create())->delete(route('highlights.comments.destroy', $comment))->assertForbidden();
        $this->actingAs($this->fan)->delete(route('highlights.comments.destroy', $comment))->assertRedirect();
        $this->actingAs($this->owner)->delete(route('highlights.comments.destroy', $another))->assertRedirect();

        $this->assertSame(0, $this->highlight->comments()->count());
    }

    public function test_mentions_in_league_chat_notify_members(): void
    {
        Notification::fake();
        $league = League::factory()->create(['name' => 'Launch Crew']);
        $league->members()->create(['user_id' => $this->owner->id]);
        $league->members()->create(['user_id' => $this->fan->id]);
        $outsider = User::factory()->create(['username' => 'casey']);

        $this->actingAs($this->fan)->post(route('leagues.messages.store', $league), ['body' => '@jordan you are going down this week. @casey']);

        Notification::assertSentTo($this->owner, Mentioned::class, fn (Mentioned $mention) => $mention->where === 'in Launch Crew'
            && $mention->url === route('leagues.show', $league).'#chat');
        Notification::assertNothingSentTo($outsider);
    }

    public function test_mentions_link_to_real_members_and_everything_else_stays_escaped(): void
    {
        $html = (string) Mentions::render('Go @jordan. Email me at me@example.com <b>now</b> @nobody');

        $this->assertStringContainsString('<a href="'.route('profiles.show', 'jordan').'"', $html);
        $this->assertStringContainsString('>@jordan</a>.', $html);
        $this->assertStringContainsString('me@example.com', $html);
        $this->assertStringContainsString('&lt;b&gt;now&lt;/b&gt;', $html);
        $this->assertStringContainsString('@nobody', $html);
        $this->assertSame(['jordan', 'nobody'], Mentions::usernames('Go @jordan. Email me at me@example.com @nobody'));
    }
}
