<?php

declare(strict_types=1);

namespace App\ProfileRequests\Ai;

use App\Models\Tenant;
use App\ProfileRequests\Actions\BuildChangeSet;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Models\ProfileRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Klassifikation einer eingegangenen Mail (SUN-RQ #6).
 *
 * Ein Aufruf der Messages API (bestehende Anthropic-Integration,
 * config/ai.php fuer Schluessel und Basis-URL, Modell aus
 * `profile-requests.claude_model`). Der Prompt enthaelt:
 *
 *  * den Mailtext (gekuerzt auf `classification.max_text_chars`),
 *  * die Liste der Portale mit ihren Domains,
 *  * die Firmenkandidaten des erkannten Portals ({@see CompanyCandidateFinder}).
 *
 * Geantwortet wird **ausschliesslich** als JSON-Objekt. Die KI waehlt
 * `company_id` nur aus der Kandidatenliste; eine ID, die dort nicht vorkommt,
 * wird hier verworfen — das Modell kann keine Zuordnung erfinden, die wir
 * ihm nicht angeboten haben.
 *
 * Ist die Antwort kein brauchbares JSON, ist die Klassifikation **nicht**
 * gescheitert, sondern liefert {@see ClassificationResult::fallback()} mit
 * `type=other`: die Anfrage ist in der Inbox, Enes liest sie selbst. Ein
 * Fehler im Modell darf keine Mail verschlucken.
 *
 * Was dieser Schritt nie tut: ins Profil schreiben, eine Mail versenden, eine
 * Anfrage erledigen. Er liefert einen Vorschlag.
 */
class RequestClassifier
{
    /**
     * Feldschluessel, die ein Diff tragen darf. Sie stammen aus
     * {@see BuildChangeSet} und der Whitelist in der Config — ein Feld, das
     * das Panel nicht anzeigen kann, braucht niemand vorgeschlagen.
     *
     * @var list<string>
     */
    private const DIFF_FIELDS = [
        'name',
        'street',
        'house_no',
        'zipcode',
        'city',
        'tel',
        'email',
        'website',
        'description',
        'opening_hours',
        'services',
        'social_facebook',
        'social_instagram',
        'social_linkedin',
        'social_youtube',
        'video_url',
    ];

    public function __construct(
        private readonly CompanyCandidateFinder $candidateFinder,
    ) {}

    public function classify(ProfileRequest $request): ClassificationResult
    {
        $candidates = $this->candidateFinder->forRequest($request);
        $portals = $this->portals();

        $config = (array) config('ai.providers.anthropic');
        $apiKey = (string) ($config['api_key'] ?? '');

        if ($apiKey === '') {
            // Kein Schluessel ist ein Betriebszustand, kein Absturz: die
            // Anfrage geht ohne Vorschlag in die Inbox.
            return ClassificationResult::fallback('kein_api_schluessel', $candidates, $request->tenant_id === null ? null : (int) $request->tenant_id);
        }

        try {
            $raw = $this->call($apiKey, $config, $this->prompt($request, $portals, $candidates));
        } catch (Throwable $e) {
            Log::warning('profile-requests: Klassifikation nicht moeglich', [
                'request_id' => $request->id,
                'exception' => $e->getMessage(),
            ]);

            return ClassificationResult::fallback('api_fehler', $candidates, $request->tenant_id === null ? null : (int) $request->tenant_id);
        }

        return $this->parse($raw, $request, $candidates, $portals);
    }

    /**
     * Portale mit Domain — die Liste, aus der die KI das Zielportal waehlt.
     *
     * @return array<int, array{id: int, name: string, domain: string}>
     */
    public function portals(): array
    {
        return Tenant::query()
            ->get(Tenant::getCustomColumns())
            ->filter(fn (Tenant $tenant): bool => $tenant->isDelivered())
            ->mapWithKeys(fn (Tenant $tenant): array => [
                (int) $tenant->getKey() => [
                    'id' => (int) $tenant->getKey(),
                    'name' => (string) $tenant->name,
                    'domain' => (string) ($tenant->getAttribute('domain') ?? ''),
                ],
            ])
            ->all();
    }

    /**
     * @param  array<int, array{id: int, name: string, domain: string}>  $portals
     * @param  array<int, array<string, mixed>>  $candidates
     */
    public function prompt(ProfileRequest $request, array $portals, array $candidates): string
    {
        $maxChars = (int) config('profile-requests.classification.max_text_chars', 8000);
        $text = Str::limit((string) $request->message, $maxChars, ' […]');

        $portalLines = [];

        foreach ($portals as $portal) {
            $portalLines[] = "- id {$portal['id']}: {$portal['name']} ({$portal['domain']})";
        }

        $candidateLines = [];

        foreach ($candidates as $candidate) {
            $candidateLines[] = sprintf(
                '- company_id %d: %s, %s, %s | Tel %s | E-Mail %s | Web %s | Treffer: %s',
                $candidate['id'],
                $candidate['name'],
                $candidate['address'] !== '' ? $candidate['address'] : 'ohne Adresse',
                $candidate['city'] !== '' ? $candidate['city'] : 'ohne Ort',
                $candidate['tel'] !== '' ? $candidate['tel'] : '—',
                $candidate['email'] !== '' ? $candidate['email'] : '—',
                $candidate['website'] !== '' ? $candidate['website'] : '—',
                implode('/', (array) $candidate['matched_on']),
            );
        }

        $tenantHint = $request->tenant_id === null
            ? 'Das Zielportal ist unbekannt. Waehle es aus der Portalliste, wenn der Text es klar benennt, sonst null.'
            : 'Das Zielportal steht aus der Empfaengeradresse fest: tenant_id '.$request->tenant_id.'. Uebernimm diesen Wert.';

        $candidateHint = $candidates === []
            ? 'Es gibt keine Kandidaten. Setze company_id auf null.'
            : 'Waehle company_id NUR aus der folgenden Liste. Passt keiner, setze null — erfinde nie eine ID.';

        $fields = implode(', ', self::DIFF_FIELDS);

        return <<<PROMPT
        Du ordnest eine E-Mail ein, die ein Betrieb an ein deutsches Branchenportal geschrieben hat.
        Antworte ausschliesslich mit einem JSON-Objekt, ohne Text davor oder danach, ohne Codeblock.

        ## Portale
        {$tenantHint}

        {$this->joinOrNone($portalLines)}

        ## Firmenkandidaten
        {$candidateHint}

        {$this->joinOrNone($candidateLines)}

        ## Absender
        Name: {$request->requester_name}
        E-Mail: {$request->requester_email}

        ## Mailtext
        ---
        {$text}
        ---

        ## Aufgabe
        1. type: "change" (Daten des Eintrags aendern), "delete" (Eintrag loeschen), "claim" (Eintrag uebernehmen/Zugang anfordern) oder "other" (alles sonst: Rueckfrage, Werbung, Bewertungsstreit).
        2. tenant_id und company_id mit einer gemeinsamen confidence zwischen 0 und 1. Die confidence bewertet NUR die Zuordnung zum Betrieb, nicht den Typ. Unter 0,7 heisst: lieber unsicher melden als falsch zuordnen.
        3. Bei type "change": fuer jedes gewuenschte Feld eine Zeile in "changes" mit field, old, new. Erlaubte field-Werte: {$fields}. "old" ist der Wert, den die Mail als bisherigen nennt oder der aus dem Kandidaten hervorgeht — unbekannt heisst null. Nenne nur Felder, die die Mail wirklich aendert.
        4. Bei type "delete": "delete_reason" mit dem genannten Grund in einem kurzen Satz.
        5. "summary": ein bis zwei Saetze, was der Absender will. Deutsch.
        6. "reply_draft": Entwurf einer Antwort an den Betrieb, deutsche Sie-Form, hoeflich, ohne Platzhalter in spitzen Klammern, ohne Anrede-Rateversuch wenn der Name fehlt ("Guten Tag,"). Sage nur zu, was ohne Pruefung zugesagt werden kann; verspreche keinen Termin.

        ## Antwortformat
        {"type":"change|delete|claim|other","tenant_id":123|null,"company_id":456|null,"confidence":0.0,"summary":"...","reply_draft":"...","delete_reason":null,"changes":[{"field":"tel","old":"0221 1234","new":"0721 98765"}]}
        PROMPT;
    }

    /**
     * @param  list<string>  $lines
     */
    private function joinOrNone(array $lines): string
    {
        return $lines === [] ? '(keine)' : implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws \RuntimeException
     */
    private function call(string $apiKey, array $config, string $prompt): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout((int) config('profile-requests.classification.timeout', 60))
            ->retry(2, 2000, throw: false)
            ->post(rtrim((string) ($config['base_url'] ?? 'https://api.anthropic.com/v1'), '/').'/messages', [
                'model' => (string) config('profile-requests.claude_model'),
                'max_tokens' => (int) config('profile-requests.classification.max_tokens', 2000),
                'system' => 'Du bist ein praeziser Klassifikator fuer Anfragen an Branchenportale. '
                    .'Du antwortest ausschliesslich mit einem JSON-Objekt und nie mit erfundenen IDs.',
                // Kein vorgegebener Antwortanfang ('{' als assistant-Turn):
                // die aktuellen Modelle lehnen Prefill mit 400 ab
                // ("does not support assistant message prefill"). Begleittext
                // faengt stattdessen der Parser ab.
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Anthropic API {$response->status()}: ".Str::limit($response->body(), 300));
        }

        // Den ersten Textblock nehmen, nicht blind den ersten Block: ein
        // Modell mit Denkschritten stellt davor einen `thinking`-Block.
        foreach ((array) $response->json('content', []) as $block) {
            if (($block['type'] ?? '') === 'text' && is_string($block['text'] ?? null)) {
                return (string) $block['text'];
            }
        }

        return '';
    }

    /**
     * Antwort einlesen. Jeder Wert wird gegen die Vorgaben geprueft: Typ aus
     * dem Enum, tenant_id aus der Portalliste, company_id aus der
     * Kandidatenliste, Felder aus {@see self::DIFF_FIELDS}.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @param  array<int, array{id: int, name: string, domain: string}>  $portals
     */
    public function parse(string $raw, ProfileRequest $request, array $candidates, array $portals): ClassificationResult
    {
        $json = $this->decode($raw);
        $requestTenantId = $request->tenant_id === null ? null : (int) $request->tenant_id;

        if ($json === null) {
            return ClassificationResult::fallback('antwort_kein_json', $candidates, $requestTenantId);
        }

        $type = RequestType::tryFrom(Str::lower(trim((string) ($json['type'] ?? '')))) ?? RequestType::OTHER;

        // Steht das Portal aus den Kopfzeilen fest, bleibt es dabei — eine
        // Empfaengeradresse ist harte Information, die KI nur eine Vermutung.
        $tenantId = $requestTenantId;

        if ($tenantId === null) {
            $vorschlag = $this->intOrNull($json['tenant_id'] ?? null);
            $tenantId = $vorschlag !== null && isset($portals[$vorschlag]) ? $vorschlag : null;
        }

        $confidence = $this->confidence($json['confidence'] ?? null);

        $companyId = $this->intOrNull($json['company_id'] ?? null);
        $allowedIds = array_map(fn (array $candidate): int => (int) $candidate['id'], $candidates);

        if ($companyId !== null && ! in_array($companyId, $allowedIds, true)) {
            Log::info('profile-requests: KI nennt eine company_id ausserhalb der Kandidaten', [
                'request_id' => $request->id,
                'company_id' => $companyId,
            ]);

            $companyId = null;
            $confidence = 0.0;
        }

        return new ClassificationResult(
            type: $type,
            tenantId: $tenantId,
            companyId: $companyId,
            confidence: $confidence,
            summary: $this->text($json['summary'] ?? null, 2000),
            replyDraft: $this->text($json['reply_draft'] ?? null, 8000),
            deleteReason: $type === RequestType::DELETE ? $this->text($json['delete_reason'] ?? null, 500) : null,
            changes: $type === RequestType::CHANGE ? $this->changes($json['changes'] ?? null) : [],
            candidates: $candidates,
            fallback: false,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $raw): ?array
    {
        $text = trim($raw);

        if ($text === '') {
            return null;
        }

        // Codeblock oder Begleittext trotz Vorgabe: vom ersten { bis zum
        // letzten }.
        $text = (string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Diff-Vorschlag. Jede Zeile braucht ein erlaubtes Feld und einen neuen
     * Wert; ein Vorschlag „aendere Telefon auf nichts" ist keine Aenderung,
     * sondern ein Missverstaendnis.
     *
     * @return array<int, array{field: string, old: mixed, new: mixed}>
     */
    private function changes(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $changes = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $field = Str::lower(trim((string) ($row['field'] ?? '')));

            if (! in_array($field, self::DIFF_FIELDS, true) || isset($changes[$field])) {
                continue;
            }

            $new = $row['new'] ?? null;

            if ($new === null || (is_string($new) && trim($new) === '')) {
                continue;
            }

            $changes[$field] = [
                'field' => $field,
                'old' => $this->value($row['old'] ?? null),
                'new' => $this->value($new),
            ];
        }

        return array_values($changes);
    }

    /**
     * Werte aus einer fremden Antwort: Skalare und flache Strukturen, nichts
     * Tieferes. `old_value`/`new_value` sind JSON-Spalten, aber eine
     * verschachtelte Phantasiestruktur kann das Panel nicht anzeigen.
     */
    private function value(mixed $value): mixed
    {
        if (is_string($value)) {
            return Str::limit(trim($value), 5000, '');
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            return array_slice(array_map(
                fn ($item): mixed => is_array($item) ? array_map(fn ($inner): mixed => is_scalar($inner) ? $inner : null, $item) : $item,
                $value,
            ), 0, 20);
        }

        return null;
    }

    private function confidence(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return round(max(0.0, min(1.0, (float) $value)), 3);
    }

    private function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value > 0 ? (int) $value : null;
        }

        return null;
    }

    private function text(mixed $value, int $limit): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : Str::limit($value, $limit, '');
    }
}
