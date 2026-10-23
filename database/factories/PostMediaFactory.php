<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostMedia>
 */
class PostMediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'path' => 'cms/images/'.fake()->uuid().'.jpg',
            'type' => 'image',
            'mime_type' => 'image/jpeg',
            'original_name' => 'editorial.jpg',
            'size' => 100,
        ];
    }
}
