<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => PostCategory::factory(),
            'title' => fake()->sentence(5),
            'slug' => fake()->unique()->slug(),
            'body' => fake()->paragraphs(3, true),
            'excerpt' => fake()->sentence(),
            'author_name' => 'Edu World',
            'is_published' => false,
            'is_featured' => false,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true, 'published_at' => now()->subDay()]);
    }
}
