<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_renders(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('Create your account');
    }

    public function test_new_user_can_register_and_is_logged_in(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Jordan Rivera',
            'email' => 'jordan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('welcome.show'));

        $user = User::firstWhere('email', 'jordan@example.com');
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_requires_matching_passwords(): void
    {
        $this->post(route('register'), [
            'name' => 'Jordan Rivera',
            'email' => 'jordan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_registration_rejects_an_email_that_is_already_taken(): void
    {
        User::factory()->create(['email' => 'jordan@example.com']);

        $this->post(route('register'), [
            'name' => 'Jordan Rivera',
            'email' => 'jordan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
