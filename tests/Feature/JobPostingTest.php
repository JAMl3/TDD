<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobPostingTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = User::factory()->create(['role' => 'client']);
    }

    public function test_validates_budget_minimum(): void
    {
        $jobData = [
            'title' => 'Test Job',
            'description' => 'Test Description',
            'budget' => -100,
            'deadline' => now()->addDays(30)->format('Y-m-d'),
            'required_skills' => ['PHP'],
        ];

        $response = $this->actingAs($this->client)
            ->postJson(route('jobs.store'), $jobData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['budget']);
    }

    public function test_validates_deadline_is_future(): void
    {
        $jobData = [
            'title' => 'Test Job',
            'description' => 'Test Description',
            'budget' => 1000,
            'deadline' => now()->subDay()->format('Y-m-d'),
            'required_skills' => ['PHP'],
        ];

        $response = $this->actingAs($this->client)
            ->postJson(route('jobs.store'), $jobData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['deadline']);
    }

    public function test_clients_can_view_their_posted_jobs(): void
    {
        $jobs = Job::factory()->count(3)->create([
            'user_id' => $this->client->id
        ]);

        $response = $this->actingAs($this->client)
            ->getJson(route('jobs.index'));

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_view_job_details(): void
    {
        $job = Job::factory()->create([
            'user_id' => $this->client->id
        ]);

        $response = $this->actingAs($this->client)
            ->getJson(route('jobs.show', $job));

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'budget',
                'deadline',
                'required_skills',
                'status',
                'client'
            ]);
    }

    public function test_client_can_create_job_with_valid_data(): void
    {
        $jobData = [
            'title' => 'Laravel Developer Needed',
            'description' => 'Looking for an experienced Laravel developer',
            'budget' => 1000,
            'deadline' => now()->addDays(30)->format('Y-m-d'),
            'required_skills' => ['Laravel', 'PHP', 'MySQL'],
        ];

        $response = $this->actingAs($this->client)
            ->postJson(route('jobs.store'), $jobData);

        $response->assertStatus(201)
            ->assertJson([
                'title' => $jobData['title'],
                'description' => $jobData['description'],
                'budget' => $jobData['budget'],
                'required_skills' => $jobData['required_skills'],
            ]);
    }

    public function test_non_clients_cannot_create_jobs(): void
    {
        $developer = User::factory()->create(['role' => 'developer']);
        
        $jobData = [
            'title' => 'Test Job',
            'description' => 'Test Description',
            'budget' => 1000,
            'deadline' => now()->addDays(30)->format('Y-m-d'),
            'required_skills' => ['PHP'],
        ];

        $response = $this->actingAs($developer)
            ->postJson(route('jobs.store'), $jobData);

        $response->assertForbidden();
    }

    public function test_validates_required_skills(): void
    {
        $jobData = [
            'title' => 'Test Job',
            'description' => 'Test Description',
            'budget' => 1000,
            'deadline' => now()->addDays(30)->format('Y-m-d'),
            'required_skills' => [],
        ];

        $response = $this->actingAs($this->client)
            ->postJson(route('jobs.store'), $jobData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['required_skills']);
    }

    public function test_client_can_update_job_status(): void
    {
        $job = Job::factory()->create([
            'user_id' => $this->client->id,
            'status' => 'open'
        ]);

        $response = $this->actingAs($this->client)
            ->putJson(route('jobs.update', $job), [
                'status' => 'in_progress'
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'in_progress'
            ]);
    }
} 