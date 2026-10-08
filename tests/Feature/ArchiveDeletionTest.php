<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ArchiveDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_version_removes_its_local_archive_and_record(): void
    {
        Storage::fake('local');
        [$user, $repository, $package, $version] = $this->versionWithArchive('local');

        $response = $this->actingAs($user)
            ->delete(route('repositories.packages.versions.destroy', [$repository, $package, $version]));

        $response->assertRedirect(route('repositories.packages.show', [$repository, $package]));
        Storage::disk('local')->assertMissing($version->zip_path);
        $this->assertDatabaseMissing('package_versions', ['id' => $version->id]);
    }

    public function test_deleting_a_version_removes_its_remote_archive_and_record(): void
    {
        Storage::fake('s3');
        [$user, $repository, $package, $version] = $this->versionWithArchive('s3');

        $response = $this->actingAs($user)
            ->delete(route('repositories.packages.versions.destroy', [$repository, $package, $version]));

        $response->assertRedirect(route('repositories.packages.show', [$repository, $package]));
        Storage::disk('s3')->assertMissing($version->zip_path);
        $this->assertDatabaseMissing('package_versions', ['id' => $version->id]);
    }

    public function test_deleting_a_package_removes_every_contained_archive_and_record(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        [$user, $repository, $package] = $this->packageWithArchives();

        $response = $this->actingAs($user)
            ->delete(route('repositories.packages.destroy', [$repository, $package]));

        $response->assertRedirect(route('repositories.show', $repository));
        Storage::disk('local')->assertDirectoryEmpty('packages');
        Storage::disk('s3')->assertDirectoryEmpty('packages');
        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
        $this->assertDatabaseCount('package_versions', 0);
    }

    public function test_deleting_a_repository_removes_every_contained_archive_and_record(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        [$user, $repository] = $this->repositoryWithArchives();

        $response = $this->actingAs($user)
            ->delete(route('repositories.destroy', $repository));

        $response->assertRedirect(route('repositories.index'));
        Storage::disk('local')->assertDirectoryEmpty('packages');
        Storage::disk('s3')->assertDirectoryEmpty('packages');
        $this->assertDatabaseMissing('repositories', ['id' => $repository->id]);
        $this->assertDatabaseCount('packages', 0);
        $this->assertDatabaseCount('package_versions', 0);
    }

    public function test_a_failed_version_archive_cleanup_keeps_the_record_and_can_be_retried(): void
    {
        [$user, $repository, $package, $version] = $this->versionWithArchive('local', false);
        $disk = Mockery::mock();
        $disk->shouldReceive('delete')->once()->with($version->zip_path)->andThrow(new \RuntimeException('Storage unavailable.'));
        $disk->shouldReceive('delete')->once()->with($version->zip_path)->andReturnTrue();
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $response = $this->actingAs($user)
            ->delete(route('repositories.packages.versions.destroy', [$repository, $package, $version]));

        $response->assertSessionHasErrors('archive');
        $this->assertDatabaseHas('package_versions', ['id' => $version->id]);

        $response = $this->actingAs($user)
            ->delete(route('repositories.packages.versions.destroy', [$repository, $package, $version]));

        $response->assertRedirect(route('repositories.packages.show', [$repository, $package]));
        $this->assertDatabaseMissing('package_versions', ['id' => $version->id]);
    }

    public function test_a_failed_package_archive_cleanup_keeps_records_and_can_be_retried(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        [$user, $repository, $package] = $this->packageWithArchives();
        $local = Storage::disk('local');
        $s3 = Mockery::mock();
        $s3->shouldReceive('delete')->once()->with('packages/example-package/2.0.0.zip')->andThrow(new \RuntimeException('Storage unavailable.'));
        $s3->shouldReceive('delete')->once()->with('packages/example-package/2.0.0.zip')->andReturnTrue();
        Storage::shouldReceive('disk')->with('local')->andReturn($local);
        Storage::shouldReceive('disk')->with('s3')->andReturn($s3);

        $response = $this->actingAs($user)
            ->delete(route('repositories.packages.destroy', [$repository, $package]));

        $response->assertSessionHasErrors('archive');
        $this->assertDatabaseHas('packages', ['id' => $package->id]);
        $this->assertDatabaseCount('package_versions', 2);

        $response = $this->actingAs($user)
            ->delete(route('repositories.packages.destroy', [$repository, $package]));

        $response->assertRedirect(route('repositories.show', $repository));
        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
    }

    public function test_a_failed_repository_archive_cleanup_keeps_records_and_can_be_retried(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        [$user, $repository] = $this->repositoryWithArchives();
        $local = Storage::disk('local');
        $s3 = Mockery::mock();
        $s3->shouldReceive('delete')->once()->with('packages/example-package/2.0.0.zip')->andThrow(new \RuntimeException('Storage unavailable.'));
        $s3->shouldReceive('delete')->once()->with('packages/example-package/2.0.0.zip')->andReturnTrue();
        // This archive may be deleted before the failure, then again on retry.
        $s3->shouldReceive('delete')->between(1, 2)->with('packages/another-package/2.0.0.zip')->andReturnTrue();
        Storage::shouldReceive('disk')->with('local')->andReturn($local);
        Storage::shouldReceive('disk')->with('s3')->andReturn($s3);

        $response = $this->actingAs($user)
            ->delete(route('repositories.destroy', $repository));

        $response->assertSessionHasErrors('archive');
        $this->assertDatabaseHas('repositories', ['id' => $repository->id]);
        $this->assertDatabaseCount('packages', 2);
        $this->assertDatabaseCount('package_versions', 4);

        $response = $this->actingAs($user)
            ->delete(route('repositories.destroy', $repository));

        $response->assertRedirect(route('repositories.index'));
        $this->assertDatabaseMissing('repositories', ['id' => $repository->id]);
    }

    private function versionWithArchive(string $disk, bool $storeArchive = true): array
    {
        $user = User::factory()->create();
        $repository = Repository::create(['name' => 'acme', 'type' => 'private']);
        $repository->users()->attach($user, ['role' => 'owner']);
        $package = $repository->packages()->create(['name' => 'example-package']);
        $version = $this->addVersion($package, $disk, '1.0.0', $storeArchive);

        return [$user, $repository, $package, $version];
    }

    private function packageWithArchives(): array
    {
        [$user, $repository, $package] = $this->versionWithArchive('local');
        $this->addVersion($package, 's3', '2.0.0');

        return [$user, $repository, $package];
    }

    private function repositoryWithArchives(): array
    {
        [$user, $repository, $package] = $this->packageWithArchives();
        $secondPackage = $repository->packages()->create(['name' => 'another-package']);
        $this->addVersion($secondPackage, 'local', '1.0.0');
        $this->addVersion($secondPackage, 's3', '2.0.0');

        return [$user, $repository];
    }

    private function addVersion(Package $package, string $disk, string $number, bool $storeArchive = true): PackageVersion
    {
        $path = "packages/{$package->name}/{$number}.zip";

        if ($storeArchive) {
            Storage::disk($disk)->put($path, 'archive');
        }

        return $package->versions()->create([
            'version' => $number,
            'type' => 'library',
            'disk' => $disk,
            'zip_path' => $path,
        ]);
    }
}
