<?php

namespace App\Domain\Administration\Services;

use App\Domain\Administration\Models\NumberSequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Issues transaction-safe, gap-minimised reference numbers such as PL-2026-000001.
 *
 * The row is locked FOR UPDATE, so concurrent callers serialize on the sequence.
 * Call inside the same transaction that persists the record consuming the number.
 */
class NumberSequenceService
{
    /**
     * Get the next reference number for a sequence key.
     */
    public function next(string $key, ?int $branchId = null, ?int $year = null): string
    {
        $config = config("car4sales.sequences.{$key}");

        if ($config === null) {
            throw new InvalidArgumentException("Unknown number sequence [{$key}].");
        }

        $year ??= (int) now()->format('Y');
        $branchScoped = (bool) ($config['per_branch'] ?? false);
        $effectiveBranchId = $branchScoped ? $branchId : null;

        $issue = function () use ($key, $config, $year, $effectiveBranchId): string {
            $sequence = NumberSequence::query()
                ->where('key', $key)
                ->where('year', $year)
                ->when(
                    $effectiveBranchId === null,
                    fn ($q) => $q->whereNull('branch_id'),
                    fn ($q) => $q->where('branch_id', $effectiveBranchId),
                )
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = NumberSequence::query()->create([
                    'key' => $key,
                    'branch_id' => $effectiveBranchId,
                    'prefix' => $config['prefix'],
                    'year' => $year,
                    'next_number' => 1,
                    'padding' => $config['padding'] ?? 6,
                ]);

                // Re-acquire under lock in case a concurrent request created it first.
                $sequence = NumberSequence::query()
                    ->whereKey($sequence->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $number = $sequence->next_number;
            $sequence->increment('next_number');

            return sprintf(
                '%s-%d-%s',
                $sequence->prefix,
                $year,
                str_pad((string) $number, $sequence->padding, '0', STR_PAD_LEFT),
            );
        };

        // Join the caller's transaction when one is open; otherwise wrap our own.
        return DB::transactionLevel() > 0 ? $issue() : DB::transaction($issue);
    }
}
