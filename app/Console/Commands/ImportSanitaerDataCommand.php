<?php

namespace App\Console\Commands;

use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyOpeningHour;
use App\Models\Portal\Review;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importiert Daten aus der Quelldatenbank "sanitaer" in die aktive Tenant-DB.
 *
 * Voraussetzungen:
 * - CategoryMappingSeeder wurde auf dem Tenant ausgeführt (source_keys vorhanden)
 * - Quelldatenbank "sanitaer" ist lokal erreichbar (root@localhost, kein Passwort)
 * - Fotos liegen unter PHOTO_SOURCE_PATH
 *
 * Nutzung:
 *   php artisan tenants:run "import:sanitaer"
 *   php artisan tenants:run "import:sanitaer --step=cities"
 *   php artisan tenants:run "import:sanitaer --fresh"  (löscht bestehende Daten vorher)
 */
class ImportSanitaerDataCommand extends Command
{
    protected $signature = 'import:sanitaer
        {--step= : Nur einen Schritt ausführen (cities,companies,reviews,hours,categories,photos)}
        {--fresh : Bestehende importierte Daten vorher löschen}
        {--force : Keine Bestätigung bei --fresh}
        {--chunk=500 : Chunk-Größe für Batch-Inserts}
        {--skip-photos : Fotos überspringen (spart Zeit bei Tests)}';

    protected $description = 'Importiert Daten aus der sanitaer-Quelldatenbank in den aktuellen Tenant';

    private const SOURCE_DB = 'sanitaer';
    private const PHOTO_SOURCE_PATH = '/Users/enes/Desktop/sites/GooglePlacesApi/storage/app/private/public/photos/';

    /** @var array<string, int> source city string → target city_id */
    private array $cityMap = [];

    /** @var array<string, int> source category slug → target category_id */
    private array $categoryMap = [];

    /** @var array<int, int> source company_id → target company_id */
    private array $companyMap = [];

    private int $chunkSize;

    public function handle(): int
    {
        $this->chunkSize = (int) $this->option('chunk');
        $step = $this->option('step');

        // Prüfe ob Quelldatenbank erreichbar ist
        try {
            DB::connection('sanitaer_source')->getPdo();
        } catch (\Exception $e) {
            $this->error("Quelldatenbank nicht erreichbar: {$e->getMessage()}");
            $this->line('Füge folgende DB-Connection in config/database.php hinzu:');
            $this->line("'sanitaer_source' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'sanitaer', 'username' => 'root', 'password' => '']");
            return self::FAILURE;
        }

        // MySQL-Session-Timezone auf UTC setzen um DST-Lücken-Fehler zu vermeiden
        // (z.B. '2025-03-30 02:23:47' existiert in Europe/Berlin nicht)
        // Muss auf ALLEN Connections gesetzt werden — Default (=Tenant) + sanitaer_source
        DB::unprepared("SET time_zone = '+00:00'");
        DB::connection('sanitaer_source')->unprepared("SET time_zone = '+00:00'");
        $this->line('MySQL-Session-Timezone: UTC (+00:00) auf allen Connections gesetzt.');

        $this->info('═══ Import aus sanitaer-Quelldatenbank ═══');
        $this->newLine();

        if ($this->option('fresh')) {
            $this->freshClean();
        }

        $steps = $step ? [$step] : ['cities', 'companies', 'reviews', 'hours', 'categories', 'photos'];

        foreach ($steps as $s) {
            match ($s) {
                'cities' => $this->importCities(),
                'companies' => $this->importCompanies(),
                'reviews' => $this->importReviews(),
                'hours' => $this->importOpeningHours(),
                'categories' => $this->importCategoryAssignments(),
                'photos' => $this->importPhotos(),
                default => $this->warn("Unbekannter Schritt: {$s}"),
            };
        }

        $this->newLine();
        $this->info('═══ Import abgeschlossen ═══');

        return self::SUCCESS;
    }

