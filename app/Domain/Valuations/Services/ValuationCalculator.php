<?php

namespace App\Domain\Valuations\Services;

/**
 * Pure valuation maths. Given the cost inputs, derives the recommended maximum
 * purchase price and the expected profit metrics.
 *
 *   Recommended Max Purchase Price =
 *       Expected Selling Price
 *     − Repair/Refurb − RTO/Documentation − Other Expected Expenses
 *     − Target Profit
 */
class ValuationCalculator
{
    /**
     * @param  array<string, float|int|string|null>  $input
     * @return array{recommended_price: float, total_expenses: float, expected_gross_profit: float, expected_net_profit: float, expected_margin_pct: float}
     */
    public function calculate(array $input): array
    {
        $expectedSelling = (float) ($input['expected_retail_price'] ?? 0);

        $expenses =
            (float) ($input['repair_estimate'] ?? 0)
            + (float) ($input['rto_expense'] ?? 0)
            + (float) ($input['documentation_expense'] ?? 0)
            + (float) ($input['transportation_expense'] ?? 0)
            + (float) ($input['insurance_expense'] ?? 0)
            + (float) ($input['brokerage'] ?? 0)
            + (float) ($input['holding_cost'] ?? 0)
            + (float) ($input['other_costs'] ?? 0);

        $targetProfit = (float) ($input['target_profit'] ?? 0);

        $recommended = round($expectedSelling - $expenses - $targetProfit, 2);

        // Actual price used to gauge realised profit: negotiated if present, else recommended.
        $purchasePrice = isset($input['final_negotiated_price']) && $input['final_negotiated_price'] !== null
            ? (float) $input['final_negotiated_price']
            : $recommended;

        $grossProfit = round($expectedSelling - $purchasePrice, 2);
        $netProfit = round($expectedSelling - $purchasePrice - $expenses, 2);
        $marginPct = $expectedSelling > 0 ? round(($netProfit / $expectedSelling) * 100, 2) : 0.0;

        return [
            'recommended_price' => $recommended,
            'total_expenses' => round($expenses, 2),
            'expected_gross_profit' => $grossProfit,
            'expected_net_profit' => $netProfit,
            'expected_margin_pct' => $marginPct,
        ];
    }
}
