<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        // Default to enabled (true) if cache key doesn't exist
        $apiEnabled = Cache::get('api_v1_enabled', true);

        if (! $apiEnabled) {
            return response()->json([
                'status' => 'maintenance',
                'message' => 'The distribution API is temporarily undergoing scheduled maintenance.',
                'timestamp' => now()->toIso8601String(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $next($request);
    }
}
