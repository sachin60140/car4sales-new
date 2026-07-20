<?php

namespace App\Domain\Finance\Enums;

use App\Support\Workflow\HasTransitions;

enum FinanceStatus: string implements HasTransitions
{
    case DocumentsPending = 'documents_pending';
    case FileReady = 'file_ready';
    case Submitted = 'submitted';
    case LoggedIn = 'logged_in';
    case FiPending = 'fi_pending';
    case CreditPending = 'credit_pending';
    case QueryRaised = 'query_raised';
    case Sanctioned = 'sanctioned';
    case Rejected = 'rejected';
    case AgreementPending = 'agreement_pending';
    case DisbursementPending = 'disbursement_pending';
    case Disbursed = 'disbursed';
    case Closed = 'closed';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DocumentsPending => [self::FileReady, self::Rejected],
            self::FileReady => [self::Submitted, self::DocumentsPending],
            self::Submitted => [self::LoggedIn, self::QueryRaised, self::Rejected],
            self::LoggedIn => [self::FiPending, self::CreditPending, self::QueryRaised, self::Rejected],
            self::FiPending => [self::CreditPending, self::QueryRaised, self::Rejected],
            self::CreditPending => [self::Sanctioned, self::QueryRaised, self::Rejected],
            self::QueryRaised => [self::Submitted, self::LoggedIn, self::CreditPending, self::Rejected],
            self::Sanctioned => [self::AgreementPending, self::Rejected],
            self::AgreementPending => [self::DisbursementPending, self::Rejected],
            self::DisbursementPending => [self::Disbursed],
            self::Disbursed => [self::Closed],
            self::Rejected => [self::Submitted, self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(HasTransitions $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
