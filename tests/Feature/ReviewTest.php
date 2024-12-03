<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Review;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $developer;
    private Job $job;
    private JobApplication $application;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create users with different roles
        $this->client = User::factory()->create(['role' => 'client']);
        $this->developer = User::factory()->create(['role' => 'developer']);
        
        // Create a completed job and accepted application
        $this->job = Job::factory()
            ->completed()
            ->create([
                'user_id' => $this->client->id,
                'status' => 'completed'
            ]);
        
        $this->application = JobApplication::factory()
            ->accepted()
            ->create([
                'job_id' => $this->job->id,
                'user_id' => $this->developer->id,
                'status' => 'accepted'
            ]);

        // Debug setup
        dump([
            'client_id' => $this->client->id,
            'client_role' => $this->client->role,
            'developer_id' => $this->developer->id,
            'developer_role' => $this->developer->role,
            'job_id' => $this->job->id,
            'job_user_id' => $this->job->user_id,
            'job_status' => $this->job->status,
            'application_id' => $this->application->id,
            'application_job_id' => $this->application->job_id,
            'application_user_id' => $this->application->user_id,
            'application_status' => $this->application->status
        ]);
    }

    public function test_client_can_review_developer(): void
    {
        // Debug output
        dump([
            'client_id' => $this->client->id,
            'client_role' => $this->client->role,
            'developer_id' => $this->developer->id,
            'developer_role' => $this->developer->role,
            'job_status' => $this->job->status,
            'application_status' => $this->application->status
        ]);

        $reviewData = [
            'rating' => 5,
            'comment' => 'Excellent work, very professional.',
            'categories' => [
                'communication' => 5,
                'quality' => 5,
                'timeliness' => 4
            ]
        ];

        $response = $this->actingAs($this->client)
            ->postJson("/developers/{$this->developer->id}/reviews", $reviewData);

        // Debug response
        dump($response->json());

        $response->assertStatus(201)
            ->assertJson([
                'reviewer_id' => $this->client->id,
                'reviewee_id' => $this->developer->id,
                'rating' => 5,
                'comment' => $reviewData['comment'],
                'categories' => $reviewData['categories']
            ]);

        $this->assertDatabaseHas('reviews', [
            'reviewer_id' => $this->client->id,
            'reviewee_id' => $this->developer->id,
            'rating' => 5,
            'comment' => $reviewData['comment']
        ]);
    }

    public function test_developer_can_review_client(): void
    {
        $reviewData = [
            'rating' => 4,
            'comment' => 'Great client, clear requirements.',
            'categories' => [
                'communication' => 4,
                'payment_timeliness' => 5,
                'requirement_clarity' => 4
            ]
        ];

        $response = $this->actingAs($this->developer)
            ->postJson("/clients/{$this->client->id}/reviews", $reviewData);

        $response->assertStatus(201)
            ->assertJson([
                'reviewer_id' => $this->developer->id,
                'reviewee_id' => $this->client->id,
                'rating' => 4,
                'comment' => $reviewData['comment'],
                'categories' => $reviewData['categories']
            ]);
    }

    public function test_validates_review_fields(): void
    {
        $response = $this->actingAs($this->client)
            ->postJson("/developers/{$this->developer->id}/reviews", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating', 'comment', 'categories']);
    }

    public function test_can_only_review_after_job_completion(): void
    {
        // Create a new job that's not completed
        $newJob = Job::factory()->create([
            'user_id' => $this->client->id,
            'status' => 'in_progress'
        ]);
        
        $newApplication = JobApplication::factory()->create([
            'job_id' => $newJob->id,
            'user_id' => $this->developer->id,
            'status' => 'accepted'
        ]);

        $reviewData = [
            'rating' => 5,
            'comment' => 'Great work!',
            'categories' => [
                'communication' => 5,
                'quality' => 5,
                'timeliness' => 5
            ]
        ];

        $response = $this->actingAs($this->client)
            ->postJson("/developers/{$this->developer->id}/reviews", $reviewData);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Cannot review before job completion']);
    }

    public function test_can_view_user_reviews(): void
    {
        // Create some reviews
        Review::factory()
            ->count(3)
            ->forJob($this->job, $this->client, $this->developer)
            ->create();

        $response = $this->actingAs($this->client)
            ->getJson("/developers/{$this->developer->id}/reviews");

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'reviewer_id',
                    'reviewee_id',
                    'rating',
                    'comment',
                    'categories',
                    'created_at'
                ]],
                'meta' => [
                    'average_rating',
                    'total_reviews'
                ]
            ]);
    }

    public function test_review_affects_user_rating(): void
    {
        // Create multiple reviews
        Review::factory()
            ->count(3)
            ->forJob($this->job, $this->client, $this->developer)
            ->create(['rating' => 4]);

        $response = $this->actingAs($this->client)
            ->getJson("/developers/{$this->developer->id}");

        $response->assertOk()
            ->assertJson([
                'average_rating' => 4.0,
                'total_reviews' => 3
            ]);
    }
} 