<?php

namespace App\Http\Controllers;

use App\Models\PackageVersion;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function download(PackageVersion $version): StreamedResponse
    {
        $repository = $version->package->repository;

        // Private repo: require auth
        if ($repository->isPrivate()) {
            $token = request()->bearerToken()
                ?? request()->header('X-API-Token')
                ?? request()->query('api_token');

            if ($token) {
                $user = User::whereHas('tokens', function ($q) use ($token) {
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
        $filename = preg_replace('/[\\\\\/\x00-\x1F\x7F]/', '_', $filename);

        try {
            $response = $disk->download($version->zip_path, $filename, [
                'Content-Type' => 'application/zip',
            ]);
            $stream = $disk->readStream($version->zip_path);
        } catch (UnableToReadFile|UnableToRetrieveMetadata) {
            abort(404, 'File not found.');
        }

        abort_unless(is_resource($stream), 404, 'File not found.');

        // Bounded reads avoid fpassthru's whole-file memory mapping on local disks.
        $response->setCallback(function () use ($stream): void {
            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 64 * 1024);
                    if ($chunk === false) {
                        throw new \RuntimeException('Unable to read archive stream.');
                    }
                    echo $chunk;
                }
            } finally {
                fclose($stream);
            }
        });

        return $response;
    }
}
