<?php

namespace App\Http\Requests\Purchase;

use App\Domain\PurchaseLeads\Models\PurchaseLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('purchase_lead');

        return $lead instanceof PurchaseLead
            ? $this->user()->can('update', $lead)
            : $this->user()->can('create', PurchaseLead::class);
    }

    public function rules(): array
    {
        return [
            'seller_name' => ['required', 'string', 'max:255'],
            'seller_type' => ['nullable', 'string', 'in:individual,dealer,company'],
            'mobile' => ['required', 'string', 'max:20'],
            'alt_mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'pin_code' => ['nullable', 'string', 'max:10'],
            'source' => ['nullable', 'string', 'max:40'],
            'registration_number' => ['nullable', 'string', 'max:20'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'variant' => ['nullable', 'string', 'max:100'],
            'manufacturing_year' => ['nullable', 'integer', 'between:1980,'.(date('Y') + 1)],
            'fuel_type' => ['nullable', 'string', 'max:30'],
            'transmission' => ['nullable', 'string', 'max:30'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'expected_price' => ['nullable', 'numeric', 'min:0'],
            'loan_status' => ['nullable', 'string', 'in:none,active,closed_pending_noc'],
            'inspection_location' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->withoutTrashed()],
            'priority' => ['nullable', 'string', 'in:low,normal,high,hot'],
            'next_follow_up_at' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
