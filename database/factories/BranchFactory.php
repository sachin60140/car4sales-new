<?php

namespace Database\Factories;

use App\Domain\Branches\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        $name = fake()->unique()->city().' Branch';

        return [
            'code' => strtoupper(Str::random(6)),
            'name' => $name,
            'slug' => Str::slug($name.'-'.Str::random(4)),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'pin_code' => (string) fake()->numberBetween(110001, 855999),
            'phone' => fake()->numerify('9#########'),
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
