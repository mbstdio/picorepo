<?php

namespace App\Services;

use App\Models\Repository;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepositoryOwnershipService
{
    public function changeRole(Repository $repository, User $user, string $role): void
    {
        DB::transaction(function () use ($repository, $user, $role) {
            $this->lockRepository($repository);

            $membership = DB::table('repository_user')
                ->where('repository_id', $repository->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            abort_if($membership === null, 404);

            if ($membership->role === 'owner' && $role !== 'owner') {
                $this->ensureOwnerRemains($repository, 'role');
            }

            $repository->users()->updateExistingPivot($user->id, ['role' => $role]);
        }, attempts: 3);
    }

    public function removeUser(Repository $repository, User $user): void
    {
        DB::transaction(function () use ($repository, $user) {
            $this->lockRepository($repository);

            $membership = DB::table('repository_user')
                ->where('repository_id', $repository->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            abort_if($membership === null, 404);

            if ($membership->role === 'owner') {
                $this->ensureOwnerRemains($repository, 'error');
            }

            $repository->users()->detach($user->id);
        }, attempts: 3);
    }

    public function deleteUser(User $user, ?Closure $beforeDelete = null): void
    {
        DB::transaction(function () use ($user, $beforeDelete) {
            $repositories = Repository::query()
                ->whereHas('users', fn ($users) => $users
                    ->where('repository_user.user_id', $user->id)
                    ->where('repository_user.role', 'owner'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($repositories as $repository) {
                $this->ensureOwnerRemains($repository, 'account');
            }

            $beforeDelete?->__invoke();

            $user->delete();
        }, attempts: 3);
    }

    private function lockRepository(Repository $repository): void
    {
        Repository::query()->whereKey($repository->id)->lockForUpdate()->firstOrFail();
    }

    private function ensureOwnerRemains(Repository $repository, string $errorKey): void
    {
        $ownerCount = DB::table('repository_user')
            ->where('repository_id', $repository->id)
            ->where('role', 'owner')
            ->lockForUpdate()
            ->count();

        if ($ownerCount === 1) {
            throw ValidationException::withMessages([
                $errorKey => 'A repository must retain at least one owner.',
            ]);
        }
    }
}
