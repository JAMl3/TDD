<?php

namespace App\Http\Controllers;

use App\Models\DeveloperProfile;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DeveloperProfileController extends Controller
{
    public function store(Request $request)
    {
        if ($request->user()->role !== 'developer') {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'bio' => 'required|string',
            'skills' => 'required|array',
            'skills.*' => 'string',
            'hourly_rate' => 'required|numeric|min:0',
            'portfolio_items' => 'array',
            'portfolio_items.*.title' => 'required|string',
            'portfolio_items.*.description' => 'required|string',
            'portfolio_items.*.image' => 'required|image',
            'github_url' => 'nullable|url',
            'linkedin_url' => 'nullable|url'
        ]);

        $profile = DeveloperProfile::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'bio' => $validated['bio'],
            'hourly_rate' => $validated['hourly_rate'],
            'github_url' => $validated['github_url'],
            'linkedin_url' => $validated['linkedin_url']
        ]);

        foreach ($validated['skills'] as $skillName) {
            $skill = Skill::firstOrCreate(['name' => $skillName]);
            $profile->skills()->attach($skill);
        }

        foreach ($validated['portfolio_items'] as $item) {
            // Create the directory if it doesn't exist
            $directory = 'portfolio/' . $request->user()->id;
            Storage::disk('public')->makeDirectory($directory);

            // Store the file
            $path = $item['image']->store($directory, 'public');
            
            // Create portfolio item
            $profile->portfolioItems()->create([
                'title' => $item['title'],
                'description' => $item['description'],
                'image_path' => $path
            ]);
        }

        return redirect('/developer/profile');
    }

    public function update(Request $request, DeveloperProfile $profile)
    {
        if ($request->user()->role !== 'developer' || $request->user()->id !== $profile->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'bio' => 'required|string',
            'skills' => 'required|array',
            'skills.*' => 'string',
            'hourly_rate' => 'required|numeric|min:0',
            'portfolio_items' => 'array',
            'portfolio_items.*.title' => 'required|string',
            'portfolio_items.*.description' => 'required|string',
            'portfolio_items.*.image' => 'required|image',
            'github_url' => 'nullable|url',
            'linkedin_url' => 'nullable|url'
        ]);

        $profile->update([
            'title' => $validated['title'],
            'bio' => $validated['bio'],
            'hourly_rate' => $validated['hourly_rate'],
            'github_url' => $validated['github_url'],
            'linkedin_url' => $validated['linkedin_url']
        ]);

        // Update skills
        $profile->skills()->detach();
        foreach ($validated['skills'] as $skillName) {
            $skill = Skill::firstOrCreate(['name' => $skillName]);
            $profile->skills()->attach($skill);
        }

        // Update portfolio items
        $profile->portfolioItems()->delete();
        foreach ($validated['portfolio_items'] as $item) {
            // Create the directory if it doesn't exist
            $directory = 'portfolio/' . $request->user()->id;
            Storage::disk('public')->makeDirectory($directory);

            // Store the file
            $path = $item['image']->store($directory, 'public');
            
            // Create portfolio item
            $profile->portfolioItems()->create([
                'title' => $item['title'],
                'description' => $item['description'],
                'image_path' => $path
            ]);
        }

        return redirect('/developer/profile');
    }

    public function search(Request $request)
    {
        $query = DeveloperProfile::query()
            ->with(['user', 'skills'])
            ->when($request->filled('skill'), function ($query) use ($request) {
                $query->whereHas('skills', function ($query) use ($request) {
                    $query->where('name', $request->skill);
                });
            });

        $developers = $query->get();

        if ($request->wantsJson()) {
            return response()->json([
                'developers' => $developers->map(function ($developer) {
                    return [
                        'id' => $developer->id,
                        'title' => $developer->title,
                        'bio' => $developer->bio,
                        'hourly_rate' => $developer->hourly_rate,
                        'skills' => $developer->skills->pluck('name'),
                        'user' => [
                            'name' => $developer->user->name
                        ]
                    ];
                })
            ]);
        }

        return view('developer.search', [
            'developers' => $developers
        ]);
    }

    public function show(DeveloperProfile $profile)
    {
        $showPrivateInfo = auth()->check() && 
            auth()->user()->id === $profile->user_id;

        if (request()->wantsJson()) {
            return response()->json([
                'profile' => [
                    'id' => $profile->id,
                    'title' => $profile->title,
                    'bio' => $profile->bio,
                    'hourly_rate' => $profile->hourly_rate,
                    'skills' => $profile->skills->pluck('name'),
                    'email' => ($showPrivateInfo || $profile->email_visible) ? $profile->user->email : null,
                    'phone' => ($showPrivateInfo || $profile->phone_visible) ? $profile->phone : null,
                    'github_url' => $profile->github_url,
                    'linkedin_url' => $profile->linkedin_url,
                ]
            ]);
        }

        return view('developer.profile.show', [
            'profile' => $profile,
            'showPrivateInfo' => $showPrivateInfo
        ]);
    }

    public function updatePrivacy(Request $request, DeveloperProfile $profile)
    {
        if ($request->user()->role !== 'developer' || $request->user()->id !== $profile->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'email_visible' => 'required|boolean',
            'phone_visible' => 'required|boolean'
        ]);

        $profile->update($validated);

        return redirect('/developer/profile');
    }
} 