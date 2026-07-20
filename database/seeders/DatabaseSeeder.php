<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            BranchSeeder::class,
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            InspectionChecklistSeeder::class,
            DocumentTemplateSeeder::class,
            PublicContentSeeder::class,
            LeadLostReasonSeeder::class,
            FinanceSeeder::class,
        ]);
    }
}
