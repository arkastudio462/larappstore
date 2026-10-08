<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'developer_id' => User::factory(),
            'category_id' => Category::factory(),
            'type' => fake()->randomElement(Product::TYPES),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'summary' => fake()->sentence(10),
            'description' => fake()->paragraphs(3, true),
            'icon_path' => 'products/icon-'.fake()->unique()->numberBetween(1, 99999).'.png',
            'banner_path' => null,
            'price' => 0,
            'currency' => 'IDR',
            'status' => Product::STATUS_PUBLISHED,
            'is_featured' => false,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Product::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    public function paid(int $price): static
    {
        return $this->state(fn (array $attributes): array => [
            'price' => $price,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_featured' => true,
        ]);
    }
}
