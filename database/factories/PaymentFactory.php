<?php

namespace Database\Factories;

use App\Models\Job;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 1000),
            'description' => $this->faker->sentence(),
            'status' => 'pending',
            'payer_id' => User::factory()->client(),
            'payee_id' => User::factory()->developer(),
            'due_date' => now()->addDays(rand(1, 30)),
            'transaction_id' => null,
            'paid_at' => null
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'transaction_id' => 'txn_' . $this->faker->uuid(),
            'paid_at' => now()
        ]);
    }
} 