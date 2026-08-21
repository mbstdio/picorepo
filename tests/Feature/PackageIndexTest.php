<?php

namespace Tests\Feature;

use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_member_is_redirected_to_the_repository_package_list(): void
    {
        $user = User::factory()->create();
        $repository = Repository::create([
            'name' => 'acme',
            'type' => 'private',
        ]);
        $repository->users()->attach($user, ['role' => 'maintainer']);

        $response = $this->actingAs($user)
            ->get(route('repositories.packages.index', $repository));

        $response->assertRedirect(route('repositories.show', $repository));
    }

    public function test_user_without_repository_access_cannot_view_the_package_list(): void
    {
        $user = User::factory()->create();
        $repository = Repository::create([
            'name' => 'acme',
            'type' => 'private',
        ]);

        $response = $this->actingAs($user)
            ->get(route('repositories.packages.index', $repository));

        $response->assertForbidden();
    }
}
