<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('version'); // Composer-compatible (ex: 1.0.0, dev-main)
            $table->enum('type', [
                'library',
                'project',
                'metapackage',
                'composer-plugin',
                'wordpress-plugin',
                'wordpress-theme',
            ])->default('library');
            $table->string('disk')->default('local'); // local or s3
            $table->string('zip_path')->nullable();
            $table->text('description')->nullable();
            $table->json('extra')->nullable(); // additional composer metadata
            $table->timestamps();

            $table->unique(['package_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_versions');
    }
};
