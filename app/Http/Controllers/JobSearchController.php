<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Http\Resources\JobResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class JobSearchController extends Controller
{
    protected array $allowedSortFields = [
        'title',
        'budget',
        'created_at'
    ];

    public function search(Request $request): JsonResponse
    {
        $query = Job::query()
            ->with(['skills', 'client'])
            ->withCount('applications')
            ->where('status', 'open');

        // Filter by skill
        if ($request->filled('skill')) {
            $query->whereHas('skills', function ($query) use ($request) {
                $query->where('name', $request->skill);
            });
        }

        // Filter by title
        if ($request->filled('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }

        // Filter by budget range
        if ($request->filled('min_budget')) {
            $query->where('budget', '>=', $request->min_budget);
        }

        if ($request->filled('max_budget')) {
            $query->where('budget', '<=', $request->max_budget);
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        // Apply sorting
        if ($request->filled('sort') && in_array($request->sort, $this->allowedSortFields)) {
            $direction = $request->input('direction', 'asc');
            $query->orderBy($request->sort, $direction === 'desc' ? 'desc' : 'asc');
        } else {
            // Default sorting by latest
            $query->latest();
        }

        // Apply pagination
        $perPage = $request->input('per_page', 10);
        $jobs = $query->paginate($perPage);

        return response()->json([
            'jobs' => [
                'data' => JobResource::collection($jobs)->collection,
                'current_page' => $jobs->currentPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
                'last_page' => $jobs->lastPage(),
            ]
        ]);
    }
} 