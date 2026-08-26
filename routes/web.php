<?php

use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\ComposerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PackageVersionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\RepositoryUserController;
use Illuminate\Support\Facades\Route;

// Redirect root to dashboard
Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Repositories
    Route::resource('repositories', RepositoryController::class);

    // Packages nested in repositories
    Route::scopeBindings()->prefix('repositories/{repository}/packages')->name('repositories.packages.')->group(function () {
        Route::get('/', [PackageController::class, 'index'])->name('index');
        Route::get('/create', [PackageController::class, 'create'])->name('create');
        Route::post('/', [PackageController::class, 'store'])->name('store');
        Route::get('/{package}/edit', [PackageController::class, 'edit'])->name('edit');
        Route::get('/{package}', [PackageController::class, 'show'])->name('show');
        Route::put('/{package}', [PackageController::class, 'update'])->name('update');
        Route::delete('/{package}', [PackageController::class, 'destroy'])->name('destroy');

        // Versions nested in packages
        Route::prefix('/{package}/versions')->name('versions.')->group(function () {
            Route::get('/create', [PackageVersionController::class, 'create'])->name('create');
            Route::post('/', [PackageVersionController::class, 'store'])->name('store');
            Route::get('/{version}/edit', [PackageVersionController::class, 'edit'])->name('edit');
            Route::put('/{version}', [PackageVersionController::class, 'update'])->name('update');
            Route::delete('/{version}', [PackageVersionController::class, 'destroy'])->name('destroy');
        });
    });

    // Repository user management
    Route::prefix('repositories/{repository}/users')->name('repositories.users.')->group(function () {
        Route::get('/', [RepositoryUserController::class, 'index'])->name('index');
        Route::post('/', [RepositoryUserController::class, 'store'])->name('store');
        Route::patch('/{user}', [RepositoryUserController::class, 'update'])->name('update');
        Route::delete('/{user}', [RepositoryUserController::class, 'destroy'])->name('destroy');
    });

    // API Tokens
    Route::get('/profile/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('/profile/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('/profile/api-tokens/{tokenId}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
});

// Package download (supports both token and session auth)
Route::get('/download/{version}', [DownloadController::class, 'download'])->name('versions.download');

// Composer endpoints (public, auth handled inside controller)
Route::prefix('composer/{repositorySlug}')->group(function () {
    Route::get('/packages.json', [ComposerController::class, 'metadata'])->name('composer.metadata');
    Route::get('/p2/{vendor}/{packageName}.json', [ComposerController::class, 'packageMetadata'])->name('composer.package-metadata');
    Route::get('/p/$package$.json', [ComposerController::class, 'allPackages'])->name('composer.all-packages');
    Route::get('/p/{packageName}.json', [ComposerController::class, 'packageMeta'])->name('composer.package')->where('packageName', '[^/]+');
});

require __DIR__.'/auth.php';
