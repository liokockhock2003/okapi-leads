<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Property type. Only `landed` and `commercial` qualify for solar leasing;
 * `condo` and `apartment` are disqualified (no sole roof ownership).
 */
enum PropertyType: string implements HasLabel
{
    case Landed     = 'landed';
    case Condo      = 'condo';
    case Apartment  = 'apartment';
    case Commercial = 'commercial';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
