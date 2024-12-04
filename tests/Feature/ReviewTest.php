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
        $this->client = User::factory()->client()->create();
        $this->developer = User::factory()->developer()->create();
        
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
    }

    public function test_client_can_review_developer(): void
    {
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
            ->postJson("/jobs/{$this->job->id}/reviews/{$this->developer->id}", $reviewData);

        $response->assertStatus(201)
            ->assertJson([
                'reviewer_id' => $this->client->id,
                'reviewee_id' => $this->developer->id,
                'job_id' => $this->job->id,
                'rating' => 5,
                'comment' => $reviewData['comment'],
                'categories' => $reviewData['categories']
            ]);

        $this->assertDatabaseHas('reviews', [
            'reviewer_id' => $this->client->id,
            'reviewee_id' => $this->developer->id,
            'job_id' => $this->job->id,
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
            ->postJson("/jobs/{$this->job->id}/reviews/{$this->client->id}", $reviewData);

        $response->assertStatus(201)
            ->assertJson([
                'reviewer_id' => $this->developer->id,
                'reviewee_id' => $this->client->id,
                'job_id' => $this->job->id,
                'rating' => 4,
                'comment' => $reviewData['comment'],
                'categories' => $reviewData['categories']
            ]);
    }

    public function test_validates_review_fields(): void
    {
        $response = $this->actingAs($this->client)
            ->postJson("/jobs/{$this->job->id}/reviews/{$this->developer->id}", []);

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
            ->postJson("/jobs/{$newJob->id}/reviews/{$this->developer->id}", $reviewData);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Cannot review before job completion']);
    }

    public function test_cannot_review_if_not_part_of_job(): void
    {
        $otherClient = User::factory()->client()->create();
        
        $reviewData = [
            'rating' => 5,
            'comment' => 'Great work!',
            'categories' => [
                'communication' => 5,
                'quality' => 5,
                'timeliness' => 5
            ]
        ];

        $response = $this->actingAs($otherClient)
            ->postJson("/jobs/{$this->job->id}/reviews/{$this->developer->id}", $reviewData);

        $response->assertStatus(403)
            ->assertJson(['message' => 'You are not authorized to review this job']);
    }

    public function test_cannot_review_invalid_reviewee(): void
    {
        $otherDeveloper = User::factory()->developer()->create();
        
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
            ->postJson("/jobs/{$this->job->id}/reviews/{$otherDeveloper->id}", $reviewData);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Invalid reviewee']);
    }

    public function test_cannot_review_same_job_twice(): void
    {
        $reviewData = [
            'rating' => 5,
            'comment' => 'Great work!',
            'categories' => [
                'communication' => 5,
                'quality' => 5,
                'timeliness' => 5
            ]
        ];

        // First review
        $this->actingAs($this->client)
            ->postJson("/jobs/{$this->job->id}/reviews/{$this->developer->id}", $reviewData);

        // Second review attempt
        $response = $this->actingAs($this->client)
            ->postJson("/jobs/{$this->job->id}/reviews/{$this->developer->id}", $reviewData);

        $response->assertStatus(422)
            ->assertJson(['message' => 'You have already reviewed this user for this job']);
    }

    public function test_can_view_user_reviews(): void
    {
        Review::factory()
            ->count(3)
            ->create([
                'reviewer_id' => $this->client->id,
                'reviewee_id' => $this->developer->id,
                'job_id' => $this->job->id,
                'rating' => 4
            ]);

        $response = $this->actingAs($this->client)
            ->getJson("/users/{$this->developer->id}/reviews");

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
        Review::factory()
            ->count(3)
            ->create([
                'reviewer_id' => $this->client->id,
                'reviewee_id' => $this->developer->id,
                'job_id' => $this->job->id,
                'rating' => 4
            ]);

        $response = $this->actingAs($this->client)
            ->getJson("/users/{$this->developer->id}");

        $response->assertOk()
            ->assertJson([
                'average_rating' => 4.0,
                'total_reviews' => 3
            ]);
    }
} 