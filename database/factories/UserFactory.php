<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'lastname' => $this->generateValidLastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password1!'),
            'remember_token' => Str::random(10),
            'user_type' => 'programmer',
            'role' => 'programmer',
        ];
    }

    /**
     * Genera un apellido válido que pase la validación del modelo
     * La regex es: /^(?!\s)[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+(?<!\s)$/
     */
    protected function generateValidLastName(): string
    {
        $lastnames = [
            'García', 'Rodríguez', 'Martínez', 'Hernández', 'López',
            'González', 'Pérez', 'Sánchez', 'Ramírez', 'Torres',
            'Flores', 'Rivera', 'Gómez', 'Díaz', 'Reyes',
            'Cruz', 'Morales', 'Ortiz', 'Gutiérrez', 'Chávez',
            'Ramos', 'Vargas', 'Castillo', 'Jiménez', 'Moreno',
            'Romero', 'Herrera', 'Medina', 'Aguilar', 'Vega',
            'Castro', 'Méndez', 'Fernández', 'Álvarez', 'Silva',
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones',
            'Miller', 'Davis', 'Wilson', 'Moore', 'Taylor',
            'Anderson', 'Thomas', 'Jackson', 'White', 'Harris'
        ];
        
        return $lastnames[array_rand($lastnames)];
    }

    public function programmer(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'programmer',
            'role' => 'programmer',
        ]);
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'company',
            'role' => 'company',
            'lastname' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'admin',
            'role' => 'admin',
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