    /**
     * Löscht importierte Daten (nicht manuell erstellte).
     */
    private function freshClean(): void
    {
        if (! $this->option('force') && ! $this->confirm('ACHTUNG: Alle importierten Daten werden gelöscht. Fortfahren?')) {
            return;
        }

        $this->warn('Lösche bestehende Daten...');

        // FK-Checks temporär deaktivieren für DELETE-Reihenfolge
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Spatie Media löschen (Fotos)
        $mediaCount = DB::table('media')->where('model_type', 'App\\Models\\Portal\\Company')->count();
        if ($mediaCount > 0) {
            DB::table('media')->where('model_type', 'App\\Models\\Portal\\Company')->delete();
            $this->line("  {$mediaCount} Media-Einträge gelöscht.");
        }

        // Reviews, Opening Hours, Pivot, Companies, Cities
        // DELETE statt TRUNCATE — Tenant-User hat kein DROP-Privilege (SEC-3)
        Review::query()->delete();
        CompanyOpeningHour::query()->delete();
        DB::table('category_company')->delete();
        Company::query()->delete();
        City::query()->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('✓ Bestehende Daten gelöscht.');
        $this->newLine();
    }

    // ════════════════════════════════════════════════════════════════════
    // STEP 1: Cities
    // ════════════════════════════════════════════════════════════════════

