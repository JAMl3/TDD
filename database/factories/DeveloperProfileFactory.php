<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\DeveloperProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeveloperProfile>
 */
class DeveloperProfileFactory extends Factory
{
    protected $model = DeveloperProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->jobTitle,
            'bio' => $this->faker->paragraph,
            'hourly_rate' => $this->faker->numberBetween(20, 200),
            'github_url' => 'https://github.com/' . $this->faker->userName,
            'linkedin_url' => 'https://linkedin.com/in/' . $this->faker->userName,
            'phone' => $this->faker->phoneNumber,
            'email_visible' => false,
            'phone_visible' => false
        ];
    }
} 