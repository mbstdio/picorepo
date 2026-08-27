<?php

namespace Tests\Feature;

use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoryOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sole_owner_cannot_be_demoted(): void
    {
        $owner = User::factory()->create();
        $repository = $this->repositoryWith($owner, 'owner');

        $this->actingAs($owner)
            ->patch(route('repositories.users.update', [$repository, $owner]), ['role' => 'maintainer'])
            ->assertSessionHasErrors('role');

        $this->assertSame('owner', $repository->users()->find($owner->id)->pivot->role);
    }

    public function test_an_owner_can_demote_another_owner_when_an_owner_remains(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $repository = $this->repositoryWith($owner, 'owner');
        $repository->users()->attach($otherOwner, ['role' => 'owner']);

        $this->actingAs($owner)
            ->patch(route('repositories.users.update', [$repository, $otherOwner]), ['role' => 'maintainer'])
            ->assertSessionHasNoErrors();

        $this->assertSame('maintainer', $repository->users()->find($otherOwner->id)->pivot->role);
        $this->assertSame('owner', $repository->users()->find($owner->id)->pivot->role);
    }

    public function test_an_owner_can_remove_another_owner_when_an_owner_remains(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $repository = $this->repositoryWith($owner, 'owner');
        $repository->users()->attach($otherOwner, ['role' => 'owner']);

        $this->actingAs($owner)
            ->delete(route('repositories.users.destroy', [$repository, $otherOwner]))
            ->assertSessionHasNoErrors();

        $this->assertFalse($repository->users()->whereKey($otherOwner->id)->exists());
        $this->assertSame('owner', $repository->users()->find($owner->id)->pivot->role);
    }

    public function test_the_sole_owner_cannot_be_removed(): void
    {
        $owner = User::factory()->create();
        $repository = $this->repositoryWith($owner, 'owner');

        $this->actingAs($owner)
            ->delete(route('repositories.users.destroy', [$repository, $owner]))
            ->assertSessionHasErrors('error');

        $this->assertSame('owner', $repository->users()->find($owner->id)->pivot->role);
    }

    public function test_a_maintainer_cannot_change_membership(): void
    {
        $owner = User::factory()->create();
        $maintainer = User::factory()->create();
        $repository = $this->repositoryWith($owner, 'owner');
        $repository->users()->attach($maintainer, ['role' => 'maintainer']);

        $this->actingAs($maintainer)
            ->patch(route('repositories.users.update', [$repository, $owner]), ['role' => 'maintainer'])
            ->assertForbidden();
    }

    public function test_membership_routes_reject_users_outside_the_repository(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $repository = $this->repositoryWith($owner, 'owner');

        $this->actingAs($owner)
            ->patch(route('repositories.users.update', [$repository, $outsider]), ['role' => 'owner'])
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('repositories.users.destroy', [$repository, $outsider]))
            ->assertNotFound();
    }

    public function test_account_deletion_cannot_orphan_a_repository(): void
    {
        $owner = User::factory()->create();
        $repository = $this->repositoryWith($owner, 'owner');

        $this->actingAs($owner)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('account');

        $this->assertNotNull($owner->fresh());
        $this->assertSame('owner', $repository->users()->find($owner->id)->pivot->role);
    }

    public function test_repeated_owner_demotions_leave_one_owner(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $repository = $this->repositoryWith($firstOwner, 'owner');
        $repository->users()->attach($secondOwner, ['role' => 'owner']);

        $this->actingAs($firstOwner)
            ->patch(route('repositories.users.update', [$repository, $secondOwner]), ['role' => 'maintainer'])
            ->assertSessionHasNoErrors();

        $this->actingAs($firstOwner)
            ->patch(route('repositories.users.update', [$repository, $firstOwner]), ['role' => 'maintainer'])
            ->assertSessionHasErrors('role');

        $this->assertSame(1, $repository->users()->wherePivot('role', 'owner')->count());
    }

    private function repositoryWith(User $user, string $role): Repository
    {
        $repository = Repository::create(['name' => fake()->unique()->company(), 'type' => 'private']);
        $repository->users()->attach($user, ['role' => $role]);

        return $repository;
    }
}
