<?php

namespace App\Http\Controllers;

use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ComposerController extends Controller
{
    /**
     * packages.json endpoint for a given repository.
     * Composer uses this as the entry point.
     */
    public function metadata(string $repositorySlug): JsonResponse
    {
        $repository = Repository::where('slug', $repositorySlug)->firstOrFail();

        $this->authorizeComposerAccess($repository);

        $packages = $repository->packages()
            ->with('versions')
            ->get();
        $packageNames = $packages
            ->filter(fn ($p) => $p->versions->isNotEmpty())
            ->map(fn ($p) => $p->fullName())
            ->values()
            ->all();

        $metadata = [
            'packages' => new \stdClass,
            'metadata-url' => '/composer/'.$repositorySlug.'/p2/%package%.json',
            'available-packages' => $packageNames,
            'info' => 'Add to composer.json repositories: '.json_encode($repository->composerConfig()),
        ];

        return response()->json($metadata)
            ->setEtag(md5(json_encode($metadata)))
            ->setLastModified($packages->flatMap->versions->max('updated_at'));
    }

    /**
     * Fallback endpoint when Composer v2 probes the literal $package$ URL.
     * Returns all packages in the repository.
     */
    public function allPackages(string $repositorySlug): JsonResponse
    {
        $repository = Repository::where('slug', $repositorySlug)->firstOrFail();

        $this->authorizeComposerAccess($repository);

        $packages = [];
        foreach ($repository->packages()->with('versions')->get() as $package) {
            $fullName = $package->fullName();
            $versions = [];
            foreach ($package->sortedVersions() as $version) {
                $versions[$version->version] = $version->toComposerArray();
            }
            if (! empty($versions)) {
                $packages[$fullName] = $versions;
            }
        }

        return response()->json([
            'packages' => $packages,
            'minified' => false,
        ]);
    }

    /**
     * Package-specific metadata: /composer/{repo}/p/{vendor}~{package}.json
     * Composer v2 replaces %24package%24 → vendor~package (/ becomes ~)
     */
    public function packageMeta(string $repositorySlug, string $packageName): JsonResponse
    {
        $repository = Repository::where('slug', $repositorySlug)->firstOrFail();

        $this->authorizeComposerAccess($repository);

        // Composer v2 encodes vendor/package as vendor~package in the URL
        $fullName = str_replace('~', '/', $packageName);

        if (! str_contains($fullName, '/')) {
            abort(404);
        }

        [, $name] = explode('/', $fullName, 2);

        $package = $repository->packages()
            ->where('name', $name)
            ->with('versions')
            ->firstOrFail();

        $versions = [];
        foreach ($package->sortedVersions() as $version) {
            $versions[$version->version] = $version->toComposerArray();
        }

        return response()->json([
            'packages' => [
                $fullName => $versions,
            ],
            'minified' => false,
        ]);
    }

    /**
     * Composer v2 package metadata: /composer/{repo}/p2/{vendor}/{package}.json
     */
    public function packageMetadata(string $repositorySlug, string $vendor, string $packageName): JsonResponse
    {
        $repository = Repository::where('slug', $repositorySlug)->firstOrFail();

        $this->authorizeComposerAccess($repository);

        if ($vendor !== strtolower($repository->name)) {
            abort(404);
        }

        $isDevelopmentMetadata = str_ends_with($packageName, '~dev');
        $packageName = $isDevelopmentMetadata ? substr($packageName, 0, -4) : $packageName;
        $package = $repository->packages()
            ->whereRaw('LOWER(name) = ?', [strtolower($packageName)])
            ->with('versions')
            ->firstOrFail();
        $versions = $package->sortedVersions()
            ->filter(fn (PackageVersion $version) => str_starts_with($version->version, 'dev-') === $isDevelopmentMetadata)
            ->map(fn (PackageVersion $version) => $version->toComposerArray())
            ->values()
            ->all();

        if ($versions === []) {
            abort(404);
        }

        $metadata = [
            'packages' => [
                $package->fullName() => $versions,
            ],
        ];

        return response()->json($metadata)
            ->setEtag(md5(json_encode($metadata)))
            ->setLastModified($package->versions->max('updated_at'));
    }

    protected function authorizeComposerAccess(Repository $repository): void
    {
        if ($repository->isPublic()) {
            return;
        }

        // Try token auth for private repos
        $token = request()->bearerToken()
            ?? request()->header('X-API-Token')
            ?? request()->query('api_token');

        if (! $token) {
            abort(401, 'Authentication required for private repositories.');
        }

        $user = User::whereHas('tokens', function ($q) use ($token) {
            $q->where('token', hash('sha256', $token));
        })->first();

        if (! $user || ! $user->hasAccessToRepository($repository)) {
            abort(403, 'Access denied to this repository.');
        }
    }
}
