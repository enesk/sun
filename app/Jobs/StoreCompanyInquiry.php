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
use Illuminate\Support\Str;

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
 * Der Betrieb steht in der Antwort `firmenprofil`: Profil-URL (Text), Objekt
 * {id, slug} oder Slug, siehe companyReference(). Gespeichert
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

        $answers = $this->answers();
        $reference = self::companyReference($answers);
        $company = $this->resolveCompany($reference);

        if ($company === null) {
            // Bewusst ohne Kontaktdaten und ohne Antworten; firmenprofil ist
            // keine personenbezogene Angabe.
            Log::warning('Anfrage keinem Betrieb zuzuordnen, nicht gespeichert.', [
                'lead_uuid' => $uuid,
                'firmenprofil_id' => $reference['id'] ?? null,
                'firmenprofil_slug' => $reference['slug'] ?? $reference['plain_slug'] ?? null,
                'firmenprofil_raw' => Str::limit($reference['raw'] ?? '', 200),
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
     * Betrieb, bei dem die Anfrage landen wuerde; fuer Trockenlaeufe.
     */
    public function company(): ?Company
    {
        return $this->resolveCompany(self::companyReference($this->answers()));
    }

    /**
     * @return array<int, mixed>
     */
    private function answers(): array
    {
        return is_array($this->lead['answers'] ?? null) ? $this->lead['answers'] : [];
    }

    /**
     * Verweis auf den Betrieb aus der Antwort `firmenprofil`. Der Dialog sendet
     * heute die Profil-URL als Text (#38), aeltere Zustellungen ein Objekt
     * {id, slug}; ein reiner Slug ist der Rueckfall des Dialogs.
     *
     * @param  array<int, mixed>  $answers
     * @return array{id: int|null, slug: string|null, plain_slug: string|null, raw: string}|null
     */
    private static function companyReference(array $answers): ?array
    {
        foreach ($answers as $answer) {
            if (! is_array($answer) || ($answer['field_key'] ?? null) !== self::COMPANY_FIELD_KEY) {
                continue;
            }

            $value = $answer['value'] ?? null;

            if (is_string($value) && is_array($decoded = json_decode($value, true))) {
                $value = $decoded;
            }

            if (is_array($value)) {
                $raw = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                if (is_numeric($value['id'] ?? null) && is_string($value['slug'] ?? null) && $value['slug'] !== '') {
                    return ['id' => (int) $value['id'], 'slug' => $value['slug'], 'plain_slug' => null, 'raw' => $raw];
                }

                $value = $value['url'] ?? $value['slug'] ?? null;

                if (! is_string($value)) {
                    return ['id' => null, 'slug' => null, 'plain_slug' => null, 'raw' => $raw];
                }
            }

            return is_scalar($value) ? self::referenceFromText((string) $value) : null;
        }

        return null;
    }

    /**
     * Profil-URL (`/{id}-{slug}` oder `/{stadt}/{id}-{slug}`) oder Slug.
     *
     * @return array{id: int|null, slug: string|null, plain_slug: string|null, raw: string}
     */
    private static function referenceFromText(string $text): array
    {
        $text = trim($text);
        $isUrl = str_contains($text, '://') || str_starts_with($text, '/');
        $segment = $text;

        if ($isUrl) {
            $path = rtrim((string) parse_url($text, PHP_URL_PATH), '/');
            $segment = Str::afterLast($path, '/');
        }

        $segment = rawurldecode($segment);
        $id = null;
        $slug = null;

        if (preg_match('/^(\d+)-(.+)$/', $segment, $matches) === 1) {
            $id = (int) $matches[1];
            $slug = $matches[2];
        }

        return [
            'id' => $id,
            'slug' => $slug,
            // Ein Slug ohne ID kann selbst mit Ziffern beginnen ("24-stunden-elektro").
            'plain_slug' => ! $isUrl && $segment !== '' ? $segment : null,
            'raw' => $text,
        ];
    }

    /**
     * Die ID findet den Betrieb, der Slug bestaetigt ihn. So landet eine
     * Anfrage nicht bei einem Betrieb, der zufaellig dieselbe ID traegt.
     * Ein Slug ohne ID gilt nur, wenn genau ein Betrieb ihn traegt.
     *
     * @param  array{id: int|null, slug: string|null, plain_slug: string|null, raw: string}|null  $reference
     */
    private function resolveCompany(?array $reference): ?Company
    {
        if ($reference === null) {
            return null;
        }

        if ($reference['id'] !== null && $reference['slug'] !== null) {
            $company = Company::find($reference['id']);

            if ($company !== null && $company->slug === $reference['slug']) {
                return $company;
            }
        }

        if ($reference['plain_slug'] === null) {
            return null;
        }

        $matches = Company::query()->where('slug', $reference['plain_slug'])->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
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
