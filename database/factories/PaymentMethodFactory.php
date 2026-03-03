<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'bank_account',
            'provider' => fake()->randomElement(['Visa', 'Mastercard', 'PayPal', 'Stripe']),
            'account_last_four' => fake()->numerify('####'),
            'is_default' => false,
        ];
    }

    public function bank_account(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'bank_account',
        ]);
    }

    public function credit_card(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'credit_card',
        ]);
    }

    public function paypal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'paypal',
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
