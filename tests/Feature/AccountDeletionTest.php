<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Highlight;
use App\Models\PlanMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_an_account_removes_its_data_and_files(): void
    {
        Storage::fake('uploads');
        $user = User::factory()->create();
        $user->replaceAvatar($this->fakeImage('me.png')->store('avatars', 'uploads'));
        $goal = Goal::factory()->for($user)->create();
        $material = PlanMaterial::storeUpload($goal, UploadedFile::fake()->create('plan.pdf', 50, 'application/pdf'), null);
        $highlight = Highlight::factory()->for($user)->create();

        $this->actingAs($user)->get(route('account.delete'))->assertOk()->assertSee('Delete your account?');
        $this->actingAs($user)->delete(route('account.destroy'), ['password' => 'password'])->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertModelMissing($user);
        $this->assertModelMissing($goal);
        $this->assertModelMissing($highlight);
        Storage::disk('uploads')->assertMissing($material->path);
        Storage::disk('uploads')->assertMissing($user->avatar_path);
    }

    public function test_the_password_must_match(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete(route('account.destroy'), ['password' => 'wrong'])->assertSessionHasErrors('password');

        $this->assertModelExists($user);
    }
}
