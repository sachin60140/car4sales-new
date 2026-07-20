<?php

namespace App\Domain\SalesLeads\Enums;

use App\Domain\SalesLeads\Enums\SalesLeadStatus as Status;

enum CallOutcome: string
{
    case Connected = 'connected';
    case NoAnswer = 'no_answer';
    case Busy = 'busy';
    case SwitchedOff = 'switched_off';
    case CallRejected = 'call_rejected';
    case WrongNumber = 'wrong_number';
    case CallLater = 'call_later';
    case Interested = 'interested';
    case NotInterested = 'not_interested';
    case VisitScheduled = 'visit_scheduled';
    case TestDriveScheduled = 'test_drive_scheduled';
    case FinanceRequired = 'finance_required';
    case ExchangeRequired = 'exchange_required';
    case BookingExpected = 'booking_expected';

    public function isConnected(): bool
    {
        return ! in_array($this, [self::NoAnswer, self::Busy, self::SwitchedOff, self::CallRejected], true);
    }

    /** Does logging this outcome require a next follow-up date? */
    public function requiresFollowUp(): bool
    {
        return in_array($this, [
            self::CallLater, self::Interested, self::VisitScheduled, self::TestDriveScheduled,
            self::FinanceRequired, self::ExchangeRequired, self::BookingExpected, self::Busy,
            self::NoAnswer, self::SwitchedOff,
        ], true);
    }

    /** Does this outcome force a terminal/lost status (and thus a lost reason)? */
    public function terminalStatus(): ?Status
    {
        return match ($this) {
            self::WrongNumber => Status::WrongNumber,
            self::NotInterested => Status::Lost,
            default => null,
        };
    }

    /** Status the lead should advance to when this outcome is logged (non-terminal). */
    public function advanceStatus(): ?Status
    {
        return match ($this) {
            self::Interested => Status::Interested,
            self::VisitScheduled => Status::VisitScheduled,
            self::TestDriveScheduled => Status::TestDrive,
            self::CallLater, self::Busy, self::NoAnswer, self::SwitchedOff => Status::FollowUp,
            self::BookingExpected => Status::Negotiation,
            self::Connected => Status::Contacted,
            default => null,
        };
    }

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    /** @return array<int, array{value: string, label: string, connected: bool}> */
    public static function options(): array
    {
        return array_map(fn (self $o) => ['value' => $o->value, 'label' => $o->label(), 'connected' => $o->isConnected()], self::cases());
    }
}
