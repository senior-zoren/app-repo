<?php

use App\Models\App;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Unauthenticated Guest Routes
Route::middleware('guest')->group(function () {
    Route::view('/', 'auth.login')->name('login');
});

// Diagnostic Upload Test Route (Handles both GET form display and POST submission)
Route::match(['get', 'post'], '/test-upload', function (Request $request) {
    if ($request->isMethod('get')) {
        return '
            <form action="/test-upload" method="POST" enctype="multipart/form-data" style="font-family: sans-serif; padding: 20px;">
                ' . csrf_field() . '
                <h3>Native PHP File Upload Test</h3>
                <input type="file" name="file" required />
                <br/><br/>
                <button type="submit" style="padding: 8px 16px;">Test Native Upload</button>
            </form>
        ';
    }

    $file = $request->file('file');

    return response()->json([
        'file_received' => $file !== null,
        'original_name' => $file?->getClientOriginalName(),
        'mime_type'     => $file?->getClientMimeType(),
        'real_path'     => $file?->getRealPath(),
        'temp_path'     => $file?->path(),
        'error_code'    => $file?->getError(),
        'error_message' => $file?->getErrorMessage(),
    ]);
});

// Authenticated Protected Routes
Route::middleware('auth')->group(function () {
    Route::view('/home', 'home')->name('home');
    Route::view('/apps/create-release', 'create')->name('apps.create-release');

    Route::get('/apps/{app}/releases', function (App $app) {
        return view('release', ['app' => $app]);
    })->name('apps.releases.show');

    // Logout Action
    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});