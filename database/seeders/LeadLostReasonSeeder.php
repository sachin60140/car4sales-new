<?php

namespace Database\Seeders;

use App\Domain\SalesLeads\Models\LeadLostReason;
use Illuminate\Database\Seeder;

class LeadLostReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['Price too high', 'price'],
            ['Bought elsewhere', 'competitor'],
            ['Finance not approved', 'finance'],
            ['Just browsing / not serious', 'not_serious'],
            ['Wanted a different model', 'general'],
            ['Budget constraints', 'price'],
            ['No response after follow-ups', 'general'],
            ['Postponed purchase', 'general'],
        ];

        $order = 0;
        foreach ($reasons as [$label, $category]) {
            LeadLostReason::query()->updateOrCreate(
                ['label' => $label],
                ['category' => $category, 'is_active' => true, 'sort_order' => $order++],
            );
        }
    }
}
