<?php

namespace Database\Factories;

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Milestone>
 */
class MilestoneFactory extends Factory
{
    protected $model = Milestone::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'amount' => fake()->randomFloat(2, 500, 2000),
            'status' => 'funded',
            'progress_status' => 'todo',
            'order' => 1,
            'due_date' => now()->addDays(14),
            'deliverables' => json_encode(['Deliverable 1', 'Deliverable 2']),
        ];
    }

    public function todo(): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_status' => 'todo',
        ]);
    }

    public function in_progress(): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_status' => 'in_progress',
        ]);
    }

    public function review(): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_status' => 'review',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_status' => 'completed',
        ]);
    }
}
