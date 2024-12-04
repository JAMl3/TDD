<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Job;
use App\Models\Review;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    public function index(User $user): JsonResponse
    {
        $reviews = Review::where('reviewee_id', $user->id)
            ->with(['reviewer', 'job'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'data' => $reviews->items(),
            'meta' => [
                'average_rating' => $user->average_rating,
                'total_reviews' => $user->total_reviews,
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total()
            ]
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'average_rating' => $user->average_rating,
            'total_reviews' => $user->total_reviews
        ]);
    }

    public function store(Request $request, Job $job, User $user): JsonResponse
    {
        // Validate base review data
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:1000'],
            'categories' => ['required', 'array'],
        ]);

        // Ensure job is completed
        if ($job->status !== 'completed') {
            return response()->json(['message' => 'Cannot review before job completion'], 403);
        }

        // Get the authenticated user
        $reviewer = auth()->user();

        // Determine if the reviewer is the client or developer for this job
        $isJobClient = $job->user_id === $reviewer->id;
        $isJobDeveloper = $job->applications()
            ->where('user_id', $reviewer->id)
            ->where('status', 'accepted')
            ->exists();

        // Ensure reviewer is part of the job
        if (!$isJobClient && !$isJobDeveloper) {
            return response()->json(['message' => 'You are not authorized to review this job'], 403);
        }

        // Ensure reviewee is the other party in the job
        if ($isJobClient) {
            // Client reviewing developer
            if ($user->id !== $job->applications()->where('status', 'accepted')->first()->user_id) {
                return response()->json(['message' => 'Invalid reviewee'], 403);
            }
            
            // Validate client-specific categories
            $request->validate([
                'categories.communication' => ['required', 'integer', 'min:1', 'max:5'],
                'categories.quality' => ['required', 'integer', 'min:1', 'max:5'],
                'categories.timeliness' => ['required', 'integer', 'min:1', 'max:5'],
            ]);
        } else {
            // Developer reviewing client
            if ($user->id !== $job->user_id) {
                return response()->json(['message' => 'Invalid reviewee'], 403);
            }
            
            // Validate developer-specific categories
            $request->validate([
                'categories.communication' => ['required', 'integer', 'min:1', 'max:5'],
                'categories.payment_timeliness' => ['required', 'integer', 'min:1', 'max:5'],
                'categories.requirement_clarity' => ['required', 'integer', 'min:1', 'max:5'],
            ]);
        }

        // Check if already reviewed
        if (Review::where('reviewer_id', $reviewer->id)
            ->where('reviewee_id', $user->id)
            ->where('job_id', $job->id)
            ->exists()) {
            return response()->json(['message' => 'You have already reviewed this user for this job'], 422);
        }

        // Create the review
        $review = Review::create([
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $user->id,
            'job_id' => $job->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'categories' => $validated['categories']
        ]);

        return response()->json($review, 201);
    }
} 