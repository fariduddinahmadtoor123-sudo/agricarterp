<?php

namespace Database\Factories;

use App\Models\Role;
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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_number' => null,
            'name' => fake()->name(),
            'full_address' => fake()->address(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => null,
            'status' => User::STATUS_ACTIVE,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (): array => [
            'role_id' => Role::query()->where('slug', Role::SLUG_SUPER_ADMIN)->value('id'),
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    public function staff(): static
    {
        return $this->state(function (): array {
            $roleId = Role::query()->firstOrCreate(
                ['slug' => 'staff'],
                [
                    'name' => 'Staff',
                    'description' => 'Test staff role',
                    'is_system' => false,
                ],
            )->id;

            return [
                'role_id' => $roleId,
                'status' => User::STATUS_ACTIVE,
            ];
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
