<?php

use App\Http\Controllers\JobController;
use App\Http\Controllers\JobApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('jobs', JobController::class);
    
    // Job Application Routes
    Route::post('jobs/{job}/apply', [JobApplicationController::class, 'store'])->name('jobs.apply');
    Route::get('applications', [JobApplicationController::class, 'index'])->name('applications.index');
}); 