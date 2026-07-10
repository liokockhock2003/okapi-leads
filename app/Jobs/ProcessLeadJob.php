<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessLeadJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<string, mixed> $data Validated lead payload from StoreLeadRequest.
     */
    public function __construct(public readonly array $data)
    {
    }

    /**
     * Orchestrates ingestion. Fleshed out over the next steps:
     *   1. resolve status via LeadQualificationService   (step 4)
     *   2. persist the Lead inside a DB transaction        (step 5)
     *   3. catch unique-violation SQLSTATE 23505 → skip     (step 5, dedup)
     *   4. send internal + customer mailables               (step 6)
     */
    public function handle(): void
    {
        // TODO(steps 4-6): qualify → persist (txn) → dedup catch → notify.
    }
}
