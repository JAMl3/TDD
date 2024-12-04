<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use App\Models\Skill;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class JobSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $developer;
    private array $skills;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create();
        $this->developer = User::factory()->developer()->create();
        
        // Create some skills
        $this->skills = collect(['PHP', 'Laravel', 'Vue.js', 'React', 'TypeScript'])
            ->map(fn ($name) => Skill::create(['name' => $name]))
            ->all();
    }

    public function test_can_search_jobs_by_skill(): void
    {
        // Create jobs with different skills
        $phpJob = Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'PHP Developer Needed',
            'status' => 'open'
        ]);
        $phpJob->skills()->attach($this->skills[0]); // PHP

        $laravelJob = Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'Laravel Expert Wanted',
            'status' => 'open'
        ]);
        $laravelJob->skills()->attach($this->skills[1]); // Laravel

        $response = $this->actingAs($this->developer)
            ->getJson('/jobs/search?skill=PHP');

        $response->assertOk()
            ->assertJsonCount(1, 'jobs.data')
            ->assertJsonPath('jobs.data.0.title', 'PHP Developer Needed');
    }

    public function test_can_search_jobs_by_budget_range(): void
    {
        Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'Low Budget Project',
            'budget' => 500,
            'status' => 'open'
        ]);

        Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'High Budget Project',
            'budget' => 5000,
            'status' => 'open'
        ]);

        $response = $this->actingAs($this->developer)
            ->getJson('/jobs/search?min_budget=1000&max_budget=6000');

        $response->assertOk()
            ->assertJsonCount(1, 'jobs.data')
            ->assertJsonPath('jobs.data.0.title', 'High Budget Project');
    }

    public function test_can_search_jobs_by_title(): void
    {
        Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'Laravel Developer Needed',
            'status' => 'open'
        ]);

        Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'PHP Expert Required',
            'status' => 'open'
        ]);

        $response = $this->actingAs($this->developer)
            ->getJson('/jobs/search?title=Laravel');

        $response->assertOk()
            ->assertJsonCount(1, 'jobs.data')
            ->assertJsonPath('jobs.data.0.title', 'Laravel Developer Needed');
    }

    public function test_can_search_jobs_by_date_range(): void
    {
        // Create an older job
        Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'Old Job',
            'status' => 'open',
            'created_at' => now()->subDays(10)
        ]);

        // Create a recent job
        Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'Recent Job',
            'status' => 'open',
            'created_at' => now()->subDay()
        ]);

        $response = $this->actingAs($this->developer)
            ->getJson('/jobs/search?from_date=' . now()->subDays(5)->format('Y-m-d'));

        $response->assertOk()
            ->assertJsonCount(1, 'jobs.data')
            ->assertJsonPath('jobs.data.0.title', 'Recent Job');
    }

    public function test_can_sort_jobs(): void
    {
        Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'B Job',
            'budget' => 1000,
            'status' => 'open',
            'created_at' => now()->subDays(2)
        ]);

        Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'A Job',
            'budget' => 2000,
            'status' => 'open',
            'created_at' => now()->subDay()
        ]);

        // Test sorting by title ascending
        $response = $this->actingAs($this->developer)
            ->getJson('/jobs/search?sort=title&direction=asc');

        $response->assertOk()
            ->assertJsonPath('jobs.data.0.title', 'A Job')
            ->assertJsonPath('jobs.data.1.title', 'B Job');

        // Test sorting by budget descending
        $response = $this->actingAs($this->developer)
            ->getJson('/jobs/search?sort=budget&direction=desc');

        $response->assertOk()
            ->assertJsonPath('jobs.data.0.budget', '2000.00')
            ->assertJsonPath('jobs.data.1.budget', '1000.00');

        // Test sorting by date
        $response = $this->actingAs($this->developer)
            ->getJson('/jobs/search?sort=created_at&direction=desc');

        $response->assertOk()
            ->assertJsonPath('jobs.data.0.title', 'A Job')
            ->assertJsonPath('jobs.data.1.title', 'B Job');
    }

    public function test_jobs_are_paginated(): void
    {
        // Create 15 jobs
        Job::factory()->count(15)->create([
            'user_id' => $this->client->id,
            'status' => 'open'
        ]);

        $response = $this->actingAs($this->developer)
            ->getJson('/jobs/search?page=1&per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'jobs.data')
            ->assertJsonStructure([
                'jobs' => [
                    'data',
                    'current_page',
                    'per_page',
                    'total',
                    'last_page'
                ]
            ]);

        // Check second page
        $response = $this->actingAs($this->developer)
            ->getJson('/jobs/search?page=2&per_page=10');

        $response->assertOk()
            ->assertJsonCount(5, 'jobs.data');
    }

    public function test_can_search_jobs_by_multiple_criteria(): void
    {
        // Create a job matching all criteria
        $matchingJob = Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'Laravel Expert Needed',
            'budget' => 3000,
            'status' => 'open'
        ]);
        $matchingJob->skills()->attach($this->skills[1]); // Laravel

        // Create a job matching only some criteria
        $partialJob = Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'Laravel Developer',
            'budget' => 500,
            'status' => 'open'
        ]);
        $partialJob->skills()->attach($this->skills[1]); // Laravel

        $response = $this->actingAs($this->developer)
            ->getJson('/jobs/search?skill=Laravel&min_budget=1000&max_budget=5000');

        $response->assertOk()
            ->assertJsonCount(1, 'jobs.data')
            ->assertJsonPath('jobs.data.0.title', 'Laravel Expert Needed');
    }

    public function test_search_only_returns_open_jobs(): void
    {
        // Create an open job
        $openJob = Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'Open Job',
            'status' => 'open'
        ]);
        $openJob->skills()->attach($this->skills[0]); // PHP

        // Create a closed job
        $closedJob = Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'Closed Job',
            'status' => 'completed'
        ]);
        $closedJob->skills()->attach($this->skills[0]); // PHP

        $response = $this->actingAs($this->developer)
            ->getJson('/jobs/search?skill=PHP');

        $response->assertOk()
            ->assertJsonCount(1, 'jobs.data')
            ->assertJsonPath('jobs.data.0.title', 'Open Job');
    }

    public function test_job_response_includes_all_necessary_data(): void
    {
        $job = Job::factory()->create([
            'user_id' => $this->client->id,
            'title' => 'Full Stack Developer',
            'description' => 'Looking for a full stack developer',
            'budget' => 5000,
            'deadline' => now()->addDays(30),
            'status' => 'open'
        ]);

        // Add some skills
        $job->skills()->attach($this->skills[0]); // PHP
        $job->skills()->attach($this->skills[1]); // Laravel

        // Create some applications
        $job->applications()->create([
            'user_id' => $this->developer->id,
            'proposal' => 'Test proposal',
            'budget' => 5000,
            'timeline' => 30
        ]);

        $response = $this->actingAs($this->developer)
            ->getJson('/jobs/search');

        $response->assertOk()
            ->assertJsonStructure([
                'jobs' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
                            'budget',
                            'deadline',
                            'status',
                            'created_at',
                            'skills' => [
                                '*' => [
                                    'id',
                                    'name'
                                ]
                            ],
                            'client' => [
                                'id',
                                'name'
                            ],
                            'application_count'
                        ]
                    ],
                    'current_page',
                    'per_page',
                    'total',
                    'last_page'
                ]
            ])
            ->assertJsonPath('jobs.data.0.application_count', 1)
            ->assertJsonPath('jobs.data.0.skills.0.name', 'PHP')
            ->assertJsonPath('jobs.data.0.skills.1.name', 'Laravel');
    }
} 