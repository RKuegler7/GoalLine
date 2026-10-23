<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\RoadmapStatus;
use App\Services\KickoffChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class KickoffAndFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_members_see_the_kickoff_checklist_with_steps_checked_off_as_they_go(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('dashboard'))->assertSee('Your kickoff')->assertSee('0 of 8 done');

        $goal = Goal::factory()->for($member)->create();
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $goal->habits()->create(['title' => 'Run', 'times_per_week' => 3]);
        $this->actingAs($member)->post(route('usage.installed'))->assertNoContent();
        $this->actingAs($member)->post(route('usage.installed')); // Counted once.

        $done = collect((new KickoffChecklist($member->fresh()))->steps())->where('done', true)->pluck('key')->all();
        $this->assertSame(['goal', 'roadmap', 'habit', 'install'], $done);
        $this->assertSame(1, DB::table('usage_events')->where('name', 'app-installed')->count());
        $this->actingAs($member)->get(route('dashboard'))->assertSee('4 of 8 done');
    }

    public function test_the_checklist_can_be_hidden_and_is_only_for_new_members(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->post(route('kickoff.dismiss'))->assertRedirect(route('dashboard'));
        $this->actingAs($member)->get(route('dashboard'))->assertDontSee('Your kickoff');

        $veteran = User::factory()->create(['created_at' => now()->subDays(KickoffChecklist::SHOW_FOR_DAYS + 1)]);
        $this->actingAs($veteran)->get(route('dashboard'))->assertDontSee('Your kickoff');
    }

    public function test_beta_testers_can_send_feedback_that_reaches_the_admins(): void
    {
        Notification::fake();
        config(['goalline.beta.invite_only' => true, 'goalline.admin_emails' => ['admin@goalline.app']]);
        $tester = User::factory()->create(['name' => 'Sam Okafor']);

        $this->actingAs($tester)->get(route('dashboard'))->assertSee('Send feedback');
        $this->actingAs($tester)->from(route('planner.show'))
            ->post(route('feedback.store'), ['body' => 'Auto-plan put everything on Sunday.', 'page' => '/plan'])
            ->assertRedirect(route('planner.show'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('feedback', ['user_id' => $tester->id, 'page' => '/plan']);
        Notification::assertSentOnDemand(AdminAlert::class, fn (AdminAlert $alert) => $alert->headline === 'Beta feedback from Sam Okafor'
            && str_contains($alert->details, 'Auto-plan put everything on Sunday.'));
    }

    public function test_admins_see_feedback_and_can_mark_it_handled(): void
    {
        config(['goalline.admin_emails' => ['admin@goalline.app']]);
        $admin = User::factory()->create(['email' => 'admin@goalline.app']);
        $id = DB::table('feedback')->insertGetId(['user_id' => User::factory()->create()->id, 'body' => 'Love the streaks.', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($admin)->get(route('admin.index'))->assertSee('Love the streaks.')->assertSee('1 open');
        $this->actingAs($admin)->post(route('admin.feedback.resolve', $id))->assertRedirect();
        $this->assertNotNull(DB::table('feedback')->where('id', $id)->value('resolved_at'));

        $this->actingAs(User::factory()->create())->post(route('admin.feedback.resolve', $id))->assertForbidden();
    }
}
