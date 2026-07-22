<?php

namespace Tests\Feature;

use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageVersionEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_maintainer_can_view_the_version_edit_page(): void
    {
        [$user, $repository, $package, $version] = $this->versionForUser();

        $response = $this->actingAs($user)
            ->get(route('repositories.packages.versions.edit', [$repository, $package, $version]));

        $response->assertOk();
    }

    public function test_repository_maintainer_can_update_a_version(): void
    {
        [$user, $repository, $package, $version] = $this->versionForUser();

        $response = $this->actingAs($user)
            ->put(route('repositories.packages.versions.update', [$repository, $package, $version]), [
                'version' => '2.0.0',
                'type' => 'project',
                'description' => 'An updated description.',
            ]);

        $response->assertRedirect(route('repositories.packages.show', [$repository, $package]));
        $this->assertDatabaseHas('package_versions', [
            'id' => $version->id,
            'version' => '2.0.0',
            'type' => 'project',
            'description' => 'An updated description.',
        ]);
    }

    private function versionForUser(): array
    {
        $user = User::factory()->create();
        $repository = Repository::create([
            'name' => 'acme',
            'type' => 'private',
        ]);
        $repository->users()->attach($user, ['role' => 'maintainer']);
        $package = $repository->packages()->create([
            'name' => 'example-package',
        ]);
        $version = $package->versions()->create([
            'version' => '1.0.0',
            'type' => 'library',
            'disk' => 'local',
            'description' => 'Original description.',
        ]);

        return [$user, $repository, $package, $version];
    }
}
