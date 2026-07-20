<?php

namespace App\Support\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Central, validated state-machine transitions. Every move validates against the
 * enum's allowed-transition map, writes a status-history row and fires the model's
 * status-changed hook — all atomically.
 *
 * @template TModel of Model
 */
class WorkflowService
{
    /**
     * Transition a workflow model to a new status.
     *
     * @param  Model&\App\Support\Workflow\Transitionable  $model
     */
    public function transition(Model $model, HasTransitions $to, ?User $user = null, ?string $remarks = null, bool $force = false): Model
    {
        $column = $model->statusColumnName();
        $from = $model->currentStatus();

        if ($from === $to) {
            return $model;
        }

        if (! $force && ! $from->canTransitionTo($to)) {
            throw InvalidTransitionException::between($from->value, $to->value);
        }

        return DB::transaction(function () use ($model, $column, $from, $to, $user, $remarks) {
            $model->forceFill([$column => $to->value])->save();
            $model->writeStatusHistory($from->value, $to->value, $user, $remarks);

            if (method_exists($model, 'onStatusChanged')) {
                $model->onStatusChanged($from, $to, $user);
            }

            return $model;
        });
    }
}
