<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Skill;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SkillsTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->developer = User::factory()->developer()->create();
    }

    public function test_can_fetch_all_available_skills(): void
    {
        // Create some skills
        $skills = collect(['PHP', 'Laravel', 'Vue.js', 'React', 'TypeScript'])
            ->map(fn ($name) => Skill::create(['name' => $name]));

        $response = $this->actingAs($this->developer)
            ->getJson('/api/skills');

        $response->assertOk()
            ->assertJsonCount(5, 'skills')
            ->assertJsonStructure([
                'skills' => [
                    '*' => [
                        'id',
                        'name'
                    ]
                ]
            ]);

        // Verify all skills are present
        $skills->each(function ($skill) use ($response) {
            $response->assertJsonFragment(['name' => $skill->name]);
        });
    }

    public function test_skills_are_returned_in_alphabetical_order(): void
    {
        // Create skills in random order
        Skill::create(['name' => 'Vue.js']);
        Skill::create(['name' => 'Angular']);
        Skill::create(['name' => 'React']);

        $response = $this->actingAs($this->developer)
            ->getJson('/api/skills');

        $response->assertOk()
            ->assertJsonPath('skills.0.name', 'Angular')
            ->assertJsonPath('skills.1.name', 'React')
            ->assertJsonPath('skills.2.name', 'Vue.js');
    }
} 