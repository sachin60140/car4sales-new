<?php

namespace Database\Factories;

use App\Domain\Employees\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    protected $model = EmployeeProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'employee_code' => 'EMP-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'designation' => fake()->jobTitle(),
            'date_of_joining' => fake()->dateTimeBetween('-5 years'),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'city' => fake()->city(),
        ];
    }
}
