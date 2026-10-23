<?php

namespace Database\Factories;

use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_number' => 'EW-'.now()->year.'-'.fake()->unique()->numerify('#####'),
            'service_type' => 'other',
            'full_name' => fake()->name(),
            'nationality' => 'Indian',
            'current_country' => 'Bahrain',
            'phone' => '+973 33640001',
            'email' => fake()->safeEmail(),
            'consent_accuracy' => true,
            'consent_processing' => true,
            'consented_at' => now(),
            'remarks' => 'Please help with my international plans.',
        ];
    }
}
