<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Payment;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $developer;
    private Job $job;
    private JobApplication $application;

    protected function setUp(): void
    {
        parent::setUp();
        
        Notification::fake();

        // Create users
        $this->client = User::factory()->client()->create();
        $this->developer = User::factory()->developer()->create();
        
        // Create job
        $this->job = Job::factory()->create([
            'user_id' => $this->client->id,
            'status' => 'in_progress',
            'budget' => 1000.00
        ]);

        // Create accepted application
        $this->application = JobApplication::factory()->create([
            'job_id' => $this->job->id,
            'user_id' => $this->developer->id,
            'status' => 'accepted',
            'budget' => 1000.00
        ]);
    }

    public function test_client_can_create_milestone_payment(): void
    {
        $paymentData = [
            'amount' => 500.00,
            'description' => 'First milestone payment',
            'due_date' => now()->addDays(7)->format('Y-m-d')
        ];

        $response = $this->actingAs($this->client)
            ->postJson("/jobs/{$this->job->id}/payments", $paymentData);

        $response->assertStatus(201)
            ->assertJson([
                'amount' => 500.00,
                'description' => 'First milestone payment',
                'status' => 'pending',
                'job_id' => $this->job->id,
                'payer_id' => $this->client->id,
                'payee_id' => $this->developer->id
            ]);

        $this->assertDatabaseHas('payments', [
            'amount' => 500.00,
            'description' => 'First milestone payment',
            'status' => 'pending',
            'job_id' => $this->job->id,
            'payer_id' => $this->client->id,
            'payee_id' => $this->developer->id
        ]);
    }

    public function test_validates_milestone_payment_amount(): void
    {
        $paymentData = [
            'amount' => 1500.00, // More than job budget
            'description' => 'Invalid payment',
            'due_date' => now()->addDays(7)->format('Y-m-d')
        ];

        $response = $this->actingAs($this->client)
            ->postJson("/jobs/{$this->job->id}/payments", $paymentData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_only_client_can_create_payments(): void
    {
        $paymentData = [
            'amount' => 500.00,
            'description' => 'First milestone payment',
            'due_date' => now()->addDays(7)->format('Y-m-d')
        ];

        $response = $this->actingAs($this->developer)
            ->postJson("/jobs/{$this->job->id}/payments", $paymentData);

        $response->assertStatus(403);
    }

    public function test_cannot_create_payment_for_completed_job(): void
    {
        // Mark job as completed
        $this->job->update(['status' => 'completed']);

        $paymentData = [
            'amount' => 500.00,
            'description' => 'First milestone payment',
            'due_date' => now()->addDays(7)->format('Y-m-d')
        ];

        $response = $this->actingAs($this->client)
            ->postJson("/jobs/{$this->job->id}/payments", $paymentData);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Cannot create payments for completed jobs']);
    }

    public function test_total_milestone_payments_cannot_exceed_job_budget(): void
    {
        // Create first payment
        Payment::create([
            'job_id' => $this->job->id,
            'amount' => 800.00,
            'description' => 'First milestone payment',
            'status' => 'pending',
            'payer_id' => $this->client->id,
            'payee_id' => $this->developer->id,
            'due_date' => now()->addDays(7)
        ]);

        // Try to create second payment that would exceed budget
        $paymentData = [
            'amount' => 300.00,
            'description' => 'Second milestone payment',
            'due_date' => now()->addDays(14)->format('Y-m-d')
        ];

        $response = $this->actingAs($this->client)
            ->postJson("/jobs/{$this->job->id}/payments", $paymentData);

        $response->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'amount' => ['Total payments cannot exceed job budget']
                ]
            ]);
    }

    public function test_client_can_view_job_payments(): void
    {
        Payment::create([
            'job_id' => $this->job->id,
            'amount' => 500.00,
            'description' => 'First milestone payment',
            'status' => 'pending',
            'payer_id' => $this->client->id,
            'payee_id' => $this->developer->id,
            'due_date' => now()->addDays(7)
        ]);

        $response = $this->actingAs($this->client)
            ->getJson("/jobs/{$this->job->id}/payments");

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonStructure([[
                'id',
                'amount',
                'description',
                'status',
                'due_date',
                'created_at'
            ]]);
    }

    public function test_developer_can_view_job_payments(): void
    {
        Payment::create([
            'job_id' => $this->job->id,
            'amount' => 500.00,
            'description' => 'First milestone payment',
            'status' => 'pending',
            'payer_id' => $this->client->id,
            'payee_id' => $this->developer->id,
            'due_date' => now()->addDays(7)
        ]);

        $response = $this->actingAs($this->developer)
            ->getJson("/jobs/{$this->job->id}/payments");

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonStructure([[
                'id',
                'amount',
                'description',
                'status',
                'due_date',
                'created_at'
            ]]);
    }
} 