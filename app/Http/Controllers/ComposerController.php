<?php

namespace App\Http\Controllers;

use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

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

        return $this->cachedMetadata($repository, function () use ($repository, $repositorySlug) {
            $packageNames = [];
            $lastModified = null;
            $packages = $repository->packages()->with(['versions' => fn ($versions) => $versions
                ->select('id', 'package_id', 'disk', 'zip_path', 'updated_at')])->lazyById(100);
            foreach ($packages as $package) {
                $usableVersions = $package->versions->filter(fn (PackageVersion $version) => $version->hasUsableArchive());
                if ($usableVersions->isNotEmpty()) {
                    $packageNames[] = $package->fullName();
                    $lastModified = max($lastModified, $usableVersions->max('updated_at'));
                }
            }

            $metadata = [
                'packages' => new \stdClass,
                'metadata-url' => '/composer/'.$repositorySlug.'/p2/%package%.json',
                'available-packages' => $packageNames,
                'info' => 'Add to composer.json repositories: '.json_encode($repository->composerConfig()),
            ];

            return response()->json($metadata)
                ->setEtag(md5(json_encode($metadata)))
                ->setLastModified($lastModified);
        });
    }

    /**
     * Fallback endpoint when Composer v2 probes the literal $package$ URL.
     * Returns all packages in the repository.
     */
    public function allPackages(string $repositorySlug): JsonResponse
    {
        $repository = Repository::where('slug', $repositorySlug)->firstOrFail();

        $this->authorizeComposerAccess($repository);

        return $this->cachedMetadata($repository, function () use ($repository) {
            $packages = [];
            foreach ($repository->packages()->with('versions')->lazyById(100) as $package) {
                $fullName = $package->fullName();
                $versions = [];
                foreach ($package->usableVersions() as $version) {
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
        });
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

        return $this->cachedMetadata($repository, function () use ($repository, $name, $fullName) {
            $package = $repository->packages()
                ->where('name', $name)
                ->with('versions')
                ->firstOrFail();

            $versions = [];
            foreach ($package->usableVersions() as $version) {
                $versions[$version->version] = $version->toComposerArray();
            }

            return response()->json([
                'packages' => [
                    $fullName => $versions,
                ],
                'minified' => false,
            ]);
        });
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

        return $this->cachedMetadata($repository, function () use ($repository, $packageName) {
            $isDevelopmentMetadata = str_ends_with($packageName, '~dev');
            $packageName = $isDevelopmentMetadata ? substr($packageName, 0, -4) : $packageName;
            $package = $repository->packages()
                ->whereRaw('LOWER(name) = ?', [strtolower($packageName)])
                ->with('versions')
                ->firstOrFail();
            $versions = $package->usableVersions()
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
        });
    }

    protected function cachedMetadata(Repository $repository, Closure $generate): JsonResponse
    {
        $key = 'composer:'.$repository->id.':'.hash('sha256',
            url()->current().'|'.$repository->name.'|'.$repository->slug
        );
        $cached = Cache::get($key);
        // Archive availability can change outside the app; recheck it at least every five minutes.
        if (! $cached || $cached['revision'] !== $repository->metadata_revision) {
            $response = $generate();
            $cached = [
                'revision' => $repository->metadata_revision,
                'body' => $response->getContent(),
                'etag' => md5($response->getContent()),
                'last_modified' => $response->headers->get('Last-Modified'),
            ];
            Cache::put($key, $cached, 300);
        }

        $response = response()->json()->setContent($cached['body'])->setEtag($cached['etag']);
        if ($cached['last_modified']) {
            $response->headers->set('Last-Modified', $cached['last_modified']);
        }

        return $response;
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
