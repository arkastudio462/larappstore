<?php

namespace Database\Factories;

use App\Models\DeveloperProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DeveloperProfile>
 */
class DeveloperProfileFactory extends Factory
{
    protected $model = DeveloperProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'studio_name' => fake()->company(),
            'slug' => Str::slug(fake()->unique()->company()),
            'bio' => fake()->sentence(),
            'website' => fake()->url(),
            'upload_credits' => 1,
            'unlimited_uploads' => false,
        ];
    }

    public function unlimited(): static
    {
        return $this->state(fn (): array => ['unlimited_uploads' => true]);
    }

    public function withCredits(int $credits): static
    {
        return $this->state(fn (): array => ['upload_credits' => $credits]);
    }
}
