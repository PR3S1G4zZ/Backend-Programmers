<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'amount' => fake()->randomFloat(2, 100, 1000),
            'type' => 'deposit',
            'description' => fake()->sentence(),
            'reference_type' => 'App\\Models\\Project',
            'reference_id' => 1,
        ];
    }

    public function deposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'deposit',
            'amount' => abs($this->faker->randomFloat(2, 100, 1000)),
        ]);
    }

    public function withdraw(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'withdraw',
            'amount' => -abs($this->faker->randomFloat(2, 100, 1000)),
        ]);
    }

    public function escrow_deposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'escrow_deposit',
            'amount' => -abs($this->faker->randomFloat(2, 100, 1000)),
            'description' => 'Depósito en Garantía',
        ]);
    }

    public function payment_received(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'payment_received',
            'amount' => abs($this->faker->randomFloat(2, 100, 1000)),
            'description' => 'Pago recibido por proyecto',
        ]);
    }
}
