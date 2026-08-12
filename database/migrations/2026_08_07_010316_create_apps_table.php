<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Primary Apps Table
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "CHRONO PMIC Parser"
            $table->string('slug')->unique(); // e.g., "chrono"
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. App Releases Table (Binary Releases Metadata)
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained()->cascadeOnDelete();
            $table->string('version'); // e.g., "1.5.0"
            $table->string('platform')->default('windows'); // e.g., "windows", "macos", "linux", "all"
            $table->string('file_path'); // Path e.g., "releases/chrono/1.5.0/CHRONO_Setup.exe"
            $table->unsignedBigInteger('file_size'); // Size in bytes
            $table->string('checksum_sha256', 64); // SHA-256 for file integrity verification
            $table->text('release_notes')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->timestamps();

            // Compound unique constraint allowing same version across different platforms
            $table->unique(['app_id', 'version', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
        Schema::dropIfExists('apps');
    }
};
