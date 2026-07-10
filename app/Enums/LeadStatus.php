<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle status of a lead, resolved by LeadQualificationService.
 *
 *  - qualified     → every rule passed
 *  - under_review  → only the bill is borderline (RM150–199); a human should look
 *  - disqualified  → any hard rule failed
 */
enum LeadStatus: string
{
    case Qualified    = 'qualified';
    case UnderReview  = 'under_review';
    case Disqualified = 'disqualified';
}
