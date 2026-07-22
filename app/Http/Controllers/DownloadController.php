<?php

namespace App\Http\Controllers;

use App\Models\PackageVersion;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class DownloadController extends Controller
{
    public function download(PackageVersion $version): Response
    {
        $repository = $version->package->repository;

        // Private repo: require auth
        if ($repository->isPrivate()) {
            $token = request()->bearerToken()
                ?? request()->header('X-API-Token')
                ?? request()->query('api_token');

            if ($token) {
                $user = \App\Models\User::whereHas('tokens', function ($q) use ($token) {
                    $q->where('token', hash('sha256', $token));
                })->first();

                if (! $user || ! $user->hasAccessToRepository($repository)) {
                    abort(403);
                }
            } elseif (! auth()->check() || ! auth()->user()->hasAccessToRepository($repository)) {
                abort(403);
            }
        }

        if (! $version->zip_path) {
            abort(404, 'No zip file for this version.');
        }

        $disk = Storage::disk($version->disk);

        if (! $disk->exists($version->zip_path)) {
            abort(404, 'File not found.');
        }

        $filename = "{$version->package->name}-{$version->version}.zip";

        return response($disk->get($version->zip_path), 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length'      => $disk->size($version->zip_path),
        ]);
    }
}
