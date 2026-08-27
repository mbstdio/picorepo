<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $repositories = DB::table('repositories')->select('id', 'name')->get();
        $packages = DB::table('packages')->select('repository_id', 'name')->get();

        if ($repositories->contains(fn ($repository) => ! preg_match('/^[a-z0-9]+([._-][a-z0-9]+)*$/', strtolower($repository->name)))) {
            throw new RuntimeException('Repository names cannot be normalized because they contain invalid Composer vendor names.');
        }

        if ($packages->contains(fn ($package) => ! preg_match('/^[a-z0-9]+(([._]|-{1,2})[a-z0-9]+)*$/', strtolower($package->name)))) {
            throw new RuntimeException('Package names cannot be normalized because they contain invalid Composer package names.');
        }

        $normalizedNames = $repositories->map(fn ($repository) => strtolower($repository->name));
        $normalizedSlugs = $repositories->map(fn ($repository) => Str::slug(strtolower($repository->name)));

        if ($normalizedNames->duplicates()->isNotEmpty() || $normalizedSlugs->duplicates()->isNotEmpty()) {
            throw new RuntimeException('Repository names cannot be normalized because canonical names or generated slugs would collide.');
        }

        $packageNames = $packages->map(fn ($package) => $package->repository_id.':'.strtolower($package->name));

        if ($packageNames->duplicates()->isNotEmpty()) {
            throw new RuntimeException('Package names cannot be normalized because canonical names would collide within a repository.');
        }

        Schema::table('packages', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        foreach ($repositories as $repository) {
            DB::table('repositories')->where('id', $repository->id)->update([
                'slug' => "__migrating_{$repository->id}",
            ]);
        }

        foreach ($repositories as $repository) {
            DB::table('repositories')->where('id', $repository->id)->update([
                'name' => strtolower($repository->name),
                'slug' => Str::slug(strtolower($repository->name)),
            ]);
        }

        DB::table('packages')->update(['name' => DB::raw('LOWER(name)')]);

        Schema::table('packages', function (Blueprint $table) {
            $table->unique(['repository_id', 'name']);
        });
    }

    public function down(): void
    {
        if (DB::table('packages')->select('name')->get()->duplicates('name')->isNotEmpty()) {
            throw new RuntimeException('Cannot restore global package uniqueness while repositories contain duplicate package names.');
        }

        Schema::table('packages', function (Blueprint $table) {
            $table->dropUnique(['repository_id', 'name']);
            $table->unique('name');
        });
    }
};
