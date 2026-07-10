<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The 13 Malaysian states + 3 federal territories.
 *
 * Sabah, Sarawak (and Labuan, an island off Sabah) are East Malaysia; the solar
 * leasing programme covers Peninsular Malaysia only, so those are excluded during
 * qualification. That business rule lives in LeadQualificationService — this enum
 * only owns the closed set of valid state names (the input contract).
 */
enum MalaysianState: string
{
    // Peninsular Malaysia — states
    case Johor          = 'Johor';
    case Kedah          = 'Kedah';
    case Kelantan       = 'Kelantan';
    case Melaka         = 'Melaka';
    case NegeriSembilan = 'Negeri Sembilan';
    case Pahang         = 'Pahang';
    case Perak          = 'Perak';
    case Perlis         = 'Perlis';
    case PulauPinang    = 'Pulau Pinang';
    case Selangor       = 'Selangor';
    case Terengganu     = 'Terengganu';

    // Peninsular Malaysia — federal territories
    case KualaLumpur    = 'Kuala Lumpur';
    case Putrajaya      = 'Putrajaya';

    // East Malaysia — excluded from the programme
    case Sabah          = 'Sabah';
    case Sarawak        = 'Sarawak';
    case Labuan         = 'Labuan';
}
