<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRepositoryRequest;
use App\Http\Requests\UpdateRepositoryRequest;
use App\Models\Repository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryController extends Controller
{
    public function index(): Response
    {
        $repositories = Repository::withCount('packages')
            ->with('users')
            ->latest()
            ->get()
            ->map(fn ($repo) => [
                'id'             => $repo->id,
                'name'           => $repo->name,
                'slug'           => $repo->slug,
                'type'           => $repo->type,
                'description'    => $repo->description,
                'packages_count' => $repo->packages_count,
                'created_at'     => $repo->created_at,
            ]);

        return Inertia::render('Repositories/Index', [
            'repositories' => $repositories,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Repositories/Create');
    }

    public function store(StoreRepositoryRequest $request): RedirectResponse
    {
        $repository = Repository::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'type'        => $request->type,
            'description' => $request->description,
        ]);

        // Attach creator as owner
        $repository->users()->attach(auth()->id(), ['role' => 'owner']);

        return redirect()->route('repositories.show', $repository)
            ->with('success', 'Repository created successfully.');
    }

    public function show(Repository $repository): Response
    {
        $this->authorize('view', $repository);

        $repository->load([
            'packages.versions',
            'users',
        ]);

        return Inertia::render('Repositories/Show', [
            'repository'    => [
                'id'             => $repository->id,
                'name'           => $repository->name,
                'slug'           => $repository->slug,
                'type'           => $repository->type,
                'description'    => $repository->description,
                'composer_config'=> $repository->composerConfig(),
                'composer_url'   => $repository->composerUrl(),
                'packages'       => $repository->packages->map(fn ($pkg) => [
                    'id'          => $pkg->id,
                    'name'        => $pkg->name,
                    'full_name'   => $pkg->fullName(),
                    'description' => $pkg->description,
                    'versions'    => $pkg->sortedVersions()->map(fn ($v) => [
                        'id'          => $v->id,
                        'version'     => $v->version,
                        'type'        => $v->type,
                        'disk'        => $v->disk,
                        'description' => $v->description,
                        'created_at'  => $v->created_at,
                    ]),
                ]),
                'users'          => $repository->users->map(fn ($u) => [
                    'id'   => $u->id,
                    'name' => $u->name,
                    'email'=> $u->email,
                    'role' => $u->pivot->role,
                ]),
                'can' => [
                    'update'         => auth()->check() && auth()->user()->can('update', $repository),
                    'delete'         => auth()->check() && auth()->user()->can('delete', $repository),
                    'manage_access'  => auth()->check() && auth()->user()->can('manageAccess', $repository),
                    'manage_versions'=> auth()->check() && auth()->user()->can('manageVersions', $repository),
                ],
            ],
        ]);
    }

    public function edit(Repository $repository): Response
    {
        $this->authorize('update', $repository);

        return Inertia::render('Repositories/Edit', [
            'repository' => $repository,
        ]);
    }

    public function update(UpdateRepositoryRequest $request, Repository $repository): RedirectResponse
    {
        $this->authorize('update', $repository);

        $repository->update([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'type'        => $request->type,
            'description' => $request->description,
        ]);

        return redirect()->route('repositories.show', $repository)
            ->with('success', 'Repository updated successfully.');
    }

    public function destroy(Repository $repository): RedirectResponse
    {
        $this->authorize('delete', $repository);

        $repository->delete();

        return redirect()->route('repositories.index')
            ->with('success', 'Repository deleted successfully.');
    }
}
