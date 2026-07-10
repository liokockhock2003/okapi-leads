<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadRequest;
use App\Jobs\ProcessLeadJob;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LeadIngestionController extends Controller
{
    /**
     * Accept a lead and hand ALL processing to a background job, returning
     * 202 Accepted immediately so bursts never block on qualify/persist/mail.
     *
     * Validation already ran (StoreLeadRequest) before this method is entered;
     * no business logic lives here — the controller stays thin.
     */
    public function store(StoreLeadRequest $request): JsonResponse
    {
        ProcessLeadJob::dispatch($request->validated());

        return response()->json(
            ['message' => 'Lead received and is being processed.'],
            Response::HTTP_ACCEPTED, // 202
        );
    }
}
