<?php

namespace App\Domain\RTO\Actions;

use App\Domain\RTO\Enums\RtoStatus;
use App\Domain\RTO\Models\RtoCase;
use App\Domain\RTO\Models\RtoDocument;
use App\Domain\RTO\Models\RtoDocumentMovement;
use App\Domain\RTO\Models\RtoExpense;
use App\Domain\RTO\Models\RtoHold;
use App\Models\User;
use App\Support\Workflow\WorkflowService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Executive operations on an RTO transfer case: validated status transitions,
 * document-custody tracking, expenses and payment holds (spec §25).
 */
class RtoCaseAction
{
    public function __construct(
        private readonly WorkflowService $workflow,
    ) {}

    public function transition(RtoCase $case, RtoStatus $to, User $actor, ?string $remarks = null): RtoCase
    {
        /** @var RtoCase $updated */
        $updated = $this->workflow->transition($case, $to, $actor, $remarks);

        return $updated->fresh();
    }

    /**
     * Record who now physically holds an original document.
     */
    public function recordMovement(RtoCase $case, string $document, string $toHolder, User $actor, ?string $fromHolder = null, ?string $remarks = null): RtoDocumentMovement
    {
        return DB::transaction(function () use ($case, $document, $toHolder, $actor, $fromHolder, $remarks) {
            // Default the source to whoever last held this document.
            if ($fromHolder === null) {
                $fromHolder = $case->movements()->where('document', $document)->value('to_holder');
            }

            return $case->movements()->create([
                'document' => $document,
                'from_holder' => $fromHolder,
                'to_holder' => $toHolder,
                'moved_by' => $actor->id,
                'moved_at' => now(),
                'remarks' => $remarks,
            ]);
        });
    }

    public function addDocument(RtoCase $case, string $type, ?string $filePath, User $actor, string $status = 'pending'): RtoDocument
    {
        return $case->documents()->create([
            'type' => $type,
            'file_path' => $filePath,
            'status' => $status,
            'uploaded_by' => $actor->id,
        ]);
    }

    public function addExpense(RtoCase $case, string $head, float $amount, User $actor, ?string $reference = null): RtoExpense
    {
        if ($amount <= 0) {
            throw new RuntimeException('Expense amount must be positive.');
        }

        return $case->expenses()->create([
            'head' => $head,
            'amount' => round($amount, 2),
            'reference' => $reference,
            'recorded_by' => $actor->id,
        ]);
    }

    public function addHold(RtoCase $case, float $amount, string $reason, User $actor): RtoHold
    {
        if ($amount <= 0) {
            throw new RuntimeException('Hold amount must be positive.');
        }

        return DB::transaction(function () use ($case, $amount, $reason, $actor) {
            $hold = $case->holds()->create([
                'amount' => round($amount, 2),
                'reason' => $reason,
                'status' => 'held',
                'held_by' => $actor->id,
            ]);

            $case->update(['hold_amount' => $case->activeHoldAmount()]);

            return $hold;
        });
    }

    public function releaseHold(RtoHold $hold, User $actor): RtoHold
    {
        if ($hold->status === 'released') {
            return $hold;
        }

        return DB::transaction(function () use ($hold, $actor) {
            $hold->update([
                'status' => 'released',
                'released_by' => $actor->id,
                'released_at' => now(),
            ]);

            $case = $hold->rtoCase;
            $case->update(['hold_amount' => $case->activeHoldAmount()]);

            return $hold->fresh();
        });
    }

    public function assign(RtoCase $case, ?int $userId, ?int $agentVendorId, User $actor): RtoCase
    {
        $case->update([
            'assigned_to' => $userId,
            'agent_vendor_id' => $agentVendorId,
        ]);

        return $case->fresh();
    }
}
