<?php

namespace Database\Seeders;

use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyOpeningHour;
use App\Models\Portal\Job;
use App\Models\Portal\Review;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeder fuer 3 Premium-Beispielunternehmen (Sanitaer-Branche).
 *
 * Erstellt vollstaendige Firmenprofile mit:
 * - Stammdaten + Adresse + Social Links
 * - Oeffnungszeiten (7 Tage)
 * - 3 Stellenanzeigen pro Firma
 * - 3 Bewertungen pro Firma (approved)
 * - 4-5 Kategorien pro Firma
 * - 5 Galerie-Bilder + Logo + Cover (via Spatie Media Library)
 *
 * Usage:
 *   php artisan tenants:run "db:seed --class=PremiumCompanySeeder"
 *   php artisan tenants:run "db:seed --class=PremiumCompanySeeder" --tenants=firmenfreund
 */
class PremiumCompanySeeder extends Seeder
{
    /**
     * Stock-Foto-URLs (Unsplash/Pexels — Free Commercial License).
     * Organisiert nach Firma und Verwendungszweck.
     */
    private array $photos = [
        'rohrbacher' => [
            'logo' => 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?w=400&h=400&fit=crop',
            'cover' => 'https://images.unsplash.com/photo-1504328345606-18bbc8c9d7d1?w=1200&h=400&fit=crop',
            'gallery' => [
                'https://images.unsplash.com/photo-1585704032915-c3400ca199e7?w=800&q=80', // Plumber at work
                'https://images.unsplash.com/photo-1552321554-5fefe8c9ef14?w=800&q=80', // Modern bathroom
                'https://images.unsplash.com/photo-1513694203232-719a280e022f?w=800&q=80', // Heating system
                'https://images.unsplash.com/photo-1607472586893-edb57bdc0e39?w=800&q=80', // Pipes and tools
                'https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=800&q=80', // Customer consultation
            ],
        ],
        'bruening' => [
            'logo' => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=400&h=400&fit=crop',
            'cover' => 'https://images.unsplash.com/photo-1565538810643-b5bdb714032a?w=1200&h=400&fit=crop',
            'gallery' => [
                'https://images.unsplash.com/photo-1509391366360-2e959784a276?w=800&q=80', // Solar panels
                'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=800&q=80', // Modern accessible bathroom
                'https://images.unsplash.com/photo-1611348586804-61bf6c080437?w=800&q=80', // Heat pump / HVAC
                'https://images.unsplash.com/photo-1600880292089-90a7e086ee0c?w=800&q=80', // Team meeting
                'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=800&q=80', // Construction / installation
            ],
        ],
        'rohrprofis' => [
            'logo' => 'https://images.unsplash.com/photo-1621905252507-b35492cc74b4?w=400&h=400&fit=crop',
            'cover' => 'https://images.unsplash.com/photo-1581092918056-0c4c3acd3789?w=1200&h=400&fit=crop',
            'gallery' => [
                'https://images.unsplash.com/photo-1504328345606-18bbc8c9d7d1?w=800&q=80', // Work van / service vehicle
                'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&q=80', // High pressure equipment
                'https://images.unsplash.com/photo-1581092160607-ee22621dd758?w=800&q=80', // Pipe inspection
                'https://images.unsplash.com/photo-1590479773265-7464e5d48118?w=800&q=80', // Underground pipes
                'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?w=800&q=80', // Construction team
            ],
        ],
    ];

    public function run(): void
    {
        $this->command->info('=== Premium-Beispielunternehmen Seeder ===');
        $this->command->info('Erstelle 3 Sanitaer-Unternehmen mit vollstaendigen Profilen...');
        $this->command->newLine();

        // Staedte sicherstellen
        $cities = $this->ensureCities();

        // Kategorien sicherstellen
        $categories = $this->ensureCategories();

        // 3 Premium-Unternehmen erstellen
        $companies = $this->getCompanyData();

        foreach ($companies as $key => $data) {
            $this->command->info("▸ Erstelle: {$data['name']}");

            // 1. Firma anlegen
            $city = $cities[$data['city_key']];
            $company = $this->createCompany($data, $city);

            // 2. Kategorien zuweisen
            $this->assignCategories($company, $data['category_names'], $categories);

            // 3. Oeffnungszeiten
            $this->createOpeningHours($company, $data['opening_hours']);

            // 4. Bewertungen
            $this->createReviews($company, $data['reviews']);

            // 5. Stellenanzeigen
            $this->createJobs($company, $data['jobs'], $city);

            // 6. Bilder herunterladen und zuweisen
            $this->attachPhotos($company, $key);

            $this->command->info("  ✓ {$company->name} komplett (Rating: {$company->rating}, {$company->rating_count} Bewertungen)");
            $this->command->newLine();
        }

        $this->command->info('=== Fertig: 3 Premium-Unternehmen mit je 3 Jobs, 3 Bewertungen, 5 Galerie-Bildern ===');
    }

