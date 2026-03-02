<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Company;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function __construct(
        private TrackingService $trackingService
    ) {}

    /**
     * POST /tracking/contact-click
     * Wird per JS-Fetch aus dem Frontend aufgerufen wenn Thomas auf Tel/E-Mail/Web/Maps klickt.
     */
    public function contactClick(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
            'contact_type' => ['required', 'string', 'in:phone,email,website,map'],
        ]);

        // Company existiert und ist aktiv?
        $exists = Company::where('id', $validated['company_id'])
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $this->trackingService->trackContactClick(
            $validated['company_id'],
            $validated['contact_type'],
            $request
        );

        return response()->json(['ok' => true]);
    }
}
