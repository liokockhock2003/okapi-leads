<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The lifecycle status of a lead, resolved by LeadQualificationService.
 *
 *  - qualified     → every rule passed
 *  - under_review  → only the bill is borderline (RM150–199); a human should look
 *  - disqualified  → any hard rule failed
 *
 * HasLabel / HasColor drive the Filament admin display (badge text + colour).
 */
enum LeadStatus: string implements HasColor, HasLabel
{
    case Qualified    = 'qualified';
    case UnderReview  = 'under_review';
    case Disqualified = 'disqualified';

    public function getLabel(): string
    {
        return match ($this) {
            self::Qualified    => 'Qualified',
            self::UnderReview  => 'Under review',
            self::Disqualified => 'Disqualified',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Qualified    => 'success',
            self::UnderReview  => 'warning',
            self::Disqualified => 'danger',
        };
    }
}
