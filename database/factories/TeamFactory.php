<?php

namespace Database\Factories;

use App\Domain\Branches\Models\Branch;
use App\Domain\Departments\Models\Department;
use App\Domain\Teams\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(6)),
            'name' => 'Team '.ucfirst(fake()->unique()->word()),
            'branch_id' => Branch::factory(),
            'department_id' => Department::factory(),
            'team_leader_id' => null,
            'is_active' => true,
        ];
    }
}
