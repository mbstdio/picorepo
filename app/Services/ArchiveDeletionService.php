<?php

namespace App\Services;

use App\Models\PackageVersion;
use Illuminate\Support\Facades\Storage;

class ArchiveDeletionService
{
    public function deleteAll(iterable $versions): void
    {
        foreach ($versions as $version) {
            $this->delete($version);
        }
    }

    public function delete(PackageVersion $version): void
    {
        if (! $version->zip_path) {
            return;
        }

        if (! Storage::disk($version->disk)->delete($version->zip_path)) {
            throw new \RuntimeException("Could not delete archive for version {$version->version}.");
        }
    }
}
