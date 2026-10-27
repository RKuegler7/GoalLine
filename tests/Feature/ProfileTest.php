<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\ProfileStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_members_get_a_username_from_their_name(): void
    {
        $first = User::factory()->create(['name' => 'Jordan Rivera']);
        $second = User::factory()->create(['name' => 'Jordan Rivera']);

        $this->assertSame('jordan.rivera', $first->username);
        $this->assertMatchesRegularExpression('/^jordan\.rivera\d{4}$/', $second->username);
    }

    public function test_profile_shows_identity_and_stats(): void
    {
        $user = User::factory()->create(['name' => 'Jordan Rivera', 'bio' => 'Chasing the CPA.', 'location' => 'Washington, DC']);
        $this->goalWithResults($user, ['past', 'in_line']);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Jordan Rivera')
            ->assertSee('@jordan.rivera')
            ->assertSee('Chasing the CPA.')
            ->assertSee('Washington, DC')
            ->assertSee('In-line streak')
            ->assertSee('League record')
            ->assertSee('Trophy case');
    }

    public function test_guests_cannot_see_profiles(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));
    }

    public function test_stats_count_points_goals_streaks_and_form(): void
    {
        $user = User::factory()->create();
        $this->goalWithResults($user, ['past', 'short', 'in_line', 'smashed', 'past']);
        $completed = Goal::factory()->for($user)->create();
        $completed->complete('Done!');

        $stats = new ProfileStats($user->fresh());

        $this->assertSame(2, $stats->goalsStarted());
        $this->assertSame(1, $stats->completedGoals()->count());
        $this->assertSame(2 + 0 + 1 + 3 + 2, $stats->points());
        $this->assertSame(3, $stats->currentStreak());
        $this->assertSame(3, $stats->bestStreak());
        $this->assertSame(['short' => 1, 'in_line' => 1, 'past' => 2, 'smashed' => 1], $stats->resultCounts());
        $this->assertSame(5, $stats->form()->count());
    }

    public function test_member_can_edit_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Jordan Rivera',
            'username' => '@Jordan_R',
            'bio' => 'One goal at a time.',
            'location' => 'DC',
        ])->assertRedirect(route('profile.show'));

        $user->refresh();
        $this->assertSame('jordan_r', $user->username);
        $this->assertSame('One goal at a time.', $user->bio);
    }

    public function test_usernames_must_be_unique_and_well_formed(): void
    {
        User::factory()->create(['username' => 'taken']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => 'X', 'username' => 'taken'])
            ->assertSessionHasErrors('username');

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => 'X', 'username' => 'no spaces!'])
            ->assertSessionHasErrors('username');
    }

    public function test_member_can_upload_and_remove_a_profile_photo(): void
    {
        Storage::fake(User::AVATAR_DISK);
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'avatar' => $this->fakeImage('me.png'),
        ]);

        $path = $user->fresh()->avatar_path;
        Storage::disk(User::AVATAR_DISK)->assertExists($path);
        $this->actingAs($user)->get(route('avatars.show', $user))->assertOk();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'remove_avatar' => '1',
        ]);

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk(User::AVATAR_DISK)->assertMissing($path);
    }

    public function test_profile_photos_must_be_images_under_2_mb(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => UploadedFile::fake()->create('me.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('avatar');
    }

    /**
     * Give the user a goal whose finished GoalWeeks landed on the given results, oldest first.
     *
     * @param  list<string>  $results
     */
    private function goalWithResults(User $user, array $results): Goal
    {
        $goal = Goal::factory()->for($user)->create(['target_date' => today()->addMonth()]);
        $goal->forceFill(['roadmap_status' => RoadmapStatus::Ready])->save();
        $start = today()->subWeeks(count($results) + 1);

        foreach ($results as $index => $result) {
            // 2 planned tasks + the milestone = 4 planned points; bonus tasks are 1 point each.
            [$planned, $done, $bonus, $milestone] = match ($result) {
                'short' => [2, 0, 0, false],   // 0 of 4
                'in_line' => [2, 2, 0, true],  // 4 of 4
                'past' => [2, 2, 1, true],     // 5 of 4
                'smashed' => [2, 2, 2, true],  // 6 of 4, with proof below
            };

            $week = $goal->weeks()->create([
                'position' => $index + 1,
                'starts_on' => $start->copy()->addWeeks($index),
                'ends_on' => $start->copy()->addWeeks($index)->addDays(6),
                'focus' => 'Focus',
                'milestone' => 'Milestone',
            ]);
            $week->forceFill(['milestone_hit_at' => $milestone ? now() : null])->save();

            foreach (range(1, $planned + $bonus) as $position) {
                $week->tasks()->create(['position' => $position, 'description' => "Task {$position}", 'is_bonus' => $position > $planned])
                    ->forceFill([
                        'completed_at' => $position <= $done || $position > $planned ? now() : null,
                        'proof_path' => $result === 'smashed' && $position === 1 ? 'proofs/test.png' : null,
                    ])->save();
            }
        }

        return $goal;
    }
}
