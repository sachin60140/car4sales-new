<?php

namespace Database\Seeders;

use App\Domain\Branches\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::query()->updateOrCreate(
            ['code' => 'HO'],
            [
                'name' => 'Head Office',
                'slug' => 'head-office',
                'city' => 'Lucknow',
                'state' => 'Uttar Pradesh',
                'is_active' => true,
                'sort_order' => 0,
            ],
        );
    }
}
