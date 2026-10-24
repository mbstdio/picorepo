<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ListScalingTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_search_and_visibility_filters_are_paginated_on_the_server(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            Repository::create(['name' => "match-$i", 'type' => 'public']);
        }
        Repository::create(['name' => 'unrelated', 'type' => 'public']);
        Repository::create(['name' => 'match-secret', 'type' => 'private']);

        $url = route('repositories.index', ['search' => 'match', 'type' => 'public']);
        $this->get($url, $this->headers())
            ->assertOk()
            ->assertJsonCount(20, 'props.repositories.data')
            ->assertJsonPath('props.repositories.total', 25)
            ->assertJsonPath('props.repositories.data.0.name', 'match-25');
        $this->get($url.'&page=2', $this->headers())
            ->assertOk()
            ->assertJsonCount(5, 'props.repositories.data')
            ->assertJsonPath('props.repositories.data.0.name', 'match-5');
    }

    public function test_repository_summary_limits_packages_versions_and_members_without_n_plus_one_queries(): void
    {
        $repository = Repository::create(['name' => 'acme', 'type' => 'public']);
        $users = User::factory()->count(25)->create();
        $repository->users()->attach($users->modelKeys(), ['role' => 'owner']);
        for ($i = 1; $i <= 25; $i++) {
            $package = $repository->packages()->create(['name' => "package-$i"]);
            for ($v = 1; $v <= 8; $v++) {
                $package->versions()->create(['version' => "1.0.$v", 'extra' => (object) ['license' => 'MIT']]);
            }
        }

        DB::enableQueryLog();
        $response = $this->get(route('repositories.show', $repository), $this->headers());
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk()
            ->assertJsonPath('props.repository.packages_count', 25)
            ->assertJsonPath('props.repository.users_count', 25)
            ->assertJsonCount(20, 'props.repository.packages.data')
            ->assertJsonCount(5, 'props.repository.packages.data.0.versions')
            ->assertJsonPath('props.repository.packages.data.0.versions_count', 8)
            ->assertJsonPath('props.repository.packages.data.0.versions.0.version', '1.0.8')
            ->assertJsonCount(5, 'props.repository.users')
            ->assertJsonMissingPath('props.repository.packages.data.0.versions.0.extra');
        $this->assertLessThanOrEqual(8, count($queries));

        $this->get(route('repositories.show', [$repository, 'page' => 2]), $this->headers())
            ->assertOk()->assertJsonCount(5, 'props.repository.packages.data');
    }

    public function test_version_and_access_lists_are_paginated(): void
    {
        $owner = User::factory()->create();
        $repository = Repository::create(['name' => 'acme', 'type' => 'public']);
        $members = User::factory()->count(24)->create();
        $repository->users()->attach([$owner->id, ...$members->modelKeys()], ['role' => 'owner']);
        $package = $repository->packages()->create(['name' => 'demo']);
        for ($i = 1; $i <= 25; $i++) {
            $package->versions()->create(['version' => "1.0.$i"]);
        }

        $this->actingAs($owner)
            ->get(route('repositories.packages.show', [$repository, $package]), $this->headers())
            ->assertOk()
            ->assertJsonCount(20, 'props.package.versions.data')
            ->assertJsonPath('props.package.versions.total', 25)
            ->assertJsonPath('props.package.versions.data.0.version', '1.0.25');
        $this->get(route('repositories.packages.show', [$repository, $package, 'page' => 2]), $this->headers())
            ->assertOk()->assertJsonCount(5, 'props.package.versions.data');
        $this->get(route('repositories.users.index', $repository), $this->headers())
            ->assertOk()->assertJsonCount(20, 'props.users.data')->assertJsonPath('props.users.total', 25);
        $this->get(route('repositories.users.index', [$repository, 'page' => 2]), $this->headers())
            ->assertOk()->assertJsonCount(5, 'props.users.data');
    }

    public function test_api_token_list_is_paginated_without_exposing_token_hashes(): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= 25; $i++) {
            $user->createToken('token-'.$i);
        }

        $this->actingAs($user)->get(route('api-tokens.index'), $this->headers())
            ->assertOk()->assertJsonCount(20, 'props.tokens.data')
            ->assertJsonPath('props.tokens.total', 25)
            ->assertJsonMissingPath('props.tokens.data.0.token');
        $this->get(route('api-tokens.index', ['page' => 2]), $this->headers())
            ->assertOk()->assertJsonCount(5, 'props.tokens.data');
    }

    private function headers(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        ];
    }
}
