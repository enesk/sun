<?php

namespace App\Console\Commands;

use App\Models\Portal\Category;
use App\Models\Portal\ChCity;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyOpeningHour;
use App\Models\Portal\FrCity;
use App\Models\Portal\Review;
use App\Models\Tenant;
use App\Services\CityStateResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Importiert Firmen aus der Google Places API (New) in die aktive Tenant-DB.
 *
 * Dienst ist places.googleapis.com — die alte Places API
 * (maps.googleapis.com/maps/api/place, Dienst places-backend.googleapis.com)
 * gibt Google fuer neu angelegte Cloud-Projekte nicht mehr frei. Die
 * API-Einschraenkung des Schluessels muss auf "Places API (New)" lauten.
 *
 * Kostenoptimierung:
 * - Dedup via google_places_id VOR Detail-Call (teuerster Call)
 * - Text Search mit FieldMask places.id (Stufe "ID Only", unberechnet)
 * - Field-Mask auf Place Details (bestimmt die Kostenstufe des Aufrufs)
 * - Fotos NUR für Firmen OHNE Website (--skip-photos deaktiviert komplett)
 * - Reviews optional (--skip-reviews)
 * - Limit pro Stadt (--limit)
 *
 * Nutzung:
 *   php artisan tenants:run "tenants:import-google --query=Sanitär"
 *   php artisan tenants:run "tenants:import-google --query=Sanitär --query=Elektriker"
 *   php artisan tenants:run "tenants:import-google --query=Restaurant --city=Berlin --limit=20"
 *   php artisan tenants:run "tenants:import-google --query=Rechtsanwalt --dry-run"
 *   php artisan tenants:run "tenants:import-google --query=Friseur --skip-photos --limit=10"
 *   php artisan tenants:run "tenants:import-google --query=Friseur --recheck"
 */
class GetCompanies extends Command
{
    protected $signature = 'tenants:import-google
        {--tenant= : Tenant-ID (numerisch) — läuft direkt für diesen Tenant}
        {--query=* : Suchbegriffe (z.B. "Sanitär", "Rechtsanwalt", "Restaurant")}
        {--city= : Nur eine bestimmte Stadt (Name oder PLZ)}
        {--state= : Nur Städte in einem Bundesland/Kanton (z.B. "Bayern", "ZH")}
        {--country=de : Länderquelle für Städte (de = City, ch = ChCity, fr = FrCity)}
        {--limit=0 : Max. neue Firmen pro Stadt+Query (0 = unbegrenzt)}
        {--skip-photos : Fotos NICHT herunterladen (Standard: Fotos AN)}
        {--skip-reviews : Reviews nicht importieren}
        {--max-photos=5 : Max. Fotos pro Firma (Standard: 5)}
        {--recheck : Bereits gecheckte Städte nochmal durchgehen}
        {--dry-run : Nur suchen und anzeigen, nicht speichern}';

    protected $description = 'Importiert Firmen aus der Google Places API in den aktuellen Tenant';

    private const BASE_URL = 'https://places.googleapis.com/v1';

    /**
     * Text Search: wir brauchen aus der Trefferliste nur die Place-ID, weil der
     * Dedup gegen companies.google_places_id vor dem Detail-Call laeuft.
     * Eine FieldMask mit ausschliesslich places.id faellt in die Stufe
     * "Text Search (ID Only)" und ist damit unberechnet.
     */
    private const SEARCH_FIELDS = 'places.id,nextPageToken';

    /**
     * Place Details: nur Felder die wir brauchen — die FieldMask bestimmt die
     * Kostenstufe des Aufrufs.
     * Essentials: id, addressComponents, types, photos
     * Pro: displayName
     * Enterprise: nationalPhoneNumber, websiteUri, regularOpeningHours, rating, userRatingCount
     * Enterprise + Atmosphere: reviews
     *
     * @var string[]
     */
    private const DETAIL_FIELDS = [
        'id',
        'displayName',
        'nationalPhoneNumber',
        'websiteUri',
        'rating',
        'userRatingCount',
        'addressComponents',
        'regularOpeningHours',
        'types',
    ];

    private string $apiKey;

    /** @var array<string, int> Google-Type → category_id */
    private array $categoryMap = [];

    /** @var string[] Ignorierte Google-Typen */
    private array $ignoredTypes = [];

    /** @var int|null Fallback-Kategorie-ID ("Sonstiges") */
    private ?int $fallbackCategoryId = null;

    private int $apiCalls = 0;

    /** Kostenpflichtige Detail-Calls (Text Search laeuft mit ID-Only-FieldMask kostenlos). */
    private int $detailCalls = 0;

    private int $photoCalls = 0;

    /** @var bool Google hat den Schluessel abgelehnt (REQUEST_DENIED) — Abbruch statt Weiterlaufen */
    private bool $apiKeyRejected = false;

    private int $limit;

    private bool $isDryRun;

    private string $country = 'de';

