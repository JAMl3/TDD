<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class JobApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;
    private User $client;
    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a developer and client
        $this->developer = User::factory()->create(['role' => 'developer']);
        $this->client = User::factory()->create(['role' => 'client']);
        
        // Create a job for testing
        $this->job = Job::factory()->create([
            'user_id' => $this->client->id,
            'status' => 'open'
        ]);
    }

    public function test_developer_can_submit_proposal(): void
    {
        $applicationData = [
            'proposal' => 'I am interested in this project and have relevant experience.',
            'timeline' => 14,
            'budget' => 1500
        ];

        $response = $this->actingAs($this->developer)
            ->postJson(route('jobs.apply', $this->job), $applicationData);

        $response->assertStatus(201)
            ->assertJson([
                'job_id' => $this->job->id,
                'user_id' => $this->developer->id,
                'status' => 'pending'
            ]);
    }

    public function test_validates_required_proposal_fields(): void
    {
        $response = $this->actingAs($this->developer)
            ->postJson(route('jobs.apply', $this->job), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['proposal', 'timeline', 'budget']);
    }

    public function test_client_cannot_apply_to_jobs(): void
    {
        $applicationData = [
            'proposal' => 'Test proposal',
            'timeline' => 7,
            'budget' => 1000
        ];

        $response = $this->actingAs($this->client)
            ->postJson(route('jobs.apply', $this->job), $applicationData);

        $response->assertForbidden();
    }

    public function test_cannot_apply_to_closed_job(): void
    {
        $closedJob = Job::factory()->create([
            'user_id' => $this->client->id,
            'status' => 'cancelled'
        ]);

        $applicationData = [
            'proposal' => 'Test proposal',
            'timeline' => 7,
            'budget' => 1000
        ];

        $response = $this->actingAs($this->developer)
            ->postJson(route('jobs.apply', $closedJob), $applicationData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['job']);
    }

    public function test_developer_can_view_their_applications(): void
    {
        // Create some applications for the developer
        $jobs = Job::factory()->count(3)->create(['user_id' => $this->client->id]);
        foreach ($jobs as $job) {
            $this->actingAs($this->developer)->postJson(route('jobs.apply', $job), [
                'proposal' => 'Test proposal',
                'timeline' => 7,
                'budget' => 1000
            ]);
        }

        $response = $this->actingAs($this->developer)
            ->getJson('/applications');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }
} 