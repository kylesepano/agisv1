<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
/**
 * Produces access roles for permission and scope tests.
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'code' => str($name)->slug('_')->limit(50, '')->toString(),
            'name' => $name,
            'description' => fake()->sentence(),
            'is_system' => false,
            'is_active' => true,
        ];
    }
}
