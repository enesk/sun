<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Portal\Company;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Zieht die fehlende PLZ importierter Betriebe ueber einen gezielten
 * Place-Details-Aufruf nach (#124, Entscheidung aus #121).
 *
 * Hintergrund: parseAddress() im Import hat die PLZ nie gelesen (Arm 'zipcode'
 * statt 'postal_code', gefixt in #118). Neue Importe fuellen companies.zipcode,
 * der Altbestand bleibt leer. --recheck des Imports hilft nicht, weil die
 * Hauptschleife einen bekannten google_places_id VOR dem Detail-Aufruf
 * verwirft und die Adresskomponenten damit nie sieht.
 *
 * Ableiten aus cities.zipcode ist ausdruecklich ausgeschlossen: die Stadtzeile
 * traegt nur eine PLZ, fuer Grossstaedte waere die Angabe falsch — und sie
 * landet ueber full_address und das LocalBusiness-JSON-LD in den oeffentlichen
 * Seiten. Eine fehlende PLZ ist eine Luecke, eine falsche eine Falschaussage.
 *
 * Kostenstufe: die FieldMask lautet ausschliesslich addressComponents, das ist
 * die Essentials-Stufe der Place Details. Der Satz je 1000 Aufrufe steht in
 * config('services.google.places_details_rate_usd') und ist ueber --rate
 * uebersteuerbar — er wird bewusst NICHT aus GetCompanies::estimateCost()
 * uebernommen, die dort verdrahteten 25 USD gelten fuer Enterprise+Atmosphere.
 *
 * Der Lauf ist von sich aus wiederholbar: er greift nur Zeilen mit leerer PLZ,
 * ein Statusfeld braucht es nicht.
 *
 * Nutzung:
 *   php artisan tenants:companies:zipcode-backfill
 *   php artisan tenants:companies:zipcode-backfill --tenant=23,24 --limit=500
 *   php artisan tenants:companies:zipcode-backfill --tenant=23 --max-cost=10 --execute
 */
class CompanyZipcodeBackfillCommand extends Command
{
    protected $signature = 'tenants:companies:zipcode-backfill
        {--tenant= : Portale (ID, UUID oder Domain), kommagetrennt — im Schreibmodus Pflicht}
        {--execute : PLZ tatsächlich schreiben (Vorgabe ist der Trockenlauf)}
        {--limit=0 : Höchstzahl der Betriebe über alle Portale (0 = ohne Grenze)}
        {--max-cost=25.00 : Kostendeckel in USD}
        {--rate= : Preis je 1000 Detail-Aufrufe in USD (Vorgabe aus der Konfiguration)}';

    protected $description = 'Zieht die fehlende PLZ importierter Betriebe über Place Details nach';

    private const BASE_URL = 'https://places.googleapis.com/v1';

    /**
     * Einzige angeforderte Antwortgruppe. Jedes weitere Feld hebt die
     * Preisstufe des Aufrufs — deshalb keine displayName, keine photos.
     */
    private const FIELD_MASK = 'addressComponents';

    /** Stellenzahl der PLZ je Land (ISO-3166-1 alpha-2). */
    private const ZIP_LENGTH = [
        'DE' => 5,
        'FR' => 5,
        'CH' => 4,
        'AT' => 4,
        'LI' => 4,
        'LU' => 4,
    ];

    private const CHUNK = 200;

    private string $apiKey = '';

    private float $rate = 0.0;

    private float $maxCost = 0.0;

    private bool $write = false;

    private int $calls = 0;

    private int $checked = 0;

    private int $filled = 0;

    private int $withoutZip = 0;

    private int $rejected = 0;

    private int $errors = 0;

    private bool $keyRejected = false;

    private bool $capReached = false;

    public function handle(): int
    {
        $this->write = (bool) $this->option('execute');
        $this->maxCost = (float) str_replace(',', '.', (string) $this->option('max-cost'));

        $rate = $this->option('rate');
        $this->rate = $rate === null || $rate === ''
            ? (float) config('services.google.places_details_rate_usd')
            : (float) str_replace(',', '.', (string) $rate);

        if ($this->rate <= 0.0) {
            $this->error('Der Satz je 1000 Aufrufe muss größer als 0 sein (--rate oder services.google.places_details_rate_usd).');

            return self::FAILURE;
        }

        if ($this->maxCost <= 0.0) {
            $this->error('Der Kostendeckel muss größer als 0 sein (--max-cost).');

            return self::FAILURE;
        }

        $tenants = $this->resolveTenants();

        if ($tenants === null) {
            return self::FAILURE;
        }

        if ($tenants === []) {
            $this->warn('Keine Portale gefunden.');

            return self::SUCCESS;
        }

        if ($this->write) {
            $this->apiKey = (string) config('services.google.places_api_key', '');

            if ($this->apiKey === '') {
                $this->error('GOOGLE_PLACES_API_KEY ist nicht gesetzt — ohne Schlüssel kann nichts nachgezogen werden.');
                $this->line('Der Trockenlauf (ohne --execute) läuft auch ohne Schlüssel.');

                return self::FAILURE;
            }
        } else {
            $this->comment('Trockenlauf — es wird nichts abgefragt und nichts geschrieben. Mit --execute nachziehen.');
            $this->newLine();
        }

        $plan = $this->buildPlan($tenants);

        $this->showPlan($plan);

        if ($plan['calls'] === 0) {
            $this->info('Es gibt nichts nachzuziehen.');

            return self::SUCCESS;
        }

        if (! $this->write) {
            $this->showResult($plan['calls']);

            return self::SUCCESS;
        }

        $answer = $this->confirmRun($plan);

        if ($answer === null) {
            return self::FAILURE;
        }

        if ($answer === false) {
            return self::SUCCESS;
        }

        $this->newLine();

        foreach ($plan['portals'] as $entry) {
            if ($this->keyRejected || $this->capReached) {
                break;
            }

            if ($entry['planned'] === 0) {
                continue;
            }

            $this->processTenant($entry['tenant'], $entry['planned'], $plan['calls']);
        }

        tenancy()->end();

        $this->newLine();
        $this->showResult($plan['calls']);

        if ($this->keyRejected) {
            $this->error('Google hat den Schlüssel abgelehnt — Lauf abgebrochen. Kein weiterer Aufruf.');

            return self::FAILURE;
        }

        if ($this->capReached) {
            $open = max(0, $plan['missing'] - $this->checked);
            $this->warn(sprintf(
                'Kostendeckel von %s USD erreicht. %s bleiben offen — ein erneuter Lauf macht dort weiter.',
                $this->money($this->maxCost),
                $this->companies($open)
            ));
        }

        return self::SUCCESS;
    }

    /**
     * Portale aufloesen. Im Schreibmodus ist --tenant Pflicht: ein Lauf ueber
     * alle 20 Portale soll nie versehentlich starten.
     *
     * @return list<Tenant>|null null = Abbruch mit Fehlermeldung
     */
    private function resolveTenants(): ?array
    {
        $raw = trim((string) $this->option('tenant'));

        if ($raw === '') {
            if ($this->write) {
                $this->error('--tenant ist im Schreibmodus Pflicht (kommagetrennt, ID, UUID oder Domain).');

                return null;
            }

            /** @var list<Tenant> $all */
            $all = array_values(Tenant::all()->all());

            return $all;
        }

        $tenants = [];

        foreach (array_filter(array_map('trim', explode(',', $raw)), 'strlen') as $needle) {
            $tenant = is_numeric($needle)
                ? Tenant::query()->find((int) $needle)
                : Tenant::query()->where('uuid', $needle)->orWhere('domain', $needle)->first();

            if (! $tenant) {
                $this->error("Portal '{$needle}' nicht gefunden.");

                return null;
            }

            $tenants[(string) $tenant->id] = $tenant;
        }

        return array_values($tenants);
    }

    /**
     * Mengen je Portal zaehlen und auf Zeilengrenze und Kostendeckel
     * herunterschneiden.
     *
     * @param  list<Tenant>  $tenants
     * @return array{portals: list<array{tenant: Tenant, name: string, missing: int, planned: int}>, missing: int, calls: int}
     */
    private function buildPlan(array $tenants): array
    {
        $limit = (int) $this->option('limit');
        $budgetCalls = (int) floor($this->maxCost / $this->rate * 1000);

        $allowed = $budgetCalls;
        if ($limit > 0) {
            $allowed = min($allowed, $limit);
        }

        $portals = [];
        $missing = 0;
        $calls = 0;

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            $count = $this->pending()->count();
            tenancy()->end();

            $planned = max(0, min($count, $allowed - $calls));
            $missing += $count;
            $calls += $planned;

            $portals[] = [
                'tenant' => $tenant,
                'name' => (string) $tenant->name,
                'missing' => $count,
                'planned' => $planned,
            ];
        }

        return ['portals' => $portals, 'missing' => $missing, 'calls' => $calls];
    }

    /**
     * @param  array{portals: list<array{tenant: Tenant, name: string, missing: int, planned: int}>, missing: int, calls: int}  $plan
     */
    private function showPlan(array $plan): void
    {
        $rows = [];

        foreach ($plan['portals'] as $entry) {
            $rows[] = [
                $entry['name'],
                $this->count($entry['missing']),
                $this->count($entry['planned']),
            ];
        }

        $rows[] = ['Summe', $this->count($plan['missing']), $this->count($plan['calls'])];

        $this->table(['Portal', 'Betriebe ohne PLZ', 'in diesem Lauf'], $rows);

        $this->line(sprintf('Detail-Aufrufe: %s', $this->count($plan['calls'])));
        $this->line(sprintf(
            'Geschätzte Kosten: %s USD (Satz %s USD je 1000 Aufrufe)',
            $this->money($this->cost($plan['calls'])),
            $this->money($this->rate)
        ));
        $this->line(sprintf('Kostendeckel: %s USD', $this->money($this->maxCost)));
        $this->newLine();
    }

    /**
     * @param  array{missing: int, calls: int}  $plan
     * @return bool|null true = fortfahren, false = abgelehnt, null = Bedienfehler
     */
    private function confirmRun(array $plan): ?bool
    {
        $question = sprintf(
            '%s nachziehen, geschätzt ~%s USD, Deckel %s USD. Fortfahren?',
            $this->companies($plan['calls']),
            $this->money($this->cost($plan['calls'])),
            $this->money($this->maxCost)
        );

        if (! $this->input->isInteractive()) {
            // Ohne Rueckfrage nur, wenn der Deckel bewusst gesetzt wurde — der
            // Vorgabewert allein ist keine Zustimmung zu einer Ausgabe.
            if (! $this->input->hasParameterOption('--max-cost')) {
                $this->error('Ohne Rückfrage (--no-interaction) läuft der Nachzug nur zusammen mit einem ausdrücklich gesetzten --max-cost.');

                return null;
            }

            $this->line($question.' — bestätigt über --max-cost.');

            return true;
        }

        if (! $this->confirm($question, false)) {
            $this->comment('Abgebrochen — es wurde nichts abgefragt.');

            return false;
        }

        return true;
    }

    private function processTenant(Tenant $tenant, int $planned, int $totalPlanned): void
    {
        tenancy()->initialize($tenant);

        $this->info(sprintf('── %s (ID %s) ──', (string) $tenant->name, (string) $tenant->id));

        $done = 0;

        $this->pending()->orderBy('id')->chunkById(self::CHUNK, function ($companies) use (&$done, $planned, $totalPlanned) {
            foreach ($companies as $company) {
                if ($done >= $planned) {
                    return false;
                }

                if ($this->cost($this->calls + 1) > $this->maxCost) {
                    $this->capReached = true;

                    return false;
                }

                $this->handleCompany($company);
                $done++;

                if ($this->keyRejected) {
                    return false;
                }

                if ($this->checked % 100 === 0) {
                    $this->progress($totalPlanned);
                }
            }

            return true;
        });

        tenancy()->end();
    }

    private function handleCompany(Company $company): void
    {
        $components = $this->fetchAddressComponents((string) $company->google_places_id);

        $this->checked++;

        if ($components === null) {
            return;
        }

        $zip = $this->pick($components, 'postal_code');
        $country = strtoupper($this->pick($components, 'country', short: true) ?? '');

        if ($zip === null || $zip === '') {
            $this->withoutZip++;

            return;
        }

        if (! $this->isValidZip($zip, $country)) {
            $this->rejected++;

            return;
        }

        // Frisch aus der Datenbank pruefen: eine vorhandene PLZ wird nie
        // ueberschrieben, auch wenn sie zwischenzeitlich entstanden ist.
        $current = (string) ($company->fresh()?->zipcode ?? '');

        if ($current !== '') {
            return;
        }

        $company->forceFill(['zipcode' => $zip])->save();
        $this->filled++;
    }

    /**
     * GET /v1/places/{id} mit der schmalsten moeglichen FieldMask.
     *
     * @return list<array<string, mixed>>|null null = Aufruf fehlgeschlagen
     */
    private function fetchAddressComponents(string $placeId): ?array
    {
        $response = Http::connectTimeout(5)->timeout(15)
            ->withHeaders([
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => self::FIELD_MASK,
            ])
            ->get(self::BASE_URL.'/places/'.rawurlencode($placeId), ['languageCode' => 'de']);

        $this->calls++;

        if (! $response->successful()) {
            $this->reportApiError($response);

            return null;
        }

        return $response->json('addressComponents') ?? [];
    }

    /**
     * Fehlerauswertung wie im Import (#88/#108): die neue API meldet ueber
     * HTTP-Code plus error-Block, nicht ueber status: REQUEST_DENIED.
     */
    private function reportApiError(Response $response): void
    {
        $json = $response->json();
        $message = (string) ($json['error']['message'] ?? $response->body());
        $status = (string) ($json['error']['status'] ?? '');

        $keyRejected = in_array($response->status(), [401, 403], true)
            || in_array($status, ['UNAUTHENTICATED', 'PERMISSION_DENIED'], true)
            || Str::contains($message, [
                'API key not valid',
                'API key is invalid',
                'API key expired',
                'has not been used in project',
                'is disabled',
                'billing',
            ], true);

        if ($keyRejected) {
            $this->keyRejected = true;
            $this->error('  Google lehnt den Places-Schlüssel ab: '.$this->ohneSchluessel($message));

            return;
        }

        $this->errors++;

        if ($response->status() === 429 || $status === 'RESOURCE_EXHAUSTED') {
            $this->warn('  Kontingent erschöpft (HTTP 429) — der Betrieb bleibt offen.');

            return;
        }

        $this->warn(sprintf('  Fehler HTTP %d — %s', $response->status(), $this->ohneSchluessel($message)));
    }

    /**
     * Google gibt den Schluessel im Klartext in der Fehlermeldung zurueck — er
     * darf weder in der Konsole noch im Protokoll landen.
     */
    private function ohneSchluessel(string $message): string
    {
        return Str::limit((string) preg_replace('/AIzaSy[A-Za-z0-9_-]{10,}/', 'AIzaSy…', $message), 200);
    }

    /**
     * Auswertung wie parseAddress() im Import: massgeblich ist types[0],
     * zusammengesetzte Typen fallen bewusst durch.
     *
     * @param  list<array<string, mixed>>  $components
     */
    private function pick(array $components, string $type, bool $short = false): ?string
    {
        foreach ($components as $component) {
            if (($component['types'][0] ?? null) !== $type) {
                continue;
            }

            $value = $short
                ? ($component['shortText'] ?? $component['longText'] ?? null)
                : ($component['longText'] ?? null);

            return $value === null ? null : trim((string) $value);
        }

        return null;
    }

    /**
     * Formatpruefung nach Land. Eine kaputte PLZ ist genauso schaedlich wie
     * eine geratene, deshalb wird Nichtpassendes verworfen und gezaehlt.
     * Ohne erkennbares Land gelten vier oder fuenf Stellen als Rahmen.
     */
    private function isValidZip(string $zip, string $country): bool
    {
        if (! preg_match('/^\d{4,5}$/', $zip)) {
            return false;
        }

        $expected = self::ZIP_LENGTH[$country] ?? null;

        return $expected === null || strlen($zip) === $expected;
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Company> */
    private function pending(): \Illuminate\Database\Eloquent\Builder
    {
        return Company::query()
            ->whereNotNull('google_places_id')
            ->where('google_places_id', '!=', '')
            ->where(function ($query) {
                $query->whereNull('zipcode')->orWhere('zipcode', '');
            });
    }

    private function progress(int $totalPlanned): void
    {
        $answered = $this->filled + $this->withoutZip + $this->rejected;
        $quota = $answered > 0 ? $this->filled / $answered * 100 : 0.0;

        $this->line(sprintf(
            '%s/%s Betriebe · %s Aufrufe · %s USD · Restdeckel %s USD · Trefferquote %s %%',
            $this->count($this->checked),
            $this->count($totalPlanned),
            $this->count($this->calls),
            $this->money($this->cost($this->calls)),
            $this->money(max(0.0, $this->maxCost - $this->cost($this->calls))),
            number_format($quota, 1, ',', '.')
        ));
    }

    private function showResult(int $planned): void
    {
        $unknown = '—';

        $rows = [
            ['Geprüft', $this->write ? $this->count($this->checked) : $this->count($planned)],
            ['PLZ gefüllt', $this->write ? $this->count($this->filled) : $unknown],
            ['Antwort ohne PLZ', $this->write ? $this->count($this->withoutZip) : $unknown],
            ['Wegen Format verworfen', $this->write ? $this->count($this->rejected) : $unknown],
            ['Fehler', $this->write ? $this->count($this->errors) : $unknown],
            ['Detail-Aufrufe', $this->count($this->write ? $this->calls : $planned)],
            ['Geschätzte Kosten (USD)', $this->money($this->cost($this->write ? $this->calls : $planned))],
        ];

        $this->table(
            [$this->write ? 'Ergebnis' : 'Ergebnis (so wäre der Lauf ausgegangen)', 'Wert'],
            $rows
        );

        if (! $this->write) {
            $this->comment('Trockenlauf: gefüllt, ohne PLZ und verworfen kann erst der echte Lauf sagen — sie stehen bei Google, nicht in der Datenbank.');
        }
    }

    private function cost(int $calls): float
    {
        return $calls / 1000 * $this->rate;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    /** Mengenangabe mit Einheit, im Singular ohne Plural-s. */
    private function companies(int $value): string
    {
        return $this->count($value).($value === 1 ? ' Betrieb' : ' Betriebe');
    }

    private function count(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
