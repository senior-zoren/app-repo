<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AppRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class AppDistributionController extends Controller
{
    /**
     * Check API system and database health status.
     */
    public function health(): JsonResponse
    {
        $apiEnabled = (bool) Cache::get('api_v1_enabled', true);

        try {
            DB::connection()->getPdo();
            $dbConnected = true;
        } catch (Throwable) {
            $dbConnected = false;
        }

        if (! $apiEnabled) {
            $status = 'disabled';
        } elseif ($dbConnected) {
            $status = 'ok';
        } else {
            $status = 'degraded';
        }

        $dbStatus = $dbConnected ? 'connected' : 'error';

        return response()->json([
            'status' => $status,
            'api_enabled' => $apiEnabled,
            'database' => $dbStatus,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get latest app version metadata
     */
    public function getLatestVersion(Request $request, string $slug): JsonResponse
    {
        $app = App::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $app) {
            return response()->json(['error' => 'App not found or inactive.'], 404);
        }

        $platform = $request->query('platform');

        /** @var AppRelease|null $latestRelease */
        $latestRelease = $app->releases()
            ->when($platform, function ($query) use ($platform) {
                $query->whereIn('platform', [$platform, 'all']);
            })
            ->latest('id')
            ->first();

        if (! $latestRelease) {
            return response()->json(['error' => 'No releases available for this app or platform.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'app' => [
                'name' => $app->name,
                'slug' => $app->slug,
            ],
            'release' => [
                'version' => $latestRelease->version,
                'platform' => $latestRelease->platform ?? 'all',
                'download_url' => route('api.apps.download', array_filter([
                    'slug' => $app->slug,
                    'version' => $latestRelease->version,
                    'platform' => ($latestRelease->platform && $latestRelease->platform !== 'all') ? $latestRelease->platform : null,
                ])),
                'file_size' => (int) $latestRelease->file_size,
                'checksum_sha256' => $latestRelease->checksum_sha256,
                'changelog' => $latestRelease->changelog ?? $latestRelease->release_notes ?? null,
                'is_mandatory' => (bool) $latestRelease->is_mandatory,
                'published_at' => $latestRelease->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Stream binary download directly
     */
    public function downloadBinary(Request $request, string $slug, ?string $version = null): BinaryFileResponse|JsonResponse
    {
        $app = App::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $app) {
            return response()->json(['error' => 'App not found.'], 404);
        }

        $platform = $request->query('platform');

        $query = $app->releases();

        if ($version) {
            $query->where('version', ltrim($version, 'v'));
        }

        if ($platform) {
            $query->whereIn('platform', [$platform, 'all']);
        }

        /** @var AppRelease|null $release */
        // Always sort by latest ID to ensure deterministic resolution
        $release = $query->latest('id')->first();

        if (! $release) {
            return response()->json(['error' => 'Requested binary release not found.'], 404);
        }

        if (! Storage::disk('local')->exists($release->file_path)) {
            return response()->json(['error' => 'Installer binary missing on storage server.'], 404);
        }

        // 1. Get the actual extension from the stored file path (.zip, .exe, .dmg, etc.)
        $extension = pathinfo($release->file_path, PATHINFO_EXTENSION);

        // 2. Build the filename dynamically preserving the actual extension
        $fileName = $release->file_name ?? "{$app->slug}_v{$release->version}.{$extension}";

        $absolutePath = Storage::disk('local')->path($release->file_path);

        // 3. Set the correct MIME type dynamically
        $contentType = strtolower($extension) === 'zip' ? 'application/zip' : 'application/octet-stream';

        return response()->download($absolutePath, $fileName, [
            'Content-Type' => $contentType,
            'X-Checksum-SHA256' => $release->checksum_sha256 ?? '',
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
