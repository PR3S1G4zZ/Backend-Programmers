<?php

namespace Database\Factories;

use App\Models\DeveloperProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeveloperProfile>
 */
class DeveloperProfileFactory extends Factory
{
    protected $model = DeveloperProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->programmer(),
            'headline' => fake()->jobTitle(),
            'bio' => fake()->paragraph(),
            'location' => fake()->city(),
            'hourly_rate' => fake()->randomFloat(2, 25, 150),
            'experience_years' => fake()->numberBetween(1, 15),
            'availability' => 'available',
            'skills' => json_encode(['PHP', 'Laravel', 'JavaScript', 'React']),
            'languages' => json_encode(['Spanish', 'English']),
            'links' => json_encode(['https://github.com/fake', 'https://linkedin.com/in/fake']),
        ];
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'availability' => 'available',
        ]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'availability' => 'unavailable',
        ]);
    }
}
