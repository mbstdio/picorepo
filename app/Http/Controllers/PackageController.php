<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePackageRequest;
use App\Http\Requests\UpdatePackageRequest;
use App\Models\Package;
use App\Models\Repository;
use App\Services\ArchiveDeletionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(Repository $repository): RedirectResponse
    {
        $this->authorize('view', $repository);

        return redirect()->route('repositories.show', $repository);
    }

    public function create(Repository $repository): Response
    {
        $this->authorize('manageVersions', $repository);

        return Inertia::render('Packages/Create', [
            'repository' => $repository,
        ]);
    }

    public function store(StorePackageRequest $request, Repository $repository): RedirectResponse
    {
        $this->authorize('manageVersions', $repository);

        $package = $repository->packages()->create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('repositories.packages.versions.create', [$repository, $package])
            ->with('success', 'Package created. Now add the first version.');
    }

    public function show(Repository $repository, Package $package): Response
    {
        $this->authorize('view', $repository);

        $package->load('versions');

        return Inertia::render('Packages/Show', [
            'repository' => $repository,
            'package' => [
                'id' => $package->id,
                'name' => $package->name,
                'full_name' => $package->fullName(),
                'description' => $package->description,
                'versions' => $package->sortedVersions()->map(fn ($v) => [
                    'id' => $v->id,
                    'version' => $v->version,
                    'type' => $v->type,
                    'disk' => $v->disk,
                    'description' => $v->description,
                    'created_at' => $v->created_at,
                ]),
                'can' => [
                    'manage' => auth()->check() && auth()->user()->hasAccessToRepository($repository),
                    'delete' => auth()->check() && auth()->user()->ownsRepository($repository),
                ],
            ],
        ]);
    }

    public function edit(Repository $repository, Package $package): Response
    {
        $this->authorize('update', $package);

        return Inertia::render('Packages/Edit', [
            'repository' => $repository,
            'package' => $package,
        ]);
    }

    public function update(UpdatePackageRequest $request, Repository $repository, Package $package): RedirectResponse
    {
        $this->authorize('update', $package);

        $package->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('repositories.packages.show', [$repository, $package])
            ->with('success', 'Package updated successfully.');
    }

    public function destroy(Repository $repository, Package $package, ArchiveDeletionService $archives): RedirectResponse
    {
        $this->authorize('delete', $package);

        try {
            $archives->deleteAll($package->versions);

            if (! $package->delete()) {
                throw new \RuntimeException("Could not delete package {$package->name}.");
            }
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'archive' => 'The package archives could not be deleted. Please try again.',
            ]);
        }

        return redirect()->route('repositories.show', $repository)
            ->with('success', 'Package deleted.');
    }
}
