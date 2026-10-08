<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'text',
            'body' => fake()->paragraph(),
            'media' => null,
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Post::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    public function withImages(int $count = 2): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'image',
            'media' => array_map(
                fn (int $i): array => ['path' => "posts/{$i}/image-{$i}.jpg"],
                range(1, $count),
            ),
        ]);
    }
}
