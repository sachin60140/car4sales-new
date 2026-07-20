<?php

namespace Database\Seeders;

use App\Domain\Branches\Models\Branch;
use App\Domain\Departments\Models\Department;
use App\Domain\Employees\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::query()->where('code', 'HO')->first();
        $department = Department::query()->where('code', 'MGMT')->first();

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@car4sales.test'],
            [
                'name' => 'System Administrator',
                'password' => 'Admin@12345',
                'branch_id' => $branch?->id,
                'department_id' => $department?->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles(['Super Admin']);

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $admin->id],
            [
                'employee_code' => 'EMP-'.now()->year.'-0001',
                'designation' => 'System Administrator',
                'date_of_joining' => now()->toDateString(),
            ],
        );
    }
}
