<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeadStatus;
use App\Enums\MalaysianState;
use App\Enums\PropertyType;
use App\Enums\RoofType;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Lead extends Model
{
    use LogsActivity;

    protected $fillable = [
        'customer_name',
        'email',
        'phone',
        'monthly_bill_rm',
        'property_type',
        'roof_type',
        'state',
        'status',
    ];

    protected $casts = [
        'monthly_bill_rm' => 'integer',
        'property_type'   => PropertyType::class,
        'roof_type'       => RoofType::class,
        'state'           => MalaysianState::class,
        'status'          => LeadStatus::class,
    ];

    /**
     * Requirement 6 is about *changes*, not creation — so we audit `updated` and
     * `deleted` only. Lead creation (API ingestion) is deliberately NOT logged.
     * In this app leads are only ever changed via the Filament admin, so every
     * audit row carries an admin causer.
     */
    protected static $recordEvents = ['updated', 'deleted'];

    /**
     * Audit trail (requirement 6): log every customer-data change and every status
     * change — field, old → new, when — into the activity_log table. `logOnlyDirty`
     * records only the attributes that actually changed.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('lead')
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
