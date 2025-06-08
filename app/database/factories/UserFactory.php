<?php

namespace Database\Factories;

use App\Models\User;
use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement([UserType::Common, UserType::Merchant]);

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'document' => $type === 'common' ? $this->generateCpf() : $this->generateCnpj(),
            'type' => $type
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->wallet()->create([
                'balance' => fake()->numberBetween(0, 10_000_000), // até R$ 10.000,00 em centavos
            ]);
        });
    }

    private function generateCpf(): string
    {
        return $this->faker->unique()->numerify(str_repeat('#', 11));
    }

    private function generateCnpj(): string
    {
        return $this->faker->unique()->numerify(str_repeat('#', 14));
    }
}
