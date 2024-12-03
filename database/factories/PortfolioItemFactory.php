<?php

namespace Database\Factories;

use App\Models\DeveloperProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class PortfolioItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'developer_profile_id' => DeveloperProfile::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph,
            'image_path' => 'portfolio/default.jpg'
        ];
    }
} 