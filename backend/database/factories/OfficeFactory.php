<?php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Office>
 */
/**
 * Produces independent office records for tests and local fixtures.
 */
class OfficeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('OFF-###??')),
            'name' => fake()->unique()->company().' Office',
            'acronym' => strtoupper(fake()->lexify('???')),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
