<?php

use App\Http\Controllers\Api\AppDistributionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/apps')->group(function () {

    // Health check endpoint (always accessible)
    Route::get('health', [AppDistributionController::class, 'health'])
        ->middleware('throttle:api');

    // Protected by API Kill Switch using the alias
    Route::middleware('api.enabled')->group(function () {

        // Fetch latest metadata & release info
        Route::get('{slug}/latest', [AppDistributionController::class, 'getLatestVersion'])
            ->middleware('throttle:api');

        // Stream installer binary directly
        Route::get('{slug}/download/{version?}', [AppDistributionController::class, 'downloadBinary'])
            ->middleware('throttle:downloads')
            ->name('api.apps.download');
    });

});
