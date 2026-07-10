<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MalaysianState;
use App\Enums\PropertyType;
use App\Enums\RoofType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the inbound shape of a lead (the API's input contract).
 *
 * Note: there is intentionally NO `unique` rule here for duplicate detection.
 * Ingestion is async (we return 202 and insert later in ProcessLeadJob), so a
 * read-then-write uniqueness check would be a TOCTOU race under bursts. Dedup is
 * enforced by the DB unique constraint + a 23505 catch in the job — not here.
 */
class StoreLeadRequest extends FormRequest
{
    /**
     * Public ingestion endpoint — no auth gate.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_name'   => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255'],
            'phone'           => ['required', 'string', 'max:20'],
            'monthly_bill_rm' => ['required', 'integer', 'min:0'],
            'property_type'   => ['required', Rule::enum(PropertyType::class)],
            'roof_type'       => ['required', Rule::enum(RoofType::class)],
            'state'           => ['required', Rule::enum(MalaysianState::class)],
        ];
    }
}
