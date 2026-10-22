<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use App\ProfileVisibility;
use App\Services\ActivityHeatmap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ActivityHeatmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_tasks_and_habits_per_day_on_the_members_clock(): void
    {
        $this->travelTo(Carbon::parse('2026-10-21 12:00', config('app.timezone'))); // A Wednesday.
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create(['target_date' => '2026-12-31']);
        $week = $goal->weeks()->create(['position' => 1, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-25', 'focus' => 'Work', 'milestone' => 'Done.']);
        $habit = $goal->habits()->create(['title' => 'Run', 'times_per_week' => 7]);

        $habit->logs()->create(['logged_on' => '2026-10-19']);
        $habit->logs()->create(['logged_on' => '2026-10-20']);
        $habit->logs()->create(['logged_on' => '2026-10-21']);
        foreach (range(1, 4) as $position) {
            $week->tasks()->create(['position' => $position, 'description' => "Task {$position}"])->forceFill(['completed_at' => '2026-10-21 10:00'])->saveQuietly();
        }

        $heatmap = new ActivityHeatmap($user);
        $thisWeek = last($heatmap->weeks());

        $this->assertCount(ActivityHeatmap::WEEKS, $heatmap->weeks());
        $this->assertSame([1, 1, 5], array_map(fn ($day) => $day['count'], array_slice($thisWeek, 0, 3)));
        $this->assertSame(3, $thisWeek[2]['level']);
        $this->assertNull($thisWeek[3]); // Thursday hasn't happened yet.
        $this->assertSame(7, $heatmap->total());
        $this->assertSame(3, $heatmap->activeDays());
        $this->assertSame(3, $heatmap->longestStreak());
    }

    public function test_lockers_show_the_heatmap(): void
    {
        $user = User::factory()->create(['profile_visibility' => ProfileVisibility::Everyone]);

        $this->actingAs($user)->get(route('profile.show'))->assertSee('A year of check-ins');
        $this->actingAs(User::factory()->create())->get(route('profiles.show', $user))->assertSee('A year of check-ins');
    }
}
