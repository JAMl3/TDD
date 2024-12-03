<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DeveloperProfile;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeveloperProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_can_update_profile(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'developer']);
        $profile = DeveloperProfile::factory()->create(['user_id' => $user->id]);
        $skill = Skill::factory()->create(['name' => 'JavaScript']);
        $profile->skills()->attach($skill);
        
        $image = UploadedFile::fake()->image('updated-project.jpg');
        
        $response = $this->actingAs($user)->put("/developer/profile/{$profile->id}", [
            'title' => 'Updated Senior Developer',
            'bio' => 'Updated bio with 7 years of experience',
            'skills' => ['PHP', 'Laravel', 'JavaScript', 'React'],
            'hourly_rate' => 85,
            'portfolio_items' => [
                [
                    'title' => 'Updated Project',
                    'description' => 'An updated project description',
                    'image' => $image
                ]
            ],
            'github_url' => 'https://github.com/janedoe',
            'linkedin_url' => 'https://linkedin.com/in/janedoe'
        ]);

        $response->assertRedirect('/developer/profile');
        
        $this->assertDatabaseHas('developer_profiles', [
            'user_id' => $user->id,
            'title' => 'Updated Senior Developer',
            'bio' => 'Updated bio with 7 years of experience',
            'hourly_rate' => 85,
            'github_url' => 'https://github.com/janedoe',
            'linkedin_url' => 'https://linkedin.com/in/janedoe'
        ]);

        $this->assertDatabaseHas('skills', ['name' => 'React']);
        $this->assertEquals(4, $profile->fresh()->skills()->count());
        
        $portfolioItem = $profile->fresh()->portfolioItems()->first();
        $this->assertNotNull($portfolioItem);
        $this->assertEquals('Updated Project', $portfolioItem->title);
        $this->assertEquals('An updated project description', $portfolioItem->description);
        
        Storage::disk('public')->assertExists($portfolioItem->image_path);
    }

    public function test_can_filter_developers_by_skills(): void
    {
        $phpDev = User::factory()->create(['role' => 'developer']);
        $phpProfile = DeveloperProfile::factory()->create(['user_id' => $phpDev->id]);
        $phpSkill = Skill::factory()->create(['name' => 'PHP']);
        $phpProfile->skills()->attach($phpSkill);

        $jsDev = User::factory()->create(['role' => 'developer']);
        $jsProfile = DeveloperProfile::factory()->create(['user_id' => $jsDev->id]);
        $jsSkill = Skill::factory()->create(['name' => 'JavaScript']);
        $jsProfile->skills()->attach($jsSkill);

        $response = $this->getJson('/developer/search?skill=PHP');
        
        $response->assertOk()
            ->assertJsonPath('developers.0.user.name', $phpDev->name)
            ->assertJsonMissing(['name' => $jsDev->name]);

        $response = $this->getJson('/developer/search?skill=JavaScript');
        
        $response->assertOk()
            ->assertJsonPath('developers.0.user.name', $jsDev->name)
            ->assertJsonMissing(['name' => $phpDev->name]);
    }

    public function test_profile_privacy_settings(): void
    {
        $developer = User::factory()->create(['role' => 'developer']);
        $profile = DeveloperProfile::factory()->create([
            'user_id' => $developer->id,
            'email_visible' => false,
            'phone_visible' => false
        ]);

        // Test public view (unauthenticated)
        $response = $this->getJson("/developer/profile/{$profile->id}");
        
        $response->assertOk()
            ->assertJsonMissing(['email' => $developer->email])
            ->assertJsonMissing(['phone' => $profile->phone]);

        // Test authenticated view (as the profile owner)
        $response = $this->actingAs($developer)
            ->getJson("/developer/profile/{$profile->id}");
        
        $response->assertOk()
            ->assertJsonPath('profile.email', $developer->email)
            ->assertJsonPath('profile.phone', $profile->phone);

        // Test privacy settings update
        $response = $this->actingAs($developer)
            ->put("/developer/profile/{$profile->id}/privacy", [
                'email_visible' => true,
                'phone_visible' => true
            ]);

        $response->assertRedirect('/developer/profile');
        
        $this->assertDatabaseHas('developer_profiles', [
            'id' => $profile->id,
            'email_visible' => true,
            'phone_visible' => true
        ]);
    }
} 