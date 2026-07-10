<?php

declare(strict_types=1);

use App\Http\Controllers\LeadIngestionController;
use Illuminate\Support\Facades\Route;

// Public lead ingestion. Registered with the `api` group (stateless, no CSRF)
// and the `/api` prefix → full path: POST /api/leads.
Route::post('/leads', [LeadIngestionController::class, 'store']);