    private function importCities(): void
    {
        $this->info('── Schritt 1/6: Cities importieren ──');

        $sourceCount = $this->sourceQuery('cities')->count();
        $this->line("Quelle: {$sourceCount} Städte");

        $bar = $this->output->createProgressBar($sourceCount);
        $bar->start();

        $imported = 0;
        $skipped = 0;

        $this->sourceQuery('cities')
            ->orderBy('id')
            ->chunk($this->chunkSize, function ($rows) use (&$imported, &$skipped, $bar) {
                $batch = [];

                foreach ($rows as $row) {
                    $slug = Str::slug($row->city);
                    if (empty($slug)) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    $batch[] = [
                        'name' => $row->city,
                        'zipcode' => $row->zipcode,
                        'administrative_area_level_1' => $row->state,
                        'latitude' => $row->latitude,
                        'longitude' => $row->longitude,
                        'community' => $row->community,
                        'slug' => $slug,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (! empty($batch)) {
                    City::upsert(
                        $batch,
                        ['name', 'administrative_area_level_1'],
                        ['zipcode', 'latitude', 'longitude', 'community', 'slug']
                    );
                    $imported += count($batch);
                }

                $bar->advance(count($rows));
            });

        $bar->finish();
        $this->newLine();

        // City-Map für Company-Import aufbauen
        $this->buildCityMap();

        $this->info("✓ Cities: {$imported} importiert, {$skipped} übersprungen.");
        $this->newLine();
    }

    private function buildCityMap(): void
    {
        // Map: "Stadtname" → city_id (für Company city-String → FK Lookup)
        // Bei Duplikaten (gleicher Name, anderes Bundesland) nehmen wir die erste
        $cities = City::all(['id', 'name', 'zipcode']);
        foreach ($cities as $city) {
            // Primär nach Name+PLZ matchen
            $key = mb_strtolower($city->name) . '|' . $city->zipcode;
            $this->cityMap[$key] = $city->id;

            // Fallback nur nach Name (erste gewinnt)
            $nameKey = mb_strtolower($city->name);
            if (! isset($this->cityMap[$nameKey])) {
                $this->cityMap[$nameKey] = $city->id;
            }
        }
    }

    private function resolveCityId(object $company): ?int
    {
        if (empty($company->city)) {
            return null;
        }

        $nameKey = mb_strtolower(trim($company->city));

        // Versuch 1: Name + PLZ
        if ($company->zipcode) {
            $key = $nameKey . '|' . $company->zipcode;
            if (isset($this->cityMap[$key])) {
                return $this->cityMap[$key];
            }
        }

        // Versuch 2: Nur Name
        return $this->cityMap[$nameKey] ?? null;
    }

    // ════════════════════════════════════════════════════════════════════
    // STEP 2: Companies
    // ════════════════════════════════════════════════════════════════════

    private function importCompanies(): void
    {
        $this->info('── Schritt 2/6: Companies importieren ──');

        if (empty($this->cityMap)) {
            $this->buildCityMap();
        }

        $sourceCount = $this->sourceQuery('companies')->count();
        $this->line("Quelle: {$sourceCount} Firmen");

        $bar = $this->output->createProgressBar($sourceCount);
        $bar->start();

        $imported = 0;
        $noCity = 0;
        $slugs = []; // Für Duplikat-Handling

        // Bestehende Slugs laden
        Company::pluck('slug')->each(function ($slug) use (&$slugs) {
            $slugs[$slug] = true;
        });

        $this->sourceQuery('companies')
            ->orderBy('id')
            ->chunk($this->chunkSize, function ($rows) use (&$imported, &$noCity, &$slugs, $bar) {
                $batch = [];

                foreach ($rows as $row) {
                    $cityId = $this->resolveCityId($row);
                    if (! $cityId) {
                        $noCity++;
                    }

                    // Slug generieren mit Duplikat-Handling
                    $baseSlug = Str::slug($row->name);
                    if (empty($baseSlug)) {
                        $baseSlug = 'firma-' . $row->id;
                    }
                    $slug = $baseSlug;
                    $suffix = 2;
                    while (isset($slugs[$slug])) {
                        $slug = "{$baseSlug}-{$suffix}";
                        $suffix++;
                    }
                    $slugs[$slug] = true;

                    $batch[] = [
                        'name' => $row->name,
                        'slug' => $slug,
                        'description' => $row->description,
                        'street' => $row->street,
                        'house_no' => $row->houseno,
                        'zipcode' => $row->zipcode,
                        'city_id' => $cityId,
                        'tel' => $row->tel,
                        'email' => $row->email,
                        'website' => $row->website,
                        'google_places_id' => $row->google_places_id,
                        'rating' => $row->rating ?? 0,
                        'rating_count' => 0, // wird nach Reviews-Import berechnet
                        'is_premium' => false,
                        'is_verified' => false,
                        'is_active' => true,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => now(),
                    ];

                    // Map aufbauen: source_id → wir müssen nach dem Insert die ID kennen
                    // Da wir google_places_id als unique haben, mappen wir darüber
                }

                if (! empty($batch)) {
                    Company::upsert(
                        $batch,
                        ['google_places_id'],
                        ['name', 'slug', 'description', 'street', 'house_no', 'zipcode', 'city_id', 'tel', 'email', 'website', 'rating', 'is_active', 'updated_at']
                    );
                    $imported += count($batch);
                }

                $bar->advance(count($rows));
            });

        $bar->finish();
        $this->newLine();

        // Company-Map aufbauen: source_id → target_id via google_places_id
        $this->buildCompanyMap();

        $this->info("✓ Companies: {$imported} importiert, {$noCity} ohne City-Zuordnung.");
        $this->newLine();
    }

    private function buildCompanyMap(): void
    {
        // Source companies: id → google_places_id
        $sourceMap = [];
        $this->sourceQuery('companies')
            ->select('id', 'google_places_id')
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (&$sourceMap) {
                foreach ($rows as $row) {
                    $sourceMap[$row->google_places_id] = $row->id;
                }
            });

        // Target companies: google_places_id → id
        $targetMap = Company::pluck('id', 'google_places_id')->toArray();

        // Merge: source_id → target_id
        foreach ($sourceMap as $gpId => $sourceId) {
            if (isset($targetMap[$gpId])) {
                $this->companyMap[$sourceId] = $targetMap[$gpId];
            }
        }

        $this->line("  Company-Map: " . count($this->companyMap) . " Zuordnungen");
    }

    // ════════════════════════════════════════════════════════════════════
    // STEP 3: Reviews
    // ════════════════════════════════════════════════════════════════════

    private function importReviews(): void
    {
        $this->info('── Schritt 3/6: Reviews importieren ──');

        if (empty($this->companyMap)) {
            $this->buildCompanyMap();
        }

        $sourceCount = $this->sourceQuery('company_reviews')->count();
        $this->line("Quelle: {$sourceCount} Reviews");

        $bar = $this->output->createProgressBar($sourceCount);
        $bar->start();

        $imported = 0;
        $skipped = 0;

        $this->sourceQuery('company_reviews')
            ->orderBy('id')
            ->chunk($this->chunkSize, function ($rows) use (&$imported, &$skipped, $bar) {
                $batch = [];

                foreach ($rows as $row) {
                    $targetCompanyId = $this->companyMap[$row->company_id] ?? null;
                    if (! $targetCompanyId) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    // Unix-Timestamp String → UTC DateTime-String
                    // Session-TZ ist auf UTC gesetzt → DST-sicher
                    $createdAt = gmdate('Y-m-d H:i:s');
                    if ($row->time && is_numeric($row->time)) {
                        $createdAt = gmdate('Y-m-d H:i:s', (int) $row->time);
                    }

                    $batch[] = [
                        'company_id' => $targetCompanyId,
                        'user_id' => null,
                        'author_name' => $row->author_name ?? 'Anonym',
                        'rating' => $row->rating ? round((float) $row->rating, 1) : null,
                        'title' => null,
                        'body' => $row->text ?: null,
                        'is_approved' => true,
                        'approved_at' => $createdAt,
                        'moderation_status' => 'approved',
                        'moderation_note' => null,
                        'moderated_by' => 'Import',
                        'created_at' => $createdAt,
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                    ];
                }

                if (! empty($batch)) {
                    DB::table('reviews')->insert($batch);
                    $imported += count($batch);
                }

                $bar->advance(count($rows));
            });

        $bar->finish();
        $this->newLine();

        // Rating-Count auf Companies nachberechnen
        $this->info('  Berechne rating_count...');
        DB::statement('
            UPDATE companies c
            SET
                c.rating_count = (SELECT COUNT(*) FROM reviews r WHERE r.company_id = c.id AND r.moderation_status = "approved"),
                c.rating = COALESCE((SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.company_id = c.id AND r.moderation_status = "approved"), 0)
        ');

        $this->info("✓ Reviews: {$imported} importiert, {$skipped} übersprungen (Company nicht gefunden).");
        $this->newLine();
    }

    // ════════════════════════════════════════════════════════════════════
    // STEP 4: Opening Hours
    // ════════════════════════════════════════════════════════════════════

    private function importOpeningHours(): void
    {
        $this->info('── Schritt 4/6: Öffnungszeiten importieren ──');

        if (empty($this->companyMap)) {
            $this->buildCompanyMap();
        }

        $sourceCount = $this->sourceQuery('company_opening_hours')->count();
        $this->line("Quelle: {$sourceCount} Öffnungszeiten-Einträge");

        $bar = $this->output->createProgressBar($sourceCount);
        $bar->start();

        $imported = 0;
        $skipped = 0;

        $this->sourceQuery('company_opening_hours')
            ->orderBy('id')
            ->chunk($this->chunkSize, function ($rows) use (&$imported, &$skipped, $bar) {
                $batch = [];

                foreach ($rows as $row) {
                    $targetCompanyId = $this->companyMap[$row->company_id] ?? null;
                    if (! $targetCompanyId) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    // Source: day 1=Mo...6=Sa (kein Sonntag)
                    // Target: day_of_week 0=Mo...6=So
                    $dayOfWeek = $row->day - 1;
                    if ($dayOfWeek < 0 || $dayOfWeek > 5) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    // "0700" → "07:00:00"
                    $opensAt = $this->parseTimeString($row->opened);
                    $closesAt = $this->parseTimeString($row->closed);

                    $batch[] = [
                        'company_id' => $targetCompanyId,
                        'day_of_week' => $dayOfWeek,
                        'opens_at' => $opensAt,
                        'closes_at' => $closesAt,
                        'is_closed' => empty($opensAt) && empty($closesAt),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (! empty($batch)) {
                    // Upsert mit Unique-Constraint (company_id, day_of_week)
                    CompanyOpeningHour::upsert(
                        $batch,
                        ['company_id', 'day_of_week'],
                        ['opens_at', 'closes_at', 'is_closed', 'updated_at']
                    );
                    $imported += count($batch);
                }

                $bar->advance(count($rows));
            });

        $bar->finish();
        $this->newLine();

        // Sonntag als geschlossen einfügen für alle Companies die keinen haben
        $this->info('  Füge Sonntag (geschlossen) hinzu...');
        $companiesWithoutSunday = DB::table('company_opening_hours')
            ->select('company_id')
            ->groupBy('company_id')
            ->havingRaw('MAX(day_of_week) < 6')
            ->pluck('company_id');

        $sundayBatch = [];
        foreach ($companiesWithoutSunday as $companyId) {
            $sundayBatch[] = [
                'company_id' => $companyId,
                'day_of_week' => 6,
                'opens_at' => null,
                'closes_at' => null,
                'is_closed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($sundayBatch) >= 1000) {
                CompanyOpeningHour::upsert($sundayBatch, ['company_id', 'day_of_week'], ['is_closed']);
                $sundayBatch = [];
            }
        }
        if (! empty($sundayBatch)) {
            CompanyOpeningHour::upsert($sundayBatch, ['company_id', 'day_of_week'], ['is_closed']);
        }

        $this->info("✓ Öffnungszeiten: {$imported} importiert, {$skipped} übersprungen, {$companiesWithoutSunday->count()} Sonntage ergänzt.");
        $this->newLine();
    }

    private function parseTimeString(?string $time): ?string
    {
        if (empty($time)) {
            return null;
        }

        // "0700" → "07:00:00", "1800" → "18:00:00"
        $time = str_pad($time, 4, '0', STR_PAD_LEFT);
        $hours = substr($time, 0, 2);
        $minutes = substr($time, 2, 2);

        if (! is_numeric($hours) || ! is_numeric($minutes)) {
            return null;
        }

        return sprintf('%02d:%02d:00', (int) $hours, (int) $minutes);
    }

    // ════════════════════════════════════════════════════════════════════
    // STEP 5: Category Assignments (Pivot)
    // ════════════════════════════════════════════════════════════════════

    private function importCategoryAssignments(): void
    {
        $this->info('── Schritt 5/6: Kategorie-Zuordnungen importieren ──');

        if (empty($this->companyMap)) {
            $this->buildCompanyMap();
        }

        $this->buildCategoryMap();

        $sourceCount = $this->sourceQuery('category_company')->count();
        $this->line("Quelle: {$sourceCount} Zuordnungen");

        $bar = $this->output->createProgressBar($sourceCount);
        $bar->start();

        $imported = 0;
        $skipped = 0;
        $fallbackCount = 0;
        $ignoredConfig = config('category-mapping.ignored', []);

        // Source: category_id → category slug
        $sourceCategoryNames = [];
        $this->sourceQuery('categories')
            ->get()
            ->each(function ($cat) use (&$sourceCategoryNames) {
                $sourceCategoryNames[$cat->id] = $cat->slug;
            });

        // Sammle alle Zuordnungen pro Company
        $companyCategories = [];

        $this->sourceQuery('category_company')
            ->orderBy('id')
            ->chunk($this->chunkSize, function ($rows) use (&$companyCategories, &$skipped, &$sourceCategoryNames, $ignoredConfig, $bar) {
                foreach ($rows as $row) {
                    $targetCompanyId = $this->companyMap[$row->company_id] ?? null;
                    if (! $targetCompanyId) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    $sourceSlug = $sourceCategoryNames[$row->category_id] ?? null;
                    if (! $sourceSlug || in_array($sourceSlug, $ignoredConfig)) {
                        $bar->advance();
                        continue;
                    }

                    $targetCategoryId = $this->categoryMap[$sourceSlug] ?? null;
                    if ($targetCategoryId) {
                        $companyCategories[$targetCompanyId][$targetCategoryId] = true;
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();

        // Fallback "Sonstiges" für Companies ohne Kategorie
        $fallbackCategory = Category::where('source_key', '_fallback')->first();
        $allCompanyIds = array_values($this->companyMap);
        $assignedCompanyIds = array_keys($companyCategories);
        $unassigned = array_diff($allCompanyIds, $assignedCompanyIds);

        if ($fallbackCategory) {
            foreach ($unassigned as $companyId) {
                $companyCategories[$companyId][$fallbackCategory->id] = true;
                $fallbackCount++;
            }
        }

        // Batch-Insert der Pivot-Tabelle
        $this->info('  Schreibe Pivot-Tabelle...');
        $batch = [];
        foreach ($companyCategories as $companyId => $catIds) {
            foreach (array_keys($catIds) as $catId) {
                $batch[] = [
                    'company_id' => $companyId,
                    'category_id' => $catId,
                ];
                $imported++;

                if (count($batch) >= 2000) {
                    DB::table('category_company')->upsert($batch, ['company_id', 'category_id'], []);
                    $batch = [];
                }
            }
        }
        if (! empty($batch)) {
            DB::table('category_company')->upsert($batch, ['company_id', 'category_id'], []);
        }

        $this->info("✓ Kategorie-Zuordnungen: {$imported} importiert, {$skipped} übersprungen, {$fallbackCount} Fallback 'Sonstiges'.");
        $this->newLine();
    }

    private function buildCategoryMap(): void
    {
        // Map: EN source slug → target category_id
        // Categories haben source_key als "plumber" oder "grocery_or_supermarket,supermarket,convenience_store,food"
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

        $this->line("  Kategorie-Map: " . count($this->categoryMap) . " Quell-Keys → " . $categories->count() . " Ziel-Kategorien");
    }

    // ════════════════════════════════════════════════════════════════════
    // STEP 6: Photos
    // ════════════════════════════════════════════════════════════════════

    private function importPhotos(): void
    {
        if ($this->option('skip-photos')) {
            $this->warn('── Schritt 6/6: Fotos übersprungen (--skip-photos) ──');
            return;
        }

        $this->info('── Schritt 6/6: Fotos importieren ──');

        if (empty($this->companyMap)) {
            $this->buildCompanyMap();
        }

        if (! is_dir(self::PHOTO_SOURCE_PATH)) {
            $this->error("Foto-Quellverzeichnis nicht gefunden: " . self::PHOTO_SOURCE_PATH);
            return;
        }

        $sourceCount = $this->sourceQuery('company_photos')->count();
        $this->line("Quelle: {$sourceCount} Fotos");

        $bar = $this->output->createProgressBar($sourceCount);
        $bar->start();

        $imported = 0;
        $skipped = 0;
        $notFound = 0;

        $this->sourceQuery('company_photos')
            ->orderBy('company_id')
            ->chunk($this->chunkSize, function ($rows) use (&$imported, &$skipped, &$notFound, $bar) {
                foreach ($rows as $row) {
                    $targetCompanyId = $this->companyMap[$row->company_id] ?? null;
                    if (! $targetCompanyId) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    $filePath = self::PHOTO_SOURCE_PATH . $row->name;
                    if (! file_exists($filePath)) {
                        $notFound++;
                        $bar->advance();
                        continue;
                    }

                    try {
                        $company = Company::find($targetCompanyId);
                        if ($company) {
                            $company->addMedia($filePath)
                                ->preservingOriginal()
                                ->toMediaCollection('gallery');
                            $imported++;
                        }
                    } catch (\Exception $e) {
                        $skipped++;
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();

        $this->info("✓ Fotos: {$imported} importiert, {$skipped} übersprungen, {$notFound} Dateien nicht gefunden.");
        $this->newLine();
    }

    // ════════════════════════════════════════════════════════════════════
    // Helper
    // ════════════════════════════════════════════════════════════════════

    private function sourceQuery(string $table): \Illuminate\Database\Query\Builder
    {
        return DB::connection('sanitaer_source')->table($table);
    }
}
