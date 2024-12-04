<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Notifications\ApplicationSubmitted;
use App\Notifications\ApplicationStatusChanged;

class JobApplicationController extends Controller
{
    public function index(): JsonResponse
    {
        $applications = JobApplication::where('user_id', auth()->id())
            ->with('job')
            ->latest()
            ->paginate(10);

        return response()->json([
            'data' => $applications->items(),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total()
            ]
        ]);
    }

    public function store(Request $request, Job $job): JsonResponse
    {
        // Validate user is a developer
        if (auth()->user()->role !== 'developer') {
            return response()->json(['message' => 'Only developers can apply to jobs'], 403);
        }

        // Check if job is open
        if ($job->status !== 'open') {
            return response()->json([
                'errors' => [
                    'job' => ['Cannot apply to closed jobs']
                ]
            ], 422);
        }

        // Validate request
        $validated = $request->validate([
            'proposal' => ['required', 'string', 'max:1000'],
            'budget' => ['required', 'numeric', 'min:0'],
            'timeline' => ['required', 'integer', 'min:1']
        ]);

        // Create application
        $application = JobApplication::create([
            'job_id' => $job->id,
            'user_id' => auth()->id(),
            'proposal' => $validated['proposal'],
            'budget' => $validated['budget'],
            'timeline' => $validated['timeline'],
            'status' => 'pending'
        ]);

        // Load relationships
        $application->load(['job', 'user']);

        // Notify job owner
        $job->client->notify(new ApplicationSubmitted($application));

        return response()->json($application, 201);
    }

    public function update(Request $request, JobApplication $application): JsonResponse
    {
        // Ensure user is job owner
        if ($application->job->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validate request
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,accepted,rejected']
        ]);

        // Update application
        $application->update([
            'status' => $validated['status']
        ]);

        // Load relationships
        $application->load(['job', 'user']);

        // Notify developer of status change
        $application->user->notify(new ApplicationStatusChanged($application, $validated['status']));

        return response()->json($application);
    }
} 