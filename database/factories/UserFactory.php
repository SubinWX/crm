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
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => User::ROLE_STAFF,
            'is_active' => true,
            'permissions' => ['clients.view'],
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_SUPER_ADMIN, 'permissions' => null]);
    }

    /** @param  string[]  $permissions */
    public function staff(array $permissions = ['clients.view']): static
    {
        return $this->state(fn () => ['role' => User::ROLE_STAFF, 'permissions' => $permissions]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