    // ─── Staedte ───────────────────────────────────────────────────

    private function ensureCities(): array
    {
        $cityData = [
            'stuttgart' => ['name' => 'Stuttgart', 'zipcode' => '70565', 'administrative_area_level_1' => 'Baden-Württemberg', 'latitude' => 48.7758, 'longitude' => 9.1829],
            'hannover' => ['name' => 'Hannover', 'zipcode' => '30161', 'administrative_area_level_1' => 'Niedersachsen', 'latitude' => 52.3759, 'longitude' => 9.7320],
            'nuernberg' => ['name' => 'Nürnberg', 'zipcode' => '90429', 'administrative_area_level_1' => 'Bayern', 'latitude' => 49.4521, 'longitude' => 11.0767],
        ];

        $cities = [];
        foreach ($cityData as $key => $data) {
            $cities[$key] = City::firstOrCreate(
                ['name' => $data['name'], 'administrative_area_level_1' => $data['administrative_area_level_1']],
                $data
            );
        }

        return $cities;
    }

    // ─── Kategorien ────────────────────────────────────────────────

    private function ensureCategories(): \Illuminate\Support\Collection
    {
        // Zusaetzliche Kategorien anlegen, die fuer Sanitaer-Betriebe passen
        $extraCategories = [
            'Heizungstechnik' => ['icon' => 'flame', 'parent' => 'Handwerk & Bau'],
            'Rohrreinigung' => ['icon' => 'droplets', 'parent' => 'Handwerk & Bau'],
            'Sanitär-Notdienst' => ['icon' => 'siren', 'parent' => 'Handwerk & Bau'],
            'Kanalsanierung' => ['icon' => 'construction', 'parent' => 'Handwerk & Bau'],
            'Solar & Wärmepumpen' => ['icon' => 'sun', 'parent' => 'Handwerk & Bau'],
        ];

        $parent = Category::where('name', 'Handwerk & Bau')->first();

        foreach ($extraCategories as $name => $data) {
            Category::firstOrCreate(
                ['name' => $name],
                [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'icon' => $data['icon'],
                    'parent_id' => $parent?->id,
                    'sort_order' => 10,
                ]
            );
        }

        return Category::all();
    }

    // ─── Firma anlegen ─────────────────────────────────────────────

    private function createCompany(array $data, City $city): Company
    {
        $company = Company::firstOrCreate(
            ['slug' => $data['slug']],
            [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'],
                'description_source' => 'manual',
                'street' => $data['street'],
                'house_no' => $data['house_no'],
                'zipcode' => $data['zipcode'],
                'city_id' => $city->id,
                'tel' => $data['tel'],
                'email' => $data['email'],
                'website' => $data['website'],
                'is_premium' => true,
                'is_verified' => true,
                'is_active' => true,
                'social_facebook' => $data['social_facebook'] ?? null,
                'social_instagram' => $data['social_instagram'] ?? null,
            ]
        );

        return $company;
    }

    // ─── Kategorien zuweisen ───────────────────────────────────────

    private function assignCategories(Company $company, array $categoryNames, \Illuminate\Support\Collection $categories): void
    {
        $categoryIds = $categories->whereIn('name', $categoryNames)->pluck('id');
        $company->categories()->syncWithoutDetaching($categoryIds);
        $this->command->info("  → {$categoryIds->count()} Kategorien zugewiesen");
    }

    // ─── Oeffnungszeiten ───────────────────────────────────────────

    private function createOpeningHours(Company $company, array $hours): void
    {
        // Bestehende loeschen falls vorhanden
        $company->openingHours()->delete();

        foreach ($hours as $hour) {
            CompanyOpeningHour::create([
                'company_id' => $company->id,
                'day_of_week' => $hour['day'],
                'opens_at' => $hour['opens'] ?? null,
                'closes_at' => $hour['closes'] ?? null,
                'is_closed' => $hour['is_closed'] ?? false,
            ]);
        }

        $this->command->info('  → 7 Öffnungszeiten erstellt');
    }

    // ─── Bewertungen ───────────────────────────────────────────────

    private function createReviews(Company $company, array $reviews): void
    {
        // Events deaktivieren fuer Performance, Rating danach manuell berechnen
        Review::unsetEventDispatcher();

        foreach ($reviews as $review) {
            Review::firstOrCreate(
                ['company_id' => $company->id, 'author_name' => $review['author']],
                [
                    'company_id' => $company->id,
                    'author_name' => $review['author'],
                    'rating' => $review['rating'],
                    'title' => $review['title'] ?? null,
                    'body' => $review['body'],
                    'moderation_status' => Review::STATUS_APPROVED,
                    'is_approved' => true,
                    'approved_at' => now()->subDays(rand(7, 90)),
                ]
            );
        }

        Review::setEventDispatcher(app('events'));
        $company->recalculateRating();

        $this->command->info("  → {$company->rating_count} Bewertungen (Rating: {$company->rating})");
    }

