<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Http\Request;

class JobApplicationController extends Controller
{
    public function index()
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

    public function store(Request $request, Job $job)
    {
        // Validate user is a developer
        if ($request->user()->role !== 'developer') {
            return response()->json(['message' => 'Only developers can apply to jobs'], 403);
        }

        // Check if job is accepting applications
        if ($job->status !== 'open') {
            return response()->json([
                'errors' => [
                    'job' => ['This job is not accepting applications']
                ]
            ], 422);
        }

        $validated = $request->validate([
            'proposal' => ['required', 'string'],
            'timeline' => ['required', 'string'],
            'budget' => ['required', 'numeric', 'min:0']
        ]);

        // Check if developer has already applied
        $existingApplication = JobApplication::where('job_id', $job->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($existingApplication) {
            return response()->json(['message' => 'You have already applied to this job'], 422);
        }

        $application = JobApplication::create([
            'job_id' => $job->id,
            'user_id' => $request->user()->id,
            'status' => 'pending',
            ...$validated
        ]);

        return response()->json($application, 201);
    }
} 