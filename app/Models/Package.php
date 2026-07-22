<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'repository_id',
        'name',
        'description',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PackageVersion::class);
    }

    /**
     * Versions sorted by semver descending (4.1.0 > 3.34.3 > 1.0.0-beta).
     */
    public function sortedVersions(): \Illuminate\Support\Collection
    {
        $this->loadMissing('versions');

        return $this->versions->sort(function ($a, $b) {
            $aIsDev = str_starts_with($a->version, 'dev-');
            $bIsDev = str_starts_with($b->version, 'dev-');
            if ($aIsDev && !$bIsDev) return 1;
            if (!$aIsDev && $bIsDev) return -1;
            return version_compare($b->version, $a->version);
        })->values();
    }

    /**
     * Returns the full Composer package name: vendor/package
     */
    public function fullName(): string
    {
        return $this->repository->name . '/' . $this->name;
    }
}
