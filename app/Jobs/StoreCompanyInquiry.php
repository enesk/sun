<?php

namespace App\Jobs;

use App\Events\Company\CompanyInquiryReceived;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Legt eine Anfrage aus dem Leadsystem am Betrieb ab (#32).
 *
 * Einziger Speicherweg fuer Anfragen: LeadWebhookController (#31) dispatcht
 * den Job nach der Signaturpruefung im Tenant-Kontext (QueueTenancyBootstrapper
 * traegt den Tenant mit) und uebergibt das Objekt `data.lead` aus dem Webhook
 * `lead.created` unveraendert:
 *
 *   uuid, score, result_key, created_at,
 *   contact {name, first_name, last_name, email, phone, ...},
 *   answers [{field_key, label, value, value_label}, ...]
 *
 * Der Betrieb steht in der Antwort `firmenprofil` (id, slug, ...). Gespeichert
 * wird unabhaengig davon, ob das Profil einen Inhaber hat. Doppelte
 * Zustellungen erkennt der Job an `lead_uuid`, das Event geht nur einmal raus.
 */
class StoreCompanyInquiry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const COMPANY_FIELD_KEY = 'firmenprofil';

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    /**
     * @param  array<string, mixed>  $lead
     */
    public function __construct(
        public array $lead,
    ) {}

    public function handle(): void
    {
        $uuid = trim((string) ($this->lead['uuid'] ?? ''));

        if ($uuid === '') {
            Log::warning('Anfrage ohne Lead-UUID verworfen.', ['lead_id' => $this->lead['id'] ?? null]);

            return;
        }

        if (CompanyInquiry::withTrashed()->where('lead_uuid', $uuid)->exists()) {
            return;
        }

        $answers = is_array($this->lead['answers'] ?? null) ? $this->lead['answers'] : [];
        $reference = $this->companyReference($answers);
        $company = $this->resolveCompany($reference);

        if ($company === null) {
            // Bewusst ohne Kontaktdaten und ohne Antworten.
            Log::warning('Anfrage keinem Betrieb zuzuordnen, nicht gespeichert.', [
                'lead_uuid' => $uuid,
                'firmenprofil_id' => $reference['id'] ?? null,
                'firmenprofil_slug' => $reference['slug'] ?? null,
            ]);

            return;
        }

        $contact = is_array($this->lead['contact'] ?? null) ? $this->lead['contact'] : [];

        try {
            $inquiry = CompanyInquiry::create([
                'company_id' => $company->id,
                'lead_uuid' => $uuid,
                'answers' => $this->mapAnswers($answers),
                'contact_name' => $this->contactName($contact),
                'contact_email' => $this->stringOrNull($contact['email'] ?? null),
                'contact_phone' => $this->stringOrNull($contact['phone'] ?? null),
                'score' => is_numeric($this->lead['score'] ?? null) ? (int) $this->lead['score'] : null,
                'result_key' => $this->stringOrNull($this->lead['result_key'] ?? null),
                'received_at' => $this->receivedAt(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Parallele Zustellung derselben Anfrage: die andere hat gewonnen.
            return;
        }

        CompanyInquiryReceived::dispatch($inquiry);
    }

    /**
     * @param  array<int, mixed>  $answers
     * @return array<string, mixed>|null
     */
    private function companyReference(array $answers): ?array
    {
        foreach ($answers as $answer) {
            if (! is_array($answer) || ($answer['field_key'] ?? null) !== self::COMPANY_FIELD_KEY) {
                continue;
            }

            $value = $answer['value'] ?? null;

            if (is_string($value)) {
                $value = json_decode($value, true);
            }

            return is_array($value) ? $value : null;
        }

        return null;
    }

    /**
     * Die ID findet den Betrieb, der Slug bestaetigt ihn. So landet eine
     * Anfrage nicht bei einem Betrieb, der zufaellig dieselbe ID traegt.
     *
     * @param  array<string, mixed>|null  $reference
     */
    private function resolveCompany(?array $reference): ?Company
    {
        $id = $reference['id'] ?? null;
        $slug = $reference['slug'] ?? null;

        if (! is_numeric($id) || ! is_string($slug) || $slug === '') {
            return null;
        }

        $company = Company::find((int) $id);

        return $company !== null && $company->slug === $slug ? $company : null;
    }

    /**
     * @param  array<int, mixed>  $answers
     * @return list<array{key: string, label: string, value: mixed, value_label: string|null}>
     */
    private function mapAnswers(array $answers): array
    {
        $mapped = [];

        foreach ($answers as $answer) {
            if (! is_array($answer)) {
                continue;
            }

            $key = (string) ($answer['field_key'] ?? '');

            if ($key === '' || $key === self::COMPANY_FIELD_KEY) {
                continue;
            }

            $mapped[] = [
                'key' => $key,
                'label' => (string) ($answer['label'] ?? $key),
                'value' => $answer['value'] ?? null,
                'value_label' => $this->stringOrNull($answer['value_label'] ?? null),
            ];
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function contactName(array $contact): ?string
    {
        $name = $this->stringOrNull($contact['name'] ?? null);

        if ($name !== null) {
            return $name;
        }

        $parts = array_filter([
            $this->stringOrNull($contact['first_name'] ?? null),
            $this->stringOrNull($contact['last_name'] ?? null),
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }

    private function receivedAt(): Carbon
    {
        $createdAt = $this->lead['created_at'] ?? null;

        if (! is_string($createdAt) || $createdAt === '') {
            return now();
        }

        try {
            return Carbon::parse($createdAt)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return now();
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
