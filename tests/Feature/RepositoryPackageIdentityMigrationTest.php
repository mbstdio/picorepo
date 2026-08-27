<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Repository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RepositoryPackageIdentityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_normalizes_existing_package_names_and_allows_the_same_name_in_another_repository(): void
    {
        $this->restoreLegacyPackageUniqueness();
        $firstRepository = Repository::create(['name' => 'Acme', 'type' => 'private']);
        $secondRepository = Repository::create(['name' => 'Other', 'type' => 'private']);
        Package::create(['repository_id' => $firstRepository->id, 'name' => 'Demo_Package']);

        $this->identityMigration()->up();

        $this->assertDatabaseHas('repositories', ['id' => $firstRepository->id, 'name' => 'acme', 'slug' => 'acme']);
        $this->assertDatabaseHas('packages', ['repository_id' => $firstRepository->id, 'name' => 'demo_package']);

        Package::create(['repository_id' => $secondRepository->id, 'name' => 'demo_package']);
        $this->assertDatabaseCount('packages', 2);
    }

    public function test_migration_rejects_invalid_legacy_composer_names(): void
    {
        $this->restoreLegacyPackageUniqueness();
        $repository = Repository::create(['name' => 'acme', 'type' => 'private']);
        Package::create(['repository_id' => $repository->id, 'name' => 'invalid---package']);

        $this->expectException(\RuntimeException::class);

        $this->identityMigration()->up();
    }

    public function test_migration_rejects_legacy_repository_slug_collisions(): void
    {
        Repository::create(['name' => 'acme_inc', 'type' => 'private']);
        DB::table('repositories')->insert([
            'name' => 'acme-inc',
            'slug' => 'legacy-acme-inc',
            'type' => 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->identityMigration()->up();
    }

    public function test_migration_rejects_package_names_that_collide_after_normalization(): void
    {
        Schema::table('packages', fn (Blueprint $table) => $table->dropUnique(['repository_id', 'name']));
        $repository = Repository::create(['name' => 'acme', 'type' => 'private']);
        Package::create(['repository_id' => $repository->id, 'name' => 'Demo']);
        Package::create(['repository_id' => $repository->id, 'name' => 'demo']);

        $this->expectException(\RuntimeException::class);

        $this->identityMigration()->up();
    }

    public function test_migration_handles_legacy_slugs_that_temporarily_conflict_with_canonical_slugs(): void
    {
        $this->restoreLegacyPackageUniqueness();
        $acme = Repository::create(['name' => 'acme', 'type' => 'private']);
        $beta = Repository::create(['name' => 'beta', 'type' => 'private']);
        DB::table('repositories')->where('id', $acme->id)->update(['slug' => 'temporary']);
        DB::table('repositories')->where('id', $beta->id)->update(['slug' => 'acme']);
        DB::table('repositories')->where('id', $acme->id)->update(['slug' => 'beta']);

        $this->identityMigration()->up();

        $this->assertDatabaseHas('repositories', ['id' => $acme->id, 'slug' => 'acme']);
        $this->assertDatabaseHas('repositories', ['id' => $beta->id, 'slug' => 'beta']);
    }

    private function restoreLegacyPackageUniqueness(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropUnique(['repository_id', 'name']);
            $table->unique('name');
        });
    }

    private function identityMigration(): object
    {
        return require database_path('migrations/2026_08_27_000000_normalize_repository_and_package_identities.php');
    }
}
