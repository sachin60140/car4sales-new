<?php

namespace App\Support\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Applied to workflow models. The model must define:
 *  - protected string $statusColumn (defaults to 'status')
 *  - protected string $statusEnum (the HasTransitions enum class)
 *  - statusHistories(): HasMany relation to its *_status_histories table
 */
trait RecordsStatusHistory
{
    public function statusColumnName(): string
    {
        return property_exists($this, 'statusColumn') ? $this->statusColumn : 'status';
    }

    /** @return class-string<HasTransitions> */
    public function statusEnumClass(): string
    {
        return $this->statusEnum;
    }

    public function currentStatus(): HasTransitions
    {
        $value = $this->{$this->statusColumnName()};

        if ($value instanceof HasTransitions) {
            return $value;
        }

        $enum = $this->statusEnumClass();

        return $enum::from($value);
    }

    abstract public function statusHistories(): HasMany;

    public function writeStatusHistory(?string $from, string $to, ?User $user, ?string $remarks): void
    {
        $this->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $user?->id,
            'remarks' => $remarks,
        ]);
    }
}
