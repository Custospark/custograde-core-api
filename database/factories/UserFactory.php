<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'account_type' => User::ACCOUNT_TYPE_INSTITUTIONAL,
            'institution_id' => null,
            'role' => User::ROLE_FOR_ACCOUNT_TYPE[User::ACCOUNT_TYPE_INSTITUTIONAL],
        ];
    }

    /**
     * A personal account: an individual teacher with no institution (AUT-06).
     */
    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'institution_id' => null,
            'account_type' => User::ACCOUNT_TYPE_PERSONAL,
            'role' => User::ROLE_FOR_ACCOUNT_TYPE[User::ACCOUNT_TYPE_PERSONAL],
        ]);
    }

    /**
     * An institutional account attached to the given institution.
     */
    public function institutional(?int $institutionId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'institution_id' => $institutionId,
            'account_type' => User::ACCOUNT_TYPE_INSTITUTIONAL,
            'role' => User::ROLE_FOR_ACCOUNT_TYPE[User::ACCOUNT_TYPE_INSTITUTIONAL],
        ]);
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
