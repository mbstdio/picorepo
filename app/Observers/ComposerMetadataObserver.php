<?php

namespace App\Observers;

use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Support\Str;

class ComposerMetadataObserver
{
    public function saved(Package|PackageVersion $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Package|PackageVersion $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Package|PackageVersion $model): void
    {
        $repositoryIds = $model instanceof Package
            ? [$model->repository_id, $model->getRawOriginal('repository_id')]
            : Package::whereIn('id', [$model->package_id, $model->getRawOriginal('package_id')])->select('repository_id');

        // Revisions commit/roll back with the write, and are never reused after a rollback.
        Repository::whereIn('id', $repositoryIds)->update(['metadata_revision' => (string) Str::uuid()]);
    }
}