    public function handle(): int
    {
        ignore_user_abort(true);
        set_time_limit(0);

        // ── Tenant-Kontext setzen ──
        // ── API Key prüfen ──
        // Ueber config(), damit der Schluessel auch mit gecachter Konfiguration
        // (php artisan config:cache im Deploy) gefunden wird (#101).
        $this->apiKey = (string) config('services.google.places_api_key', '');
        if (empty($this->apiKey)) {
            $this->error('GOOGLE_PLACES_API_KEY ist nicht in .env gesetzt.');
            $this->line('Füge folgende Zeile zu .env hinzu:');
            $this->line('GOOGLE_PLACES_API_KEY=dein_api_key');

            return self::FAILURE;
        }

        if (! tenancy()->initialized) {
            $tenantId = $this->option('tenant');

            if (! $tenantId) {
                // Interaktive Auswahl
                $tenants = Tenant::all();
                if ($tenants->isEmpty()) {
                    $this->error('Keine Tenants vorhanden.');

                    return self::FAILURE;
                }

                $choices = $tenants->mapWithKeys(fn ($t) => [
                    $t->id => "{$t->name} (ID {$t->id})",
                ])->toArray();

                $selected = $this->choice('Welchen Tenant möchtest du verwenden?', $choices);

                // choice() gibt den Wert zurück — wir brauchen den Key
                $tenantId = array_search($selected, $choices);
            }

            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                $this->error("Tenant mit ID {$tenantId} nicht gefunden.");

                return self::FAILURE;
            }

            tenancy()->initialize($tenant);
            $this->info("Tenant: {$tenant->name} (ID {$tenant->id})");
            $this->newLine();
        }

        // ── Optionen ──
        $queries = $this->option('query');
        if (empty($queries)) {
            $input = $this->ask('Suchbegriff(e) eingeben (mehrere mit Komma trennen, z.B. "Sanitär, Heizung")');
            if (empty($input)) {
                $this->error('Mindestens ein Suchbegriff erforderlich.');

                return self::FAILURE;
            }
            $queries = array_map('trim', explode(',', $input));
        }

        $this->limit = (int) $this->option('limit');
        $this->isDryRun = (bool) $this->option('dry-run');
        $this->country = strtolower($this->option('country') ?? 'de');

        // ── Kategorie-Map aufbauen ──
        $this->buildCategoryMap();
        $this->ignoredTypes = config('category-mapping.ignored', []);

        if ($this->isDryRun) {
            $this->warn('🔍 DRY-RUN — keine Daten werden gespeichert');
            $this->newLine();
        }

        $this->info('═══ Google Places Import ═══');
        $this->info('Suchbegriffe: '.implode(', ', $queries));
        $countryLabels = ['de' => 'Deutschland (cities)', 'ch' => 'Schweiz (ch_cities)', 'fr' => 'Frankreich (fr_cities)'];
        $this->info('Städte-Quelle: '.($countryLabels[$this->country] ?? $this->country));
        $this->newLine();

        // ── Cities laden ──
        $allCities = $this->loadCities();
        if ($allCities->isEmpty()) {
            $this->error('Keine Städte gefunden. Zuerst CategoryMappingSeeder oder Import ausführen.');

            return self::FAILURE;
        }

        $recheck = (bool) $this->option('recheck');
        $cities = $recheck ? $allCities : $allCities->where('checked', false);
        $skippedCities = $allCities->count() - $cities->count();

        if ($skippedCities > 0 && ! $recheck) {
            $this->info("Städte: {$cities->count()} offen, {$skippedCities} bereits gecheckt (--recheck zum Wiederholen)");
        } else {
            $this->info("Städte: {$cities->count()}".($recheck ? ' (Recheck-Modus)' : ''));
        }

        if ($cities->isEmpty()) {
            $this->info('Alle Städte bereits gecheckt. Nutze --recheck um sie erneut zu durchlaufen.');

            return self::SUCCESS;
        }

        $this->newLine();

        // ── Kostenwarnung bei großen Imports ──
        $cityCount = $cities->count();
        if ($cityCount > 100 && ! $this->isDryRun) {
            $estimatedCalls = $cityCount * 30; // ~30 Calls/Stadt (konservativ)
            $estimatedCost = ($estimatedCalls / 1000) * 25;
            $this->warn("⚠ {$cityCount} Städte = ~{$estimatedCalls} API-Calls = ~\${$estimatedCost}");
            if (! $this->confirm('Fortfahren?', true)) {
                return self::SUCCESS;
            }
        }

        // ── Hauptschleife ──
        $totalNew = 0;
        $totalSkipped = 0;
        $totalErrors = 0;
        $startTime = microtime(true);

