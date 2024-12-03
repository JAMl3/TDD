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

    public function storeDeveloperReview(Request $request, User $developer): JsonResponse
    {
        try {
            // Validate the request first to ensure we get 422 for validation failures
            $validated = $request->validate([
                'rating' => ['required', 'integer', 'min:1', 'max:5'],
                'comment' => ['required', 'string'],
                'categories' => ['required', 'array'],
                'categories.communication' => ['required', 'integer', 'min:1', 'max:5'],
                'categories.quality' => ['required', 'integer', 'min:1', 'max:5'],
                'categories.timeliness' => ['required', 'integer', 'min:1', 'max:5']
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        Log::debug('Review attempt', [
            'auth_id' => auth()->id(),
            'developer_id' => $developer->id,
            'validated' => $validated
        ]);

        Log::debug('Developer review check', [
            'auth_user' => [
                'id' => auth()->id(),
                'role' => auth()->user()->role
            ],
            'developer' => [
                'id' => $developer->id,
                'role' => $developer->role
            ]
        ]);

        Log::debug('Developer review auth check', [
            'auth_user' => [
                'id' => auth()->id(),
                'role' => auth()->user()->role,
                'is_client' => auth()->user()->isClient()
            ],
            'developer' => [
                'id' => $developer->id,
                'role' => $developer->role,
                'is_developer' => $developer->isDeveloper()
            ]
        ]);

        Log::debug('Developer review role check', [
            'auth_user' => [
                'id' => auth()->id(),
                'role' => auth()->user()->role,
                'is_client' => auth()->user()->isClient(),
                'raw_user' => auth()->user()->toArray()
            ],
            'developer' => [
                'id' => $developer->id,
                'role' => $developer->role,
                'is_developer' => $developer->isDeveloper(),
                'raw_user' => $developer->toArray()
            ]
        ]);

        // Check if there's a completed job between the client and developer
        $completedJob = Job::with(['applications' => function ($query) use ($developer) {
                $query->where('user_id', $developer->id)
                    ->where('status', 'accepted');
            }])
            ->where('user_id', auth()->id())
            ->where('status', 'completed')
            ->whereHas('applications', function ($query) use ($developer) {
                $query->where('user_id', $developer->id)
                    ->where('status', 'accepted');
            })
            ->first();

        Log::debug('Developer review job query', [
            'completed_job' => $completedJob ? [
                'id' => $completedJob->id,
                'user_id' => $completedJob->user_id,
                'status' => $completedJob->status
            ] : null,
            'query_conditions' => [
                'user_id' => auth()->id(),
                'developer_id' => $developer->id
            ]
        ]);

        Log::debug('Developer review job query result', [
            'completed_job' => $completedJob ? [
                'id' => $completedJob->id,
                'user_id' => $completedJob->user_id,
                'status' => $completedJob->status,
                'applications' => $completedJob->applications->map(fn($app) => [
                    'id' => $app->id,
                    'user_id' => $app->user_id,
                    'status' => $app->status
                ])->toArray()
            ] : null,
            'query_conditions' => [
                'job_owner_id' => auth()->id(),
                'developer_id' => $developer->id,
                'job_status' => 'completed',
                'application_status' => 'accepted'
            ]
        ]);

        Log::debug('Developer review job check', [
            'job_query_params' => [
                'user_id' => auth()->id(),
                'status' => 'completed',
                'developer_id' => $developer->id
            ],
            'found_job' => $completedJob ? [
                'id' => $completedJob->id,
                'user_id' => $completedJob->user_id,
                'status' => $completedJob->status,
                'applications' => $completedJob->applications->map(fn($app) => [
                    'id' => $app->id,
                    'user_id' => $app->user_id,
                    'status' => $app->status
                ])->toArray()
            ] : null,
            'raw_sql' => Job::where('user_id', auth()->id())
                ->where('status', 'completed')
                ->whereHas('applications', function ($query) use ($developer) {
                    $query->where('user_id', $developer->id)
                        ->where('status', 'accepted');
                })
                ->toSql()
        ]);

        Log::debug('Developer review final check', [
            'auth_user' => [
                'id' => auth()->id(),
                'role' => auth()->user()->role,
                'is_client' => auth()->user()->isClient()
            ],
            'developer' => [
                'id' => $developer->id,
                'role' => $developer->role,
                'is_developer' => $developer->isDeveloper()
            ],
            'job' => $completedJob ? [
                'id' => $completedJob->id,
                'user_id' => $completedJob->user_id,
                'status' => $completedJob->status,
                'applications' => $completedJob->applications->map(fn($app) => [
                    'id' => $app->id,
                    'user_id' => $app->user_id,
                    'status' => $app->status
                ])->toArray()
            ] : null
        ]);

        Log::debug('Developer review job completion check', [
            'auth_user' => [
                'id' => auth()->id(),
                'role' => auth()->user()->role,
                'is_client' => auth()->user()->isClient()
            ],
            'developer' => [
                'id' => $developer->id,
                'role' => $developer->role,
                'is_developer' => $developer->isDeveloper()
            ],
            'job_query' => [
                'user_id' => auth()->id(),
                'status' => 'completed',
                'developer_id' => $developer->id,
                'application_status' => 'accepted'
            ],
            'found_job' => $completedJob ? [
                'id' => $completedJob->id,
                'user_id' => $completedJob->user_id,
                'status' => $completedJob->status,
                'applications' => $completedJob->applications->map(fn($app) => [
                    'id' => $app->id,
                    'user_id' => $app->user_id,
                    'status' => $app->status
                ])->toArray()
            ] : null
        ]);

        if (!$completedJob) {
            return response()->json(['message' => 'Cannot review before job completion'], 403);
        }

        // Check if already reviewed
        $existingReview = Review::where('reviewer_id', auth()->id())
            ->where('reviewee_id', $developer->id)
            ->where('job_id', $completedJob->id)
            ->exists();

        if ($existingReview) {
            return response()->json(['message' => 'You have already reviewed this developer for this job'], 422);
        }

        // Check if reviewee is a developer
        if (!$developer->isDeveloper()) {
            return response()->json(['message' => 'User is not a developer'], 403);
        }

        // Check if reviewer is a client
        if (!auth()->user()->isClient()) {
            return response()->json(['message' => 'Only clients can review developers'], 403);
        }

        $review = Review::create([
            'reviewer_id' => auth()->id(),
            'reviewee_id' => $developer->id,
            'job_id' => $completedJob->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'categories' => $validated['categories']
        ]);

        return response()->json($review, 201);
    }

    public function storeClientReview(Request $request, User $client): JsonResponse
    {
        try {
            // Validate the request first to ensure we get 422 for validation failures
            $validated = $request->validate([
                'rating' => ['required', 'integer', 'min:1', 'max:5'],
                'comment' => ['required', 'string'],
                'categories' => ['required', 'array'],
                'categories.communication' => ['required', 'integer', 'min:1', 'max:5'],
                'categories.payment_timeliness' => ['required', 'integer', 'min:1', 'max:5'],
                'categories.requirement_clarity' => ['required', 'integer', 'min:1', 'max:5']
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        Log::debug('Client review attempt', [
            'auth_id' => auth()->id(),
            'client_id' => $client->id,
            'validated' => $validated
        ]);

        Log::debug('Client review auth check', [
            'auth_user' => [
                'id' => auth()->id(),
                'role' => auth()->user()->role,
                'is_developer' => auth()->user()->isDeveloper()
            ],
            'client' => [
                'id' => $client->id,
                'role' => $client->role,
                'is_client' => $client->isClient()
            ]
        ]);

        Log::debug('Client review role check', [
            'auth_user' => [
                'id' => auth()->id(),
                'role' => auth()->user()->role,
                'is_developer' => auth()->user()->isDeveloper(),
                'raw_user' => auth()->user()->toArray()
            ],
            'client' => [
                'id' => $client->id,
                'role' => $client->role,
                'is_client' => $client->isClient(),
                'raw_user' => $client->toArray()
            ]
        ]);

        // Check if there's a completed job between the developer and client
        $completedJob = Job::with(['applications' => function ($query) {
                $query->where('user_id', auth()->id())
                    ->where('status', 'accepted');
            }])
            ->where('user_id', $client->id)
            ->where('status', 'completed')
            ->whereHas('applications', function ($query) {
                $query->where('user_id', auth()->id())
                    ->where('status', 'accepted');
            })
            ->first();

        Log::debug('Client review job query', [
            'completed_job' => $completedJob ? [
                'id' => $completedJob->id,
                'user_id' => $completedJob->user_id,
                'status' => $completedJob->status
            ] : null,
            'query_conditions' => [
                'user_id' => auth()->id(),
                'developer_id' => $client->id
            ]
        ]);

        Log::debug('Client review job query result', [
            'completed_job' => $completedJob ? [
                'id' => $completedJob->id,
                'user_id' => $completedJob->user_id,
                'status' => $completedJob->status,
                'applications' => $completedJob->applications->map(fn($app) => [
                    'id' => $app->id,
                    'user_id' => $app->user_id,
                    'status' => $app->status
                ])->toArray()
            ] : null,
            'query_conditions' => [
                'job_owner_id' => $client->id,
                'developer_id' => auth()->id(),
                'job_status' => 'completed',
                'application_status' => 'accepted'
            ]
        ]);

        Log::debug('Client review job check', [
            'job_query_params' => [
                'user_id' => $client->id,
                'status' => 'completed',
                'developer_id' => auth()->id()
            ],
            'found_job' => $completedJob ? [
                'id' => $completedJob->id,
                'user_id' => $completedJob->user_id,
                'status' => $completedJob->status,
                'applications' => $completedJob->applications->map(fn($app) => [
                    'id' => $app->id,
                    'user_id' => $app->user_id,
                    'status' => $app->status
                ])->toArray()
            ] : null,
            'raw_sql' => Job::where('user_id', $client->id)
                ->where('status', 'completed')
                ->whereHas('applications', function ($query) {
                    $query->where('user_id', auth()->id())
                        ->where('status', 'accepted');
                })
                ->toSql()
        ]);

        Log::debug('Client review final check', [
            'auth_user' => [
                'id' => auth()->id(),
                'role' => auth()->user()->role,
                'is_developer' => auth()->user()->isDeveloper()
            ],
            'client' => [
                'id' => $client->id,
                'role' => $client->role,
                'is_client' => $client->isClient()
            ],
            'job' => $completedJob ? [
                'id' => $completedJob->id,
                'user_id' => $completedJob->user_id,
                'status' => $completedJob->status,
                'applications' => $completedJob->applications->map(fn($app) => [
                    'id' => $app->id,
                    'user_id' => $app->user_id,
                    'status' => $app->status
                ])->toArray()
            ] : null
        ]);

        Log::debug('Client review job completion check', [
            'auth_user' => [
                'id' => auth()->id(),
                'role' => auth()->user()->role,
                'is_developer' => auth()->user()->isDeveloper()
            ],
            'client' => [
                'id' => $client->id,
                'role' => $client->role,
                'is_client' => $client->isClient()
            ],
            'completed_job' => $completedJob ? [
                'id' => $completedJob->id,
                'user_id' => $completedJob->user_id,
                'status' => $completedJob->status,
                'applications' => $completedJob->applications->map(fn($app) => [
                    'id' => $app->id,
                    'user_id' => $app->user_id,
                    'status' => $app->status
                ])->toArray()
            ] : null,
            'sql' => Job::where('user_id', $client->id)
                ->where('status', 'completed')
                ->whereHas('applications', function ($query) {
                    $query->where('user_id', auth()->id())
                        ->where('status', 'accepted');
                })
                ->toSql()
        ]);

        if (!$completedJob) {
            return response()->json(['message' => 'Cannot review before job completion'], 403);
        }

        // Check if already reviewed
        $existingReview = Review::where('reviewer_id', auth()->id())
            ->where('reviewee_id', $client->id)
            ->where('job_id', $completedJob->id)
            ->exists();

        if ($existingReview) {
            return response()->json(['message' => 'You have already reviewed this client for this job'], 422);
        }

        // Check if reviewee is a client
        if (!$client->isClient()) {
            return response()->json(['message' => 'User is not a client'], 403);
        }

        // Check if reviewer is a developer
        if (!auth()->user()->isDeveloper()) {
            return response()->json(['message' => 'Only developers can review clients'], 403);
        }

        $review = Review::create([
            'reviewer_id' => auth()->id(),
            'reviewee_id' => $client->id,
            'job_id' => $completedJob->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'categories' => $validated['categories']
        ]);

        return response()->json($review, 201);
    }
} 