    // ─── Stellenanzeigen ───────────────────────────────────────────

    private function createJobs(Company $company, array $jobs, City $city): void
    {
        foreach ($jobs as $jobData) {
            Job::firstOrCreate(
                ['company_id' => $company->id, 'slug' => $jobData['slug']],
                [
                    'company_id' => $company->id,
                    'title' => $jobData['title'],
                    'slug' => $jobData['slug'],
                    'description' => $jobData['description'],
                    'requirements' => $jobData['requirements'] ?? null,
                    'benefits' => $jobData['benefits'] ?? null,
                    'employment_type' => $jobData['employment_type'],
                    'location' => $jobData['location'] ?? $city->name,
                    'city_id' => $city->id,
                    'salary_min' => $jobData['salary_min'] ?? null,
                    'salary_max' => $jobData['salary_max'] ?? null,
                    'salary_type' => $jobData['salary_type'] ?? null,
                    'is_active' => true,
                    'published_at' => now()->subDays(rand(1, 14)),
                    'expires_at' => now()->addDays(rand(16, 30)),
                ]
            );
        }

        $this->command->info("  → " . count($jobs) . ' Stellenanzeigen erstellt');
    }

    // ─── Bilder herunterladen und zuweisen ──────────────────────────

    private function attachPhotos(Company $company, string $companyKey): void
    {
        if (! isset($this->photos[$companyKey])) {
            $this->command->warn("  ⚠ Keine Fotos fuer {$companyKey} definiert");
            return;
        }

        $photos = $this->photos[$companyKey];
        $downloaded = 0;

        // Logo
        try {
            $this->downloadAndAttachMedia($company, $photos['logo'], 'logo', 'logo.jpg');
            $downloaded++;
        } catch (\Exception $e) {
            $this->command->warn("  ⚠ Logo-Download fehlgeschlagen: {$e->getMessage()}");
        }

        // Cover
        try {
            $this->downloadAndAttachMedia($company, $photos['cover'], 'cover', 'cover.jpg');
            $downloaded++;
        } catch (\Exception $e) {
            $this->command->warn("  ⚠ Cover-Download fehlgeschlagen: {$e->getMessage()}");
        }

        // Galerie
        foreach ($photos['gallery'] as $index => $url) {
            try {
                $filename = "gallery-" . ($index + 1) . '.jpg';
                $this->downloadAndAttachMedia($company, $url, 'gallery', $filename);
                $downloaded++;
            } catch (\Exception $e) {
                $this->command->warn("  ⚠ Galerie-Bild {$index} fehlgeschlagen: {$e->getMessage()}");
            }
        }

        $this->command->info("  → {$downloaded} Bilder heruntergeladen und zugewiesen");
    }

