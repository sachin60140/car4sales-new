<?php

namespace Database\Seeders;

use App\Domain\Departments\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DepartmentSeeder extends Seeder
{
    public const DEPARTMENTS = [
        'MGMT' => 'Management',
        'ADMIN' => 'Administration',
        'PURCHASE' => 'Purchase',
        'INSPECT' => 'Inspection',
        'INVENTORY' => 'Inventory',
        'WORKSHOP' => 'Workshop',
        'TELECALL' => 'Telecalling',
        'SALES' => 'Sales',
        'FINANCE' => 'Finance',
        'ACCOUNTS' => 'Accounts',
        'DELIVERY' => 'Delivery',
        'RTO' => 'RTO',
        'LEGAL' => 'Legal and Compliance',
        'SUPPORT' => 'Customer Support',
    ];

    public function run(): void
    {
        $sort = 0;

        foreach (self::DEPARTMENTS as $code => $name) {
            Department::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'is_active' => true,
                    'sort_order' => $sort++,
                ],
            );
        }
    }
}
