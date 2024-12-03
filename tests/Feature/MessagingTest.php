<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Message;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;
    private User $recipient;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create users with different roles for testing
        $this->sender = User::factory()->create(['role' => 'developer']);
        $this->recipient = User::factory()->create(['role' => 'client']);
    }

    public function test_user_can_send_direct_message(): void
    {
        $messageData = [
            'recipient_id' => $this->recipient->id,
            'content' => 'Hello, I am interested in discussing your project.',
        ];

        $response = $this->actingAs($this->sender)
            ->postJson('/messages', $messageData);

        $response->assertStatus(201)
            ->assertJson([
                'sender_id' => $this->sender->id,
                'recipient_id' => $this->recipient->id,
                'content' => $messageData['content'],
                'read' => false
            ]);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id,
            'content' => $messageData['content']
        ]);
    }

    public function test_user_can_view_their_messages(): void
    {
        // Create some messages
        Message::factory()->count(3)->create([
            'sender_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id
        ]);

        Message::factory()->count(2)->create([
            'sender_id' => $this->recipient->id,
            'recipient_id' => $this->sender->id
        ]);

        // Test recipient can view received messages
        $response = $this->actingAs($this->recipient)
            ->getJson('/messages');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'sender_id',
                    'recipient_id',
                    'content',
                    'read',
                    'created_at'
                ]],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    public function test_validates_required_message_fields(): void
    {
        $response = $this->actingAs($this->sender)
            ->postJson('/messages', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_id', 'content']);
    }

    public function test_user_can_mark_message_as_read(): void
    {
        $message = Message::factory()->create([
            'sender_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id,
            'read' => false
        ]);

        $response = $this->actingAs($this->recipient)
            ->putJson("/messages/{$message->id}/read");

        $response->assertOk()
            ->assertJson(['read' => true]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'read' => true
        ]);
    }

    public function test_only_recipient_can_mark_message_as_read(): void
    {
        $message = Message::factory()->create([
            'sender_id' => $this->sender->id,
            'recipient_id' => $this->recipient->id,
            'read' => false
        ]);

        // Try to mark as read as sender (should fail)
        $response = $this->actingAs($this->sender)
            ->putJson("/messages/{$message->id}/read");

        $response->assertForbidden();

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'read' => false
        ]);
    }
} 