<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePackageVersionRequest;
use App\Http\Requests\UpdatePackageVersionRequest;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PackageVersionController extends Controller
{
    public function create(Repository $repository, Package $package): Response
    {
        $this->authorize('manageVersions', $repository);

        return Inertia::render('Versions/Create', [
            'repository' => $repository,
            'package' => [
                'id' => $package->id,
                'name' => $package->name,
                'full_name' => $package->fullName(),
            ],
            'availableDisks' => StorePackageVersionRequest::availableDisks(),
            'types' => [
                'library',
                'project',
                'metapackage',
                'composer-plugin',
                'wordpress-plugin',
                'wordpress-theme',
            ],
        ]);
    }

    public function store(StorePackageVersionRequest $request, Repository $repository, Package $package): RedirectResponse
    {
        $this->authorize('manageVersions', $repository);

        $disk = $request->disk;
        $storage = null;
        $zipPath = null;

        try {
            $storage = Storage::disk($disk);
            $zipPath = $storage->putFile(
                "packages/{$repository->slug}/{$package->name}/{$request->version}",
                $request->file('zip_file')
            );

            if (! $zipPath) {
                throw new \RuntimeException('Archive upload failed.');
            }

            DB::transaction(fn () => $package->versions()->create([
                'version' => $request->version,
                'type' => $request->type,
                'disk' => $disk,
                'zip_path' => $zipPath,
                'description' => $request->description,
                'extra' => $request->extra ? json_decode($request->extra, true) : null,
            ]));
        } catch (\Throwable $exception) {
            if ($zipPath && $storage) {
                $storage->delete($zipPath);
            }

            report($exception);

            return back()->withErrors([
                'zip_file' => 'The archive could not be published. Please try again.',
            ]);
        }

        return redirect()->route('repositories.packages.show', [$repository, $package])
            ->with('success', "Version {$request->version} added successfully.");
    }

    public function edit(Repository $repository, Package $package, PackageVersion $version): Response
    {
        $this->authorize('manageVersions', $repository);

        return Inertia::render('Versions/Edit', [
            'repository' => $repository,
            'package' => [
                'id' => $package->id,
                'full_name' => $package->fullName(),
            ],
            'version' => [
                'id' => $version->id,
                'version' => $version->version,
                'type' => $version->type,
                'disk' => $version->disk,
                'description' => $version->description,
            ],
            'types' => [
                'library',
                'project',
                'metapackage',
                'composer-plugin',
                'wordpress-plugin',
                'wordpress-theme',
            ],
        ]);
    }

    public function update(UpdatePackageVersionRequest $request, Repository $repository, Package $package, PackageVersion $version): RedirectResponse
    {
        $this->authorize('manageVersions', $repository);

        $version->update($request->validated());

        return redirect()->route('repositories.packages.show', [$repository, $package])
            ->with('success', "Version {$version->version} updated successfully.");
    }

    public function destroy(Repository $repository, Package $package, PackageVersion $version): RedirectResponse
    {
        $this->authorize('manageVersions', $repository);

        if ($version->zip_path) {
            Storage::disk($version->disk)->delete($version->zip_path);
        }

        $version->delete();

        return redirect()->route('repositories.packages.show', [$repository, $package])
            ->with('success', "Version {$version->version} deleted.");
    }
}