        foreach ($queries as $query) {
            $this->info("━━━ Suche: \"{$query}\" ━━━");
            $this->newLine();

            $cityIndex = 0;

            foreach ($cities as $city) {
                $cityIndex++;
                $elapsed = round(microtime(true) - $startTime);
                $cityName = $this->getCityName($city);
                $cityZip = $this->getCityZipcode($city);
                $this->line("[{$cityIndex}/{$cityCount}] {$cityName} ({$cityZip}) — {$elapsed}s elapsed, {$this->apiCalls} API-Calls, {$this->estimateCost()}");

                $searchTerm = match ($this->country) {
                    'ch' => "{$query} in {$cityZip} {$cityName}, Schweiz",
                    'fr' => "{$query} in {$cityZip} {$cityName}, France",
                    default => "{$query} in {$cityZip} {$cityName}",
                };

                try {
                    $results = $this->textSearch($searchTerm);
                } catch (\Exception $e) {
                    $this->warn("  ✗ Text Search fehlgeschlagen: {$e->getMessage()}");
                    $totalErrors++;

                    continue;
                }

                if ($this->apiKeyRejected) {
                    $this->newLine();
                    $this->error('Abbruch: Der Schluessel in GOOGLE_PLACES_API_KEY ist gesperrt oder das Google-Projekt ist deaktiviert.');
                    $this->line('Neuen Schluessel ausstellen und in .env eintragen — siehe docs/messungen/places-key-rotation-anleitung.md.');

                    return self::FAILURE;
                }

                if (empty($results)) {
                    $this->line('  → 0 Ergebnisse');

                    if (! $this->isDryRun && ! $city->checked) {
                        $city->update(['checked' => true]);
                    }

                    continue;
                }

                $newInCity = 0;
                $skippedInCity = 0;

                foreach ($results as $i => $place) {
                    $placeId = $place['place_id'] ?? null;
                    if (! $placeId) {
                        continue;
                    }

                    // Dedup VOR Detail-Call = größte Kostenersparnis
                    if (Company::where('google_places_id', $placeId)->exists()) {
                        $skippedInCity++;

                        continue;
                    }

                    // Limit pro Stadt+Query
                    if ($this->limit > 0 && $newInCity >= $this->limit) {
                        break;
                    }

                    if ($this->isDryRun) {
                        $newInCity++;

                        continue;
                    }

                    // Place Details holen (kostenintensiv)
                    try {
                        $details = $this->getPlaceDetails($placeId);
                    } catch (\Exception $e) {
                        $this->warn("  ✗ Detail-Call fehlgeschlagen: {$e->getMessage()}");
                        $totalErrors++;

                        continue;
                    }

                    if ($this->apiKeyRejected) {
                        $this->newLine();
                        $this->error('Abbruch: Der Schluessel in GOOGLE_PLACES_API_KEY ist gesperrt oder das Google-Projekt ist deaktiviert.');
                        $this->line('Neuen Schluessel ausstellen und in .env eintragen — siehe docs/messungen/places-key-rotation-anleitung.md.');

                        return self::FAILURE;
                    }

                    if (! $details) {
                        $totalErrors++;

                        continue;
                    }

                    // Nur Places OHNE Website importieren
                    if (! empty($details['website'])) {
                        $placeName = $details['name'] ?? '?';
                        $this->line("    ⊘ Übersprungen (hat Website): {$placeName}");
                        $skippedInCity++;

                        continue;
                    }

                    // Nur Places im Zielland — regionCode gewichtet die Suche nur (#16)
                    $placeCountry = $this->placeCountry($details['address_components'] ?? []);
                    if ($placeCountry !== null && $placeCountry !== $this->regionCode()) {
                        $placeName = $details['name'] ?? '?';
                        $this->line("    ⊘ Übersprungen (Land {$placeCountry}): {$placeName}");
                        $skippedInCity++;

                        continue;
                    }

                    // Speichern
                    $company = $this->saveCompany($details, $city);
                    if ($company) {
                        $newInCity++;
                        $placeName = $details['name'] ?? '?';
                        $this->line("  ✓ [{$newInCity}] {$placeName}");
                    } else {
                        $totalErrors++;
                    }
                }

                $totalNew += $newInCity;
                $totalSkipped += $skippedInCity;

                $this->line("  → {$newInCity} neu, {$skippedInCity} übersprungen");

                // Stadt als gecheckt markieren (nicht im Dry-Run)
                if (! $this->isDryRun && ! $city->checked) {
                    $city->update(['checked' => true]);
                }
            }

            $this->newLine();
        }

        // ── Zusammenfassung ──
        $this->info('═══ Zusammenfassung ═══');
        $this->table(
            ['Metrik', 'Wert'],
            [
                ['Neu importiert', $totalNew],
                ['Übersprungen (existiert)', $totalSkipped],
                ['Fehler', $totalErrors],
                ['API-Calls gesamt', $this->apiCalls],
                ['Geschätzte Kosten', $this->estimateCost()],
            ]
        );

