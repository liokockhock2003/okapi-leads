<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roof type. `flat` roofs are disqualified (poor panel angle); the rest are fine.
 */
enum RoofType: string
{
    case Tile     = 'tile';
    case Metal    = 'metal';
    case Flat     = 'flat';
    case Concrete = 'concrete';
}
