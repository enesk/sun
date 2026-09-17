<?php

declare(strict_types=1);

namespace App\Services\Leads;

use Illuminate\Support\Facades\Log;

/**
 * Speichert eine Anfrage aus dem Leadsystem beim passenden Betrieb.
 *
 * Vertrag zwischen #31 (Empfang) und #32 (Speicherung): StoreCompanyInquiry
 * ruft record() im Tenant-Kontext mit dem vollstaendigen, bereits geprueften
 * Umschlag des Ereignisses 'lead.created' auf:
 *
 *   id, event, occurred_at, funnel{public_token, name},
 *   data.lead{id, uuid, state, score, result_key, created_at, contact, answers}
 *
 * answers kommt aus dem Leadsystem im Format der LeadResource (#30); die
 * Zuordnung zum Betrieb steht in answers.firmenprofil. record() muss
 * idempotent ueber data.lead.uuid sein, der Empfang filtert Doppelte nur
 * vorab ueber den Cache.
 *
 * Bis #32 steht, wird die Anfrage nur ohne Kontaktdaten protokolliert.
 */
class CompanyInquiryRecorder
{
    /**
     * @param  array<string, mixed>  $envelope
     */
    public function record(array $envelope): void
    {
        Log::info('Anfrage empfangen, Speicherung folgt mit #32', [
            'tenant' => tenant()?->getTenantKey(),
            'envelope_id' => $envelope['id'] ?? null,
            'lead_uuid' => data_get($envelope, 'data.lead.uuid'),
            'firmenprofil_id' => data_get($envelope, 'data.lead.answers.firmenprofil.id'),
        ]);
    }
}
