<?php

namespace App\Policies;

use App\Models\Package;
use App\Models\User;

class PackagePolicy
{
    public function view(?User $user, Package $package): bool
    {
        return (new RepositoryPolicy)->view($user, $package->repository);
    }

    public function create(User $user, Package $package): bool
    {
        return $user->hasAccessToRepository($package->repository);
    }

    public function update(User $user, Package $package): bool
    {
        return $user->hasAccessToRepository($package->repository);
    }

    public function delete(User $user, Package $package): bool
    {
        return $user->ownsRepository($package->repository);
    }
}
