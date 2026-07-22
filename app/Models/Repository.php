<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Repository extends Model
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
    ];

    protected static function booted(): void
    {
        static::creating(function (Repository $repo) {
            if (empty($repo->slug)) {
                $repo->slug = Str::slug($repo->name);
            }
        });
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'repository_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isPublic(): bool
    {
        return $this->type === 'public';
    }

    public function isPrivate(): bool
    {
        return $this->type === 'private';
    }

    public function composerUrl(): string
    {
        return url("/composer/{$this->slug}");
    }

    public function composerConfig(): array
    {
        return [
            'type' => 'composer',
            'url'  => $this->composerUrl(),
        ];
    }
}
