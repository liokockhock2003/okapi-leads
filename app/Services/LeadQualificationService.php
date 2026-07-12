<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\MalaysianState;
use App\Enums\PropertyType;
use App\Enums\RoofType;

class LeadQualificationService
{
    private const QUALIFY_BILL = 200;

    private const REVIEW_FLOOR = 150;

    public function resolve(int $bill, PropertyType $property, RoofType $roofType, MalaysianState $state): LeadStatus
    {
        // Property must be Landed or Commercial
        if (! in_array($property, [PropertyType::Landed, PropertyType::Commercial], true)) {
            return LeadStatus::Disqualified;
        }

        // Roof: if Roof is Flat, return Disqualified
        if ($roofType === RoofType::Flat) {
            return LeadStatus::Disqualified;
        }

        // State: if state is Sabah, Sarawak or Labuan, return Disqualified
        if (in_array($state, [MalaysianState::Sarawak, MalaysianState::Sabah, MalaysianState::Labuan], true)) {
            return LeadStatus::Disqualified;
        }

        // Bill floor: if bill < REVIEW_FLOOR, return Disqualified
        if ($bill < self::REVIEW_FLOOR) {
            return LeadStatus::Disqualified;
        }

        // All hard rules pass and bill >= 150
        if ($bill >= self::QUALIFY_BILL) {
            return LeadStatus::Qualified;
        } else {
            return LeadStatus::UnderReview;
        }
    }
}
