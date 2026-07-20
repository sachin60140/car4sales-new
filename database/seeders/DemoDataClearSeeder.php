<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Removes every record created by DemoDataSeeder.
 *
 *   php artisan db:seed --class=DemoDataClearSeeder
 */
class DemoDataClearSeeder extends Seeder
{
    public function run(): void
    {
        DemoDataSeeder::clear();

        $this->command?->info('Demo data cleared.');
    }
}
