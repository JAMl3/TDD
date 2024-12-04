<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_as_client(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'client'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role'
                ],
                'token'
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => 'client'
        ]);
    }

    public function test_user_can_register_as_developer(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'developer'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role'
                ],
                'token'
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'role' => 'developer'
        ]);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_validates_email_is_unique(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'client'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_validates_role_is_valid(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'invalid_role'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }
} 