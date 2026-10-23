<?php

namespace Slakbal\TenancyFoundation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function modelName(): string
    {
        return config('tenancy-foundation.models.user');
    }

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, string>
     */
    protected function nameAttributes(): array
    {
        return collect(config('tenancy-foundation.user_name_attributes'))
            ->mapWithKeys(fn (string $attribute): array => [$attribute => match ($attribute) {
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'name' => fake()->name(),
                default => fake()->word(),
            }])
            ->all();
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            ...$this->nameAttributes(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
