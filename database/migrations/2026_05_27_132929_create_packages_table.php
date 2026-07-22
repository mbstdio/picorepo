<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // ex: "elementor-pro" (without vendor)
            $table->text('description')->nullable();
            $table->timestamps();

            // vendor/package must be globally unique
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
