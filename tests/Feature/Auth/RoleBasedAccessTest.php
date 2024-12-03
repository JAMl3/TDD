<?php

namespace Tests\Feature\Auth;

use App\Models\Job;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleBasedAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_developers_can_view_jobs()
    {
        $developer = User::factory()->create([
            'role' => 'developer'
        ]);

        $response = $this->actingAs($developer)->get('/jobs');

        $response->assertOk();
    }

    public function test_developers_can_apply_to_jobs()
    {
        $developer = User::factory()->create([
            'role' => 'developer'
        ]);

        $client = User::factory()->create([
            'role' => 'client'
        ]);

        $job = Job::factory()->create([
            'user_id' => $client->id
        ]);

        $response = $this->actingAs($developer)->post("/jobs/{$job->id}/apply", [
            'proposal' => 'I am interested in this job',
            'timeline' => '2 weeks',
            'budget' => 1000
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('job_applications', [
            'user_id' => $developer->id,
            'job_id' => $job->id,
            'proposal' => 'I am interested in this job'
        ]);
    }

    public function test_clients_can_post_jobs()
    {
        $client = User::factory()->create([
            'role' => 'client'
        ]);

        $response = $this->actingAs($client)->post('/jobs', [
            'title' => 'Laravel Developer Needed',
            'description' => 'Need help with Laravel project',
            'budget' => 1000,
            'deadline' => now()->addWeeks(2),
            'required_skills' => ['Laravel', 'PHP', 'MySQL']
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('jobs', [
            'title' => 'Laravel Developer Needed',
            'user_id' => $client->id
        ]);
    }

    public function test_clients_cannot_apply_to_jobs()
    {
        $client = User::factory()->create([
            'role' => 'client'
        ]);

        $job = Job::factory()->create();

        $response = $this->actingAs($client)->post("/jobs/{$job->id}/apply", [
            'proposal' => 'I am interested in this job'
        ]);

        $response->assertForbidden();
    }

    public function test_developers_cannot_post_jobs()
    {
        $developer = User::factory()->create([
            'role' => 'developer'
        ]);

        $response = $this->actingAs($developer)->post('/jobs', [
            'title' => 'Laravel Developer Needed'
        ]);

        $response->assertForbidden();
    }

    public function test_admins_can_access_admin_dashboard()
    {
        $admin = User::factory()->create([
            'role' => 'admin'
        ]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
    }

    public function test_non_admins_cannot_access_admin_dashboard()
    {
        $developer = User::factory()->create([
            'role' => 'developer'
        ]);

        $response = $this->actingAs($developer)->get('/admin/dashboard');

        $response->assertForbidden();
    }
} 