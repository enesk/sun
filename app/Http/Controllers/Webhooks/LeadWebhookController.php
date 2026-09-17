<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\StoreCompanyInquiry;
use App\Models\Tenant;
use App\Services\Leads\LeadWebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Empfang des Leadsystem-Webhooks 'lead.created' je Portal (#31).
 *
 * Antworten: 404 ohne Secret, 401 bei fehlender/falscher Signatur oder zu
 * altem Zeitstempel, 400 bei unlesbarem Rumpf, 204 fuer andere Ereignisse,
 * 403 bei fremdem Funnel, 422 ohne data.lead.uuid, sonst 202 — auch bei einer
 * doppelten Zustellung, damit das Leadsystem nicht erneut sendet.
 * Protokolliert wird nie der Rumpf und nie Kontaktdaten.
 */
class LeadWebhookController extends Controller
{
    private const HANDLED_EVENT = 'lead.created';

    /** Wie lange eine Lead-Kennung als bereits angenommen gilt (Sekunden). */
    private const DEDUPLICATE_FOR = 60 * 60 * 24 * 7;

    public function __invoke(Request $request, LeadWebhookSignature $signature): Response|JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();
        $secret = $tenant?->leadWebhookSecret();

        if ($tenant === null || $secret === null) {
            return $this->reject(404, 'kein Secret');
        }

        $payload = $request->getContent();
        $timestamp = (string) $request->header(LeadWebhookSignature::TIMESTAMP_HEADER, '');
        $given = (string) $request->header(LeadWebhookSignature::SIGNATURE_HEADER, '');

        if (! ctype_digit($timestamp) || $given === '') {
            return $this->reject(401, 'Signatur fehlt');
        }

        if (! $signature->isFresh((int) $timestamp)) {
            return $this->reject(401, 'Zeitstempel ausserhalb des Fensters');
        }

        if (! $signature->verify($secret, $payload, (int) $timestamp, $given)) {
            return $this->reject(401, 'Signatur ungueltig');
        }

        $envelope = json_decode($payload, true);

        if (! is_array($envelope)) {
            return $this->reject(400, 'Rumpf ist kein JSON');
        }

        $event = (string) ($envelope['event'] ?? $request->header(LeadWebhookSignature::EVENT_HEADER, ''));

        if ($event !== self::HANDLED_EVENT) {
            return response()->noContent();
        }

        $funnelToken = (string) data_get($envelope, 'funnel.public_token', '');
        $ownToken = $tenant->leadFunnelToken();

        if ($ownToken === null || ! hash_equals($ownToken, $funnelToken)) {
            return $this->reject(403, 'fremder Funnel', ['envelope_id' => $envelope['id'] ?? null]);
        }

        $lead = data_get($envelope, 'data.lead');
        $key = is_array($lead) ? trim((string) ($lead['uuid'] ?? '')) : '';

        if ($key === '') {
            return $this->reject(422, 'Lead oder Lead-UUID fehlt', ['envelope_id' => $envelope['id'] ?? null]);
        }

        $context = [
            'tenant' => $tenant->getTenantKey(),
            'envelope_id' => $envelope['id'] ?? null,
            'lead_id' => data_get($envelope, 'data.lead.id'),
            'lead_uuid' => data_get($envelope, 'data.lead.uuid'),
        ];

        if (! Cache::add("lead-webhook:{$tenant->getTenantKey()}:{$key}", true, self::DEDUPLICATE_FOR)) {
            Log::info('Lead-Webhook doppelt zugestellt, ignoriert', $context);

            return response()->json(['status' => 'duplicate'], 202);
        }

        StoreCompanyInquiry::dispatch($lead);
        Log::info('Lead-Webhook angenommen', $context);

        return response()->json(['status' => 'accepted'], 202);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function reject(int $status, string $reason, array $context = []): Response
    {
        Log::warning("Lead-Webhook abgelehnt: {$reason}", [
            'tenant' => tenant()?->getTenantKey(),
            'status' => $status,
            'ip' => request()->ip(),
            ...$context,
        ]);

        return response()->noContent($status);
    }
}
