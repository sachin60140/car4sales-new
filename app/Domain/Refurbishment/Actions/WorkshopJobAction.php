<?php

namespace App\Domain\Refurbishment\Actions;

use App\Domain\Administration\Services\NumberSequenceService;
use App\Domain\Inventory\Enums\ExpenseCategory;
use App\Domain\Inventory\Enums\VehicleStatus;
use App\Domain\Inventory\Models\Vehicle;
use App\Domain\Inventory\Services\VehicleExpenseService;
use App\Domain\Refurbishment\Enums\WorkshopJobStatus;
use App\Domain\Refurbishment\Models\WorkshopJob;
use App\Models\User;
use App\Support\Workflow\InvalidTransitionException;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Refurbishment job-card lifecycle. Approving sets the approved budget; on QC
 * pass the actual cost is posted as an approved vehicle expense — which is what
 * increases the vehicle's landed cost (via VehicleExpenseService).
 */
class WorkshopJobAction
{
    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly VehicleExpenseService $expenses,
        private readonly WorkflowService $workflow,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     */
    public function create(Vehicle $vehicle, array $data, array $items, User $creator): WorkshopJob
    {
        return DB::transaction(function () use ($vehicle, $data, $items, $creator) {
            $estimate = 0.0;

            $job = WorkshopJob::query()->create([
                'job_number' => $this->sequences->next('workshop_job'),
                'vehicle_id' => $vehicle->id,
                'vendor_id' => $data['vendor_id'] ?? null,
                'branch_id' => $vehicle->branch_id,
                'type' => $data['type'] ?? 'internal',
                'description' => $data['description'] ?? null,
                'expected_completion' => $data['expected_completion'] ?? null,
                'status' => WorkshopJobStatus::Draft->value,
                'created_by' => $creator->id,
            ]);

            foreach ($items as $item) {
                $estimate += (float) ($item['estimate'] ?? 0);
                $job->items()->create([
                    'defect' => $item['defect'] ?? null,
                    'work_type' => $item['work_type'] ?? 'labour',
                    'description' => $item['description'],
                    'estimate' => $item['estimate'] ?? 0,
                    'status' => 'pending',
                ]);
            }

            $job->update(['estimate_total' => $estimate]);

            return $job->fresh('items');
        });
    }

    public function approve(WorkshopJob $job, User $actor, ?float $approvedTotal = null): WorkshopJob
    {
        return DB::transaction(function () use ($job, $actor, $approvedTotal) {
            $this->moveJob($job, WorkshopJobStatus::Approved);

            $job->update([
                'approved_total' => $approvedTotal ?? $job->estimate_total,
                'start_date' => $job->start_date ?? now()->toDateString(),
            ]);
            $job->items()->update(['status' => 'approved']);

            // Move the vehicle under refurbishment.
            $vehicle = $job->vehicle;
            if (in_array($vehicle->status, [VehicleStatus::InStock, VehicleStatus::InspectionPending, VehicleStatus::ReadyForSale], true)) {
                $this->workflow->transition($vehicle, VehicleStatus::UnderRefurbishment, $actor, 'Refurbishment job '.$job->job_number, force: true);
            }

            return $job->fresh();
        });
    }

    public function start(WorkshopJob $job, User $actor): WorkshopJob
    {
        $this->moveJob($job, WorkshopJobStatus::InProgress);

        return $job->fresh();
    }

    /**
     * Complete a job with actual amounts. QC pass posts the expense and returns
     * the vehicle to stock; QC fail sends it back to in-progress for rework.
     *
     * @param  array<int, array{id: int, actual_amount: float|int}>  $itemActuals
     */
    public function complete(WorkshopJob $job, User $actor, array $itemActuals, string $qc, float $actualTotal): WorkshopJob
    {
        if (! in_array($qc, ['passed', 'failed'], true)) {
            throw new RuntimeException('QC result must be passed or failed.');
        }

        return DB::transaction(function () use ($job, $actor, $itemActuals, $qc, $actualTotal) {
            foreach ($itemActuals as $row) {
                $job->items()->where('id', $row['id'])->update([
                    'actual_amount' => $row['actual_amount'],
                    'status' => 'done',
                ]);
            }

            $this->moveJob($job, WorkshopJobStatus::Completed);

            $job->update([
                'actual_total' => $actualTotal,
                'actual_completion' => now()->toDateString(),
                'qc_status' => $qc,
                'qc_by' => $actor->id,
                'qc_at' => now(),
            ]);

            $vehicle = $job->vehicle;

            if ($qc === 'passed') {
                $this->moveJob($job, WorkshopJobStatus::QcPassed);

                // Post the refurbishment expense — this increases landed cost.
                if ($actualTotal > 0) {
                    $this->expenses->createApproved($vehicle, [
                        'category' => ExpenseCategory::Refurbishment->value,
                        'description' => 'Workshop job '.$job->job_number,
                        'amount' => $actualTotal,
                        'vendor_id' => $job->vendor_id,
                        'workshop_job_id' => $job->id,
                    ], $actor);
                }

                $this->workflow->transition($vehicle, VehicleStatus::ReadyForSale, $actor, 'Refurbishment complete', force: true);
            } else {
                $this->moveJob($job, WorkshopJobStatus::QcFailed);
            }

            return $job->fresh();
        });
    }

    /**
     * Validate and apply a workshop-job status change. Job history is captured by
     * activitylog (the model logs status changes), so no dedicated history table
     * is needed.
     */
    private function moveJob(WorkshopJob $job, WorkshopJobStatus $to, bool $force = true): void
    {
        $from = $job->status;

        if (! $force && ! $from->canTransitionTo($to)) {
            throw InvalidTransitionException::between($from->value, $to->value);
        }

        $job->update(['status' => $to->value]);
    }
}
