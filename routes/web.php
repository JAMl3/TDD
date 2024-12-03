<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DeveloperProfileController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ReviewController;

Route::get('/', function () {
    return view('welcome');
});

// Public routes for developer profiles
Route::get('/developer/search', [DeveloperProfileController::class, 'search'])
    ->name('developer.search');
Route::get('/developer/profile/{profile}', [DeveloperProfileController::class, 'show'])
    ->name('developer.profile.show');

// Auth routes
Route::post('/register', [RegisterController::class, 'store'])
    ->middleware('guest')
    ->name('register');

Route::post('/login', [LoginController::class, 'store'])
    ->middleware('guest')
    ->name('login');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Job routes
Route::middleware(['auth'])->group(function () {
    // Routes for both developers and clients
    Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');

    // Routes for clients only
    Route::middleware('auth')->group(function () {
        Route::post('/jobs', [JobController::class, 'store'])
            ->middleware([\App\Http\Middleware\EnsureUserHasRole::class . ':client'])
            ->name('jobs.store');
        Route::put('/jobs/{job}', [JobController::class, 'update'])
            ->middleware([\App\Http\Middleware\EnsureUserHasRole::class . ':client'])
            ->name('jobs.update');
        Route::delete('/jobs/{job}', [JobController::class, 'destroy'])
            ->middleware([\App\Http\Middleware\EnsureUserHasRole::class . ':client'])
            ->name('jobs.destroy');
    });

    // Routes for developers only
    Route::middleware('auth')->group(function () {
        Route::post('/jobs/{job}/apply', [JobApplicationController::class, 'store'])
            ->middleware([\App\Http\Middleware\EnsureUserHasRole::class . ':developer'])
            ->name('jobs.apply');
        Route::get('/applications', [JobApplicationController::class, 'index'])
            ->middleware([\App\Http\Middleware\EnsureUserHasRole::class . ':developer'])
            ->name('applications.index');
    });

    // Routes for admins only
    Route::middleware('auth')->group(function () {
        Route::get('/admin/dashboard', [AdminController::class, 'index'])
            ->middleware([\App\Http\Middleware\EnsureUserHasRole::class . ':admin'])
            ->name('admin.dashboard');
    });

    // Developer Profile routes
    Route::middleware(['auth'])->group(function () {
        Route::post('/developer/profile', [DeveloperProfileController::class, 'store'])
            ->middleware([\App\Http\Middleware\EnsureUserHasRole::class . ':developer'])
            ->name('developer.profile.store');
        
        Route::put('/developer/profile/{profile}', [DeveloperProfileController::class, 'update'])
            ->middleware([\App\Http\Middleware\EnsureUserHasRole::class . ':developer'])
            ->name('developer.profile.update');

        Route::put('/developer/profile/{profile}/privacy', [DeveloperProfileController::class, 'updatePrivacy'])
            ->middleware([\App\Http\Middleware\EnsureUserHasRole::class . ':developer'])
            ->name('developer.profile.privacy.update');
    });

    // Message routes
    Route::middleware(['auth'])->group(function () {
        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
        Route::put('/messages/{message}/read', [MessageController::class, 'markAsRead'])->name('messages.mark-as-read');
    });

    // Review routes
    Route::middleware(['auth'])->group(function () {
        Route::get('/developers/{user}/reviews', [ReviewController::class, 'index'])->name('developers.reviews.index');
        Route::get('/clients/{user}/reviews', [ReviewController::class, 'index'])->name('clients.reviews.index');
        Route::get('/developers/{user}', [ReviewController::class, 'show'])->name('developers.show');
        Route::get('/clients/{user}', [ReviewController::class, 'show'])->name('clients.show');
        Route::post('/developers/{user}/reviews', [ReviewController::class, 'storeDeveloperReview'])->name('developers.reviews.store');
        Route::post('/clients/{user}/reviews', [ReviewController::class, 'storeClientReview'])->name('clients.reviews.store');
    });
});
