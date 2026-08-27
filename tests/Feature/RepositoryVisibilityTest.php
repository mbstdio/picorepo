<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_only_receive_public_repositories_and_no_collaborator_email_addresses(): void
    {
        $public = Repository::create(['name' => 'Public', 'type' => 'public']);
        $private = Repository::create(['name' => 'Private', 'type' => 'private']);
        $collaborator = User::factory()->create();
        $public->users()->attach($collaborator, ['role' => 'owner']);
        $private->users()->attach($collaborator, ['role' => 'owner']);

        $this->get(route('repositories.index'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'props.repositories')
            ->assertJsonFragment(['id' => $public->id]);

        $this->get(route('repositories.show', $public), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonMissing(['email' => $collaborator->email]);

        $this->get(route('repositories.show', $private), $this->inertiaHeaders())
            ->assertForbidden();
    }

    public function test_unrelated_users_only_receive_public_repositories_and_no_collaborator_email_addresses(): void
    {
        $unrelatedUser = User::factory()->create();
        $public = Repository::create(['name' => 'Public', 'type' => 'public']);
        $private = Repository::create(['name' => 'Private', 'type' => 'private']);
        $collaborator = User::factory()->create();
        $public->users()->attach($collaborator, ['role' => 'owner']);
        $private->users()->attach($collaborator, ['role' => 'owner']);

        $this->actingAs($unrelatedUser)
            ->get(route('repositories.index'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'props.repositories')
            ->assertJsonFragment(['id' => $public->id]);

        $this->actingAs($unrelatedUser)
            ->get(route('repositories.show', $public), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonMissing(['email' => $collaborator->email]);

        $this->actingAs($unrelatedUser)
            ->get(route('repositories.show', $private), $this->inertiaHeaders())
            ->assertForbidden();
    }

    public function test_maintainers_receive_accessible_repositories_and_collaborator_email_addresses(): void
    {
        $maintainer = User::factory()->create();
        $owner = User::factory()->create();
        $private = Repository::create(['name' => 'Private', 'type' => 'private']);
        $public = Repository::create(['name' => 'Public', 'type' => 'public']);
        $private->users()->attach($owner, ['role' => 'owner']);
        $private->users()->attach($maintainer, ['role' => 'maintainer']);
        $public->users()->attach($owner, ['role' => 'owner']);
        $public->users()->attach($maintainer, ['role' => 'maintainer']);

        $this->actingAs($maintainer)
            ->get(route('repositories.index'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonCount(2, 'props.repositories')
            ->assertJsonFragment(['id' => $private->id])
            ->assertJsonFragment(['id' => $public->id]);

        $this->actingAs($maintainer)
            ->get(route('repositories.show', $private), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonFragment(['email' => $owner->email]);

        $this->actingAs($maintainer)
            ->get(route('repositories.show', $public), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonFragment(['email' => $owner->email]);
    }

    public function test_owners_receive_accessible_repositories_and_collaborator_email_addresses(): void
    {
        $owner = User::factory()->create();
        $maintainer = User::factory()->create();
        $private = Repository::create(['name' => 'Private', 'type' => 'private']);
        $public = Repository::create(['name' => 'Public', 'type' => 'public']);
        $private->users()->attach($owner, ['role' => 'owner']);
        $private->users()->attach($maintainer, ['role' => 'maintainer']);
        $public->users()->attach($owner, ['role' => 'owner']);
        $public->users()->attach($maintainer, ['role' => 'maintainer']);

        $this->actingAs($owner)
            ->get(route('repositories.index'), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonCount(2, 'props.repositories')
            ->assertJsonFragment(['id' => $private->id])
            ->assertJsonFragment(['id' => $public->id]);

        $this->actingAs($owner)
            ->get(route('repositories.show', $private), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonFragment(['email' => $maintainer->email]);

        $this->actingAs($owner)
            ->get(route('repositories.show', $public), $this->inertiaHeaders())
            ->assertOk()
            ->assertJsonFragment(['email' => $maintainer->email]);
    }

    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        ];
    }
}
