<?php

namespace Database\Seeders;

use App\Domain\Branches\Models\Branch;
use App\Domain\Finance\Models\Lender;
use App\Domain\Payments\Models\PaymentAccount;
use Illuminate\Database\Seeder;

/**
 * Baseline finance configuration: lenders and payment accounts.
 */
class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        $lenders = [
            ['HDFC Bank', 'bank', 10.5],
            ['ICICI Bank', 'bank', 10.75],
            ['State Bank of India', 'bank', 9.9],
            ['Bajaj Finserv', 'nbfc', 12.5],
            ['Mahindra Finance', 'nbfc', 13.0],
            ['Cholamandalam Finance', 'nbfc', 12.75],
        ];

        foreach ($lenders as $i => [$name, $type, $rate]) {
            Lender::query()->updateOrCreate(
                ['code' => 'LND-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                ['name' => $name, 'type' => $type, 'base_interest_rate' => $rate, 'is_active' => true],
            );
        }

        $ho = Branch::query()->where('code', 'HO')->first();
        $accounts = [
            ['CASH-HO', 'Head Office Cash', 'cash'],
            ['BANK-HO', 'Head Office Bank', 'bank'],
            ['UPI-HO', 'Head Office UPI', 'upi'],
        ];

        foreach ($accounts as [$code, $name, $type]) {
            PaymentAccount::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'branch_id' => $ho?->id, 'is_active' => true],
            );
        }
    }
}