    private function downloadAndAttachMedia(Company $company, string $url, string $collection, string $filename): void
    {
        $response = Http::timeout(30)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} fuer {$url}");
        }

        $tempPath = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($tempPath, $response->body());

        $company->addMedia($tempPath)
            ->usingFileName($filename)
            ->toMediaCollection($collection);
    }

    // ─── Firmendaten ───────────────────────────────────────────────

    private function getCompanyData(): array
    {
        return [
            'rohrbacher' => [
                'name' => 'Rohrbacher Sanitär & Heizungstechnik GmbH',
                'slug' => 'rohrbacher-sanitaer-heizungstechnik',
                'city_key' => 'stuttgart',
                'description' => 'Wenn bei Ihnen das Wasser nicht mehr dahin fließt wo es soll, sind wir zur Stelle — seit 1994. Angefangen hat alles mit einem Transporter und einer Rohrzange, heute sind wir 14 Leute und decken alles ab, was mit Wasser, Wärme und Abfluss zu tun hat. Badmodernisierung, Heizungstausch, Rohrsanierung, Notdienst — ehrlich gesagt gibt es wenig, was wir noch nicht gesehen haben. Und ja, wir kommen auch am Wochenende, wenn Ihre Kellerdecke tropft. Manche Dinge dulden halt keinen Aufschub. Unsere Kunden sagen oft, dass ihnen am besten gefällt, dass wir vorher sagen was es kostet — und hinterher stimmt die Rechnung auch.',
                'street' => 'Industriestraße',
                'house_no' => '8',
                'zipcode' => '70565',
                'tel' => '0711 7834 2290',
                'email' => 'info@rohrbacher-sanitaer.de',
                'website' => 'https://www.rohrbacher-sanitaer.de',
                'social_facebook' => 'https://www.facebook.com/rohrbacher.sanitaer',
                'social_instagram' => 'https://www.instagram.com/rohrbacher_sanitaer',
                'category_names' => ['Klempner & Sanitär', 'Heizungstechnik', 'Rohrreinigung'],

                'opening_hours' => [
                    ['day' => 0, 'opens' => '07:00', 'closes' => '17:00'],
                    ['day' => 1, 'opens' => '07:00', 'closes' => '17:00'],
                    ['day' => 2, 'opens' => '07:00', 'closes' => '17:00'],
                    ['day' => 3, 'opens' => '07:00', 'closes' => '17:00'],
                    ['day' => 4, 'opens' => '07:00', 'closes' => '14:00'],
                    ['day' => 5, 'opens' => '08:00', 'closes' => '12:00', 'is_closed' => false], // Sa: Notdienst
                    ['day' => 6, 'is_closed' => true], // So: Notdienst
                ],

                'reviews' => [
                    [
                        'author' => 'Petra W.',
                        'rating' => 5,
                        'title' => 'Notdienst am Sonntag — top!',
                        'body' => 'Rohrbruch am Sonntagabend — innerhalb von 45 Minuten war der Monteur da. Kein Wucherpreis trotz Notdienst, alles sauber repariert. Das nenne ich Handwerk.',
                    ],
                    [
                        'author' => 'Klaus D.',
                        'rating' => 5,
                        'title' => 'Badsanierung in 9 Tagen',
                        'body' => 'Komplette Badsanierung in 9 Tagen, von der alten Badewanne zur bodengleichen Dusche. Die Jungs haben jeden Abend aufgeräumt und den Flur abgeklebt. Ergebnis sieht aus wie aus dem Katalog.',
                    ],
                    [
                        'author' => 'Monika H.',
                        'rating' => 4,
                        'title' => 'Gute Arbeit, Angebot kam spät',
                        'body' => 'Heizungswartung war einwandfrei. Einziger Kritikpunkt: Den Kostenvoranschlag musste ich zweimal anfordern, bis er kam. Aber die Arbeit selbst — top.',
                    ],
                ],

                'jobs' => [
                    [
                        'title' => 'Anlagenmechaniker SHK (m/w/d) — Kundendienst & Badsanierung',
                        'slug' => 'anlagenmechaniker-shk-kundendienst',
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'description' => "Du fährst morgens vom Betrieb los und bist den Tag über eigenverantwortlich unterwegs — Heizungswartungen, Rohrbruch-Reparaturen, Badsanierungen. Kein Tag ist wie der andere. Du hast einen eigenen Firmenwagen, das Material ist bestellt, und wenn du mal nicht weiterkommst, erreichst du unseren Meister jederzeit per Telefon.\n\nDeine Aufgaben:\n- Wartung und Instandhaltung von Heizungsanlagen\n- Rohrbruch-Reparaturen und Sanitärinstallationen\n- Badsanierung im 2er-Team\n- Kundenberatung vor Ort\n- Dokumentation der durchgeführten Arbeiten",
                        'requirements' => "- Abgeschlossene Ausbildung als Anlagenmechaniker SHK oder Gas-Wasser-Installateur\n- Mindestens 3 Jahre Berufserfahrung\n- Führerschein Klasse B\n- Kundenfreundliches Auftreten\n- Selbstständige Arbeitsweise",
                        'benefits' => "- Eigener Firmenwagen (Heim-Arbeit-Heim)\n- 30 Tage Urlaub\n- Überstundenkonto mit Freizeitausgleich\n- Arbeitskleidung und Werkzeug wird gestellt\n- Unbefristeter Vertrag",
                        'salary_min' => 3200,
                        'salary_max' => 3800,
                        'salary_type' => Job::SALARY_MONTHLY,
                        'location' => 'Stuttgart',
                    ],
                    [
                        'title' => 'Auszubildender Anlagenmechaniker SHK (m/w/d) — Start September 2026',
                        'slug' => 'ausbildung-anlagenmechaniker-shk-2026',
                        'employment_type' => Job::TYPE_AUSBILDUNG,
                        'description' => "Drei Jahre, in denen du lernst, wie man Bäder baut, Heizungen installiert und Rohrleitungssysteme verlegt. Kein Ausbildungsberuf, in dem du am Schreibtisch versauerst — du bist ab Tag eins auf der Baustelle dabei. Unser Meister zeigt dir alles, was er in 25 Jahren gelernt hat. Und ja, die Ausbildungsvergütung ist überdurchschnittlich.\n\nDas lernst du:\n- Installation von Sanitäranlagen und Heizungen\n- Verlegung von Rohrleitungssystemen\n- Moderne Techniken: Wärmepumpen, Solarthermie\n- Kundendienst und Fehlerdiagnose\n- Eigenständige Projekte ab dem 2. Lehrjahr",
                        'requirements' => "- Hauptschulabschluss oder Mittlere Reife\n- Handwerkliches Geschick und räumliches Vorstellungsvermögen\n- Du scheust dich nicht vor Dreck und engen Räumen\n- Zuverlässigkeit und Pünktlichkeit",
                        'benefits' => "- Übernahmegarantie bei guten Leistungen\n- Tablet für die Berufsschule\n- Prüfungsvorbereitung während der Arbeitszeit\n- Azubi-Events und Teamausflüge\n- Überdurchschnittliche Ausbildungsvergütung",
                        'salary_min' => 900,
                        'salary_max' => 1150,
                        'salary_type' => Job::SALARY_MONTHLY,
                        'location' => 'Stuttgart',
                    ],
                    [
                        'title' => 'Kundendiensttechniker Heizung (m/w/d) — Wartung & Instandsetzung',
                        'slug' => 'kundendiensttechniker-heizung',
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'description' => "Du wartest und reparierst Heizungsanlagen aller Fabrikate — Gas, Öl, Wärmepumpe. Pro Tag fährst du 4-5 Kunden an, die Touren planen wir so, dass du nicht kreuz und quer durch die Stadt musst. Wenn im Winter die Heizung ausfällt, bist du der Held. Das klingt pathetisch, aber glaub mir: Für die Leute bist du das wirklich.\n\nDeine Aufgaben:\n- Wartung von Heizungsanlagen (Gas, Öl, Wärmepumpe)\n- Störungsbehebung und Reparaturen\n- Inbetriebnahme neuer Anlagen\n- Kundenberatung zu Modernisierung und Energieeffizienz",
                        'requirements' => "- Ausbildung als Anlagenmechaniker SHK\n- Erfahrung mit Heizkesseln und Wärmepumpen\n- Viessmann- oder Buderus-Schulungen von Vorteil\n- Führerschein Klasse B\n- Eigenverantwortliche Arbeitsweise",
                        'benefits' => "- Firmenwagen auch zur Privatnutzung\n- Bereitschaftszulage\n- Fortbildungen bei Herstellern (Viessmann, Buderus, Vaillant)\n- Kurzer Freitag (bis 14 Uhr)\n- Betriebliche Altersvorsorge",
                        'salary_min' => 3400,
                        'salary_max' => 4000,
                        'salary_type' => Job::SALARY_MONTHLY,
                        'location' => 'Stuttgart',
                    ],
                ],
            ],

            'bruening' => [
                'name' => 'Haustechnik Brüning — Bad, Heizung, Solar',
                'slug' => 'haustechnik-bruening-bad-heizung-solar',
                'city_key' => 'hannover',
                'description' => 'Wir machen warmes Wasser, warme Räume und warme Gefühle — letzteres vor allem, wenn die Gasrechnung niedriger ausfällt als erwartet. Seit 2008 sind wir in Hannover und Umgebung für alles zuständig, was unter die Haustechnik fällt: Badmodernisierung, Heizungsanlagen, Solarthermie, Wärmepumpen und seit Neuestem auch Photovoltaik-Kombisysteme. Unser Steckenpferd? Die Kombination aus klassischem Sanitärhandwerk und moderner Energietechnik. Wir installieren nicht einfach nur eine Wärmepumpe — wir schauen uns vorher das ganze Haus an und beraten ehrlich, was sich lohnt und was nicht.',
                'street' => 'Lister Meile',
                'house_no' => '78',
                'zipcode' => '30161',
                'tel' => '0511 9488 3360',
                'email' => 'kontakt@haustechnik-bruening.de',
                'website' => 'https://www.haustechnik-bruening.de',
                'social_facebook' => 'https://www.facebook.com/haustechnik.bruening',
                'social_instagram' => 'https://www.instagram.com/haustechnik_bruening',
                'category_names' => ['Klempner & Sanitär', 'Heizungstechnik', 'Solar & Wärmepumpen'],

                'opening_hours' => [
                    ['day' => 0, 'opens' => '07:30', 'closes' => '17:00'],
                    ['day' => 1, 'opens' => '07:30', 'closes' => '17:00'],
                    ['day' => 2, 'opens' => '07:30', 'closes' => '17:00'],
                    ['day' => 3, 'opens' => '07:30', 'closes' => '18:00'],
                    ['day' => 4, 'opens' => '07:30', 'closes' => '14:00'],
                    ['day' => 5, 'is_closed' => true],
                    ['day' => 6, 'is_closed' => true],
                ],

                'reviews' => [
                    [
                        'author' => 'Frank B.',
                        'rating' => 5,
                        'title' => 'Ehrliche Beratung, 4.000 EUR gespart',
                        'body' => 'Herr Brüning hat sich 2 Stunden Zeit genommen, um unser Haus zu begutachten, bevor er eine Wärmepumpe empfohlen hat. Am Ende war es eine Hybridlösung, die 4.000 EUR weniger gekostet hat als das, was der Wettbewerber angeboten hat. Ehrliche Beratung, super Umsetzung.',
                    ],
                    [
                        'author' => 'Ingrid S.',
                        'rating' => 5,
                        'title' => 'Barrierefreies Bad aus einer Hand',
                        'body' => 'Barrierefreies Bad für meine Mutter — alles aus einer Hand, von der Planung über die Fliesen bis zur Walk-in-Dusche. Die Monteure waren höflich und haben sogar die Schuhe ausgezogen.',
                    ],
                    [
                        'author' => 'Markus T.',
                        'rating' => 4,
                        'title' => '60% weniger Warmwasserkosten',
                        'body' => 'Solarthermie-Anlage installiert, läuft seit einem Jahr einwandfrei. Die Warmwasserkosten sind um 60% gesunken. Punkt Abzug, weil der Termin einmal verschoben wurde — aber insgesamt sehr zufrieden.',
                    ],
                ],

                'jobs' => [
                    [
                        'title' => 'SHK-Meister / Techniker (m/w/d) — Schwerpunkt erneuerbare Energien',
                        'slug' => 'shk-meister-erneuerbare-energien',
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'description' => "Du planst und begleitest Projekte rund um Wärmepumpen, Solarthermie und Hybridheizungen — vom Erstgespräch über die technische Planung bis zur Abnahme. Du bist unser Ansprechpartner für Fördermittel-Anträge (BAFA, KfW) und berätst Kunden, welche Lösung für ihr Gebäude wirklich sinnvoll ist. Nicht die teuerste — die sinnvollste.\n\nDeine Aufgaben:\n- Technische Planung von Wärmepumpen- und Solaranlagen\n- Kundenberatung und Angebotserstellung\n- Fördermittel-Beratung (BAFA, KfW)\n- Projektleitung und Baustellenkoordination\n- Qualitätskontrolle bei Abnahme",
                        'requirements' => "- Meisterbrief SHK oder Techniker-Abschluss\n- Erfahrung mit Wärmepumpen und Solarsystemen\n- Kenntnisse im Fördermittelwesen\n- Beratungskompetenz und Kundenorientierung\n- Führerschein Klasse B",
                        'benefits' => "- 4-Tage-Woche möglich\n- Firmenwagen (auch privat)\n- Fortbildungsbudget 3.000 EUR/Jahr\n- Umsatzbeteiligung bei eigenen Projekten\n- Flexible Arbeitszeiten",
                        'salary_min' => 4200,
                        'salary_max' => 5200,
                        'salary_type' => Job::SALARY_MONTHLY,
                        'location' => 'Hannover',
                    ],
                    [
                        'title' => 'Anlagenmechaniker SHK (m/w/d) — Bad & Heizung',
                        'slug' => 'anlagenmechaniker-bad-heizung',
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'description' => "Du installierst Sanitäranlagen, baust Bäder um und schließt Heizkörper an. Die Projekte dauern meist 1-3 Wochen, du arbeitest im 2er-Team und hast feste Ansprechpartner für Material und Planung. Wenn du keine Lust mehr auf tägliche Anfahrten quer durch die Stadt hast — bei uns sind die Baustellen selten weiter als 20 km vom Betrieb.\n\nDeine Aufgaben:\n- Installation von Sanitäranlagen und Heizkörpern\n- Badsanierung im 2er-Team\n- Verlegung von Trinkwasser- und Abwasserleitungen\n- Montage von Fußbodenheizungen",
                        'requirements' => "- Abgeschlossene Ausbildung SHK\n- Erfahrung im Kundendienst oder Badsanierung\n- Führerschein Klasse B\n- Teamfähigkeit und saubere Arbeitsweise",
                        'benefits' => "- 30 Tage Urlaub\n- Keine Montageeinsätze (regionale Baustellen)\n- Werkzeugpauschale\n- Zuschuss zum Fitnessstudio\n- Betriebliche Altersvorsorge",
                        'salary_min' => 3000,
                        'salary_max' => 3600,
                        'salary_type' => Job::SALARY_MONTHLY,
                        'location' => 'Hannover',
                    ],
                    [
                        'title' => 'Bürokraft / Sachbearbeitung (m/w/d) — Angebote, Rechnungen, Kunden',
                        'slug' => 'buerokraft-sachbearbeitung',
                        'employment_type' => Job::TYPE_TEILZEIT,
                        'description' => "Du schreibst Angebote, erstellst Rechnungen, koordinierst Termine zwischen Kunden und Monteuren und sorgst dafür, dass der Papierkram nicht liegen bleibt. 25 Stunden pro Woche, idealerweise vormittags. Handwerkswissen ist nett, aber kein Muss — wir bringen dir die Fachbegriffe bei.\n\nDeine Aufgaben:\n- Angebots- und Rechnungserstellung\n- Terminkoordination für Monteure\n- Kundenempfang und Telefonzentrale\n- Vorbereitende Buchhaltung\n- Materialbestellung und Lagerverwaltung",
                        'requirements' => "- Kaufmännische Ausbildung oder vergleichbare Berufserfahrung\n- Sicher in Word und Excel\n- Organisationstalent\n- Freundliche Telefonstimme\n- Erfahrung im Handwerk von Vorteil",
                        'benefits' => "- Flexible Arbeitszeiten (Kernzeit 8-13 Uhr)\n- Homeoffice 1 Tag/Woche möglich\n- Kollegiales Team\n- Unbefristeter Vertrag\n- Kaffee-Flatrate",
                        'salary_min' => 14,
                        'salary_max' => 17,
                        'salary_type' => Job::SALARY_HOURLY,
                        'location' => 'Hannover',
                    ],
                ],
            ],

            'rohrprofis' => [
                'name' => 'Rohr-Profis Nürnberg',
                'slug' => 'rohr-profis-nuernberg',
                'city_key' => 'nuernberg',
                'description' => 'Verstopftes Rohr um 23 Uhr? Wir kommen. Kamerainspektion im Kanal? Machen wir. Geruch aus dem Abfluss, der nicht weggehen will? Dafür gibt es uns. Die Rohr-Profis sind seit 2011 die Nummer für alles, was mit Abfluss, Kanal und Rohrleitung zu tun hat — und zwar rund um die Uhr. Wir arbeiten mit Hochdruckspülgeräten, Kameratechnik und bei Bedarf auch mit grabenloser Sanierung, also ohne dass Ihr Garten hinterher aussieht wie ein Schlachtfeld. Unser Versprechen: Festpreise vor Arbeitsbeginn. Keine Anfahrtspauschale innerhalb Nürnbergs. Und wenn es doch mal länger dauert als geplant, geht das auf unsere Kappe.',
                'street' => 'Fürther Straße',
                'house_no' => '112',
                'zipcode' => '90429',
                'tel' => '0911 2478 8810',
                'email' => 'hilfe@rohr-profis-nuernberg.de',
                'website' => 'https://www.rohr-profis-nuernberg.de',
                'social_facebook' => 'https://www.facebook.com/rohrprofis.nuernberg',
                'social_instagram' => null,
                'category_names' => ['Rohrreinigung', 'Sanitär-Notdienst', 'Kanalsanierung', 'Klempner & Sanitär'],

                'opening_hours' => [
                    ['day' => 0, 'opens' => '00:00', 'closes' => '23:59'],
                    ['day' => 1, 'opens' => '00:00', 'closes' => '23:59'],
                    ['day' => 2, 'opens' => '00:00', 'closes' => '23:59'],
                    ['day' => 3, 'opens' => '00:00', 'closes' => '23:59'],
                    ['day' => 4, 'opens' => '00:00', 'closes' => '23:59'],
                    ['day' => 5, 'opens' => '00:00', 'closes' => '23:59'],
                    ['day' => 6, 'opens' => '00:00', 'closes' => '23:59'],
                ],

                'reviews' => [
                    [
                        'author' => 'Stefan G.',
                        'rating' => 5,
                        'title' => 'Silvester-Notfall in 30 Minuten gelöst',
                        'body' => 'Silvesterabend, 21 Uhr, Toilette komplett verstopft — volle Hütte mit Gästen. Die Rohr-Profis waren in 30 Minuten da und haben das Problem in 15 Minuten gelöst. Festpreis, kein Aufschlag für den Feiertag. Lebensretter.',
                    ],
                    [
                        'author' => 'Barbara K.',
                        'rating' => 5,
                        'title' => 'Grabenlose Sanierung — Garten gerettet',
                        'body' => 'Kamerainspektion hat gezeigt, dass unser Kanalrohr an zwei Stellen gebrochen war. Die grabenlose Sanierung hat den Garten komplett verschont — kein Bagger, keine Verwüstung. Hätte ich nicht für möglich gehalten.',
                    ],
                    [
                        'author' => 'Jens M.',
                        'rating' => 4,
                        'title' => 'Zuverlässig, faire Preise',
                        'body' => 'Zuverlässiger Notdienst, fairer Preis. Einen Stern Abzug, weil die Wartezeit beim zweiten Mal fast eine Stunde war — aber die haben halt viel zu tun. Qualität der Arbeit stimmt.',
                    ],
                ],

                'jobs' => [
                    [
                        'title' => 'Rohrreiniger / Kanaltechniker (m/w/d) — Tagschicht oder Schichtsystem',
                        'slug' => 'rohrreiniger-kanaltechniker',
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'description' => "Du fährst Einsätze — verstopfte Abflüsse, Kanalreinigungen, Kamerainspektionen. Mal eine Küche in Fürth, mal ein Industriebetrieb in Erlangen. Du arbeitest eigenständig, hast deinen eigenen Wagen und das komplette Equipment dabei. Robuste Nerven brauchst du, einen empfindlichen Magen solltest du nicht haben.\n\nDeine Aufgaben:\n- Rohrreinigung mit Hochdruckspülgeräten\n- Kamerainspektion und Leitungsortung\n- Notdienst-Einsätze (im Schichtsystem)\n- Dokumentation und Berichterstattung\n- Fahrzeugpflege und Equipmentwartung",
                        'requirements' => "- Handwerkliche Ausbildung (Sanitär, Tiefbau oder vergleichbar) oder Quereinsteiger mit Erfahrung\n- Führerschein Klasse B (Klasse C1 von Vorteil)\n- Körperliche Belastbarkeit\n- Bereitschaft zu Wochenenddiensten\n- Robuste Persönlichkeit",
                        'benefits' => "- Schichtzulagen (Nacht/Wochenende)\n- Eigener Firmenwagen\n- Überdurchschnittliche Bezahlung\n- Jeder Tag ist anders — keine Langeweile\n- Moderne Ausrüstung",
                        'salary_min' => 2800,
                        'salary_max' => 3500,
                        'salary_type' => Job::SALARY_MONTHLY,
                        'location' => 'Nürnberg',
                    ],
                    [
                        'title' => 'Disponent / Einsatzleiter (m/w/d) — Zentrale',
                        'slug' => 'disponent-einsatzleiter',
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'description' => "Du nimmst Notrufe entgegen, koordinierst die Einsatzfahrzeuge und sorgst dafür, dass der richtige Monteur zur richtigen Zeit am richtigen Ort ist. Stressresistenz ist Pflicht — wenn freitags um 22 Uhr drei Anrufe gleichzeitig kommen, musst du priorisieren können. Dafür hast du ein eingespieltes Team und klare Prozesse.\n\nDeine Aufgaben:\n- Annahme und Priorisierung von Notrufen\n- Disposition der Einsatzfahrzeuge (GPS-basiert)\n- Kundenkommunikation und Terminkoordination\n- Einsatzprotokollierung\n- Schichtübergabe und Berichterstattung",
                        'requirements' => "- Erfahrung in Disposition, Logistik oder Kundendienst-Koordination\n- Ruhiges Auftreten unter Druck\n- Sicherer Umgang mit digitaler Tourenplanung\n- Bereitschaft zu Spätschichten\n- Empathie am Telefon",
                        'benefits' => "- Rotierendes Schichtmodell (keine Dauernachtschicht)\n- Bonus bei Zielerreichung\n- Moderne Zentrale mit Echtzeit-GPS-Tracking\n- Kostenloses Mittagessen\n- Teamevents",
                        'salary_min' => 3000,
                        'salary_max' => 3600,
                        'salary_type' => Job::SALARY_MONTHLY,
                        'location' => 'Nürnberg',
                    ],
                    [
                        'title' => 'Auszubildender Rohrleitungsbauer (m/w/d) — Start 2026',
                        'slug' => 'ausbildung-rohrleitungsbauer-2026',
                        'employment_type' => Job::TYPE_AUSBILDUNG,
                        'description' => "Rohre verlegen, Kanäle sanieren, mit Kameras durch unterirdische Leitungen fahren — klingt spannender als Büro, oder? In drei Jahren lernst du alles über Rohrleitungssysteme, von der Hausentwässerung bis zur Kanalsanierung. Du bist von Anfang an bei echten Einsätzen dabei.\n\nDas lernst du:\n- Grundlagen der Rohr- und Kanaltechnik\n- Bedienung von Hochdruckspülgeräten\n- Kamerainspektion und Auswertung\n- Grabenlose Sanierungstechniken\n- Arbeitssicherheit und Erste Hilfe",
                        'requirements' => "- Hauptschulabschluss\n- Körperliche Fitness\n- Keine Angst vor Schmutz und engen Räumen\n- Teamfähigkeit und Pünktlichkeit\n- Interesse an Technik",
                        'benefits' => "- Übernahmegarantie bei guten Leistungen\n- Ausbildungsvergütung über Tarif\n- Führerschein-Zuschuss im 3. Lehrjahr\n- Azubi-Ticket für ÖPNV\n- Praxisnahe Ausbildung (keine Langeweile)",
                        'salary_min' => 880,
                        'salary_max' => 1100,
                        'salary_type' => Job::SALARY_MONTHLY,
                        'location' => 'Nürnberg',
                    ],
                ],
            ],
        ];
    }
}
