<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Payment;
use App\Models\Notification;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use App\Notifications\ApplicationSubmitted;
use App\Notifications\ApplicationStatusChanged;
use App\Notifications\PaymentCreated;
use App\Notifications\PaymentReceived;
use Illuminate\Support\Str;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $developer;
    private Job $job;
    private JobApplication $application;

    protected function setUp(): void
    {
        parent::setUp();
        
        NotificationFacade::fake();

        // Create users
        $this->client = User::factory()->client()->create();
        $this->developer = User::factory()->developer()->create();
        
        // Create job
        $this->job = Job::factory()->create([
            'user_id' => $this->client->id,
            'status' => 'open',
            'budget' => 1000.00
        ]);
    }

    public function test_client_notified_when_application_submitted(): void
    {
        $this->actingAs($this->developer);
        
        // Submit application
        $response = $this->postJson(route('jobs.apply', $this->job), [
            'proposal' => 'Test proposal',
            'budget' => 1000,
            'timeline' => 30
        ]);

        $response->assertStatus(201);

        NotificationFacade::assertSentTo(
            $this->client,
            ApplicationSubmitted::class,
            function ($notification) {
                return $notification->application->job_id === $this->job->id;
            }
        );
    }

    public function test_developer_notified_when_application_status_changes(): void
    {
        // Create application
        $application = JobApplication::factory()->create([
            'job_id' => $this->job->id,
            'user_id' => $this->developer->id,
            'status' => 'pending'
        ]);

        $this->actingAs($this->client);
        
        // Update application status
        $response = $this->patchJson(route('applications.update', $application), [
            'status' => 'accepted'
        ]);

        $response->assertOk();

        NotificationFacade::assertSentTo(
            $this->developer,
            ApplicationStatusChanged::class,
            function ($notification) use ($application) {
                return $notification->application->id === $application->id
                    && $notification->newStatus === 'accepted';
            }
        );
    }

    public function test_developer_notified_when_payment_created(): void
    {
        // Create accepted application
        $application = JobApplication::factory()->create([
            'job_id' => $this->job->id,
            'user_id' => $this->developer->id,
            'status' => 'accepted'
        ]);

        // Update job status to in_progress
        $this->job->update(['status' => 'in_progress']);

        // Load the application with its user relationship
        $application->load('user');

        // Debug assertions
        $this->assertNotNull($application->user, 'Application user is null');
        $this->assertEquals($this->developer->id, $application->user->id, 'User IDs do not match');

        $this->actingAs($this->client);
        
        // Create payment
        $response = $this->postJson(route('jobs.payments.store', $this->job), [
            'amount' => 500.00,
            'description' => 'First milestone payment',
            'due_date' => now()->addDays(7)->format('Y-m-d')
        ]);

        $response->assertStatus(201);

        // Get the created payment from the response
        $payment = json_decode($response->getContent());
        $this->assertNotNull($payment, 'Payment response is null');

        // Debug notifications
        $notifications = NotificationFacade::sent($application->user, PaymentCreated::class);
        $this->assertNotEmpty($notifications, 'No notifications were sent to the developer');

        NotificationFacade::assertSentTo(
            $application->user,
            PaymentCreated::class,
            function ($notification) use ($payment) {
                return $notification->payment->amount == 500.00;
            }
        );
    }

    public function test_client_notified_when_payment_received(): void
    {
        // Create accepted application
        $application = JobApplication::factory()->create([
            'job_id' => $this->job->id,
            'user_id' => $this->developer->id,
            'status' => 'accepted'
        ]);

        // Create payment
        $payment = Payment::factory()->create([
            'job_id' => $this->job->id,
            'amount' => 500.00,
            'payer_id' => $this->client->id,
            'payee_id' => $this->developer->id,
            'status' => 'pending'
        ]);

        $this->actingAs($this->client);
        
        // Mark payment as paid
        $response = $this->patchJson(route('payments.update', $payment), [
            'status' => 'paid',
            'transaction_id' => 'test_transaction_123'
        ]);

        $response->assertOk();

        NotificationFacade::assertSentTo(
            $this->client,
            PaymentReceived::class,
            function ($notification) use ($payment) {
                return $notification->payment->id === $payment->id;
            }
        );
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $this->actingAs($this->client);

        // Create notification
        $notification = $this->client->notifications()->create([
            'id' => Str::uuid(),
            'type' => ApplicationSubmitted::class,
            'data' => ['message' => 'Test notification'],
            'read_at' => null
        ]);

        $response = $this->patchJson("/notifications/{$notification->id}", [
            'read' => true
        ]);

        $response->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_get_unread_notification_count(): void
    {
        $this->actingAs($this->client);

        // Create notifications
        $this->client->notifications()->createMany([
            [
                'id' => Str::uuid(),
                'type' => ApplicationSubmitted::class,
                'data' => ['message' => 'Test notification 1'],
                'read_at' => null
            ],
            [
                'id' => Str::uuid(),
                'type' => ApplicationSubmitted::class,
                'data' => ['message' => 'Test notification 2'],
                'read_at' => null
            ],
            [
                'id' => Str::uuid(),
                'type' => ApplicationSubmitted::class,
                'data' => ['message' => 'Test notification 3'],
                'read_at' => now()
            ]
        ]);

        $response = $this->getJson('/notifications/unread/count');

        $response->assertOk()
            ->assertJson(['count' => 2]);
    }

    public function test_user_can_get_notifications_paginated(): void
    {
        $this->actingAs($this->client);

        // Create multiple notifications
        $this->client->notifications()->createMany(
            collect(range(1, 15))->map(fn($i) => [
                'id' => Str::uuid(),
                'type' => ApplicationSubmitted::class,
                'data' => ['message' => "Test notification {$i}"],
                'read_at' => null
            ])->toArray()
        );

        $response = $this->getJson('/notifications?page=1');

        $response->assertOk()
            ->assertJsonCount(10, 'data') // Default pagination
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'data',
                        'read_at',
                        'created_at'
                    ]
                ],
                'links',
                'meta'
            ]);
    }
} 