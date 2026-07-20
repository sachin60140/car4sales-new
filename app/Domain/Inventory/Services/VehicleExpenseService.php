<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Inventory\Models\VehicleExpense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single source of truth for vehicle expenses and their effect on landed cost.
 *
 * Rule (spec §17): "Approved expenses must increase vehicle landed cost."
 * Only this service mutates `vehicles.landed_cost` for expenses, and it does so
 * exactly once per expense (guarded by `added_to_landed_cost`). Corrections are
 * made by reversal rows, never by editing or deleting a posted expense.
 */
class VehicleExpenseService
{
    public function __construct(private readonly NumberSequenceService $sequences) {}

    /**
     * Record a pending expense (awaiting approval).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Vehicle $vehicle, array $data, User $creator): VehicleExpense
    {
        return $vehicle->expenses()->create([
            'expense_number' => $this->sequences->next('vehicle_expense'),
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'vendor_id' => $data['vendor_id'] ?? null,
            'workshop_job_id' => $data['workshop_job_id'] ?? null,
            'invoice_path' => $data['invoice_path'] ?? null,
            'status' => 'pending',
            'created_by' => $creator->id,
            'remarks' => $data['remarks'] ?? null,
        ]);
    }

    /**
     * Approve an expense and add it to the vehicle's landed cost (once).
     */
    public function approve(VehicleExpense $expense, User $approver): VehicleExpense
    {
        if ($expense->status !== 'pending') {
            throw new RuntimeException('Only pending expenses can be approved.');
        }

        return DB::transaction(function () use ($expense, $approver) {
            $expense->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            $this->addToLandedCost($expense);

            return $expense->fresh();
        });
    }

    /**
     * Create and immediately approve an expense (used by workshop completion,
     * where the job itself already carried the approval).
     *
     * @param  array<string, mixed>  $data
     */
    public function createApproved(Vehicle $vehicle, array $data, User $actor): VehicleExpense
    {
        return DB::transaction(function () use ($vehicle, $data, $actor) {
            $expense = $this->create($vehicle, $data, $actor);
            $expense->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
            $this->addToLandedCost($expense);

            return $expense->fresh();
        });
    }

    public function reject(VehicleExpense $expense, User $actor, ?string $remarks = null): VehicleExpense
    {
        if ($expense->status !== 'pending') {
            throw new RuntimeException('Only pending expenses can be rejected.');
        }

        $expense->update(['status' => 'rejected', 'remarks' => $remarks ?? $expense->remarks]);

        return $expense;
    }

    /**
     * Reverse an approved expense: posts a negative reversal row and subtracts
     * the amount back out of landed cost.
     */
    public function reverse(VehicleExpense $expense, User $actor, string $remarks): VehicleExpense
    {
        if ($expense->status !== 'approved' || ! $expense->added_to_landed_cost) {
            throw new RuntimeException('Only approved, posted expenses can be reversed.');
        }

        if ($expense->reversal_of !== null) {
            throw new RuntimeException('A reversal entry cannot itself be reversed.');
        }

        return DB::transaction(function () use ($expense, $actor, $remarks) {
            $reversal = $expense->vehicle->expenses()->create([
                'expense_number' => $this->sequences->next('vehicle_expense'),
                'category' => $expense->category,
                'description' => 'Reversal of '.$expense->expense_number,
                'amount' => (string) (-1 * (float) $expense->amount),
                'vendor_id' => $expense->vendor_id,
                'status' => 'reversed',
                'added_to_landed_cost' => true,
                'reversal_of' => $expense->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'created_by' => $actor->id,
                'remarks' => $remarks,
            ]);

            $expense->update(['status' => 'reversed']);
            $expense->vehicle->decrement('landed_cost', (float) $expense->amount);

            return $reversal;
        });
    }

    private function addToLandedCost(VehicleExpense $expense): void
    {
        if ($expense->added_to_landed_cost) {
            return;
        }

        $expense->vehicle->increment('landed_cost', (float) $expense->amount);
        $expense->update(['added_to_landed_cost' => true]);
    }
}
