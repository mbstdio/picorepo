<?php

namespace App\Models;

use App\Rules\ComposerMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use stdClass;

class PackageVersion extends Model
{
    protected $fillable = [
        'package_id',
        'version',
        'type',
        'disk',
        'zip_path',
        'description',
        'extra',
    ];

    protected $casts = [
        'extra' => 'object',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function downloadUrl(): string
    {
        return route('versions.download', $this->id);
    }

    public function getZipUrl(): ?string
    {
        if (! $this->zip_path) {
            return null;
        }

        return Storage::disk($this->disk)->temporaryUrl(
            $this->zip_path,
            now()->addMinutes(5)
        );
    }

    public function hasUsableArchive(): bool
    {
        if (! $this->zip_path || ! in_array($this->disk, ['local', 's3'], true)) {
            return false;
        }

        try {
            return Storage::disk($this->disk)->exists($this->zip_path);
        } catch (\Throwable) {
            return false;
        }
    }

    public function toComposerArray(): array
    {
        $data = [
            'name' => $this->package->fullName(),
            'version' => $this->version,
            'type' => $this->type ?? 'library',
            'dist' => [
                'url' => $this->downloadUrl(),
                'type' => 'zip',
            ],
        ];

        if ($this->description) {
            $data['description'] = $this->description;
        }

        if ($this->extra instanceof stdClass) {
            $data += array_intersect_key(
                get_object_vars($this->extra),
                array_flip(ComposerMetadata::SUPPORTED_KEYS)
            );
        }

        return $data;
    }
}
