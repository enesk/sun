<?php

declare(strict_types=1);

namespace App\Content\Providers;

use App\Content\Providers\Support\GoogleServiceAccountToken;
use App\Guide\Llm\ContentBudgetGuard;
use App\Guide\Llm\LlmContext;
use App\Guide\Models\Central\LlmUsageLog;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ertragsdaten aus der AdSense Management API v2 (#23).
 *
 * Genutzt wird genau ein Endpunkt: accounts.reports.generate mit der
 * Dimension PAGE_URL und den Metriken PAGE_VIEWS und ESTIMATED_EARNINGS.
 * Damit laesst sich der Ertrag einem einzelnen Ratgeber zuordnen, ohne je
 * Artikel einen eigenen Anzeigenkanal anlegen zu muessen.
 *
 * Die Zuordnung ist optional und darf nie den Collector scheitern lassen:
 * ohne freigeschaltetes Dienstkonto (config content.adsense.enabled = false),
 * ohne Konto-Kennung oder ohne Treffer bleiben `pageviews` und
 * `adsense_revenue_usd` in article_metrics schlicht null. Null heisst
 * "unbekannt", nicht "null Euro" — der Unterschied entscheidet in der
 * Performance-Ansicht (#25) darueber, ob ein Artikel als ertragslos oder als
 * ungemessen gilt.
 *
 * Der Abruf kostet nichts, wird aber wie jeder Provider-Aufruf in
 * llm_usage_logs protokolliert, damit er im Quellen-Monitor (#20) auftaucht.
 */
final class AdSenseClient
{
    public const PROVIDER = 'adsense';

    public const OPERATION = 'page_report';

    public function __construct(
        private readonly ContentBudgetGuard $budget,
    ) {}

    /**
     * Ist das Feature ueberhaupt eingeschaltet und ein Schluessel hinterlegt?
     */
    public function isConfigured(): bool
    {
        if (! (bool) $this->option('enabled', false)) {
            return false;
        }

        return $this->account() !== null && $this->token()->isConfigured();
    }

    /**
     * Liegt eine lesbare Schluesseldatei vor? Getrennt von isConfigured(),
     * damit die Go-Live-Pruefung (#92) benennen kann, welche der beiden
     * Vorbedingungen fehlt: Konto-Kennung oder Schluessel.
     */
    public function hasReadableCredentials(): bool
    {
        return $this->token()->isConfigured();
    }

    /**
     * Konten, die das Dienstkonto sehen darf (accounts.list).
     *
     * Nur fuer die Abnahme gedacht (#90): so laesst sich vor dem ersten
     * Collector-Lauf feststellen, ob die Freischaltung im AdSense-Konto
     * wirklich angekommen ist und ob ADSENSE_ACCOUNT_ID dazu passt. Bewusst
     * ohne den Schalter content.adsense.enabled — geprueft wird der Zugang,
     * nicht die Freigabe des Features.
     *
     * @return array<string, string> Kennung => Anzeigename
     */
    public function accounts(): array
    {
        $url = rtrim((string) $this->option('base_url', 'https://adsense.googleapis.com/v2'), '/').'/accounts';

        $response = $this->http()->get($url);

        if ($response->failed()) {
            throw new RuntimeException(
                'AdSense antwortet mit HTTP '.$response->status().': '.Str::limit($response->body(), 200, ''),
            );
        }

        $accounts = [];

        foreach ((array) ($response->json('accounts') ?? []) as $entry) {
            $name = $entry['name'] ?? null;

            if (is_string($name) && $name !== '') {
                $accounts[$name] = (string) ($entry['displayName'] ?? $name);
            }
        }

        return $accounts;
    }

    /**
     * Ertrag je Seite ueber ein Zeitfenster.
     *
     * Schluessel der Rueckgabe ist der Pfad der Seite ('/ratgeber/slug'),
     * damit der Collector ohne Wissen ueber Schema und Host zuordnen kann.
     * Ein leeres Ergebnis ist ein gueltiges Ergebnis.
     *
     * @return array<string, array{pageviews: int, earnings: float, page: string}>
     */
    public function pageReport(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        ?string $domain = null,
        ?LlmContext $context = null,
    ): array {
        if (! $this->isConfigured()) {
            return [];
        }

        $context = ($context ?? LlmContext::current())->withOperation(self::OPERATION);
        $startedAt = microtime(true);

        try {
            $body = $this->request($startDate, $endDate, $domain);
        } catch (\Throwable $exception) {
            $this->record($context, $this->elapsed($startedAt), false, $exception->getMessage());

            throw $exception;
        }

        $this->record($context, $this->elapsed($startedAt), true, null);

        return $this->toRows($body);
    }

    /**
     * @return array<string, mixed>
     */
    private function request(DateTimeInterface $startDate, DateTimeInterface $endDate, ?string $domain): array
    {
        $start = CarbonImmutable::parse($startDate);
        $end = CarbonImmutable::parse($endDate);

        $query = [
            'dateRange' => 'CUSTOM',
            'startDate.year' => $start->year,
            'startDate.month' => $start->month,
            'startDate.day' => $start->day,
            'endDate.year' => $end->year,
            'endDate.month' => $end->month,
            'endDate.day' => $end->day,
            'dimensions' => ['PAGE_URL'],
            'metrics' => ['PAGE_VIEWS', 'ESTIMATED_EARNINGS'],
            'currencyCode' => (string) $this->option('currency', 'USD'),
            'limit' => max(1, (int) $this->option('max_rows', 5000)),
            'orderBy' => ['-ESTIMATED_EARNINGS'],
        ];

        // Ein Dienstkonto sieht alle Domains des AdSense-Kontos. Der Filter
        // haelt die Antwort beim Mandanten und damit klein.
        if ($domain !== null && trim($domain) !== '') {
            $query['filters'] = ['DOMAIN_NAME=='.trim($domain)];
        }

        $url = rtrim((string) $this->option('base_url', 'https://adsense.googleapis.com/v2'), '/')
            .'/'.trim((string) $this->account(), '/').'/reports:generate';

        $response = $this->http()->get($url, $query);

        if ($response->failed()) {
            throw new RuntimeException(
                'AdSense antwortet mit HTTP '.$response->status().': '.Str::limit($response->body(), 200, ''),
            );
        }

        return (array) $response->json();
    }

    /**
     * Zeilen der Antwort auf Pfade abbilden.
     *
     * Die Reihenfolge der Zellen folgt der Reihenfolge von dimensions +
     * metrics; die Kopfzeile wird trotzdem ausgewertet, damit eine kuenftige
     * Umstellung der API nicht stillschweigend falsche Zahlen liefert.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, array{pageviews: int, earnings: float, page: string}>
     */
    private function toRows(array $body): array
    {
        $headers = [];

        foreach ((array) ($body['headers'] ?? []) as $index => $header) {
            $headers[(int) $index] = (string) ($header['name'] ?? '');
        }

        $rows = [];

        foreach ((array) ($body['rows'] ?? []) as $row) {
            $cells = (array) ($row['cells'] ?? []);
            $values = [];

            foreach ($cells as $index => $cell) {
                $name = $headers[(int) $index] ?? (string) $index;
                $values[$name] = (string) ($cell['value'] ?? '');
            }

            $page = trim($values['PAGE_URL'] ?? '');

            if ($page === '') {
                continue;
            }

            $path = $this->path($page);

            if ($path === null) {
                continue;
            }

            // Eine Seite kann mehrfach auftauchen (http/https, mit und ohne
            // www). Die Werte werden auf dem Pfad summiert.
            $rows[$path] = [
                'page' => $page,
                'pageviews' => (int) ($rows[$path]['pageviews'] ?? 0) + (int) round((float) ($values['PAGE_VIEWS'] ?? 0)),
                'earnings' => round(
                    (float) ($rows[$path]['earnings'] ?? 0.0) + (float) ($values['ESTIMATED_EARNINGS'] ?? 0),
                    4,
                ),
            ];
        }

        return $rows;
    }

    /**
     * Pfad einer AdSense-Seitenangabe. Sie kommt je nach Konto als
     * 'example.de/ratgeber/slug', als vollstaendige URL oder bereits als Pfad.
     */
    private function path(string $page): ?string
    {
        $value = preg_replace('#^https?://#i', '', $page) ?? $page;
        $position = strpos($value, '/');

        if ($position === false) {
            return null;
        }

        $path = rtrim(substr($value, $position), '/');

        return $path === '' ? '/' : $path;
    }

    /**
     * Konto-Kennung in der Form 'accounts/pub-1234567890123456'.
     */
    public function account(): ?string
    {
        $account = $this->option('account');

        if (! is_string($account) || trim($account) === '') {
            return null;
        }

        $account = trim($account);

        return str_starts_with($account, 'accounts/') ? $account : 'accounts/'.$account;
    }

    private function token(): GoogleServiceAccountToken
    {
        return new GoogleServiceAccountToken(
            credentialsPath: $this->option('credentials_path') ?? config('content.providers.search_console.credentials_path'),
            scope: (string) $this->option('scope', 'https://www.googleapis.com/auth/adsense.readonly'),
            tokenUrl: (string) config('content.providers.search_console.token_url', 'https://oauth2.googleapis.com/token'),
            timeout: (int) $this->option('timeout', 60),
        );
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token()->token())
            ->acceptJson()
            ->timeout((int) $this->option('timeout', 60));
    }

    private function record(LlmContext $context, int $durationMs, bool $successful, ?string $error): void
    {
        LlmUsageLog::create([
            'tenant_id' => $context->tenantId,
            'provider' => self::PROVIDER,
            'model' => 'adsense-management-v2',
            'operation' => Str::limit($context->operation ?? self::OPERATION, 64, ''),
            'reference_type' => $context->referenceType,
            'reference_id' => $context->referenceId,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'requests' => 1,
            'cost_usd' => 0.0,
            'duration_ms' => $durationMs,
            'was_successful' => $successful,
            'error_message' => $error === null ? null : Str::limit($error, 500, ''),
        ]);

        // Der Abruf ist kostenlos; gezaehlt wird er trotzdem, sonst fehlt der
        // Provider im Quellen-Monitor.
        $this->budget->record(self::PROVIDER, 0.0);
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.adsense.{$key}", $default);
    }
}
