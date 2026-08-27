<?php

namespace Tests\Feature;

use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryPackageIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_and_package_names_are_normalized_to_canonical_composer_coordinates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('repositories.store'), [
                'name' => 'Acme_Inc',
                'type' => 'private',
            ])
            ->assertRedirect();

        $repository = Repository::sole();
        $this->assertSame('acme_inc', $repository->name);
        $this->assertSame('acme-inc', $repository->slug);

        $this->actingAs($user)
            ->post(route('repositories.packages.store', $repository), [
                'name' => 'Demo_Package',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('packages', [
            'repository_id' => $repository->id,
            'name' => 'demo_package',
        ]);
    }

    public function test_package_name_must_be_a_valid_composer_name_segment(): void
    {
        [$user, $repository] = $this->repositoryForUser();

        $this->actingAs($user)
            ->post(route('repositories.packages.store', $repository), [
                'name' => 'invalid---package',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_package_names_are_unique_within_their_repository(): void
    {
        [$user, $firstRepository] = $this->repositoryForUser('first');
        [, $secondRepository] = $this->repositoryForUser('second');
        $secondRepository->users()->attach($user, ['role' => 'maintainer']);

        $firstRepository->packages()->create(['name' => 'demo']);

        $this->actingAs($user)
            ->post(route('repositories.packages.store', $secondRepository), [
                'name' => 'demo',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $secondRepository->packages()->create(['name' => 'other']);

        $this->actingAs($user)
            ->put(route('repositories.packages.update', [$secondRepository, $secondRepository->packages()->where('name', 'demo')->sole()]), [
                'name' => 'other',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_repository_name_is_rejected_when_its_generated_slug_collides(): void
    {
        [$user] = $this->repositoryForUser('acme_inc');

        $this->actingAs($user)
            ->post(route('repositories.store'), [
                'name' => 'acme-inc',
                'type' => 'private',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_published_repository_name_cannot_be_renamed(): void
    {
        [$user, $repository] = $this->repositoryForUser('acme');
        $package = $repository->packages()->create(['name' => 'demo']);
        $package->versions()->create([
            'version' => '1.0.0',
            'type' => 'library',
            'disk' => 'local',
            'zip_path' => 'packages/demo.zip',
        ]);

        $this->actingAs($user)
            ->put(route('repositories.update', $repository), [
                'name' => 'renamed',
                'type' => 'private',
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('repositories', [
            'id' => $repository->id,
            'name' => 'acme',
            'slug' => 'acme',
        ]);
    }

    private function repositoryForUser(string $name = 'acme'): array
    {
        $user = User::factory()->create();
        $repository = Repository::create([
            'name' => $name,
            'type' => 'private',
        ]);
        $repository->users()->attach($user, ['role' => 'maintainer']);

        return [$user, $repository];
    }
}
