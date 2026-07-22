<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Repository;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        $repositoryIds = $user->repositories()->pluck('repositories.id');

        $stats = [
            'repositories' => Repository::count(),
            'my_repos'     => $repositoryIds->count(),
            'packages'     => Package::whereIn('repository_id', $repositoryIds)->count(),
            'total_packages' => Package::count(),
        ];

        $recentRepos = Repository::whereIn('id', $repositoryIds)
            ->withCount('packages')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($r) => [
                'id'             => $r->id,
                'name'           => $r->name,
                'slug'           => $r->slug,
                'type'           => $r->type,
                'packages_count' => $r->packages_count,
            ]);

        return Inertia::render('Dashboard', [
            'stats'       => $stats,
            'recentRepos' => $recentRepos,
        ]);
    }
}
