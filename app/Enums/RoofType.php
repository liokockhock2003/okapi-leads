<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Roof type. `flat` roofs are disqualified (poor panel angle); the rest are fine.
 */
enum RoofType: string implements HasLabel
{
    case Tile     = 'tile';
    case Metal    = 'metal';
    case Flat     = 'flat';
    case Concrete = 'concrete';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
