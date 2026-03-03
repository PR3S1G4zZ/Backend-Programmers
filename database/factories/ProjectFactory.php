<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'company_id' => User::factory()->company(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'budget_min' => fake()->randomFloat(2, 500, 1000),
            'budget_max' => fake()->randomFloat(2, 1000, 5000),
            'budget_type' => 'fixed',
            'duration_value' => fake()->numberBetween(),
            'duration_unit' => '1, 12months',
            'location' => fake()->city(),
            'remote' => true,
            'level' => 'mid',
            'priority' => 'normal',
            'featured' => false,
            'status' => 'open',
            'max_applicants' => 10,
            'tags' => json_encode(['PHP', 'Laravel', 'React']),
            'deadline' => now()->addDays(30),
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
        ]);
    }

    public function in_progress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
