<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRepositoryUserRequest;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryUserController extends Controller
{
    public function index(Repository $repository): Response
    {
        $this->authorize('manageAccess', $repository);

        $users = $repository->users()->get()->map(fn ($u) => [
            'id'    => $u->id,
            'name'  => $u->name,
            'email' => $u->email,
            'role'  => $u->pivot->role,
        ]);

        return Inertia::render('Users/Index', [
            'repository' => $repository,
            'users'      => $users,
        ]);
    }

    public function store(StoreRepositoryUserRequest $request, Repository $repository): RedirectResponse
    {
        $this->authorize('manageAccess', $repository);

        $user = User::where('email', $request->email)->firstOrFail();

        $repository->users()->attach($user->id, ['role' => $request->role]);

        return redirect()->route('repositories.users.index', $repository)
            ->with('success', "{$user->name} added as {$request->role}.");
    }

    public function update(Repository $repository, User $user): RedirectResponse
    {
        $this->authorize('manageAccess', $repository);

        $role = request()->validate([
            'role' => ['required', 'in:owner,maintainer'],
        ])['role'];

        $repository->users()->updateExistingPivot($user->id, ['role' => $role]);

        return redirect()->route('repositories.users.index', $repository)
            ->with('success', 'Role updated.');
    }

    public function destroy(Repository $repository, User $user): RedirectResponse
    {
        $this->authorize('manageAccess', $repository);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot remove yourself.']);
        }

        $repository->users()->detach($user->id);

        return redirect()->route('repositories.users.index', $repository)
            ->with('success', 'User removed from repository.');
    }
}
