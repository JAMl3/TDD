<?php

namespace Database\Factories;

use App\Models\Job;
use App\Models\User;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobApplicationFactory extends Factory
{
    protected $model = JobApplication::class;

    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'user_id' => User::factory()->create(['role' => 'developer'])->id,
            'proposal' => $this->faker->paragraph,
            'timeline' => $this->faker->randomElement(['1 week', '2 weeks', '1 month']),
            'budget' => $this->faker->numberBetween(500, 10000),
            'status' => $this->faker->randomElement(['pending', 'accepted', 'rejected']),
            'portfolio_items' => []
        ];
    }

    public function accepted(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted'
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected'
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending'
        ]);
    }
} 