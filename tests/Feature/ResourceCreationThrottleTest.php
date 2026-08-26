<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceCreationThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_creation_attempts_are_throttled(): void
    {
        config(['registration.resource_creation_max_attempts' => 1]);
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->post(route('repositories.store'), [])
            ->assertSessionHasErrors(['name', 'type']);
        $this->actingAs($user)->post(route('repositories.store'), [])
            ->assertTooManyRequests();
    }
}
