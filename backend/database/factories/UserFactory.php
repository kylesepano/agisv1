<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
/**
 * Produces normalized employee accounts with safe test credentials.
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
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        $employeeId = fake()->unique()->bothify('EMP-####-??');

        return [
            'role_id' => Role::factory(),
            'office_id' => Office::factory(),
            'employee_id' => strtoupper($employeeId),
            'username' => 'employee:'.strtolower($employeeId),
            'name' => "{$firstName} {$lastName}",
            'first_name' => $firstName,
            'middle_name' => null,
            'last_name' => $lastName,
            'name_extension' => null,
            'initials' => strtoupper(mb_substr($firstName, 0, 1).mb_substr($lastName, 0, 1)),
            'email' => fake()->unique()->safeEmail(),
            'position' => fake()->jobTitle(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => null,
            'lock_version' => 0,
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

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);
    }
}
