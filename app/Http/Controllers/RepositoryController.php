<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRepositoryRequest;
use App\Http\Requests\UpdateRepositoryRequest;
use App\Models\Repository;
use App\Services\ArchiveDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:all,public,private'],
        ]);
        $repositories = Repository::query()
            ->where(function ($query) {
                $query->where('type', 'public');

                if ($userId = auth()->id()) {
                    $query->orWhereHas('users', fn ($users) => $users->whereKey($userId));
                }
            })
            ->when(isset($filters['search']) && $filters['search'] !== '', fn ($query) => $query->where('name', 'like', '%'.$filters['search'].'%'))
            ->when(in_array($filters['type'] ?? 'all', ['public', 'private']), fn ($query) => $query->where('type', $filters['type']))
            ->withCount('packages')
            ->latest()
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($repo) => [
                'id' => $repo->id,
                'name' => $repo->name,
                'slug' => $repo->slug,
                'type' => $repo->type,
                'description' => $repo->description,
                'packages_count' => $repo->packages_count,
                'created_at' => $repo->created_at,
            ]);

        return Inertia::render('Repositories/Index', [
            'repositories' => $repositories,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Repositories/Create');
    }

    public function store(StoreRepositoryRequest $request): RedirectResponse
    {
        $repository = Repository::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'type' => $request->type,
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

        $repository->loadCount(['packages', 'users']);
        $packages = $repository->packages()
            ->withCount('versions')
            ->with(['versions' => fn ($versions) => $versions
                ->select('id', 'package_id', 'version')->latest()->orderByDesc('id')->limit(5)])
            ->latest()->orderByDesc('id')
            ->paginate(20)->withQueryString();
        $users = $repository->users()->orderBy('users.id')->limit(5)->get();

        $canViewCollaborators = auth()->user()?->hasAccessToRepository($repository) ?? false;

        return Inertia::render('Repositories/Show', [
            'repository' => [
                'id' => $repository->id,
                'name' => $repository->name,
                'slug' => $repository->slug,
                'type' => $repository->type,
                'description' => $repository->description,
                'composer_config' => $repository->composerConfig(),
                'composer_url' => $repository->composerUrl(),
                'packages_count' => $repository->packages_count,
                'users_count' => $repository->users_count,
                'packages' => $packages->through(fn ($pkg) => [
                    'id' => $pkg->id,
                    'name' => $pkg->name,
                    'full_name' => $pkg->fullName(),
                    'description' => $pkg->description,
                    'versions_count' => $pkg->versions_count,
                    'versions' => $pkg->versions->map(fn ($v) => [
                        'id' => $v->id,
                        'version' => $v->version,
                    ]),
                ]),
                'users' => $users->map(fn ($u) => array_filter([
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $canViewCollaborators ? $u->email : null,
                    'role' => $u->pivot->role,
                ], fn ($value) => $value !== null)),
                'can' => [
                    'update' => auth()->check() && auth()->user()->can('update', $repository),
                    'delete' => auth()->check() && auth()->user()->can('delete', $repository),
                    'manage_access' => auth()->check() && auth()->user()->can('manageAccess', $repository),
                    'manage_versions' => auth()->check() && auth()->user()->can('manageVersions', $repository),
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
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'type' => $request->type,
            'description' => $request->description,
        ]);

        return redirect()->route('repositories.show', $repository)
            ->with('success', 'Repository updated successfully.');
    }

    public function destroy(Repository $repository, ArchiveDeletionService $archives): RedirectResponse
    {
        $this->authorize('delete', $repository);

        try {
            $archives->deleteAll($repository->packages()->with('versions')->get()->flatMap->versions);

            if (! $repository->delete()) {
                throw new \RuntimeException("Could not delete repository {$repository->name}.");
            }
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'archive' => 'The repository archives could not be deleted. Please try again.',
            ]);
        }

        return redirect()->route('repositories.index')
            ->with('success', 'Repository deleted successfully.');
    }
}
