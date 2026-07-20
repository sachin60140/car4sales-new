<?php

namespace Database\Factories;

use App\Domain\PurchaseLeads\Enums\PurchaseLeadStatus;
use App\Domain\PurchaseLeads\Models\PurchaseLead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseLead>
 */
class PurchaseLeadFactory extends Factory
{
    protected $model = PurchaseLead::class;

    public function definition(): array
    {
        static $seq = 1;

        return [
            'lead_number' => 'PL-'.now()->year.'-'.str_pad((string) $seq++, 6, '0', STR_PAD_LEFT),
            'seller_name' => fake()->name(),
            'seller_type' => 'individual',
            'mobile' => fake()->numerify('9#########'),
            'source' => 'manual',
            'make' => fake()->randomElement(['Maruti', 'Hyundai', 'Honda', 'Tata']),
            'model' => fake()->randomElement(['Swift', 'i20', 'City', 'Nexon']),
            'manufacturing_year' => fake()->numberBetween(2015, 2023),
            'fuel_type' => 'Petrol',
            'transmission' => 'Manual',
            'odometer_km' => fake()->numberBetween(10000, 90000),
            'expected_price' => fake()->numberBetween(300000, 900000),
            'loan_status' => 'none',
            'priority' => 'normal',
            'status' => PurchaseLeadStatus::New->value,
        ];
    }
}