        return self::SUCCESS;
    }

    // ════════════════════════════════════════════════════════════════════
    // Google Places API
    // ════════════════════════════════════════════════════════════════════

    /**
     * Text Search (Places API New) — findet Places zu einem Suchbegriff.
     * POST /v1/places:searchText, Schluessel im Header, Feldauswahl per FieldMask.
     * Paginiert automatisch (max. 3 Seiten x 20 = 60 Ergebnisse); die neue API
     * gibt den pageToken sofort gueltig zurueck, die Wartezeit der alten API
     * entfaellt.
     *
     * Rueckgabe in der alten Form (['place_id' => ...]), damit die Hauptschleife
     * unveraendert bleibt.
     *
     * @return array<int, array{place_id: string}>
     */
    private function textSearch(string $query): array
    {
        $allResults = [];
        $nextPageToken = null;
        $page = 0;

        do {
            $page++;

            // regionCode gewichtet die Treffer auf das Zielland; ohne ihn lieferte
            // "Elektriker in 73045 …" auch Betriebe aus Oklahoma (#16).
            $payload = [
                'textQuery' => $query,
                'languageCode' => 'de',
                'regionCode' => $this->regionCode(),
                'pageSize' => 20,
            ];

            if ($nextPageToken) {
                $payload['pageToken'] = $nextPageToken;
            }

            $response = Http::connectTimeout(5)->timeout(15)
                ->withHeaders([
                    'X-Goog-Api-Key' => $this->apiKey,
                    'X-Goog-FieldMask' => self::SEARCH_FIELDS,
                ])
                ->post(self::BASE_URL.'/places:searchText', $payload);
            $this->apiCalls++;

            if (! $response->successful()) {
                $this->reportApiError('Text Search', $response);

                return $allResults;
            }

            $json = $response->json();

            foreach ($json['places'] ?? [] as $place) {
                if (! empty($place['id'])) {
                    $allResults[] = ['place_id' => $place['id']];
                }
            }

            $nextPageToken = $json['nextPageToken'] ?? null;

            // Max 3 Seiten (Kostendeckel wie bisher)
            if ($page >= 3) {
                break;
            }
        } while ($nextPageToken);

        return $allResults;
    }

    /**
     * Place Details (Places API New) — GET /v1/places/{placeId}.
     * Die FieldMask ist Pflicht (ohne sie antwortet die API mit HTTP 400) und
     * bestimmt zugleich die Kostenstufe.
     *
     * Rueckgabe in der alten Feldform, damit saveCompany() unveraendert bleibt.
     */
    private function getPlaceDetails(string $placeId): ?array
    {
        $response = Http::connectTimeout(5)->timeout(15)
            ->withHeaders([
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => $this->detailFieldMask(),
            ])
            ->get(self::BASE_URL.'/places/'.rawurlencode($placeId), ['languageCode' => 'de']);
        $this->apiCalls++;
        $this->detailCalls++;

        if (! $response->successful()) {
            $this->reportApiError('Place Details', $response);

            return null;
        }

        return $this->mapPlace($response->json() ?? []);
    }

    /**
     * FieldMask fuer den Detail-Call. Fotos und Reviews kosten extra und werden
     * nur angefordert, wenn der Lauf sie auch verarbeitet.
     */
    private function detailFieldMask(): string
    {
        $fields = self::DETAIL_FIELDS;

        if (! $this->option('skip-reviews')) {
            $fields[] = 'reviews';
        }

        if (! $this->option('skip-photos')) {
            $fields[] = 'photos';
        }

        return implode(',', $fields);
    }

    /**
     * Fehlerauswertung der neuen API: sie meldet ueber HTTP-Codes und einen
     * error-Block, nicht mehr ueber status: REQUEST_DENIED (#88).
     */
    private function reportApiError(string $label, Response $response): void
    {
        $json = $response->json();
        $message = (string) ($json['error']['message'] ?? $response->body());
        $status = (string) ($json['error']['status'] ?? '');

        if ($response->status() === 429 || $status === 'RESOURCE_EXHAUSTED') {
            $this->error('  API-Limit erreicht! Warte oder erhöhe dein Quota.');

            return;
        }

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
            $this->apiKeyRejected = true;
            $this->error('  Google lehnt GOOGLE_PLACES_API_KEY ab: '.$this->ohneSchluessel($message));

            return;
        }

        $this->warn("  API-Fehler {$label}: HTTP {$response->status()} — ".$this->ohneSchluessel($message));
    }

    /**
     * Google gibt den Schluessel in der Fehlermeldung zurueck ("Consumer
     * 'api_key:AIza…' has been suspended") — er darf nicht in Konsole oder Log
     * landen.
     */
    private function ohneSchluessel(string $message): string
    {
        $maskiert = (string) preg_replace('/AIzaSy[A-Za-z0-9_-]{10,}/', 'AIzaSy…', $message);

        return Str::limit($maskiert, 200);
    }

    /**
     * Antwort der neuen API auf die alten Feldnamen abbilden.
     * Die Place-IDs beider Fassungen sind identisch, companies.google_places_id
     * bleibt damit der Dedup-Schluessel.
     */
    private function mapPlace(array $place): ?array
    {
        if (empty($place['id'])) {
            return null;
        }

        return [
            'place_id' => $place['id'],
            'name' => $place['displayName']['text'] ?? null,
            'formatted_phone_number' => $place['nationalPhoneNumber'] ?? null,
            'website' => $place['websiteUri'] ?? null,
            'rating' => $place['rating'] ?? 0,
            'user_ratings_total' => $place['userRatingCount'] ?? 0,
            'types' => $place['types'] ?? [],
            'address_components' => $this->mapAddressComponents($place['addressComponents'] ?? []),
            'opening_hours' => ['periods' => $this->mapOpeningHours($place['regularOpeningHours']['periods'] ?? [])],
            'reviews' => $this->mapReviews($place['reviews'] ?? []),
            'photos' => $this->mapPhotos($place['photos'] ?? []),
        ];
    }

    /**
     * addressComponents: longText/shortText → long_name/short_name.
     */
    private function mapAddressComponents(array $components): array
    {
        $mapped = [];

        foreach ($components as $component) {
            $mapped[] = [
                'types' => $component['types'] ?? [],
                'long_name' => $component['longText'] ?? '',
                'short_name' => $component['shortText'] ?? '',
            ];
        }

        return $mapped;
    }

    /**
     * regularOpeningHours: {hour, minute} → 'HHMM' wie in der alten API,
     * damit formatGoogleTime() unveraendert bleibt.
     */
    private function mapOpeningHours(array $periods): array
    {
        $mapped = [];

        foreach ($periods as $period) {
            if (! isset($period['open'])) {
                continue;
            }

            // Proto-JSON laesst Nullwerte weg — fehlender day heisst Sonntag (0).
            $entry = [
                'open' => [
                    'day' => (int) ($period['open']['day'] ?? 0),
                    'time' => $this->clockTime($period['open']),
                ],
            ];

            if (isset($period['close'])) {
                $entry['close'] = [
                    'day' => (int) ($period['close']['day'] ?? 0),
                    'time' => $this->clockTime($period['close']),
                ];
            }

            $mapped[] = $entry;
        }

        return $mapped;
    }

    private function clockTime(array $point): string
    {
        return sprintf('%02d%02d', (int) ($point['hour'] ?? 0), (int) ($point['minute'] ?? 0));
    }

    /**
     * reviews: authorAttribution.displayName, text.text und publishTime (RFC 3339)
     * → author_name, text und Unix-Zeit wie in der alten API.
     */
    private function mapReviews(array $reviews): array
    {
        $mapped = [];

        foreach ($reviews as $review) {
            $time = isset($review['publishTime']) ? strtotime((string) $review['publishTime']) : false;

            $mapped[] = [
                'author_name' => $review['authorAttribution']['displayName'] ?? 'Anonym',
                'rating' => $review['rating'] ?? null,
                'text' => $review['text']['text'] ?? ($review['originalText']['text'] ?? null),
                'time' => $time === false ? null : $time,
            ];
        }

        return $mapped;
    }

    /**
     * photos: der Ressourcenname ("places/<id>/photos/<ref>") tritt an die
     * Stelle der alten photo_reference.
     */
    private function mapPhotos(array $photos): array
    {
        $mapped = [];

        foreach ($photos as $photo) {
            if (empty($photo['name'])) {
                continue;
            }

            $mapped[] = ['photo_reference' => $photo['name']];
        }

        return $mapped;
    }

    /**
     * Place Photo (Places API New) — GET /v1/{photoName}/media.
     * Gibt den Bild-Inhalt als String zurück (Http folgt der Weiterleitung).
     */
    private function downloadPhoto(string $photoReference, int $maxWidth = 1200): ?string
    {
        $response = Http::connectTimeout(5)->timeout(20)
            ->withHeaders(['X-Goog-Api-Key' => $this->apiKey])
            ->get(self::BASE_URL.'/'.ltrim($photoReference, '/').'/media', [
                'maxWidthPx' => $maxWidth,
            ]);
        $this->apiCalls++;
        $this->photoCalls++;

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    // ════════════════════════════════════════════════════════════════════
    // Daten speichern
    // ════════════════════════════════════════════════════════════════════

    /**
     * Speichert eine Firma mit allen Relationen.
     */
    private function saveCompany(array $details, Model $searchCity): ?Company
    {
        $address = $this->parseAddress($details['address_components'] ?? []);

        // City auflösen: Google-Adresse → unsere Cities-Tabelle
        $cityId = $this->resolveCity($address, $searchCity);

        // Slug generieren (mit Duplikat-Handling)
        $slug = $this->generateUniqueSlug($details['name'] ?? 'firma');

        try {
            $company = Company::create([
                'name' => $details['name'] ?? 'Unbekannt',
                'slug' => $slug,
                'street' => $address['street'] ?? null,
                'house_no' => $address['house_no'] ?? null,
                'zipcode' => $address['zipcode'] ?? null,
                'city_id' => $cityId,
                'tel' => $details['formatted_phone_number'] ?? null,
                'website' => isset($details['website']) ? Str::limit($details['website'], 250) : null,
                'google_places_id' => $details['place_id'],
                'rating' => $details['rating'] ?? 0,
                'rating_count' => $details['user_ratings_total'] ?? 0,
                'is_premium' => false,
                'is_verified' => false,
                'is_active' => true,
                'google_added_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Unique-Constraint (google_places_id) oder andere DB-Fehler
            return null;
        }

        // Öffnungszeiten
        if (! empty($details['opening_hours']['periods'])) {
            $this->saveOpeningHours($company, $details['opening_hours']['periods']);
        }

        // Reviews
        if (! $this->option('skip-reviews') && ! empty($details['reviews'])) {
            $this->saveReviews($company, $details['reviews']);
        }

        // Kategorien zuordnen
        $this->assignCategories($company, $details['types'] ?? []);

        // Fotos: nur wenn keine Website vorhanden (Firmen MIT Website brauchen keine Google-Fotos)
        // --skip-photos deaktiviert Fotos komplett
        $hasWebsite = ! empty($company->website);
        if (! $this->option('skip-photos') && ! $hasWebsite && ! empty($details['photos'])) {
            $maxPhotos = (int) $this->option('max-photos');
            $this->savePhotos($company, $details['photos'], $maxPhotos);
        }

        return $company;
    }

    /**
     * Öffnungszeiten speichern.
     * Google: 0=Sonntag, 1=Montag, ..., 6=Samstag
     * Unser System: 0=Montag, ..., 6=Sonntag
     */
    private function saveOpeningHours(Company $company, array $periods): void
    {
        // Google kann einen Eintrag mit nur open.day=0 (Sonntag) und keinem close liefern
        // → "24h geöffnet". Wir behandeln das separat.
        $dayData = [];

        foreach ($periods as $period) {
            $googleDay = $period['open']['day'] ?? null;
            if ($googleDay === null) {
                continue;
            }

            // Google → Unser Day-Mapping
            $dayOfWeek = $googleDay === 0 ? 6 : $googleDay - 1;

            $opensAt = $this->formatGoogleTime($period['open']['time'] ?? null);
            $closesAt = $this->formatGoogleTime($period['close']['time'] ?? null);

            $dayData[$dayOfWeek] = [
                'company_id' => $company->id,
                'day_of_week' => $dayOfWeek,
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
                'is_closed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Fehlende Tage als "geschlossen" einfügen
        for ($day = 0; $day <= 6; $day++) {
            if (! isset($dayData[$day])) {
                $dayData[$day] = [
                    'company_id' => $company->id,
                    'day_of_week' => $day,
                    'opens_at' => null,
                    'closes_at' => null,
                    'is_closed' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Batch-Insert (schneller als einzelne creates)
        CompanyOpeningHour::upsert(
            array_values($dayData),
            ['company_id', 'day_of_week'],
            ['opens_at', 'closes_at', 'is_closed', 'updated_at']
        );
    }

    /**
     * Reviews speichern.
     * Google-Reviews werden als "approved" importiert (Import = moderiert).
     * Rating-Berechnung wird am Ende per Bulk-SQL gemacht (verhindert N+1 durch Model-Events).
     */
    private function saveReviews(Company $company, array $reviews): void
    {
        $batch = [];

        foreach ($reviews as $review) {
            $createdAt = now();
            if (isset($review['time']) && is_numeric($review['time'])) {
                $createdAt = DB::raw('FROM_UNIXTIME('.(int) $review['time'].')');
            }

            $batch[] = [
                'company_id' => $company->id,
                'user_id' => null,
                'author_name' => $review['author_name'] ?? 'Anonym',
                'rating' => isset($review['rating']) ? round((float) $review['rating'], 1) : null,
                'title' => null,
                'body' => $review['text'] ?? null,
                'is_approved' => true,
                'approved_at' => now(),
                'moderation_status' => Review::STATUS_APPROVED,
                'moderation_note' => null,
                'moderated_by' => 'Google Import',
                'created_at' => $createdAt,
                'updated_at' => now(),
            ];
        }

        if (! empty($batch)) {
            // Direkt in DB schreiben statt Model::create() — umgeht Model-Events
            // (verhindert N recalculateRating()-Aufrufe pro Review)
            DB::table('reviews')->insert($batch);

            // Rating einmalig nachberechnen
            $company->recalculateRating();
        }
    }

    /**
     * Kategorien zuordnen via config/category-mapping.php.
     * Google-Typen → Deutsche Kategorien über source_key.
     */
    private function assignCategories(Company $company, array $googleTypes): void
    {
        $categoryIds = [];

        foreach ($googleTypes as $type) {
            // Ignorierte generische Typen überspringen
            if (in_array($type, $this->ignoredTypes, true)) {
                continue;
            }

            if (isset($this->categoryMap[$type])) {
                $categoryIds[$this->categoryMap[$type]] = true;
            }
        }

        // Fallback: Wenn keine spezifische Kategorie gefunden → "Sonstiges"
        if (empty($categoryIds) && $this->fallbackCategoryId) {
            $categoryIds[$this->fallbackCategoryId] = true;
        }

        if (! empty($categoryIds)) {
            $company->categories()->sync(array_keys($categoryIds));
        }
    }

    /**
     * Fotos herunterladen und über Spatie Media Library anhängen.
     */
    private function savePhotos(Company $company, array $photos, int $maxPhotos = 5): void
    {
        $count = 0;

        foreach ($photos as $photo) {
            if ($count >= $maxPhotos) {
                break;
            }

            $photoRef = $photo['photo_reference'] ?? null;
            if (! $photoRef) {
                continue;
            }

            try {
                $imageData = $this->downloadPhoto($photoRef);
                if (! $imageData) {
                    continue;
                }

                // Temporäre Datei für Spatie Media Library
                $tempPath = sys_get_temp_dir().'/'.uniqid('gp_').'.jpg';
                file_put_contents($tempPath, $imageData);

                $company->addMedia($tempPath)
                    ->toMediaCollection('gallery');

                $count++;
            } catch (\Exception $e) {
                // Foto-Import ist nicht kritisch — überspringen
                continue;
            }
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // Adress-Parsing & City-Resolution
    // ════════════════════════════════════════════════════════════════════

    /**
     * Extrahiert Adresskomponenten aus Google's address_components Array.
     */
    private function parseAddress(array $components): array
    {
        $data = [];

        foreach ($components as $component) {
            $type = $component['types'][0] ?? null;
            if (! $type) {
                continue;
            }

            match ($type) {
                'route' => $data['street'] = $component['long_name'],
                'street_number' => $data['house_no'] = $component['long_name'],
                'postal_code' => $data['zipcode'] = $component['long_name'],
                'locality' => $data['city'] = $component['long_name'],
                'administrative_area_level_1' => $data['state'] = $component['long_name'],
                'administrative_area_level_2' => $data['district'] = $component['long_name'],
                'administrative_area_level_3' => $data['community'] = $component['long_name'],
                default => null,
            };
        }

        return $data;
    }

    /**
     * ISO-3166-1-Kuerzel des Place aus der Adresskomponente 'country' (short_name),
     * null wenn Google keine liefert.
     */
    private function placeCountry(array $components): ?string
    {
        foreach ($components as $component) {
            if (in_array('country', $component['types'] ?? [], true) && ($component['short_name'] ?? '') !== '') {
                return strtoupper($component['short_name']);
            }
        }

        return null;
    }

    /**
     * Zielland der Laenderquelle (--country) als ISO-3166-1-Kuerzel.
     */
    private function regionCode(): string
    {
        return strtoupper($this->country);
    }

    /**
     * Findet oder erstellt die City basierend auf Google-Adressdaten.
     * Fallback: Die Stadt die für die Suche verwendet wurde.
     */
    private function resolveCity(array $address, Model $searchCity): int
    {
        return match ($this->country) {
            'ch' => $this->resolveChCity($address, $searchCity),
            'fr' => $this->resolveFrCity($address, $searchCity),
            default => $this->resolveDeCity($address, $searchCity),
        };
    }

    private function resolveDeCity(array $address, Model $searchCity): int
    {
        $cityName = $address['city'] ?? null;
        $zipcode = $address['zipcode'] ?? null;
        // Nur echte Bundeslaender uebernehmen, Regionen wie "Allgäu" nicht (#18)
        $state = app(CityStateResolver::class)->forGermanCity($address['state'] ?? null, $zipcode);

        if (City::isPlaceholderName($cityName)) {
            return $searchCity->id;
        }

        if ($state) {
            $city = City::where('name', $cityName)
                ->where('administrative_area_level_1', $state)
                ->first();
            if ($city) {
                return $city->id;
            }
        }

        if ($zipcode) {
            $city = City::where('name', $cityName)
                ->where('zipcode', $zipcode)
                ->first();
            if ($city) {
                return $city->id;
            }
        }

        $city = City::where('name', $cityName)->first();
        if ($city) {
            return $city->id;
        }

        $slug = Str::slug($cityName);
        $city = City::where('slug', $slug)->first();
        if ($city) {
            return $city->id;
        }

        $city = City::create([
            'name' => $cityName,
            'zipcode' => $zipcode,
            'administrative_area_level_1' => $state,
            'community' => $address['community'] ?? null,
        ]);

        return $city->id;
    }

    private function resolveChCity(array $address, Model $searchCity): int
    {
        $cityName = $address['city'] ?? null;
        $zipcode = $address['zipcode'] ?? null;

        if (City::isPlaceholderName($cityName)) {
            return $searchCity->id;
        }

        if ($zipcode) {
            $city = ChCity::where('name', $cityName)
                ->where('postal_code', $zipcode)
                ->first();
            if ($city) {
                return $city->id;
            }
        }

        $city = ChCity::where('name', $cityName)->first();
        if ($city) {
            return $city->id;
        }

        return $searchCity->id;
    }

    private function resolveFrCity(array $address, Model $searchCity): int
    {
        $cityName = $address['city'] ?? null;
        $zipcode = $address['zipcode'] ?? null;

        if (City::isPlaceholderName($cityName)) {
            return $searchCity->id;
        }

        if ($zipcode) {
            $city = FrCity::where('name', $cityName)
                ->where('postal_code', $zipcode)
                ->first();
            if ($city) {
                return $city->id;
            }
        }

        $city = FrCity::where('name', $cityName)->first();
        if ($city) {
            return $city->id;
        }

        return $searchCity->id;
    }

    // ════════════════════════════════════════════════════════════════════
    // Helper
    // ════════════════════════════════════════════════════════════════════

    /**
     * Baut die Kategorie-Map: Google-Type → category_id.
     * Nutzt source_key aus config/category-mapping.php.
     */
    private function buildCategoryMap(): void
    {
        $categories = Category::whereNotNull('source_key')
            ->where('source_key', '!=', '_parent')
            ->where('source_key', '!=', '_fallback')
            ->get(['id', 'source_key']);

        foreach ($categories as $cat) {
            $keys = explode(',', $cat->source_key);
            foreach ($keys as $key) {
                $this->categoryMap[trim($key)] = $cat->id;
            }
        }

        // Fallback-Kategorie laden
        $fallback = Category::where('source_key', '_fallback')->first();
        $this->fallbackCategoryId = $fallback?->id;

        $this->line('Kategorie-Map: '.count($this->categoryMap).' Google-Types → '.$categories->count().' Kategorien');
    }

    /**
     * Google Zeitformat "0800" → "08:00:00"
     */
    private function formatGoogleTime(?string $time): ?string
    {
        if (empty($time)) {
            return null;
        }

        $time = str_pad($time, 4, '0', STR_PAD_LEFT);
        $hours = substr($time, 0, 2);
        $minutes = substr($time, 2, 2);

        if (! is_numeric($hours) || ! is_numeric($minutes)) {
            return null;
        }

        return sprintf('%02d:%02d:00', (int) $hours, (int) $minutes);
    }

    /**
     * Generiert einen eindeutigen Slug mit Suffix-Handling.
     */
    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        if (empty($baseSlug)) {
            $baseSlug = 'firma-'.uniqid();
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (Company::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Geschätzte API-Kosten basierend auf Anzahl der Calls.
     * Places API (New), Preise pro 1000 Aufrufe (Stand 2025):
     * - Text Search (ID Only): $0 — unsere Such-FieldMask ist places.id
     * - Place Details (Enterprise + Atmosphere): ~$25
     * - Place Photo: ~$7
     */
    private function estimateCost(): string
    {
        $estimated = ($this->detailCalls / 1000) * 25 + ($this->photoCalls / 1000) * 7;

        return '~$'.number_format($estimated, 2);
    }

    /**
     * Städte laden mit optionalen Filtern.
     */
    private function loadCities(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->country === 'ch') {
            return $this->loadChCities();
        }

        if ($this->country === 'fr') {
            return $this->loadFrCities();
        }

        $query = City::query()->orderBy('name');

        if ($cityFilter = $this->option('city')) {
            $query->where(function ($q) use ($cityFilter) {
                $q->where('name', 'like', "%{$cityFilter}%")
                    ->orWhere('zipcode', $cityFilter)
                    ->orWhere('id', $cityFilter);
            });
        }

        if ($stateFilter = $this->option('state')) {
            $query->where('administrative_area_level_1', $stateFilter);
        }

        return $query->get();
    }

    private function loadChCities(): \Illuminate\Database\Eloquent\Collection
    {
        $query = ChCity::query()->orderBy('name');

        if ($cityFilter = $this->option('city')) {
            $query->where(function ($q) use ($cityFilter) {
                $q->where('name', 'like', "%{$cityFilter}%")
                    ->orWhere('zipcode', $cityFilter)
                    ->orWhere('municipality_name', 'like', "%{$cityFilter}%")
                    ->orWhere('id', $cityFilter);
            });
        }

        if ($stateFilter = $this->option('state')) {
            $query->where('canton', $stateFilter);
        }

        return $query->get();
    }

    private function loadFrCities(): \Illuminate\Database\Eloquent\Collection
    {
        $query = FrCity::query()->orderBy('name');

        if ($cityFilter = $this->option('city')) {
            $query->where(function ($q) use ($cityFilter) {
                $q->where('name', 'like', "%{$cityFilter}%")
                    ->orWhere('postal_code', $cityFilter)
                    ->orWhere('id', $cityFilter);
            });
        }

        if ($stateFilter = $this->option('state')) {
            $query->where('region_id', $stateFilter);
        }

        return $query->get();
    }

    private function getCityName(Model $city): string
    {
        return $city instanceof ChCity ? $city->name : $city->name;
    }

    private function getCityZipcode(Model $city): string
    {
        $zipcode = match (true) {
            $city instanceof ChCity, $city instanceof FrCity => $city->postal_code,
            default => $city->zipcode,
        };

        return $zipcode ?? '';
    }
}
