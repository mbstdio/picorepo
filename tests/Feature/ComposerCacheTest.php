<?php

namespace Tests\Feature;

use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComposerCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_composer_endpoints_eager_load_relations_and_reuse_cached_metadata(): void
    {
        $repository = $this->repository();
        for ($p = 1; $p <= 6; $p++) {
            $package = $repository->packages()->create(['name' => "demo-$p"]);
            for ($v = 1; $v <= 3; $v++) {
                $package->versions()->create(['version' => "1.0.$v", 'zip_path' => 'archive.zip', 'disk' => 'local']);
            }
            $package->versions()->create(['version' => 'dev-main', 'zip_path' => 'archive.zip', 'disk' => 'local']);
        }

        foreach (['packages.json', 'p/$package$.json', 'p/acme~demo-1.json', 'p2/acme/demo-1.json', 'p2/acme/demo-1~dev.json'] as $path) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $cold = $this->get('/composer/acme/'.$path)->assertOk();
            $this->assertLessThanOrEqual(5, count(DB::getQueryLog()), $path.' must avoid N+1 queries');
            DB::flushQueryLog();
            $warm = $this->get('/composer/acme/'.$path)->assertOk();
            $this->assertLessThanOrEqual(1, count(DB::getQueryLog()), $path.' should only query current repository/access state');
            $this->assertSame($cold->getContent(), $warm->getContent());
            DB::disableQueryLog();
        }
    }

    public function test_package_and_version_writes_invalidate_every_composer_metadata_shape(): void
    {
        $repository = $this->repository();
        $package = $repository->packages()->create(['name' => 'demo']);
        $version = $package->versions()->create(['version' => '1.0.0', 'zip_path' => 'archive.zip', 'disk' => 'local']);
        $this->assertPublished('demo', ['1.0.0']);

        $second = $package->versions()->create(['version' => '2.0.0', 'zip_path' => 'archive.zip', 'disk' => 'local']);
        $this->assertPublished('demo', ['2.0.0', '1.0.0']);
        $second->update(['version' => '2.1.0']);
        $this->assertPublished('demo', ['2.1.0', '1.0.0']);
        $second->delete();
        $this->assertPublished('demo', ['1.0.0']);

        $package->update(['name' => 'renamed']);
        $this->assertPublished('renamed', ['1.0.0']);
        $this->get('/composer/acme/p2/acme/demo.json')->assertNotFound();
        $package->delete();
        $this->get('/composer/acme/packages.json')->assertOk()->assertJsonCount(0, 'available-packages');
        $this->get('/composer/acme/p/$package$.json')->assertOk()->assertJsonCount(0, 'packages');
        $this->get('/composer/acme/p2/acme/renamed.json')->assertNotFound();
    }

    public function test_rolled_back_metadata_cannot_be_reused_by_a_later_write(): void
    {
        $repository = $this->repository();
        $package = $repository->packages()->create(['name' => 'demo']);
        $version = $package->versions()->create(['version' => '1.0.0', 'zip_path' => 'archive.zip', 'disk' => 'local']);
        $this->assertPublished('demo', ['1.0.0']);

        DB::beginTransaction();
        $version->update(['version' => '2.0.0']);
        $this->assertPublished('demo', ['2.0.0']);
        DB::rollBack();
        $this->assertPublished('demo', ['1.0.0']);

        $version->refresh()->update(['version' => '3.0.0']);
        $this->assertPublished('demo', ['3.0.0']);
    }

    public function test_cached_metadata_still_checks_current_privacy_membership_and_token_state(): void
    {
        $repository = $this->repository();
        $package = $repository->packages()->create(['name' => 'demo']);
        $package->versions()->create(['version' => '1.0.0', 'zip_path' => 'archive.zip', 'disk' => 'local']);
        $user = User::factory()->create();
        $repository->users()->attach($user, ['role' => 'maintainer']);
        $plainToken = 'composer-cache-test-token';
        $token = $user->tokens()->create(['name' => 'composer', 'token' => hash('sha256', $plainToken), 'abilities' => ['composer:read']]);

        foreach (['packages.json', 'p/$package$.json', 'p/acme~demo.json', 'p2/acme/demo.json'] as $path) {
            $this->get('/composer/acme/'.$path)->assertOk();
        }
        $repository->update(['type' => 'private']);
        foreach (['packages.json', 'p/$package$.json', 'p/acme~demo.json', 'p2/acme/demo.json'] as $path) {
            $this->get('/composer/acme/'.$path)->assertUnauthorized();
            $this->get('/composer/acme/'.$path, ['Authorization' => 'Bearer '.$plainToken])->assertOk();
        }
        $repository->users()->detach($user);
        $this->get('/composer/acme/packages.json', ['Authorization' => 'Bearer '.$plainToken])->assertForbidden();
        $repository->users()->attach($user, ['role' => 'maintainer']);
        $token->delete();
        $this->get('/composer/acme/packages.json', ['Authorization' => 'Bearer '.$plainToken])->assertForbidden();
    }

    public function test_cache_expiry_rechecks_external_archive_availability(): void
    {
        $repository = $this->repository();
        $package = $repository->packages()->create(['name' => 'demo']);
        $package->versions()->create(['version' => '1.0.0', 'zip_path' => 'archive.zip', 'disk' => 'local']);
        $this->assertPublished('demo', ['1.0.0']);
        Storage::disk('local')->delete('archive.zip');
        $this->assertPublished('demo', ['1.0.0']);
        $this->travel(301)->seconds();
        $this->get('/composer/acme/packages.json')->assertOk()->assertJsonCount(0, 'available-packages');
        $this->get('/composer/acme/p2/acme/demo.json')->assertNotFound();
    }

    private function assertPublished(string $name, array $versions): void
    {
        $this->get('/composer/acme/packages.json')->assertOk()->assertJsonPath('available-packages', ['acme/'.$name]);
        foreach (['p/$package$.json', 'p/acme~'.$name.'.json'] as $path) {
            $response = $this->get('/composer/acme/'.$path)->assertOk();
            $this->assertSame($versions, array_keys($response->json('packages.acme/'.$name)));
        }
        $response = $this->get('/composer/acme/p2/acme/'.$name.'.json')->assertOk();
        $this->assertSame($versions, array_column($response->json('packages.acme/'.$name), 'version'));
    }

    private function repository(): Repository
    {
        Storage::fake('local');
        Storage::disk('local')->put('archive.zip', 'archive');

        return Repository::create(['name' => 'acme', 'type' => 'public']);
    }
}
