<?php

namespace App\Policies;

use App\Models\Repository;
use App\Models\User;

class RepositoryPolicy
{
    /**
     * View a repository (public repos are viewable by all, private only by members).
     */
    public function view(?User $user, Repository $repository): bool
    {
        if ($repository->isPublic()) {
            return true;
        }

        return $user && $user->hasAccessToRepository($repository);
    }

    /**
     * Create a repository — any authenticated user.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Update a repository — owner only.
     */
    public function update(User $user, Repository $repository): bool
    {
        return $user->ownsRepository($repository);
    }

    /**
     * Delete a repository — owner only.
     */
    public function delete(User $user, Repository $repository): bool
    {
        return $user->ownsRepository($repository);
    }

    /**
     * Manage access (add/remove users) — owner only.
     */
    public function manageAccess(User $user, Repository $repository): bool
    {
        return $user->ownsRepository($repository);
    }

    /**
     * Upload/manage versions — owner or maintainer.
     */
    public function manageVersions(User $user, Repository $repository): bool
    {
        return $user->hasAccessToRepository($repository);
    }
}
