<?php

namespace Database\Factories;

use App\Domain\Departments\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' Department';

        return [
            'code' => strtoupper(Str::random(6)),
            'name' => $name,
            'slug' => Str::slug($name.'-'.Str::random(4)),
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
