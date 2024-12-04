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
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\JobSearchController;
use App\Http\Controllers\SkillsController;

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

// Web logout route
Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// API logout route
Route::post('/api/logout', [LoginController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('api.logout');

// Job routes
Route::middleware(['auth'])->group(function () {
    Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/search', [JobSearchController::class, 'search'])->name('jobs.search');
    Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
    Route::post('/jobs', [JobController::class, 'store'])->name('jobs.store');
    Route::put('/jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
    Route::delete('/jobs/{job}', [JobController::class, 'destroy'])->name('jobs.destroy');

    // Routes for clients only
    Route::middleware([\App\Http\Middleware\EnsureUserHasRole::class . ':client'])->group(function () {
        Route::post('/jobs', [JobController::class, 'store'])->name('jobs.store');
        Route::put('/jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
        Route::delete('/jobs/{job}', [JobController::class, 'destroy'])->name('jobs.destroy');
    });

    // Routes for developers only
    Route::middleware([\App\Http\Middleware\EnsureUserHasRole::class . ':developer'])->group(function () {
        Route::post('/jobs/{job}/apply', [JobApplicationController::class, 'store'])->name('jobs.apply');
        Route::get('/applications', [JobApplicationController::class, 'index'])->name('applications.index');
    });

    // Job application status updates (for clients)
    Route::middleware([\App\Http\Middleware\EnsureUserHasRole::class . ':client'])->group(function () {
        Route::patch('/applications/{application}', [JobApplicationController::class, 'update'])->name('applications.update');
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
        Route::get('/users/{user}/reviews', [ReviewController::class, 'index'])->name('users.reviews.index');
        Route::get('/users/{user}', [ReviewController::class, 'show'])->name('users.show');
        Route::post('/jobs/{job}/reviews/{user}', [ReviewController::class, 'store'])->name('jobs.reviews.store');
    });

    // Payment routes
    Route::middleware(['auth'])->group(function () {
        Route::get('/jobs/{job}/payments', [PaymentController::class, 'index'])->name('jobs.payments.index');
        Route::post('/jobs/{job}/payments', [PaymentController::class, 'store'])->name('jobs.payments.store');
        Route::patch('/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    });

    // Notification routes
    Route::middleware('auth')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread/count', [NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/{notification}', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
    });

    // Skills routes
    Route::get('/api/skills', [SkillsController::class, 'index'])->name('api.skills.index');
});
