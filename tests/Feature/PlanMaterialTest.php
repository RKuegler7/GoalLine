<?php

namespace Tests\Feature;

use App\Jobs\GenerateRoadmap;
use App\Jobs\ReadPlanMaterial;
use App\Models\Goal;
use App\Models\PlanMaterial;
use App\Models\User;
use App\RoadmapStatus;
use App\Services\RoadmapPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class PlanMaterialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PlanMaterial::DISK);
    }

    public function test_owner_can_upload_a_pdf_and_it_is_queued_for_reading(): void
    {
        Queue::fake();
        $goal = Goal::factory()->create();

        $this->actingAs($goal->user)
            ->post(route('goals.materials.store', $goal), [
                'material' => UploadedFile::fake()->create('becker-far.pdf', 500, 'application/pdf'),
                'material_note' => 'Becker FAR study plan',
            ])
            ->assertRedirect(route('goals.show', $goal).'#materials');

        $material = $goal->materials()->sole();
        $this->assertSame('becker-far.pdf', $material->original_name);
        $this->assertSame('Becker FAR study plan', $material->note);
        $this->assertSame(RoadmapStatus::Pending, $material->status);
        Storage::disk(PlanMaterial::DISK)->assertExists($material->path);
        Queue::assertPushed(ReadPlanMaterial::class, fn (ReadPlanMaterial $job) => ! $job->thenBuildRoadmap);
    }

    public function test_uploads_must_be_a_pdf_or_image_under_10_mb(): void
    {
        Queue::fake();
        $goal = Goal::factory()->create();

        $this->actingAs($goal->user)
            ->post(route('goals.materials.store', $goal), ['material' => UploadedFile::fake()->create('notes.docx', 100)])
            ->assertSessionHasErrors('material');

        $this->actingAs($goal->user)
            ->post(route('goals.materials.store', $goal), ['material' => UploadedFile::fake()->create('huge.pdf', 11_000, 'application/pdf')])
            ->assertSessionHasErrors('material');

        $this->assertSame(0, $goal->materials()->count());
    }

    public function test_a_goal_holds_at_most_five_materials(): void
    {
        Queue::fake();
        $goal = Goal::factory()->create();
        PlanMaterial::factory()->count(PlanMaterial::MAX_PER_GOAL)->for($goal)->create();

        $this->actingAs($goal->user)
            ->post(route('goals.materials.store', $goal), ['material' => $this->fakeImage('plan.png')])
            ->assertSessionHasErrors('material');
    }

    public function test_only_the_owner_can_upload_or_remove(): void
    {
        Queue::fake();
        $material = PlanMaterial::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('goals.materials.store', $material->goal), ['material' => $this->fakeImage('plan.png')])
            ->assertForbidden();

        $this->actingAs($stranger)->delete(route('materials.destroy', $material))->assertForbidden();
        $this->assertModelExists($material);
    }

    public function test_removing_a_material_deletes_its_file(): void
    {
        $goal = Goal::factory()->create();
        $material = PlanMaterial::storeUpload($goal, $this->fakeImage('plan.png'), null);

        $this->actingAs($goal->user)->delete(route('materials.destroy', $material));

        $this->assertModelMissing($material);
        Storage::disk(PlanMaterial::DISK)->assertMissing($material->path);
    }

    public function test_setting_a_goal_with_a_plan_reads_the_plan_before_building_the_roadmap(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('goals.store'), [
            'title' => 'Pass the CPA FAR exam',
            'category' => 'career',
            'target_date' => today()->addWeeks(12)->format('Y-m-d'),
            'material' => UploadedFile::fake()->create('becker.pdf', 300, 'application/pdf'),
        ]);

        $goal = $user->goals()->sole();
        $this->assertSame(RoadmapStatus::Pending, $goal->roadmap_status);
        $this->assertSame(1, $goal->materials()->count());
        Queue::assertPushed(ReadPlanMaterial::class, fn (ReadPlanMaterial $job) => $job->thenBuildRoadmap);
        Queue::assertNotPushed(GenerateRoadmap::class);
    }

    public function test_reading_saves_the_digest_and_then_builds_the_roadmap(): void
    {
        Queue::fake([GenerateRoadmap::class]);
        $material = PlanMaterial::factory()->create();
        $this->mock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('readMaterial')->andReturn([
            'kind' => 'CPA review course study plan',
            'title' => 'Becker FAR 12-Week Plan',
            'summary' => 'Twelve weeks of units, then final review.',
            'structure' => "Weeks 1-2: Conceptual framework\nWeek 12: Final review",
            'requirements' => '15 hours a week',
            'relevant' => true,
        ]));

        (new ReadPlanMaterial($material, thenBuildRoadmap: true))->handle(app(RoadmapPlanner::class));

        $material->refresh();
        $this->assertSame(RoadmapStatus::Ready, $material->status);
        $this->assertSame('Becker FAR 12-Week Plan', $material->digest['title']);
        Queue::assertPushed(GenerateRoadmap::class);
    }

    public function test_the_roadmap_is_still_built_if_reading_fails(): void
    {
        Queue::fake([GenerateRoadmap::class]);
        $material = PlanMaterial::factory()->create();
        $this->mock(RoadmapPlanner::class, fn (MockInterface $mock) => $mock->shouldReceive('readMaterial')->andThrow(new RuntimeException('Unreadable')));

        (new ReadPlanMaterial($material, thenBuildRoadmap: true))->handle(app(RoadmapPlanner::class));

        $this->assertSame(RoadmapStatus::Failed, $material->fresh()->status);
        Queue::assertPushed(GenerateRoadmap::class);
    }

    public function test_goal_page_shows_what_goalline_took_from_a_material(): void
    {
        $material = PlanMaterial::factory()->create();
        $material->forceFill(['status' => RoadmapStatus::Ready, 'digest' => [
            'kind' => 'CPA review course study plan',
            'title' => 'Becker FAR 12-Week Plan',
            'summary' => 'Twelve weeks of units, then final review.',
            'structure' => 'Weeks 1-2: Conceptual framework',
            'requirements' => '15 hours a week',
            'relevant' => true,
        ]])->save();

        $this->actingAs($material->goal->user)
            ->get(route('goals.show', $material->goal))
            ->assertSee('Becker FAR 12-Week Plan')
            ->assertSee('What GoalLine took from it')
            ->assertSee('15 hours a week');
    }
}
