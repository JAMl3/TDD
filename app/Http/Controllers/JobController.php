<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Http\Requests\StoreJobRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class JobController extends Controller
{
    public function index()
    {
        $jobs = auth()->user()->role === 'client' 
            ? auth()->user()->jobs()->latest()->paginate(10)
            : Job::latest()->paginate(10);
            
        return response()->json($jobs);
    }

    public function show(Job $job)
    {
        return response()->json($job->load('client'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'budget' => ['required', 'numeric', 'min:0'],
            'deadline' => ['required', 'date', 'after:today'],
            'required_skills' => ['required', 'array'],
            'required_skills.*' => ['string', 'max:50'],
        ]);

        if ($request->user()->role !== 'client') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $job = Job::create([
            'user_id' => $request->user()->id,
            ...$validated
        ]);

        if ($request->wantsJson()) {
            return response()->json($job, 201);
        }

        return redirect()->route('jobs.show', $job);
    }

    public function update(Request $request, Job $job)
    {
        if ($request->user()->id !== $job->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'budget' => ['sometimes', 'numeric', 'min:0'],
            'deadline' => ['sometimes', 'date', 'after:today'],
            'required_skills' => ['sometimes', 'array'],
            'required_skills.*' => ['string', 'max:50'],
            'status' => ['sometimes', 'in:open,in_progress,completed,cancelled'],
        ]);

        $job->update($validated);

        return response()->json($job);
    }

    public function destroy(Job $job)
    {
        if (auth()->user()->id !== $job->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $job->delete();

        return response()->noContent();
    }
} 