<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Job;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'reviewer_id' => User::factory()->create(['role' => 'client'])->id,
            'reviewee_id' => User::factory()->create(['role' => 'developer'])->id,
            'job_id' => Job::factory()->create(['status' => 'completed'])->id,
            'rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->paragraph,
            'categories' => [
                'communication' => $this->faker->numberBetween(1, 5),
                'quality' => $this->faker->numberBetween(1, 5),
                'timeliness' => $this->faker->numberBetween(1, 5)
            ]
        ];
    }

    public function forClient(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'categories' => [
                    'communication' => $this->faker->numberBetween(1, 5),
                    'payment_timeliness' => $this->faker->numberBetween(1, 5),
                    'requirement_clarity' => $this->faker->numberBetween(1, 5)
                ]
            ];
        });
    }

    public function forJob(Job $job, User $reviewer, User $reviewee): self
    {
        return $this->state(function (array $attributes) use ($job, $reviewer, $reviewee) {
            $categories = $reviewer->isClient() ? [
                'communication' => $this->faker->numberBetween(1, 5),
                'quality' => $this->faker->numberBetween(1, 5),
                'timeliness' => $this->faker->numberBetween(1, 5)
            ] : [
                'communication' => $this->faker->numberBetween(1, 5),
                'payment_timeliness' => $this->faker->numberBetween(1, 5),
                'requirement_clarity' => $this->faker->numberBetween(1, 5)
            ];

            return [
                'reviewer_id' => $reviewer->id,
                'reviewee_id' => $reviewee->id,
                'job_id' => $job->id,
                'categories' => $categories
            ];
        });
    }
} 