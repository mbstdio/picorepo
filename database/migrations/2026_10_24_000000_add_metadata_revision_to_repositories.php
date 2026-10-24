<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->string('metadata_revision', 36)->default('initial');
            $table->index(['created_at', 'id']);
        });
        Schema::table('packages', function (Blueprint $table) {
            $table->index(['repository_id', 'created_at', 'id']);
        });
        Schema::table('package_versions', function (Blueprint $table) {
            $table->index(['package_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropIndex(['created_at', 'id']);
            $table->dropColumn('metadata_revision');
        });
        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['repository_id', 'created_at', 'id']);
        });
        Schema::table('package_versions', function (Blueprint $table) {
            $table->dropIndex(['package_id', 'created_at', 'id']);
        });
    }
};
