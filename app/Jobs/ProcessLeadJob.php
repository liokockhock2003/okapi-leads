<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MalaysianState;
use App\Enums\PropertyType;
use App\Enums\RoofType;
use App\Mail\LeadStatusCustomer;
use App\Mail\LeadStatusInternal;
use App\Models\Lead;
use App\Services\LeadQualificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessLeadJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data  Validated lead payload from StoreLeadRequest.
     */
    public function __construct(public readonly array $data) {}

    /**
     * Orchestrates ingestion:
     *   1. resolve status via LeadQualificationService
     *   2. persist the Lead inside a DB transaction
     *   3. catch unique-violation SQLSTATE 23505 → duplicate, skip
     *   4. send internal + customer mailables
     */
    public function handle(LeadQualificationService $qualifier): void
    {
        // 1. Resolve the lead's status from the business rules.
        $status = $qualifier->resolve(
            (int) $this->data['monthly_bill_rm'],
            PropertyType::from($this->data['property_type']),
            RoofType::from($this->data['roof_type']),
            MalaysianState::from($this->data['state']),
        );

        // 2. Persist. The transaction keeps the leads insert and its activity_log
        //    audit row (written by the model's LogsActivity trait) atomic. The DB
        //    unique constraint is our only atomic dedup guarantee, so we attempt
        //    the insert and handle the violation rather than pre-checking.
        try {
            $lead = DB::transaction(fn (): Lead => Lead::create([
                'customer_name' => $this->data['customer_name'],
                'email' => $this->data['email'],
                'phone' => $this->data['phone'],
                'monthly_bill_rm' => (int) $this->data['monthly_bill_rm'],
                'property_type' => PropertyType::from($this->data['property_type']),
                'roof_type' => RoofType::from($this->data['roof_type']),
                'state' => MalaysianState::from($this->data['state']),
                'status' => $status,
            ]));
        } catch (QueryException $e) {
            // Postgres SQLSTATE 23505 = unique_violation → duplicate lead.
            // Skip quietly (no emails); let any other DB error bubble up to retry.
            if ($e->getCode() === '23505') {
                Log::info('Duplicate lead ignored.', [
                    'email' => $this->data['email'],
                    'phone' => $this->data['phone'],
                ]);

                return;
            }

            throw $e;
        }

        // 3. Notify the internal team and the customer. The `log` mail driver
        //    writes both messages to storage/logs/laravel.log.
        Mail::to(config('mail.internal_recipient'))->send(new LeadStatusInternal($lead));
        Mail::to($lead->email)->send(new LeadStatusCustomer($lead));
    }
}
