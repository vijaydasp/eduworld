<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\PaymentRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentRequest>
 */
class PaymentRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => fake()->unique()->uuid(),
            'type' => PaymentRequest::TYPE_APPLICATION,
            'application_id' => Application::factory(),
            'payment_id' => null,
            'customer_name' => null,
            'customer_email' => null,
            'customer_phone' => null,
            'amount' => '25.000',
            'currency' => 'BHD',
            'description' => 'Agreed service fee',
            'status' => 'sent',
            'expires_at' => now()->addDays(7),
            'sent_at' => now(),
            'paid_at' => null,
            'created_by' => null,
        ];
    }

    public function standalone(): static
    {
        return $this->state(fn (): array => [
            'type' => PaymentRequest::TYPE_STANDALONE,
            'application_id' => null,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '+973 39000000',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => ['status' => 'paid', 'paid_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => 'cancelled']);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['status' => 'expired', 'expires_at' => now()->subDay()]);
    }
}
