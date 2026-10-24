<?php

namespace Tests\Feature;

use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToRetrieveMetadata;
use Mockery;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ArchiveDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_local_archives_are_streamed_with_download_headers(): void
    {
        Storage::fake('local');
        $version = $this->archive();
        Storage::disk('local')->put($version->zip_path, 'archive contents');

        $response = $this->get(route('versions.download', $version));

        $response->assertOk()
            ->assertDownload('example-package-1.0.0.zip')
            ->assertHeader('Content-Type', 'application/zip')
            ->assertHeader('Content-Length', '16');
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $response->assertStreamedContent('archive contents');
    }

    public function test_remote_archives_are_streamed(): void
    {
        Storage::fake('s3');
        $version = $this->archive('s3');
        Storage::disk('s3')->put($version->zip_path, 'remote archive');

        $this->get(route('versions.download', $version))
            ->assertOk()
            ->assertDownload('example-package-1.0.0.zip')
            ->assertHeader('Content-Type', 'application/zip')
            ->assertHeader('Content-Length', '14')
            ->assertStreamedContent('remote archive');
    }

    public function test_private_archives_require_repository_membership_before_accessing_storage(): void
    {
        Storage::fake('s3');
        $version = $this->archive('s3', 'private');
        $url = route('versions.download', $version);

        $this->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        $member = User::factory()->create();
        $version->package->repository->users()->attach($member, ['role' => 'maintainer']);
        $this->actingAs($member)->get($url)->assertNotFound();

        Storage::disk('s3')->put($version->zip_path, 'private archive');
        $this->actingAs($member)->get($url)
            ->assertOk()
            ->assertStreamedContent('private archive');
    }

    public function test_missing_archives_return_not_found(): void
    {
        foreach (['local', 's3'] as $disk) {
            Storage::fake($disk);
            $version = $this->archive($disk);

            $this->get(route('versions.download', $version))->assertNotFound();

            $version->update(['zip_path' => null]);
            $this->get(route('versions.download', $version))->assertNotFound();
        }

        $this->get(route('versions.download', 99999))->assertNotFound();
    }

    public function test_large_archives_are_delivered_without_buffering_the_whole_file(): void
    {
        Storage::fake('local');
        $version = $this->archive();
        $stream = tmpfile();
        $chunk = str_repeat('a', 1024 * 1024);
        $expectedHash = hash_init('sha256');
        for ($i = 0; $i < 32; $i++) {
            fwrite($stream, $chunk);
            hash_update($expectedHash, $chunk);
        }
        rewind($stream);
        Storage::disk('local')->put($version->zip_path, $stream);
        fclose($stream);
        unset($chunk);

        memory_reset_peak_usage();
        $baseline = memory_get_usage(true);
        $response = $this->get(route('versions.download', $version));
        $response->assertOk()->assertHeader('Content-Length', '33554432');
        $this->assertLessThan(8 * 1024 * 1024, memory_get_peak_usage(true) - $baseline);

        $actualHash = hash_init('sha256');
        $bytes = 0;
        ob_start(function (string $chunk) use ($actualHash, &$bytes): string {
            $bytes += strlen($chunk);
            hash_update($actualHash, $chunk);

            return '';
        }, 8192);
        try {
            $response->sendContent();
        } finally {
            ob_end_flush();
        }

        $this->assertSame(33554432, $bytes);
        $this->assertSame(hash_final($expectedHash), hash_final($actualHash));
        $this->assertLessThan(8 * 1024 * 1024, memory_get_peak_usage(true) - $baseline);
    }

    public function test_an_archive_deleted_during_metadata_lookup_returns_not_found(): void
    {
        $version = $this->archive('s3');
        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->once()->with($version->zip_path)->andReturnTrue();
        $disk->shouldReceive('download')->once()->andThrow(
            UnableToRetrieveMetadata::fileSize($version->zip_path)
        );
        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

        $this->get(route('versions.download', $version))->assertNotFound();
    }

    public function test_download_filenames_are_sanitized_and_encoded(): void
    {
        Storage::fake('local');
        $version = $this->archive();
        $version->update(['version' => "1.0/\\\r\né\""]);
        Storage::disk('local')->put($version->zip_path, 'archive');

        $response = $this->get(route('versions.download', $version));

        $response->assertOk()->assertStreamedContent('archive');
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString("filename*=utf-8''example-package-1.0____%C3%A9%22.zip", $disposition);
        $this->assertStringContainsString('filename="example-package-1.0____e\\".zip"', $disposition);
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
    }

    private function archive(string $disk = 'local', string $visibility = 'public'): PackageVersion
    {
        $repository = Repository::create(['name' => 'acme-'.$disk, 'type' => $visibility]);
        $package = $repository->packages()->create(['name' => 'example-package']);

        return $package->versions()->create([
            'version' => '1.0.0',
            'type' => 'library',
            'disk' => $disk,
            'zip_path' => 'packages/example-package/1.0.0.zip',
        ]);
    }
}
