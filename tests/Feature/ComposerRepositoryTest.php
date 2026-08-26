<?php

namespace Tests\Feature;

use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComposerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_repository_advertises_a_composer_v2_metadata_url(): void
    {
        [$repository] = $this->publicPackage();

        $response = $this->get("/composer/{$repository->slug}/packages.json");

        $response->assertOk()
            ->assertHeader('Last-Modified')
            ->assertHeader('ETag')
            ->assertJsonPath('metadata-url', "/composer/{$repository->slug}/p2/%package%.json")
            ->assertJsonPath('available-packages.0', 'acme/demo');
    }

    public function test_canonical_package_metadata_uses_the_composer_v2_version_structure(): void
    {
        [$repository] = $this->publicPackage();

        $response = $this->get("/composer/{$repository->slug}/p2/acme/demo.json");

        $response->assertOk()
            ->assertHeader('Last-Modified')
            ->assertHeader('ETag')
            ->assertJsonPath('packages.acme/demo.0.name', 'acme/demo')
            ->assertJsonPath('packages.acme/demo.0.version', '1.0.0');
    }

    public function test_development_package_metadata_resolves_at_the_composer_v2_url(): void
    {
        [$repository, $package] = $this->publicPackage();
        $package->versions()->create([
            'version' => 'dev-main',
            'type' => 'library',
            'disk' => 'local',
        ]);

        $response = $this->get("/composer/{$repository->slug}/p2/acme/demo~dev.json");

        $response->assertOk()
            ->assertJsonPath('packages.acme/demo.0.version', 'dev-main');
    }

    public function test_wrong_vendor_returns_not_found(): void
    {
        [$repository] = $this->publicPackage();

        $this->get("/composer/{$repository->slug}/p2/other/demo.json")
            ->assertNotFound();
    }

    public function test_canonical_lowercase_name_resolves_for_a_legacy_mixed_case_package(): void
    {
        [$repository] = $this->publicPackage('Acme', 'Demo');

        $this->get("/composer/{$repository->slug}/p2/acme/demo.json")
            ->assertOk()
            ->assertJsonPath('packages.acme/demo.0.name', 'acme/demo');
    }

    public function test_unknown_package_returns_not_found(): void
    {
        [$repository] = $this->publicPackage();

        $this->get("/composer/{$repository->slug}/p2/acme/missing.json")
            ->assertNotFound();
    }

    private function publicPackage(string $repositoryName = 'acme', string $packageName = 'demo'): array
    {
        $repository = Repository::create([
            'name' => $repositoryName,
            'type' => 'public',
        ]);
        $package = $repository->packages()->create([
            'name' => $packageName,
        ]);
        $package->versions()->create([
            'version' => '1.0.0',
            'type' => 'library',
            'disk' => 'local',
        ]);

        return [$repository, $package];
    }
}
