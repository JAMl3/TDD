<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'budget' => $this->faker->randomFloat(2, 100, 10000),
            'deadline' => $this->faker->dateTimeBetween('+1 week', '+1 year'),
            'required_skills' => $this->faker->randomElements(['PHP', 'Laravel', 'Vue.js', 'React', 'MySQL'], 2),
            'status' => 'open',
        ];
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed'
        ]);
    }
} 