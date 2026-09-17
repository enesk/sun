<?php

namespace App\Http\Controllers\Portal;

use App\Dto\Leads\LeadRequest;
use App\Enums\LeadRoute;
use App\Http\Controllers\Controller;
use App\Models\Portal\Company;
use App\Services\Leads\FunnelDefinitionClient;
use App\Services\Premium\LeadRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Exklusive Anfragen aus dem Profil-Dialog (#9), angesprochen von
 * lead-dialog.js. Antworten im Format der Funnel-API ({data: ...}, 422 mit
 * errors.answers.<key>), damit das Skript beide Wege gleich behandelt.
 *
 * route():  beim Oeffnen, ob der Dialog exklusiv laeuft.
 * store():  Absenden im exklusiven Modus; prueft erneut und meldet
 *           route=marketplace, wenn das Kontingent inzwischen weg ist. Das
 *           Skript uebergibt die Anfrage dann wie bisher an widileads.
 *
 * Bewusst ein Controller statt einer Livewire-Komponente: Schritte und
 * Verzweigungen des Dialogs steuert weiterhin das Leadsystem.
 */
class ExclusiveLeadController extends Controller
{
    public function __construct(
        private readonly LeadRoutingService $routing,
    ) {}

    public function route(int $company): JsonResponse
    {
        $company = Company::active()->findOrFail($company);

        return $this->routeResponse($this->routing->resolve($company));
    }

    public function store(Request $request, int $company, FunnelDefinitionClient $funnels): JsonResponse
    {
        $company = Company::active()->findOrFail($company);

        // Honigtopf gefuellt: Erfolg vortaeuschen, nichts speichern
        if (filled($request->input('website'))) {
            return $this->routeResponse(LeadRoute::Exclusive);
        }

        $token = tenant()?->leadFunnelToken();
        $funnel = $token === null ? null : $funnels->get($token);

        if ($funnel === null) {
            return $this->routeResponse(LeadRoute::Marketplace);
        }

        $answers = $request->input('answers');

        if (! is_array($answers)) {
            throw ValidationException::withMessages(['answers' => [__('validation.array', ['attribute' => 'answers'])]]);
        }

        $leadRequest = LeadRequest::fromFunnel($funnel, $answers);
        $lead = $this->routing->deliverExclusive($company, $leadRequest, route('portal.owner.inquiries.index'));

        return $this->routeResponse($lead === null ? LeadRoute::Marketplace : LeadRoute::Exclusive);
    }

    private function routeResponse(LeadRoute $route): JsonResponse
    {
        return response()
            ->json(['data' => ['route' => $route->value]])
            ->header('Cache-Control', 'no-store, private');
    }
}
