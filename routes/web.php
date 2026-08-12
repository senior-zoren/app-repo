<?php

use App\Models\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Unauthenticated Guest Routes
Route::middleware('guest')->group(function () {
    Route::view('/', 'auth.login')->name('login');
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
