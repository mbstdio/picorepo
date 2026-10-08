<?php

namespace Tests\Feature;

use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ComposerMetadataTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('nonObjectMetadata')]
    public function test_publishing_and_editing_reject_metadata_that_is_not_a_json_object(string $metadata): void
    {
        [$user, $repository, $package] = $this->packageForUser();

        $this->actingAs($user)
            ->post(route('repositories.packages.versions.store', [$repository, $package]), [
                'version' => '1.0.0',
                'type' => 'library',
                'disk' => 'local',
                'zip_file' => UploadedFile::fake()->create('package.zip', 1, 'application/zip'),
                'extra' => $metadata,
            ])
            ->assertSessionHasErrors('extra');

        $this->get($this->metadataUrl($repository))->assertNotFound();
        $this->assertSame([], Storage::disk('local')->allFiles());

        $version = $package->versions()->create([
            'version' => '1.0.0', 'type' => 'library', 'disk' => 'local',
        ]);
        $this->put(route('repositories.packages.versions.update', [$repository, $package, $version]), [
            'version' => '1.0.0', 'type' => 'library', 'extra' => $metadata,
        ])->assertSessionHasErrors('extra');
    }

    public static function nonObjectMetadata(): array
    {
        return [
            'string' => ['"text"'],
            'number' => ['42'],
            'boolean' => ['true'],
            'null' => ['null'],
            'list' => ['[]'],
            'invalid JSON' => ['{"require":'],
        ];
    }

    #[DataProvider('unsupportedKeys')]
    public function test_custom_metadata_cannot_override_protected_or_unsupported_keys(string $key): void
    {
        [$user, $repository, $package] = $this->packageForUser();
        $metadata = json_encode([$key => 'override']);

        $this->actingAs($user)
            ->post(route('repositories.packages.versions.store', [$repository, $package]), [
                'version' => '1.0.0',
                'type' => 'library',
                'disk' => 'local',
                'zip_file' => UploadedFile::fake()->create('package.zip', 1, 'application/zip'),
                'extra' => $metadata,
            ])
            ->assertSessionHasErrors('extra');

        $version = $package->versions()->create([
            'version' => '1.0.0', 'type' => 'library', 'disk' => 'local',
            'zip_path' => 'packages/demo.zip',
        ]);
        Storage::disk('local')->put('packages/demo.zip', 'archive');

        $this->put(route('repositories.packages.versions.update', [$repository, $package, $version]), [
            'version' => '1.0.0',
            'type' => 'library',
            'extra' => $metadata,
        ])->assertSessionHasErrors('extra');

        $this->get($this->metadataUrl($repository))
            ->assertOk()
            ->assertExactJson(['packages' => ['acme/demo' => [[
                'name' => 'acme/demo',
                'version' => '1.0.0',
                'type' => 'library',
                'dist' => ['url' => route('versions.download', $version), 'type' => 'zip'],
            ]]]]);
    }

    public static function unsupportedKeys(): array
    {
        return array_map(fn (string $key) => [$key], [
            'name', 'version', 'version_normalized', 'type', 'dist', 'source',
            'repositories', 'scripts', 'config', 'unknown',
        ]);
    }

    public function test_metadata_is_limited_to_16_kib_on_publish_and_edit(): void
    {
        [$user, $repository, $package] = $this->packageForUser();
        // The wrapper is 22 bytes, leaving 16,362 bytes for the string value.
        $atLimit = '{"extra":{"value":"'.str_repeat('a', 16362).'"}}';
        $this->assertSame(16384, strlen($atLimit));

        $this->actingAs($user)->post(route('repositories.packages.versions.store', [$repository, $package]), [
            'version' => '1.0.0', 'type' => 'library', 'disk' => 'local',
            'zip_file' => UploadedFile::fake()->create('package.zip', 1, 'application/zip'),
            'extra' => $atLimit,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $version = $package->versions()->sole();
        $overLimit = str_replace('"}}', 'a"}}', $atLimit);

        $this->post(route('repositories.packages.versions.store', [$repository, $package]), [
            'version' => '2.0.0', 'type' => 'library', 'disk' => 'local',
            'zip_file' => UploadedFile::fake()->create('package.zip', 1, 'application/zip'),
            'extra' => $overLimit,
        ])->assertSessionHasErrors('extra');

        $this->put(route('repositories.packages.versions.update', [$repository, $package, $version]), [
            'version' => '1.0.0', 'type' => 'library', 'extra' => $overLimit,
        ])->assertSessionHasErrors('extra');
    }

    public function test_published_metadata_preserves_composer_fields_and_json_object_shapes(): void
    {
        [$user, $repository, $package] = $this->packageForUser();

        $this->actingAs($user)->post(route('repositories.packages.versions.store', [$repository, $package]), [
            'version' => '1.0.0', 'type' => 'library', 'disk' => 'local',
            'zip_file' => UploadedFile::fake()->create('package.zip', 1, 'application/zip'),
            'extra' => '{"require":{"php":"^8.3"},"autoload":{"psr-4":{"Acme\\\\Demo\\\\":"src/"}},"license":"MIT","extra":{"empty":{},"list":[],"numeric":{"0":"zero"},"label":"café"}}',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $response = $this->get($this->metadataUrl($repository));
        $response->assertOk()
            ->assertJsonPath('packages.acme/demo.0.require.php', '^8.3')
            ->assertJsonPath('packages.acme/demo.0.autoload.psr-4.Acme\\Demo\\', 'src/')
            ->assertJsonPath('packages.acme/demo.0.license', 'MIT')
            ->assertJsonPath('packages.acme/demo.0.extra.label', 'café');
        $metadata = json_decode($response->getContent())->packages->{'acme/demo'}[0];
        $this->assertInstanceOf(\stdClass::class, $metadata->extra->empty);
        $this->assertSame([], $metadata->extra->list);
        $this->assertInstanceOf(\stdClass::class, $metadata->extra->numeric);
        $this->assertSame('zero', $metadata->extra->numeric->{'0'});
    }

    #[DataProvider('legacyInvalidMetadata')]
    public function test_legacy_invalid_metadata_cannot_break_or_override_composer_responses(string $metadata): void
    {
        [, $repository, $package] = $this->packageForUser();
        $version = $package->versions()->create([
            'version' => '1.0.0', 'type' => 'library', 'disk' => 'local',
            'zip_path' => 'packages/demo.zip',
        ]);
        Storage::disk('local')->put('packages/demo.zip', 'archive');
        DB::table('package_versions')->where('id', $version->id)->update(['extra' => $metadata]);

        $this->get($this->metadataUrl($repository))
            ->assertOk()
            ->assertExactJson(['packages' => ['acme/demo' => [[
                'name' => 'acme/demo',
                'version' => '1.0.0',
                'type' => 'library',
                'dist' => ['url' => route('versions.download', $version), 'type' => 'zip'],
            ]]]]);
    }

    public static function legacyInvalidMetadata(): array
    {
        return array_merge(self::nonObjectMetadata(), [
            'protected fields' => ['{"name":"evil/package","version":"9.0.0","type":"project","dist":{"url":"https://example.com/evil.zip"},"source":{},"unknown":true}'],
        ]);
    }

    #[DataProvider('legacyInvalidMetadata')]
    public function test_maintainers_can_inspect_and_repair_legacy_metadata_through_the_edit_flow(string $metadata): void
    {
        [$user, $repository, $package] = $this->packageForUser();
        $version = $package->versions()->create([
            'version' => '1.0.0', 'type' => 'library', 'disk' => 'local',
            'zip_path' => 'packages/demo.zip',
        ]);
        Storage::disk('local')->put('packages/demo.zip', 'archive');
        DB::table('package_versions')->where('id', $version->id)->update(['extra' => $metadata]);

        $this->actingAs($user)
            ->get(route('repositories.packages.versions.edit', [$repository, $package, $version]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Versions/Edit')->where('version.extra', $metadata));

        $repaired = '{"require":{"php":"^8.3"},"extra":{"empty":{}}}';
        $this->put(route('repositories.packages.versions.update', [$repository, $package, $version]), [
            'version' => '1.0.0', 'type' => 'library', 'extra' => $repaired,
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('repositories.packages.show', [$repository, $package]));

        $response = $this->get($this->metadataUrl($repository));
        $response->assertOk()->assertJsonPath('packages.acme/demo.0.require.php', '^8.3');
        $published = json_decode($response->getContent())->packages->{'acme/demo'}[0];
        $this->assertInstanceOf(\stdClass::class, $published->extra->empty);

        $this->get(route('repositories.packages.versions.edit', [$repository, $package, $version]))
            ->assertInertia(fn (Assert $page) => $page->where('version.extra', $repaired));
    }

    public function test_metadata_can_be_cleared_or_left_untouched_during_an_edit(): void
    {
        [$user, $repository, $package] = $this->packageForUser();
        $version = $package->versions()->create([
            'version' => '1.0.0', 'type' => 'library', 'disk' => 'local',
            'zip_path' => 'packages/demo.zip', 'extra' => ['license' => 'MIT'],
        ]);
        Storage::disk('local')->put('packages/demo.zip', 'archive');
        $updateUrl = route('repositories.packages.versions.update', [$repository, $package, $version]);

        $this->actingAs($user)->put($updateUrl, [
            'version' => '1.0.0', 'type' => 'library', 'description' => 'Updated description',
        ])->assertSessionHasNoErrors();
        $this->get($this->metadataUrl($repository))
            ->assertOk()->assertJsonPath('packages.acme/demo.0.license', 'MIT');

        $this->put($updateUrl, [
            'version' => '1.0.0', 'type' => 'library', 'extra' => '',
        ])->assertSessionHasNoErrors();
        $this->get($this->metadataUrl($repository))
            ->assertOk()->assertJsonMissingPath('packages.acme/demo.0.license');
        $this->get(route('repositories.packages.versions.edit', [$repository, $package, $version]))
            ->assertInertia(fn (Assert $page) => $page->where('version.extra', null));

        $this->put($updateUrl, [
            'version' => '1.0.0', 'type' => 'library', 'extra' => '{}',
        ])->assertSessionHasNoErrors();
        $this->get(route('repositories.packages.versions.edit', [$repository, $package, $version]))
            ->assertInertia(fn (Assert $page) => $page->where('version.extra', '{}'));
    }

    private function packageForUser(): array
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $repository = Repository::create(['name' => 'acme', 'type' => 'public']);
        $repository->users()->attach($user, ['role' => 'maintainer']);
        $package = $repository->packages()->create(['name' => 'demo']);

        return [$user, $repository, $package];
    }

    private function metadataUrl(Repository $repository): string
    {
        return "/composer/{$repository->slug}/p2/acme/demo.json";
    }
}
