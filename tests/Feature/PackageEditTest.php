<?php

namespace Tests\Feature;

use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_maintainer_can_view_the_package_edit_page(): void
    {
        [$user, $repository, $package] = $this->packageForUser();

        $response = $this->actingAs($user)
            ->get(route('repositories.packages.edit', [$repository, $package]));

        $response->assertOk();
    }

    public function test_repository_maintainer_can_update_a_package(): void
    {
        [$user, $repository, $package] = $this->packageForUser();

        $response = $this->actingAs($user)
            ->put(route('repositories.packages.update', [$repository, $package]), [
                'name' => 'renamed-package',
                'description' => 'An updated description.',
            ]);

        $response->assertRedirect(route('repositories.packages.show', [$repository, $package]));
        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'name' => 'renamed-package',
            'description' => 'An updated description.',
        ]);
    }

    private function packageForUser(): array
    {
        $user = User::factory()->create();
        $repository = Repository::create([
            'name' => 'acme',
            'type' => 'private',
        ]);
        $repository->users()->attach($user, ['role' => 'maintainer']);
        $package = $repository->packages()->create([
            'name' => 'example-package',
            'description' => 'Original description.',
        ]);

        return [$user, $repository, $package];
    }
}
