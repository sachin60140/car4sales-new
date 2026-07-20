<?php

namespace App\Domain\PublicWebsite\Services;

/**
 * On-site EMI estimator for the finance widget. Standard reducing-balance EMI.
 */
class FinanceEstimator
{
    /**
     * @return array{loan_amount: float, down_payment: float, emi: float, tenure_months: int, interest_rate: float, total_payable: float}
     */
    public function estimate(float $price, ?float $downPayment = null, ?int $tenureMonths = null, ?float $annualRate = null): array
    {
        $config = config('car4sales.public.finance');

        $tenure = $tenureMonths ?? (int) $config['tenure_months'];
        $rate = $annualRate ?? (float) $config['interest_rate'];
        $down = $downPayment ?? round($price * ((float) $config['down_payment_pct'] / 100), 2);

        $loan = max($price - $down, 0);
        $monthlyRate = $rate / 12 / 100;

        if ($monthlyRate <= 0 || $tenure <= 0) {
            $emi = $tenure > 0 ? $loan / $tenure : 0;
        } else {
            $factor = (1 + $monthlyRate) ** $tenure;
            $emi = $loan * $monthlyRate * $factor / ($factor - 1);
        }

        return [
            'loan_amount' => round($loan, 2),
            'down_payment' => round($down, 2),
            'emi' => round($emi, 0),
            'tenure_months' => $tenure,
            'interest_rate' => $rate,
            'total_payable' => round($emi * $tenure + $down, 0),
        ];
    }
}
