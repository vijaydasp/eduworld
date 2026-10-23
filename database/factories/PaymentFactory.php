<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_reference' => 'EWP-TEST-'.fake()->unique()->numerify('#########'),
            'gateway' => 'test-gateway',
            'gateway_reference' => fake()->unique()->uuid(),
            'customer_name' => fake()->name(),
            'phone' => '+97333640001',
            'email' => fake()->safeEmail(),
            'service' => 'other',
            'amount' => '12.345',
            'amount_received' => '0.000',
            'currency' => 'BHD',
            'channel' => 'gateway',
            'status' => 'pending',
        ];
    }
}
