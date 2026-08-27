<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        config(['registration.enabled' => true]);

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        config(['registration.enabled' => true]);
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        Notification::assertSentTo(User::first(), VerifyEmail::class);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_is_not_available_when_disabled(): void
    {
        config(['registration.enabled' => false]);

        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_attempts_are_throttled(): void
    {
        config(['registration.enabled' => true, 'registration.max_attempts' => 1]);

        $this->post('/register', [])->assertSessionHasErrors(['name', 'email', 'password']);
        $this->post('/register', [])->assertTooManyRequests();
    }
}
