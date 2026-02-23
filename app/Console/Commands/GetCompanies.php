<?php

namespace App\Console\Commands;

use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyOpeningHour;
use App\Models\Portal\Review;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Stancl\Tenancy\Concerns\HasATenantArgument;
use Stancl\Tenancy\Concerns\TenantAwareCommand;

/**
 * Importiert Firmen aus der Google Places API in die aktive Tenant-DB.
 *
 * Kostenoptimierung:
 * - Dedup via google_places_id VOR Detail-Call (teuerster Call)
 * - Field-Mask auf Place Details (nur angeforderte Felder werden berechnet)
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
        {--state= : Nur Städte in einem Bundesland (z.B. "Bayern")}
        {--limit=0 : Max. neue Firmen pro Stadt+Query (0 = unbegrenzt)}
        {--skip-photos : Fotos NICHT herunterladen (Standard: Fotos AN)}
        {--skip-reviews : Reviews nicht importieren}
        {--max-photos=5 : Max. Fotos pro Firma (Standard: 5)}
        {--recheck : Bereits gecheckte Städte nochmal durchgehen}
        {--dry-run : Nur suchen und anzeigen, nicht speichern}';

    protected $description = 'Importiert Firmen aus der Google Places API in den aktuellen Tenant';

    private const BASE_URL = 'https://maps.googleapis.com/maps/api/place';

    // Place Details: nur Felder die wir brauchen
    // Basic (kostenlos mit Text Search): name, place_id, types, formatted_address
    // Contact ($3/1000): formatted_phone_number, website, opening_hours
    // Atmosphere ($5/1000): rating, reviews, user_ratings_total
    private const DETAIL_FIELDS = 'name,formatted_phone_number,website,rating,place_id,address_components,opening_hours,reviews,photos,types,user_ratings_total';

    private string $apiKey;

    /** @var array<string, int> Google-Type → category_id */
    private array $categoryMap = [];

    /** @var string[] Ignorierte Google-Typen */
    private array $ignoredTypes = [];

    /** @var int|null Fallback-Kategorie-ID ("Sonstiges") */
    private ?int $fallbackCategoryId = null;

    private int $apiCalls = 0;
    private int $limit;
    private bool $isDryRun;

    public function handle(): int
    {
        ignore_user_abort(true);
        set_time_limit(0);

        // ── Tenant-Kontext setzen (wenn --tenant angegeben) ──
        if ($tenantId = $this->option('tenant')) {
            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                $this->error("Tenant mit ID {$tenantId} nicht gefunden.");
                $this->line('Verfügbare Tenants:');
                Tenant::all()->each(fn ($t) => $this->line("  ID {$t->id} — {$t->name} (UUID: {$t->uuid})"));
                return self::FAILURE;
            }

            tenancy()->initialize($tenant);
            $this->info("Tenant: {$tenant->name} (ID {$tenant->id}, UUID {$tenant->uuid})");
            $this->newLine();
        } elseif (! tenancy()->initialized) {
            $this->error('Kein Tenant-Kontext aktiv. Nutze eine der folgenden Varianten:');
            $this->line('  1) php artisan tenants:import-google --tenant=3 --query="Sanitär"');
            $this->line('  2) php artisan tenants:run "tenants:import-google --query=Sanitär"');
            return self::FAILURE;
        }

        // ── API Key prüfen ──
        $this->apiKey = env('GOOGLE_PLACES_API_KEY', '');
        if (empty($this->apiKey)) {
            $this->error('GOOGLE_PLACES_API_KEY ist nicht in .env gesetzt.');
            $this->line('Füge folgende Zeile zu .env hinzu:');
            $this->line('GOOGLE_PLACES_API_KEY=dein_api_key');
            return self::FAILURE;
        }

        // ── Optionen ──
        $queries = $this->option('query');
        if (empty($queries)) {
            $this->error('Mindestens ein --query Parameter erforderlich.');
            $this->line('Beispiel: php artisan tenants:import-google --query="Sanitär"');
            return self::FAILURE;
        }

        $this->limit = (int) $this->option('limit');
        $this->isDryRun = (bool) $this->option('dry-run');

        // ── Kategorie-Map aufbauen ──
        $this->buildCategoryMap();
        $this->ignoredTypes = config('category-mapping.ignored', []);

        if ($this->isDryRun) {
            $this->warn('🔍 DRY-RUN — keine Daten werden gespeichert');
            $this->newLine();
        }

        $this->info('═══ Google Places Import ═══');
        $this->info('Suchbegriffe: ' . implode(', ', $queries));
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
            $this->info("Städte: {$cities->count()}" . ($recheck ? ' (Recheck-Modus)' : ''));
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
                $this->line("[{$cityIndex}/{$cityCount}] {$city->name} ({$city->zipcode}) — {$elapsed}s elapsed, {$this->apiCalls} API-Calls, ~{$this->estimateCost()}");

                $searchTerm = "{$query} in {$city->zipcode} {$city->name}";

                try {
                    $results = $this->textSearch($searchTerm);
                } catch (\Exception $e) {
                    $this->warn("  ✗ Text Search fehlgeschlagen: {$e->getMessage()}");
                    $totalErrors++;
                    continue;
                }

                if (empty($results)) {
                    $this->line("  → 0 Ergebnisse");

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

                    if (! $details) {
                        $totalErrors++;
                        continue;
                    }

                    // Domain-Blacklist: Spam/Konkurrenz-Domains überspringen
                    if (! empty($details['website']) && Str::contains($details['website'], 'fliesenleger.io', true)) {
                        $placeName = $details['displayName']['text'] ?? $details['name'] ?? '?';
                        $this->line("    ⊘ Übersprungen (Domain-Blacklist): {$placeName} — {$details['website']}");
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
     * Text Search API — findet Places zu einem Suchbegriff.
     * Paginiert automatisch (max. 3 Seiten × 20 = 60 Ergebnisse).
     *
     * @return array<int, array>
     */
    private function textSearch(string $query): array
    {
        $allResults = [];
        $nextPageToken = null;
        $page = 0;

        do {
            $page++;

            if ($nextPageToken) {
                // Google verlangt ~2s Wartezeit bevor next_page_token gültig ist
                sleep(2);
            }

            $params = [
                'query' => $query,
                'key' => $this->apiKey,
                'language' => 'de',
            ];

            if ($nextPageToken) {
                $params['pagetoken'] = $nextPageToken;
            }

            $response = Http::connectTimeout(5)->timeout(15)->get(self::BASE_URL . '/textsearch/json', $params);
            $this->apiCalls++;

            if (! $response->successful()) {
                $this->warn("  API-Fehler Text Search: HTTP {$response->status()}");
                break;
            }

            $json = $response->json();

            if (($json['status'] ?? '') !== 'OK' && ($json['status'] ?? '') !== 'ZERO_RESULTS') {
                if (($json['status'] ?? '') === 'OVER_QUERY_LIMIT') {
                    $this->error('  API-Limit erreicht! Warte oder erhöhe dein Quota.');
                    return $allResults;
                }
                $this->warn("  API Status: " . ($json['status'] ?? 'UNKNOWN'));
                if (isset($json['error_message'])) {
                    $this->warn("  " . $json['error_message']);
                }
                break;
            }

            $results = $json['results'] ?? [];
            $allResults = array_merge($allResults, $results);
            $nextPageToken = $json['next_page_token'] ?? null;

            // Max 3 Seiten (Google-Limit)
            if ($page >= 3) {
                break;
            }
        } while ($nextPageToken);

        return $allResults;
    }

    /**
     * Place Details API — holt Detail-Infos zu einer Place-ID.
     * Verwendet Field-Mask für Kostenoptimierung.
     */
    private function getPlaceDetails(string $placeId): ?array
    {
        $response = Http::connectTimeout(5)->timeout(15)->get(self::BASE_URL . '/details/json', [
            'place_id' => $placeId,
            'key' => $this->apiKey,
            'language' => 'de',
            'fields' => self::DETAIL_FIELDS,
        ]);
        $this->apiCalls++;

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        if (($json['status'] ?? '') !== 'OK') {
            return null;
        }

        return $json['result'] ?? null;
    }

    /**
     * Place Photo API — lädt ein Foto herunter.
     * Gibt den Bild-Inhalt als String zurück.
     */
    private function downloadPhoto(string $photoReference, int $maxWidth = 1200): ?string
    {
        $response = Http::connectTimeout(5)->timeout(20)->get(self::BASE_URL . '/photo', [
            'photoreference' => $photoReference,
            'maxwidth' => $maxWidth,
            'key' => $this->apiKey,
        ]);
        $this->apiCalls++;

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
    private function saveCompany(array $details, City $searchCity): ?Company
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
                $createdAt = gmdate('Y-m-d H:i:s', (int) $review['time']);
            }

            $batch[] = [
                'company_id' => $company->id,
                'user_id' => null,
                'author_name' => $review['author_name'] ?? 'Anonym',
                'rating' => isset($review['rating']) ? round((float) $review['rating'], 1) : null,
                'title' => null,
                'body' => $review['text'] ?? null,
                'is_approved' => true,
                'approved_at' => $createdAt,
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
                $tempPath = sys_get_temp_dir() . '/' . uniqid('gp_') . '.jpg';
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
     * Findet oder erstellt die City basierend auf Google-Adressdaten.
     * Fallback: Die Stadt die für die Suche verwendet wurde.
     */
    private function resolveCity(array $address, City $searchCity): int
    {
        $cityName = $address['city'] ?? null;
        $state = $address['state'] ?? null;
        $zipcode = $address['zipcode'] ?? null;

        if (! $cityName) {
            return $searchCity->id;
        }

        // Exakter Match: Name + Bundesland
        if ($state) {
            $city = City::where('name', $cityName)
                ->where('administrative_area_level_1', $state)
                ->first();
            if ($city) {
                return $city->id;
            }
        }

        // Fallback: Name + PLZ
        if ($zipcode) {
            $city = City::where('name', $cityName)
                ->where('zipcode', $zipcode)
                ->first();
            if ($city) {
                return $city->id;
            }
        }

        // Fallback: Nur Name (erste gewinnt)
        $city = City::where('name', $cityName)->first();
        if ($city) {
            return $city->id;
        }

        // Fallback: Slug-Match (verhindert Duplicate-Entry auf unique slug)
        $slug = Str::slug($cityName);
        $city = City::where('slug', $slug)->first();
        if ($city) {
            return $city->id;
        }

        // Stadt existiert nicht → erstellen
        $city = City::create([
            'name' => $cityName,
            'zipcode' => $zipcode,
            'administrative_area_level_1' => $state,
            'community' => $address['community'] ?? null,
        ]);

        return $city->id;
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

        $this->line("Kategorie-Map: " . count($this->categoryMap) . " Google-Types → " . $categories->count() . " Kategorien");
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
            $baseSlug = 'firma-' . uniqid();
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
     * Google Places Pricing (Stand 2025):
     * - Text Search: $32/1000
     * - Place Details: $17/1000
     * - Place Photos: $7/1000
     */
    private function estimateCost(): string
    {
        // Grobe Schätzung: ~$25/1000 Calls im Mix
        $estimated = ($this->apiCalls / 1000) * 25;
        return '~$' . number_format($estimated, 2);
    }

    /**
     * Städte laden mit optionalen Filtern.
     */
    private function loadCities(): \Illuminate\Database\Eloquent\Collection
    {
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
}
