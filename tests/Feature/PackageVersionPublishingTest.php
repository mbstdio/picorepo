<?php

namespace Tests\Feature;

use App\Models\Repository;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

class PackageVersionPublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_version_requires_a_zip_archive_on_every_disk(): void
    {
        [, $repository, $package] = $this->packageForUser();

        $response = $this->publish($repository, $package, [
            'disk' => 's3',
        ]);

        $response->assertSessionHasErrors('zip_file');
        $this->assertDatabaseMissing('package_versions', [
            'package_id' => $package->id,
            'version' => '1.0.0',
        ]);
    }

    public function test_a_version_can_be_published_with_a_zip_to_local_storage(): void
    {
        Storage::fake('local');
        [, $repository, $package] = $this->packageForUser();

        $response = $this->publish($repository, $package, [
            'zip_file' => UploadedFile::fake()->create('package.zip', 100, 'application/zip'),
        ]);

        $response->assertRedirect(route('repositories.packages.show', [$repository, $package]));
        $version = $package->versions()->sole();
        Storage::disk('local')->assertExists($version->zip_path);
    }

    public function test_an_incompletely_configured_s3_disk_cannot_be_selected(): void
    {
        config()->set('filesystems.disks.s3.key', 'access-key');
        config()->set('filesystems.disks.s3.secret', null);
        config()->set('filesystems.disks.s3.region', 'us-east-1');
        config()->set('filesystems.disks.s3.bucket', 'packages');
        [, $repository, $package] = $this->packageForUser();

        $response = $this->publish($repository, $package, [
            'disk' => 's3',
            'zip_file' => UploadedFile::fake()->create('package.zip', 100, 'application/zip'),
        ]);

        $response->assertSessionHasErrors('disk');
        $this->assertDatabaseMissing('package_versions', [
            'package_id' => $package->id,
            'version' => '1.0.0',
        ]);
    }

    public function test_a_version_can_be_published_with_a_zip_to_s3_storage(): void
    {
        $this->configureS3();
        Storage::fake('s3');
        [, $repository, $package] = $this->packageForUser();

        $response = $this->publish($repository, $package, [
            'disk' => 's3',
            'zip_file' => UploadedFile::fake()->create('package.zip', 100, 'application/zip'),
        ]);

        $response->assertRedirect(route('repositories.packages.show', [$repository, $package]));
        $version = $package->versions()->sole();
        Storage::disk('s3')->assertExists($version->zip_path);
    }

    public function test_a_storage_failure_does_not_create_a_version(): void
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('putFile')->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        [, $repository, $package] = $this->packageForUser();

        $response = $this->publish($repository, $package, [
            'zip_file' => UploadedFile::fake()->create('package.zip', 100, 'application/zip'),
        ]);

        $response->assertSessionHasErrors('zip_file');
        $this->assertDatabaseMissing('package_versions', [
            'package_id' => $package->id,
            'version' => '1.0.0',
        ]);
    }

    public function test_an_s3_storage_failure_does_not_create_a_version(): void
    {
        $this->configureS3();
        $disk = Mockery::mock();
        $disk->shouldReceive('putFile')->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);
        [, $repository, $package] = $this->packageForUser();

        $response = $this->publish($repository, $package, [
            'disk' => 's3',
            'zip_file' => UploadedFile::fake()->create('package.zip', 100, 'application/zip'),
        ]);

        $response->assertSessionHasErrors('zip_file');
        $this->assertDatabaseMissing('package_versions', [
            'package_id' => $package->id,
            'version' => '1.0.0',
        ]);
    }

    public function test_a_database_failure_removes_the_uploaded_archive(): void
    {
        Storage::fake('local');
        [, $repository, $package] = $this->packageForUser();
        $this->failDatabaseOnVersionInsert();

        $response = $this->publish($repository, $package, [
            'zip_file' => UploadedFile::fake()->create('package.zip', 100, 'application/zip'),
        ]);

        $response->assertSessionHasErrors('zip_file');
        $this->assertDatabaseMissing('package_versions', [
            'package_id' => $package->id,
            'version' => '1.0.0',
        ]);
        $this->assertSame([], Storage::disk('local')->allFiles('packages'));
    }

    public function test_an_s3_database_failure_removes_the_uploaded_archive(): void
    {
        $this->configureS3();
        Storage::fake('s3');
        [, $repository, $package] = $this->packageForUser();
        $this->failDatabaseOnVersionInsert();

        $response = $this->publish($repository, $package, [
            'disk' => 's3',
            'zip_file' => UploadedFile::fake()->create('package.zip', 100, 'application/zip'),
        ]);

        $response->assertSessionHasErrors('zip_file');
        $this->assertDatabaseMissing('package_versions', [
            'package_id' => $package->id,
            'version' => '1.0.0',
        ]);
        $this->assertSame([], Storage::disk('s3')->allFiles('packages'));
    }

    public function test_composer_metadata_excludes_versions_without_an_archive(): void
    {
        Storage::fake('local');
        [, $repository, $package] = $this->packageForUser();
        $repository->update(['type' => 'public']);
        Storage::disk('local')->put('packages/1.0.0.zip', 'archive');
        $package->versions()->create([
            'version' => '1.0.0',
            'type' => 'library',
            'disk' => 'local',
            'zip_path' => 'packages/1.0.0.zip',
        ]);
        $package->versions()->create([
            'version' => '1.1.0',
            'type' => 'library',
            'disk' => 'local',
            'zip_path' => 'packages/missing.zip',
        ]);

        $response = $this->get("/composer/{$repository->slug}/p2/acme/example-package.json");

        $response->assertOk()
            ->assertJsonPath('packages.acme/example-package.0.version', '1.0.0')
            ->assertJsonCount(1, 'packages.acme/example-package');
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
        ]);

        return [$user, $repository, $package];
    }

    private function publish(Repository $repository, mixed $package, array $overrides = []): TestResponse
    {
        return $this->actingAs($repository->users()->first())
            ->post(route('repositories.packages.versions.store', [$repository, $package]), array_merge([
                'version' => '1.0.0',
                'type' => 'library',
                'disk' => 'local',
            ], $overrides));
    }

    private function configureS3(): void
    {
        config()->set('filesystems.disks.s3.key', 'access-key');
        config()->set('filesystems.disks.s3.secret', 'secret');
        config()->set('filesystems.disks.s3.region', 'us-east-1');
        config()->set('filesystems.disks.s3.bucket', 'packages');
    }

    private function failDatabaseOnVersionInsert(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into "package_versions"')) {
                throw new \RuntimeException('Database unavailable.');
            }
        });
    }
}
