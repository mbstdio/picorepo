<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    public function repositories(): BelongsToMany
    {
        return $this->belongsToMany(Repository::class, 'repository_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function ownsRepository(Repository $repository): bool
    {
        return $this->repositories()
            ->wherePivot('role', 'owner')
            ->where('repositories.id', $repository->id)
            ->exists();
    }

    public function hasAccessToRepository(Repository $repository): bool
    {
        return $this->repositories()
            ->where('repositories.id', $repository->id)
            ->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

