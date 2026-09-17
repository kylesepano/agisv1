<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
/**
 * Produces granular permission records for authorization tests.
 */
class PermissionFactory extends Factory
{
    public function definition(): array
    {
        $module = fake()->unique()->slug(2);
        $action = fake()->randomElement(['view', 'create', 'update', 'approve', 'delete', 'export']);

        return [
            'code' => "{$module}.{$action}",
            'name' => str("{$action} {$module}")->headline()->toString(),
            'module' => $module,
            'action' => $action,
            'description' => fake()->sentence(),
        ];
    }
}
