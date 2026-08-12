<?php

namespace Database\Seeders;

use App\Models\App;
use App\Models\AppRelease;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class AppDistributionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $appsData = [
            [
                'name' => 'CHRONO PMIC Parser',
                'slug' => 'chrono',
                'description' => 'PMIC Log Parser and Desktop Analysis Tool for Engineers.',
                'releases' => [
                    [
                        'version' => '1.0.0',
                        'release_notes' => "Initial release.\n- Basic log parsing features.\n- Light theme UI.",
                        'is_mandatory' => false,
                    ],
                    [
                        'version' => '1.5.0',
                        'release_notes' => "Major update.\n- Added LZMA solid compression.\n- Optimized memory usage for 1GB+ log files.",
                        'is_mandatory' => true,
                    ],
                ],
            ],
            [
                'name' => 'TIME KEEP Worklog Tracker',
                'slug' => 'time-keep',
                'description' => 'Automated desktop activity and project time tracking utility.',
                'releases' => [
                    [
                        'version' => '2.1.0',
                        'release_notes' => 'Bug fixes and performance improvements for multi-monitor setups.',
                        'is_mandatory' => false,
                    ],
                ],
            ],
        ];

        foreach ($appsData as $appData) {
            // Create or update the primary App record
            $app = App::updateOrCreate(
                ['slug' => $appData['slug']],
                [
                    'name' => $appData['name'],
                    'description' => $appData['description'],
                    'is_active' => true,
                ]
            );

            foreach ($appData['releases'] as $releaseData) {
                // Relative path stored in DB (Laravel maps this relative to storage/app/private/)
                $relativePath = "releases/{$app->slug}/{$releaseData['version']}/{$app->slug}_v{$releaseData['version']}_Setup.exe";

                // Ensure target folder exists inside storage/app/private/
                Storage::disk('local')->makeDirectory("releases/{$app->slug}/{$releaseData['version']}");

                // If no real file exists at storage/app/private/{relativePath}, create a mock binary file
                if (! Storage::disk('local')->exists($relativePath)) {
                    $dummyContent = 'MZ'.str_repeat("\x00\x90\x00\x03\x00\x00\x00\x04", 2048); // Mock PE/EXE header
                    Storage::disk('local')->put($relativePath, $dummyContent);
                }

                // Resolve absolute physical path on server
                $fullStoragePath = Storage::disk('local')->path($relativePath);

                // Calculate file metadata dynamically from actual file on disk
                $fileSize = filesize($fullStoragePath);
                $checksum = hash_file('sha256', $fullStoragePath);

                // Save or update AppRelease record
                AppRelease::updateOrCreate(
                    [
                        'app_id' => $app->id,
                        'version' => $releaseData['version'],
                    ],
                    [
                        'file_path' => $relativePath, // Stores: releases/chrono/1.0.0/chrono_v1.0.0_Setup.exe
                        'file_size' => $fileSize,
                        'checksum_sha256' => $checksum,
                        'release_notes' => $releaseData['release_notes'],
                        'is_mandatory' => $releaseData['is_mandatory'],
                    ]
                );
            }
        }

        $this->command->info('App Distribution Seeder executed successfully! Files mapped to storage/app/private/.');
    }
}
