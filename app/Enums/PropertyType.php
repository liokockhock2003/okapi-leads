<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Property type. Only `landed` and `commercial` qualify for solar leasing;
 * `condo` and `apartment` are disqualified (no sole roof ownership).
 */
enum PropertyType: string
{
    case Landed     = 'landed';
    case Condo      = 'condo';
    case Apartment  = 'apartment';
    case Commercial = 'commercial';
}
