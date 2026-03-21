<?php

namespace Database\Seeders;

use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyOpeningHour;
use App\Models\Portal\FAQ;
use App\Models\Portal\Job;
use App\Models\Portal\Post;
use App\Models\Portal\PostCategory;
use App\Models\Portal\PostTag;
use App\Models\Portal\Review;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Seeder fuer den Hoch- und Tiefbau Portal Tenant (ID: 17).
 *
 * Erstellt:
 * - 5 Premium-Bauunternehmen mit Fotos, Oeffnungszeiten, Bewertungen
 * - Stellenanzeigen pro Firma
 * - 8 Ratgeber-Artikel
 * - 15 FAQs (Startseite + FAQ-Seite)
 *
 * Usage:
 *   php artisan tenants:run "db:seed --class=HochTiefbauSeeder" --tenants=17
 */
class HochTiefbauSeeder extends Seeder
{
    private array $photos = [
        'bergmann' => [
            'logo' => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=400&h=400&fit=crop',
            'cover' => 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?w=1200&h=400&fit=crop',
            'gallery' => [
                'https://images.unsplash.com/photo-1590846406792-0adc7f938f1d?w=800&q=80',
                'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=800&q=80',
                'https://images.unsplash.com/photo-1581092160607-ee22621dd758?w=800&q=80',
                'https://images.unsplash.com/photo-1589939705384-5185137a7f0f?w=800&q=80',
                'https://images.unsplash.com/photo-1517089596392-fb9a9033e05b?w=800&q=80',
            ],
        ],
        'kessler' => [
            'logo' => 'https://images.unsplash.com/photo-1581092918056-0c4c3acd3789?w=400&h=400&fit=crop',
            'cover' => 'https://images.unsplash.com/photo-1590846406792-0adc7f938f1d?w=1200&h=400&fit=crop',
            'gallery' => [
                'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=800&q=80',
                'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?w=800&q=80',
                'https://images.unsplash.com/photo-1621905252507-b35492cc74b4?w=800&q=80',
                'https://images.unsplash.com/photo-1523413651479-597eb2da0ad6?w=800&q=80',
                'https://images.unsplash.com/photo-1572981779307-38b8cabb2407?w=800&q=80',
            ],
        ],
        'erdreich' => [
            'logo' => 'https://images.unsplash.com/photo-1621905252507-b35492cc74b4?w=400&h=400&fit=crop',
            'cover' => 'https://images.unsplash.com/photo-1517089596392-fb9a9033e05b?w=1200&h=400&fit=crop',
            'gallery' => [
                'https://images.unsplash.com/photo-1589939705384-5185137a7f0f?w=800&q=80',
                'https://images.unsplash.com/photo-1590846406792-0adc7f938f1d?w=800&q=80',
                'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=800&q=80',
                'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?w=800&q=80',
                'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=800&q=80',
            ],
        ],
        'schachtmeister' => [
            'logo' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=400&h=400&fit=crop',
            'cover' => 'https://images.unsplash.com/photo-1589939705384-5185137a7f0f?w=1200&h=400&fit=crop',
            'gallery' => [
                'https://images.unsplash.com/photo-1572981779307-38b8cabb2407?w=800&q=80',
                'https://images.unsplash.com/photo-1517089596392-fb9a9033e05b?w=800&q=80',
                'https://images.unsplash.com/photo-1590846406792-0adc7f938f1d?w=800&q=80',
                'https://images.unsplash.com/photo-1581092918056-0c4c3acd3789?w=800&q=80',
                'https://images.unsplash.com/photo-1523413651479-597eb2da0ad6?w=800&q=80',
            ],
        ],
        'stahlbau_wolf' => [
            'logo' => 'https://images.unsplash.com/photo-1523413651479-597eb2da0ad6?w=400&h=400&fit=crop',
            'cover' => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=1200&h=400&fit=crop',
            'gallery' => [
                'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?w=800&q=80',
                'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=800&q=80',
                'https://images.unsplash.com/photo-1589939705384-5185137a7f0f?w=800&q=80',
                'https://images.unsplash.com/photo-1572981779307-38b8cabb2407?w=800&q=80',
                'https://images.unsplash.com/photo-1590846406792-0adc7f938f1d?w=800&q=80',
            ],
        ],
    ];

    public function run(): void
    {
        $this->command->info('=== Hoch- und Tiefbau Portal Seeder ===');
        $this->command->newLine();

        // 1. Staedte
        $cities = $this->ensureCities();
        $this->command->info('Staedte OK');

        // 2. Kategorien
        $categories = $this->ensureCategories();
        $this->command->info('Kategorien OK');

        // 3. Premium-Firmen
        $this->seedCompanies($cities, $categories);
        $this->command->newLine();

        // 4. Ratgeber
        $this->seedArticles();
        $this->command->info('Ratgeber-Artikel OK');

        // 5. FAQs
        $this->seedFaqs();
        $this->command->info('FAQs OK');

        $this->command->newLine();
        $this->command->info('=== Fertig ===');
    }

    // ─── Staedte ─────────────────────────────────────────────────────

    private function ensureCities(): array
    {
        $cityData = [
            'berlin' => ['name' => 'Berlin', 'zipcode' => '10115', 'administrative_area_level_1' => 'Berlin', 'latitude' => 52.5200, 'longitude' => 13.4050],
            'muenchen' => ['name' => 'München', 'zipcode' => '80331', 'administrative_area_level_1' => 'Bayern', 'latitude' => 49.4521, 'longitude' => 11.0767],
            'hamburg' => ['name' => 'Hamburg', 'zipcode' => '20095', 'administrative_area_level_1' => 'Hamburg', 'latitude' => 53.5511, 'longitude' => 9.9937],
            'frankfurt' => ['name' => 'Frankfurt am Main', 'zipcode' => '60311', 'administrative_area_level_1' => 'Hessen', 'latitude' => 50.1109, 'longitude' => 8.6821],
            'duesseldorf' => ['name' => 'Düsseldorf', 'zipcode' => '40213', 'administrative_area_level_1' => 'Nordrhein-Westfalen', 'latitude' => 51.2277, 'longitude' => 6.7735],
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

    // ─── Kategorien ──────────────────────────────────────────────────

    private function ensureCategories(): \Illuminate\Support\Collection
    {
        // Parent-Kategorie sicherstellen
        $parent = Category::firstOrCreate(
            ['name' => 'Handwerk & Bau'],
            ['name' => 'Handwerk & Bau', 'slug' => 'handwerk-bau', 'icon' => 'wrench', 'sort_order' => 1]
        );

        $bauKategorien = [
            'Hochbau' => ['icon' => 'building', 'source_key' => 'general_contractor'],
            'Tiefbau' => ['icon' => 'hard-hat', 'source_key' => 'tiefbau'],
            'Straßenbau' => ['icon' => 'road', 'source_key' => 'strassenbau'],
            'Kanalbau' => ['icon' => 'droplets', 'source_key' => 'kanalbau'],
            'Erdbau' => ['icon' => 'mountain', 'source_key' => 'erdbau'],
            'Betonbau' => ['icon' => 'cube', 'source_key' => 'betonbau'],
            'Stahlbau' => ['icon' => 'bolt', 'source_key' => 'stahlbau'],
            'Abbrucharbeiten' => ['icon' => 'hammer', 'source_key' => 'abbruch'],
            'Fundamentbau' => ['icon' => 'layers', 'source_key' => 'fundamentbau'],
            'Rohrleitungsbau' => ['icon' => 'cable', 'source_key' => 'rohrleitungsbau'],
            'Bauunternehmen' => ['icon' => 'building-2', 'source_key' => 'general_contractor,bauunternehmen'],
        ];

        foreach ($bauKategorien as $name => $data) {
            Category::firstOrCreate(
                ['name' => $name],
                [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'icon' => $data['icon'],
                    'source_key' => $data['source_key'],
                    'parent_id' => $parent->id,
                    'sort_order' => 10,
                ]
            );
        }

        // Sonstiges als Fallback
        Category::firstOrCreate(
            ['name' => 'Sonstiges'],
            ['name' => 'Sonstiges', 'slug' => 'sonstiges', 'icon' => 'circle-dot', 'sort_order' => 99]
        );

        return Category::all();
    }

    // ─── Firmen ──────────────────────────────────────────────────────

    private function seedCompanies(array $cities, \Illuminate\Support\Collection $categories): void
    {
        $companies = $this->getCompanyData();

        foreach ($companies as $key => $data) {
            $this->command->info("▸ Erstelle: {$data['name']}");

            $city = $cities[$data['city_key']];
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

            // Kategorien
            $categoryIds = $categories->whereIn('name', $data['category_names'])->pluck('id');
            $company->categories()->syncWithoutDetaching($categoryIds);

            // Oeffnungszeiten
            if ($company->openingHours()->count() === 0) {
                foreach ($data['opening_hours'] as $hour) {
                    CompanyOpeningHour::create([
                        'company_id' => $company->id,
                        'day_of_week' => $hour['day'],
                        'opens_at' => $hour['opens'] ?? null,
                        'closes_at' => $hour['closes'] ?? null,
                        'is_closed' => $hour['is_closed'] ?? false,
                    ]);
                }
            }

            // Bewertungen
            Review::unsetEventDispatcher();
            foreach ($data['reviews'] as $review) {
                Review::firstOrCreate(
                    ['company_id' => $company->id, 'author_name' => $review['author']],
                    [
                        'company_id' => $company->id,
                        'author_name' => $review['author'],
                        'rating' => $review['rating'],
                        'title' => $review['title'],
                        'body' => $review['body'],
                        'moderation_status' => Review::STATUS_APPROVED,
                        'is_approved' => true,
                        'approved_at' => now()->subDays(rand(7, 90)),
                    ]
                );
            }
            Review::setEventDispatcher(app('events'));
            $company->recalculateRating();

            // Jobs
            foreach ($data['jobs'] as $jobData) {
                Job::firstOrCreate(
                    ['company_id' => $company->id, 'slug' => $jobData['slug']],
                    [
                        'company_id' => $company->id,
                        'title' => $jobData['title'],
                        'slug' => $jobData['slug'],
                        'description' => $jobData['description'],
                        'requirements' => $jobData['requirements'],
                        'benefits' => $jobData['benefits'],
                        'employment_type' => $jobData['employment_type'],
                        'location' => $city->name,
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

            // Fotos
            $this->attachPhotos($company, $key);

            $this->command->info("  ✓ {$company->name} (Rating: {$company->rating}, {$company->rating_count} Bewertungen, " . count($data['jobs']) . " Jobs)");
            $this->command->newLine();
        }
    }

    private function attachPhotos(Company $company, string $companyKey): void
    {
        if (! isset($this->photos[$companyKey]) || $company->getMedia('logo')->count() > 0) {
            return;
        }

        $photos = $this->photos[$companyKey];
        $downloaded = 0;

        try {
            $this->downloadAndAttach($company, $photos['logo'], 'logo', 'logo.jpg');
            $downloaded++;
        } catch (\Exception $e) {
            $this->command->warn("  ⚠ Logo fehlgeschlagen: {$e->getMessage()}");
        }

        try {
            $this->downloadAndAttach($company, $photos['cover'], 'cover', 'cover.jpg');
            $downloaded++;
        } catch (\Exception $e) {
            $this->command->warn("  ⚠ Cover fehlgeschlagen: {$e->getMessage()}");
        }

        foreach ($photos['gallery'] as $i => $url) {
            try {
                $this->downloadAndAttach($company, $url, 'gallery', "gallery-" . ($i + 1) . ".jpg");
                $downloaded++;
            } catch (\Exception $e) {
                $this->command->warn("  ⚠ Galerie-Bild {$i} fehlgeschlagen: {$e->getMessage()}");
            }
        }

        $this->command->info("  → {$downloaded} Bilder heruntergeladen");
    }

    private function downloadAndAttach(Company $company, string $url, string $collection, string $filename): void
    {
        $response = Http::timeout(30)->get($url);
        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()}");
        }

        $tempPath = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($tempPath, $response->body());

        $company->addMedia($tempPath)
            ->usingFileName($filename)
            ->toMediaCollection($collection);
    }

    // ─── Firmendaten ──────────────────────────────────────────────────

    private function getCompanyData(): array
    {
        return [
            'bergmann' => [
                'name' => 'Bergmann Hoch- und Tiefbau GmbH',
                'slug' => 'bergmann-hoch-und-tiefbau',
                'city_key' => 'berlin',
                'description' => 'Seit 1987 bauen wir in Berlin und Brandenburg — vom Einfamilienhaus bis zur Gewerbeimmobilie. Angefangen hat alles als Zwei-Mann-Betrieb im Ostteil der Stadt, heute sind wir 45 Mitarbeiter und haben mehr als 600 Projekte abgeschlossen. Unser Schwerpunkt liegt auf schlüsselfertigem Hochbau, aber wir machen auch den Tiefbau selbst: Fundamente, Kanäle, Außenanlagen. Wer bei uns anruft, bekommt nicht erst in drei Wochen einen Termin, sondern eine ehrliche Aussage, wann wir anfangen können — und wann wir fertig sind.',
                'street' => 'Greifswalder Str.',
                'house_no' => '42',
                'zipcode' => '10405',
                'tel' => '030 4423 8891',
                'email' => 'info@bergmann-bau-berlin.de',
                'website' => 'https://www.bergmann-bau-berlin.de',
                'social_facebook' => 'https://www.facebook.com/bergmann.bau.berlin',
                'social_instagram' => 'https://www.instagram.com/bergmann_bau',
                'category_names' => ['Hochbau', 'Tiefbau', 'Bauunternehmen', 'Fundamentbau'],

                'opening_hours' => [
                    ['day' => 0, 'opens' => '06:30', 'closes' => '17:00'],
                    ['day' => 1, 'opens' => '06:30', 'closes' => '17:00'],
                    ['day' => 2, 'opens' => '06:30', 'closes' => '17:00'],
                    ['day' => 3, 'opens' => '06:30', 'closes' => '17:00'],
                    ['day' => 4, 'opens' => '06:30', 'closes' => '14:00'],
                    ['day' => 5, 'is_closed' => true],
                    ['day' => 6, 'is_closed' => true],
                ],

                'reviews' => [
                    [
                        'author' => 'Thomas M.',
                        'rating' => 5,
                        'title' => 'Einfamilienhaus termingerecht fertig',
                        'body' => 'Unser Einfamilienhaus wurde innerhalb von 8 Monaten schlüsselfertig übergeben — exakt im Zeitplan. Die Kommunikation war hervorragend, der Bauleiter war mindestens dreimal pro Woche vor Ort. Preis-Leistung absolut fair.',
                    ],
                    [
                        'author' => 'Sabine K.',
                        'rating' => 5,
                        'title' => 'Professionelle Tiefbauarbeiten',
                        'body' => 'Kanalsanierung im Altbaugebiet — keine einfache Aufgabe. Bergmann hat das sauber gelöst, Anwohner wurden informiert, Straße war nur 3 Tage gesperrt statt der angekündigten Woche. Top.',
                    ],
                    [
                        'author' => 'Jürgen R.',
                        'rating' => 4,
                        'title' => 'Solide Arbeit beim Anbau',
                        'body' => 'Anbau an unser Reihenhaus — statisch nicht trivial. Die Planung hat etwas gedauert, aber das Ergebnis stimmt. Einen Stern Abzug weil die Endreinigung vergessen wurde, aber insgesamt empfehlenswert.',
                    ],
                ],

                'jobs' => [
                    [
                        'title' => 'Polier Hochbau (m/w/d)',
                        'slug' => 'polier-hochbau-berlin-bergmann',
                        'description' => "Als Polier übernehmen Sie die Leitung unserer Hochbau-Baustellen in Berlin und Brandenburg.\n\nIhre Aufgaben:\n- Eigenverantwortliche Baustellenleitung im Hochbau\n- Koordination der Gewerke und Nachunternehmer\n- Materialbestellung und Qualitätskontrolle\n- Führung von 5-15 Facharbeitern\n- Aufmaß und Dokumentation\n- Abstimmung mit Bauleitung und Bauherren",
                        'requirements' => "- Meister oder Polier im Hochbau\n- Mindestens 5 Jahre Erfahrung als Polier\n- Führerschein Klasse B (BE wünschenswert)\n- Durchsetzungsvermögen und Organisationstalent\n- Deutsch fließend in Wort und Schrift",
                        'benefits' => "- Übertarifliche Bezahlung ab 4.500 EUR/Monat\n- Firmenwagen auch zur Privatnutzung\n- 30 Tage Urlaub\n- Betriebliche Altersvorsorge\n- Weiterbildungsbudget 2.000 EUR/Jahr",
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'salary_min' => 4500,
                        'salary_max' => 5500,
                        'salary_type' => Job::SALARY_MONTHLY,
                    ],
                    [
                        'title' => 'Maurer / Betonbauer (m/w/d)',
                        'slug' => 'maurer-betonbauer-berlin-bergmann',
                        'description' => "Wir suchen erfahrene Maurer und Betonbauer für unsere Bauprojekte im Raum Berlin.\n\nIhre Aufgaben:\n- Mauerwerksarbeiten im Wohnungs- und Gewerbebau\n- Betonierarbeiten (Fundamente, Decken, Stützen)\n- Schalungsarbeiten\n- Verputzarbeiten\n- Qualitätssicherung der eigenen Arbeit",
                        'requirements' => "- Abgeschlossene Ausbildung als Maurer oder Betonbauer\n- Mindestens 2 Jahre Berufserfahrung\n- Zuverlässigkeit und körperliche Belastbarkeit\n- Teamfähigkeit\n- Führerschein Klasse B",
                        'benefits' => "- Stundenlohn ab 20 EUR je nach Qualifikation\n- Geregelte Arbeitszeiten Mo-Fr\n- Fahrtkosten- und Verpflegungszuschuss\n- Modernes Werkzeug\n- Übernahme in Festanstellung",
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'salary_min' => 3200,
                        'salary_max' => 3800,
                        'salary_type' => Job::SALARY_MONTHLY,
                    ],
                    [
                        'title' => 'Auszubildender Hochbaufacharbeiter (m/w/d)',
                        'slug' => 'ausbildung-hochbau-berlin-bergmann',
                        'description' => "Starte deine Karriere im Baugewerbe bei einem der größten Bauunternehmen Berlins!\n\nDas erwartet dich:\n- 3-jährige duale Ausbildung\n- Abwechslungsreiche Projekte vom Keller bis zum Dach\n- Erfahrene Ausbilder auf jeder Baustelle\n- Möglichkeit zur Spezialisierung ab dem 2. Lehrjahr\n- Übernahmegarantie bei guter Leistung",
                        'requirements' => "- Hauptschulabschluss oder besser\n- Handwerkliches Geschick\n- Interesse an Mathematik und Technik\n- Körperliche Fitness\n- Teamfähigkeit",
                        'benefits' => "- Ausbildungsvergütung: 1. Jahr 920 EUR, 2. Jahr 1.100 EUR, 3. Jahr 1.350 EUR\n- Übernahmegarantie\n- Fahrtkostenzuschuss\n- Moderne Arbeitskleidung wird gestellt\n- Prüfungsvorbereitungskurse",
                        'employment_type' => Job::TYPE_AUSBILDUNG,
                        'salary_min' => 920,
                        'salary_max' => 1350,
                        'salary_type' => Job::SALARY_MONTHLY,
                    ],
                ],
            ],

            'kessler' => [
                'name' => 'Kessler Tiefbau & Kanalbau GmbH & Co. KG',
                'slug' => 'kessler-tiefbau-kanalbau',
                'city_key' => 'muenchen',
                'description' => 'Tiefbau ist Vertrauenssache — denn was unter der Erde passiert, sieht man nachher nicht mehr. Genau deshalb arbeiten wir seit 2001 nach dem Prinzip: Alles dokumentieren, alles sauber hinterlassen. Unsere Spezialität sind Kanalbau, Leitungsverlegung und Straßenbau im Großraum München. 32 Mitarbeiter, eigener Maschinenpark, keine Subunternehmer für die Kernarbeiten. Wenn Sie mit uns bauen, wissen Sie genau wer auf Ihrer Baustelle steht.',
                'street' => 'Dachauer Str.',
                'house_no' => '187',
                'zipcode' => '80637',
                'tel' => '089 5544 3321',
                'email' => 'info@kessler-tiefbau.de',
                'website' => 'https://www.kessler-tiefbau.de',
                'social_facebook' => null,
                'social_instagram' => 'https://www.instagram.com/kessler_tiefbau',
                'category_names' => ['Tiefbau', 'Kanalbau', 'Straßenbau', 'Rohrleitungsbau'],

                'opening_hours' => [
                    ['day' => 0, 'opens' => '06:00', 'closes' => '16:30'],
                    ['day' => 1, 'opens' => '06:00', 'closes' => '16:30'],
                    ['day' => 2, 'opens' => '06:00', 'closes' => '16:30'],
                    ['day' => 3, 'opens' => '06:00', 'closes' => '16:30'],
                    ['day' => 4, 'opens' => '06:00', 'closes' => '14:00'],
                    ['day' => 5, 'is_closed' => true],
                    ['day' => 6, 'is_closed' => true],
                ],

                'reviews' => [
                    [
                        'author' => 'Martin S.',
                        'rating' => 5,
                        'title' => 'Kanalsanierung ohne Ärger',
                        'body' => 'Komplette Kanalsanierung für unser Mehrfamilienhaus. Kessler hat vorab eine Kamerabefahrung gemacht, alles erklärt und dann in 4 Tagen erledigt. Die Mieter waren kaum beeinträchtigt. So muss das laufen.',
                    ],
                    [
                        'author' => 'Andrea B.',
                        'rating' => 5,
                        'title' => 'Straßenbau pünktlich fertig',
                        'body' => 'Erschließung eines Neubaugebiets — Kessler hat Straßen, Kanäle und Leitungsgräben termingerecht fertiggestellt. Besonders positiv: Die tägliche Abstimmung mit der Gemeinde lief reibungslos.',
                    ],
                    [
                        'author' => 'Franz-Josef W.',
                        'rating' => 4,
                        'title' => 'Gute Arbeit, Preis am oberen Ende',
                        'body' => 'Leitungsverlegung für unseren Neubau war handwerklich einwandfrei. Der Preis lag etwas über den anderen Angeboten, dafür gab es keine Nachforderungen. Unter dem Strich fair.',
                    ],
                ],

                'jobs' => [
                    [
                        'title' => 'Baggerführer / Baumaschinenführer (m/w/d)',
                        'slug' => 'baggerfuehrer-muenchen-kessler',
                        'description' => "Für unsere Tiefbau-Baustellen im Raum München suchen wir einen erfahrenen Baggerführer.\n\nIhre Aufgaben:\n- Bedienung von Baggern (5t bis 25t) und Radladern\n- Aushubarbeiten für Kanäle, Fundamente und Leitungsgräben\n- Verfüllung und Verdichtung\n- Tägliche Maschinenpflege und Wartungschecks\n- Zusammenarbeit mit dem Polier vor Ort",
                        'requirements' => "- Führerschein Klasse CE\n- Baggerschein / Baumaschinenschein\n- Mindestens 3 Jahre Erfahrung im Tiefbau\n- Zuverlässigkeit und Sorgfalt\n- Bereitschaft für wechselnde Einsatzorte",
                        'benefits' => "- Stundenlohn 22-26 EUR je nach Erfahrung\n- Eigenes Einsatzfahrzeug\n- Überstundenzuschläge\n- 30 Tage Urlaub\n- Moderner Maschinenpark (Liebherr, CAT)",
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'salary_min' => 3500,
                        'salary_max' => 4200,
                        'salary_type' => Job::SALARY_MONTHLY,
                    ],
                    [
                        'title' => 'Tiefbaufacharbeiter / Kanalbauer (m/w/d)',
                        'slug' => 'tiefbaufacharbeiter-muenchen-kessler',
                        'description' => "Verstärken Sie unser Kanalbau-Team im Großraum München.\n\nIhre Aufgaben:\n- Verlegen von Abwasser- und Regenwasserleitungen\n- Schachtbau und Einbau von Schachtbauwerken\n- Verbau- und Sicherungsarbeiten in Baugruben\n- Rohrverlegung in offener und geschlossener Bauweise\n- Prüfung der verlegten Leitungen (Dichtheitsprüfung)",
                        'requirements' => "- Ausbildung als Tiefbaufacharbeiter, Kanalbauer oder Rohrleitungsbauer\n- Erfahrung im Kanalbau wünschenswert\n- Körperliche Fitness\n- Führerschein Klasse B\n- Deutsch B1 oder besser",
                        'benefits' => "- Übertarifliche Bezahlung\n- Fahrtkosten und Verpflegungsgeld\n- Arbeitskleidung wird gestellt\n- Keine Montage — alle Baustellen im Umkreis München\n- Unbefristeter Vertrag ab Tag 1",
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'salary_min' => 2900,
                        'salary_max' => 3600,
                        'salary_type' => Job::SALARY_MONTHLY,
                    ],
                ],
            ],

            'erdreich' => [
                'name' => 'Erdreich Erdbau & Abbruch GmbH',
                'slug' => 'erdreich-erdbau-abbruch',
                'city_key' => 'hamburg',
                'description' => 'Abreißen, ausgraben, planieren — wir machen den Anfang, damit andere bauen können. In Hamburg und Umgebung sind wir die Spezialisten für Erdbau, kontrollierte Abbrucharbeiten und Baugrubensicherung. Unser Fuhrpark umfasst 18 Maschinen von der Miniraupe bis zum 30-Tonnen-Bagger. Ob Sie ein Einfamilienhaus abreißen oder eine Baugrube für ein Parkhaus brauchen — wir haben die passende Maschine und die Leute, die sie bedienen können. Seit 2008, inhabergeführt.',
                'street' => 'Billbrookdeich',
                'house_no' => '210',
                'zipcode' => '22113',
                'tel' => '040 7312 5580',
                'email' => 'anfrage@erdreich-hamburg.de',
                'website' => 'https://www.erdreich-hamburg.de',
                'social_facebook' => 'https://www.facebook.com/erdreich.hamburg',
                'social_instagram' => null,
                'category_names' => ['Erdbau', 'Abbrucharbeiten', 'Tiefbau'],

                'opening_hours' => [
                    ['day' => 0, 'opens' => '06:00', 'closes' => '16:00'],
                    ['day' => 1, 'opens' => '06:00', 'closes' => '16:00'],
                    ['day' => 2, 'opens' => '06:00', 'closes' => '16:00'],
                    ['day' => 3, 'opens' => '06:00', 'closes' => '16:00'],
                    ['day' => 4, 'opens' => '06:00', 'closes' => '13:00'],
                    ['day' => 5, 'is_closed' => true],
                    ['day' => 6, 'is_closed' => true],
                ],

                'reviews' => [
                    [
                        'author' => 'Henrik L.',
                        'rating' => 5,
                        'title' => 'Abbruch in Rekordzeit',
                        'body' => 'Altes Lagergebäude abgerissen, Gelände geräumt und planiert — in 6 Tagen statt der veranschlagten 10. Die Entsorgung der Baumaterialien war komplett im Angebot enthalten. Vorbildlich.',
                    ],
                    [
                        'author' => 'Silke N.',
                        'rating' => 5,
                        'title' => 'Baugrube exakt nach Plan',
                        'body' => 'Baugrube für unseren Neubau. Die Vermessung stimmte auf den Zentimeter, der Aushub war in 3 Tagen erledigt. Besonders beeindruckt hat mich, wie sauber die Zufahrt danach war.',
                    ],
                    [
                        'author' => 'Ralf T.',
                        'rating' => 4,
                        'title' => 'Ordentlich, aber teuer',
                        'body' => 'Grundstücksrodung und Erdaushub für ein Doppelhaus. Arbeit war einwandfrei, die Rechnung lag aber 15% über dem KVA. Nachtrag war nachvollziehbar (Bodenverhältnisse), hätte aber früher kommuniziert werden können.',
                    ],
                ],

                'jobs' => [
                    [
                        'title' => 'Baumaschinenführer Erdbau (m/w/d)',
                        'slug' => 'baumaschinenfuehrer-erdbau-hamburg-erdreich',
                        'description' => "Für unsere Erdbau-Projekte in Hamburg suchen wir einen erfahrenen Baumaschinenführer.\n\nIhre Aufgaben:\n- Bedienung von Bagger, Radlader und Planierraupe\n- Erdaushub, Auffüllung und Verdichtung\n- Geländemodellierung nach Bauplan\n- Transport von Schüttgütern auf der Baustelle\n- Unterstützung bei Abbrucharbeiten",
                        'requirements' => "- Baumaschinenschein (Erdbaumaschinen)\n- Führerschein CE von Vorteil\n- Mindestens 3 Jahre Erfahrung\n- Selbständige Arbeitsweise\n- Pünktlichkeit und Zuverlässigkeit",
                        'benefits' => "- Stundenlohn 21-25 EUR\n- Alle Überstunden werden bezahlt\n- Moderner Maschinenpark\n- Keine Montage\n- 30 Tage Urlaub",
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'salary_min' => 3400,
                        'salary_max' => 4000,
                        'salary_type' => Job::SALARY_MONTHLY,
                    ],
                    [
                        'title' => 'Abbruchfacharbeiter (m/w/d)',
                        'slug' => 'abbruchfacharbeiter-hamburg-erdreich',
                        'description' => "Wir suchen Verstärkung für unsere Abbruch-Kolonne.\n\nIhre Aufgaben:\n- Kontrollierter Rückbau von Gebäuden und Bauwerken\n- Bedienung von Abbruchgeräten (Hydraulikhammer, Betonschere)\n- Trennung und Sortierung von Abbruchmaterialien\n- Sicherung der Abbruchstelle\n- Entsorgungslogistik",
                        'requirements' => "- Erfahrung im Abbruch oder Tiefbau\n- Kenntnisse in Schadstoffsanierung von Vorteil\n- Körperliche Belastbarkeit\n- Führerschein B, CE wünschenswert\n- Teamfähigkeit",
                        'benefits' => "- Übertarifliche Bezahlung\n- Gefahrenzulage\n- Arbeitskleidung und PSA gestellt\n- Weiterbildung Schadstoffsanierung\n- Unbefristeter Vertrag",
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'salary_min' => 3000,
                        'salary_max' => 3800,
                        'salary_type' => Job::SALARY_MONTHLY,
                    ],
                ],
            ],

            'schachtmeister' => [
                'name' => 'Schachtmeister Frankfurt GmbH',
                'slug' => 'schachtmeister-frankfurt',
                'city_key' => 'frankfurt',
                'description' => 'Wir sind Frankfurts Spezialisten für alles, was unter dem Straßenniveau passiert: Schachtbau, Kanalsanierung, grabenloses Bauen und Leitungstiefbau. Seit 2005 arbeiten wir vorwiegend für Kommunen, Stadtwerke und Generalunternehmer im Rhein-Main-Gebiet. Unser Team von 25 Fachkräften beherrscht sowohl klassische offene Bauweisen als auch moderne grabenlose Verfahren wie Rohrvortrieb und Inliner-Sanierung. Wir investieren regelmäßig in Schulungen und neue Technik — weil im Tiefbau Stillstand Rückschritt bedeutet.',
                'street' => 'Hanauer Landstr.',
                'house_no' => '328',
                'zipcode' => '60314',
                'tel' => '069 8877 4455',
                'email' => 'kontakt@schachtmeister-ffm.de',
                'website' => 'https://www.schachtmeister-ffm.de',
                'social_facebook' => null,
                'social_instagram' => null,
                'category_names' => ['Kanalbau', 'Tiefbau', 'Rohrleitungsbau'],

                'opening_hours' => [
                    ['day' => 0, 'opens' => '07:00', 'closes' => '16:00'],
                    ['day' => 1, 'opens' => '07:00', 'closes' => '16:00'],
                    ['day' => 2, 'opens' => '07:00', 'closes' => '16:00'],
                    ['day' => 3, 'opens' => '07:00', 'closes' => '16:00'],
                    ['day' => 4, 'opens' => '07:00', 'closes' => '14:00'],
                    ['day' => 5, 'is_closed' => true],
                    ['day' => 6, 'is_closed' => true],
                ],

                'reviews' => [
                    [
                        'author' => 'Gerhard P.',
                        'rating' => 5,
                        'title' => 'Grabenloses Verfahren hat uns überzeugt',
                        'body' => 'Kanalreparatur unter einer vielbefahrenen Kreuzung — klassisch hätte das wochenlange Sperrung bedeutet. Schachtmeister hat das grabenlos in 3 Tagen gelöst. Die Stadt war begeistert, wir auch.',
                    ],
                    [
                        'author' => 'Claudia F.',
                        'rating' => 5,
                        'title' => 'Zuverlässig bei kommunalem Auftrag',
                        'body' => 'Als Stadtverwaltung arbeiten wir seit 4 Jahren mit Schachtmeister zusammen. Terminzuverlässig, saubere Dokumentation, faire Nachträge. Können wir uneingeschränkt weiterempfehlen.',
                    ],
                    [
                        'author' => 'Helmut B.',
                        'rating' => 4,
                        'title' => 'Kompetent, aber nicht der Günstigste',
                        'body' => 'Hausanschluss und Leitungsverlegung für unseren Neubau. Fachlich gibt es nichts zu meckern — die Jungs wissen was sie tun. Preislich liegt Schachtmeister im oberen Drittel, dafür stimmt die Qualität.',
                    ],
                ],

                'jobs' => [
                    [
                        'title' => 'Rohrleitungsbauer (m/w/d)',
                        'slug' => 'rohrleitungsbauer-frankfurt-schachtmeister',
                        'description' => "Für unsere Projekte im Rhein-Main-Gebiet suchen wir einen Rohrleitungsbauer.\n\nIhre Aufgaben:\n- Verlegung von Trinkwasser-, Gas- und Abwasserleitungen\n- Arbeiten in offener und geschlossener Bauweise\n- Schweißarbeiten an PE- und Stahlrohren\n- Dichtheitsprüfungen\n- Zusammenarbeit mit Versorgungsunternehmen",
                        'requirements' => "- Abgeschlossene Ausbildung als Rohrleitungsbauer oder vergleichbar\n- PE-Schweißerschein wünschenswert\n- Berufserfahrung im Leitungsbau\n- Führerschein Klasse B\n- Teamfähigkeit und Sorgfalt",
                        'benefits' => "- Attraktives Gehalt 3.200-4.000 EUR\n- Weiterbildung (PE-Schweißen, Spezialtiefbau)\n- 30 Tage Urlaub\n- Firmenwagen bei Rufbereitschaft\n- Unbefristeter Vertrag",
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'salary_min' => 3200,
                        'salary_max' => 4000,
                        'salary_type' => Job::SALARY_MONTHLY,
                    ],
                ],
            ],

            'stahlbau_wolf' => [
                'name' => 'Wolf Stahlbau & Hallenbau AG',
                'slug' => 'wolf-stahlbau-hallenbau',
                'city_key' => 'duesseldorf',
                'description' => 'Stahl ist unser Material, Hallen und Industriebauten sind unsere Spezialität. Die Wolf Stahlbau AG plant, fertigt und montiert Stahlkonstruktionen für Gewerbe und Industrie — von der 500m²-Lagerhalle bis zur 10.000m²-Produktionsstätte. Wir haben eine eigene Fertigung in Düsseldorf-Heerdt mit modernsten CNC-Anlagen und ein Montageteam von 20 erfahrenen Stahlbauern. Seit der Gründung 1995 haben wir über 300 Hallenprojekte in NRW und darüber hinaus realisiert. Referenzen auf Anfrage — wir zeigen Ihnen gerne, was wir gebaut haben.',
                'street' => 'Heerdter Lohweg',
                'house_no' => '55',
                'zipcode' => '40549',
                'tel' => '0211 5567 8890',
                'email' => 'anfrage@wolf-stahlbau.de',
                'website' => 'https://www.wolf-stahlbau.de',
                'social_facebook' => 'https://www.facebook.com/wolf.stahlbau',
                'social_instagram' => 'https://www.instagram.com/wolf_stahlbau',
                'category_names' => ['Stahlbau', 'Hochbau', 'Bauunternehmen'],

                'opening_hours' => [
                    ['day' => 0, 'opens' => '07:00', 'closes' => '16:30'],
                    ['day' => 1, 'opens' => '07:00', 'closes' => '16:30'],
                    ['day' => 2, 'opens' => '07:00', 'closes' => '16:30'],
                    ['day' => 3, 'opens' => '07:00', 'closes' => '16:30'],
                    ['day' => 4, 'opens' => '07:00', 'closes' => '14:00'],
                    ['day' => 5, 'is_closed' => true],
                    ['day' => 6, 'is_closed' => true],
                ],

                'reviews' => [
                    [
                        'author' => 'Markus E.',
                        'rating' => 5,
                        'title' => 'Neue Lagerhalle in 8 Wochen',
                        'body' => '2.000m² Lagerhalle inklusive Fundament, Bodenplatte und Dacheindeckung. Von der Bestellung bis zur Schlüsselübergabe vergingen nur 8 Wochen. Die Konstruktion ist solide, die Optik hochwertig. Klare Empfehlung.',
                    ],
                    [
                        'author' => 'Christine M.',
                        'rating' => 5,
                        'title' => 'Erweiterung Produktionshalle',
                        'body' => 'Anbau an unsere bestehende Halle — nahtloser Übergang zur alten Konstruktion, laufender Betrieb war kaum eingeschränkt. Wolf hat ein Montagekonzept entwickelt, das auch nachts und am Wochenende möglich war. Professionell.',
                    ],
                    [
                        'author' => 'Robert S.',
                        'rating' => 4,
                        'title' => 'Fachlich top, Kommunikation ausbaufähig',
                        'body' => 'Stahlbaukonstruktion für ein Autohaus — Ergebnis ist hervorragend. In der Planungsphase war die Kommunikation aber manchmal zäh, Rückmeldungen dauerten teils eine Woche. Auf der Baustelle dann aber alles reibungslos.',
                    ],
                ],

                'jobs' => [
                    [
                        'title' => 'Stahlbaumonteur / Metallbauer (m/w/d)',
                        'slug' => 'stahlbaumonteur-duesseldorf-wolf',
                        'description' => "Für unsere Montageprojekte in NRW und bundesweit suchen wir erfahrene Stahlbaumonteure.\n\nIhre Aufgaben:\n- Montage von Stahlkonstruktionen (Hallen, Industriebauten)\n- Arbeiten in der Höhe (Stahlbaumontage ab Hallendach)\n- Verschraubung und Schweißarbeiten vor Ort\n- Kranführung und Einweisung\n- Montageplanung mit dem Vorarbeiter",
                        'requirements' => "- Ausbildung als Metallbauer, Konstruktionsmechaniker oder Stahlbauer\n- Höhentauglichkeit (G41-Untersuchung)\n- Schweißerschein MAG/WIG wünschenswert\n- Bereitschaft zu Montagefahrten (Mo-Fr)\n- Führerschein B, CE von Vorteil",
                        'benefits' => "- Stundenlohn 22-28 EUR plus Montagezulage\n- Auslöse bei Montage\n- Firmenfahrzeug zur Montage\n- Hochwertige Arbeitskleidung\n- 30 Tage Urlaub\n- Zuschuss zur Altersvorsorge",
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'salary_min' => 3600,
                        'salary_max' => 4500,
                        'salary_type' => Job::SALARY_MONTHLY,
                    ],
                    [
                        'title' => 'Technischer Zeichner / CAD-Konstrukteur Stahlbau (m/w/d)',
                        'slug' => 'cad-konstrukteur-stahlbau-duesseldorf-wolf',
                        'description' => "Für unsere Konstruktionsabteilung in Düsseldorf suchen wir einen CAD-Konstrukteur.\n\nIhre Aufgaben:\n- Erstellung von Werkstattzeichnungen und Montageplänen in Tekla/Advance Steel\n- 3D-Modellierung von Stahlkonstruktionen\n- Stücklisten und Materialauszüge erstellen\n- Abstimmung mit Statikern und Bauleitern\n- Prüfung und Freigabe von Fertigungszeichnungen",
                        'requirements' => "- Ausbildung/Studium als Technischer Zeichner, Bauzeichner oder Konstrukteur\n- Erfahrung mit Tekla Structures oder Advance Steel\n- Kenntnisse im Stahlbau (Normen, Verbindungstechnik)\n- Sorgfältige und strukturierte Arbeitsweise\n- AutoCAD-Kenntnisse",
                        'benefits' => "- Gehalt 3.500-4.500 EUR je nach Erfahrung\n- Moderner Arbeitsplatz mit Dual-Monitor\n- 2 Tage Home-Office möglich\n- Weiterbildung (Tekla-Schulungen)\n- Gleitzeit\n- 30 Tage Urlaub",
                        'employment_type' => Job::TYPE_VOLLZEIT,
                        'salary_min' => 3500,
                        'salary_max' => 4500,
                        'salary_type' => Job::SALARY_MONTHLY,
                    ],
                ],
            ],
        ];
    }

    // ─── Ratgeber-Artikel ─────────────────────────────────────────────

    private function seedArticles(): void
    {
        if (Post::count() > 0) {
            $this->command->warn('Blog-Artikel existieren bereits — überspringe.');
            return;
        }

        // Kategorien
        $categories = [];
        $catDefs = [
            ['name' => 'Ratgeber', 'slug' => 'ratgeber', 'description' => 'Praktische Tipps rund um Hoch- und Tiefbau', 'sort_order' => 1],
            ['name' => 'Kosten', 'slug' => 'kosten', 'description' => 'Preisübersichten und Kalkulationshilfen für Bauprojekte', 'sort_order' => 2],
            ['name' => 'Normen & Vorschriften', 'slug' => 'normen-vorschriften', 'description' => 'Baurecht, Normen und Vorschriften im Überblick', 'sort_order' => 3],
            ['name' => 'Baupraxis', 'slug' => 'baupraxis', 'description' => 'Erfahrungsberichte und Best Practices aus dem Baualltag', 'sort_order' => 4],
        ];
        foreach ($catDefs as $def) {
            $categories[$def['name']] = PostCategory::firstOrCreate(['slug' => $def['slug']], $def);
        }

        // Tags
        $tags = [];
        $tagNames = ['Hochbau', 'Tiefbau', 'Kanalbau', 'Erdbau', 'Stahlbau', 'Betonbau', 'Abbruch', 'Kosten', 'Baurecht', 'Sicherheit', 'Nachhaltigkeit', 'Ausschreibung'];
        foreach ($tagNames as $name) {
            $tags[Str::slug($name)] = PostTag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'slug' => Str::slug($name)]);
        }

        $articles = $this->getArticles();
        $count = 0;

        foreach ($articles as $article) {
            $category = $categories[$article['category']] ?? null;

            $post = Post::create([
                'title' => $article['title'],
                'slug' => $article['slug'],
                'excerpt' => $article['excerpt'],
                'body' => $article['body'],
                'category_id' => $category?->id,
                'author_id' => 1,
                'status' => 'published',
                'published_at' => now()->subDays(count($articles) - $count),
                'meta_title' => $article['meta_title'],
                'meta_description' => $article['meta_description'],
            ]);

            $tagIds = [];
            foreach ($article['tags'] as $tagName) {
                $slug = Str::slug($tagName);
                if (isset($tags[$slug])) {
                    $tagIds[] = $tags[$slug]->id;
                }
            }
            if ($tagIds) {
                $post->tags()->sync($tagIds);
            }

            $count++;
        }

        $this->command->info("  → {$count} Ratgeber-Artikel erstellt");
    }

    private function getArticles(): array
    {
        return [
            [
                'title' => 'Den richtigen Bauunternehmer finden: Worauf Sie achten sollten',
                'slug' => 'richtigen-bauunternehmer-finden',
                'excerpt' => 'Die Wahl des Bauunternehmers ist eine der wichtigsten Entscheidungen bei jedem Bauprojekt. Wir zeigen Ihnen, worauf es wirklich ankommt.',
                'category' => 'Ratgeber',
                'tags' => ['Hochbau', 'Tiefbau', 'Ausschreibung'],
                'meta_title' => 'Bauunternehmer finden — Checkliste und Tipps',
                'meta_description' => 'So finden Sie den richtigen Bauunternehmer für Ihr Projekt: Referenzen prüfen, Angebote vergleichen, Verträge richtig gestalten.',
                'body' => '<h2>Warum die Wahl des Bauunternehmers entscheidend ist</h2>
<p>Ein Bauprojekt steht und fällt mit dem Unternehmer, der es umsetzt. Ob Einfamilienhaus, Gewerbebau oder Tiefbauprojekt — der richtige Partner spart Ihnen Zeit, Geld und Nerven. Der falsche kostet Sie all das doppelt.</p>

<h2>1. Referenzen und Erfahrung prüfen</h2>
<p>Fragen Sie nach vergleichbaren Projekten. Ein Unternehmen, das hauptsächlich Einfamilienhäuser baut, ist nicht automatisch der richtige Partner für eine Industriehalle. Lassen Sie sich Referenzobjekte zeigen — idealerweise solche, die schon einige Jahre stehen.</p>

<h2>2. Mindestens drei Angebote einholen</h2>
<p>Vergleichen Sie nicht nur den Endpreis. Achten Sie auf:</p>
<ul>
<li>Welche Leistungen sind im Angebot enthalten, welche nicht?</li>
<li>Sind Baustelleneinrichtung, Entsorgung und Vermessung eingepreist?</li>
<li>Wie sind die Zahlungsbedingungen?</li>
<li>Gibt es einen verbindlichen Zeitplan?</li>
</ul>

<h2>3. Auf Handwerkskammer-Eintragung achten</h2>
<p>Seriöse Bauunternehmen sind in der Handwerkskammer eingetragen. Prüfen Sie außerdem, ob das Unternehmen eine Betriebshaftpflichtversicherung hat — bei Bauprojekten ein Muss.</p>

<h2>4. Vertragliche Absicherung</h2>
<p>Ein guter Bauvertrag regelt Leistungsumfang, Termine, Preise und Gewährleistung. Setzen Sie auf VOB/B-Verträge oder lassen Sie den Vertrag von einem Baujuristen prüfen. Mündliche Zusagen sind im Streitfall wertlos.</p>

<h2>5. Bewertungen lesen, aber richtig einordnen</h2>
<p>Online-Bewertungen geben einen Anhaltspunkt, aber ein einzelner negativer Kommentar sagt wenig aus. Achten Sie auf wiederkehrende Muster: Wird Termintreue gelobt oder kritisiert? Wie reagiert das Unternehmen auf Kritik?</p>

<h2>Fazit</h2>
<p>Nehmen Sie sich Zeit für die Auswahl. Ein guter Bauunternehmer kommuniziert offen, hat vergleichbare Projekte als Referenz und scheut sich nicht vor vertraglicher Klarheit. Nutzen Sie unser Portal, um Bewertungen zu lesen und den passenden Partner für Ihr Bauprojekt zu finden.</p>',
            ],
            [
                'title' => 'Was kostet Tiefbau? Preisübersicht für Kanalbau, Erdarbeiten & Co.',
                'slug' => 'tiefbau-kosten-preisuebersicht',
                'excerpt' => 'Tiefbauarbeiten sind schwer kalkulierbar. Wir geben Ihnen eine realistische Übersicht über die Kosten für Kanalbau, Erdarbeiten und Leitungsverlegung.',
                'category' => 'Kosten',
                'tags' => ['Tiefbau', 'Kanalbau', 'Erdbau', 'Kosten'],
                'meta_title' => 'Tiefbau Kosten 2026 — Preise für Kanalbau, Erdarbeiten & Leitungsbau',
                'meta_description' => 'Was kostet Tiefbau? Aktuelle Preise für Kanalbau (80-250 EUR/m), Erdaushub (15-35 EUR/m³) und Leitungsverlegung. Mit Beispielrechnung.',
                'body' => '<h2>Tiefbau-Kosten: Warum pauschale Angaben schwierig sind</h2>
<p>Im Tiefbau hängen die Kosten stark von den örtlichen Gegebenheiten ab: Bodenklasse, Grundwasserstand, vorhandene Leitungen, Zugänglichkeit der Baustelle. Trotzdem möchten wir Ihnen eine Orientierung geben.</p>

<h2>Erdarbeiten und Aushub</h2>
<p>Die Kosten für Erdarbeiten richten sich nach der Bodenklasse:</p>
<ul>
<li><strong>Bodenklasse 1-3</strong> (Oberboden, leichter Boden): 15-25 EUR/m³</li>
<li><strong>Bodenklasse 4-5</strong> (mittelschwerer bis schwerer Boden): 25-45 EUR/m³</li>
<li><strong>Bodenklasse 6-7</strong> (Fels, schwerer Fels): 60-150 EUR/m³</li>
</ul>
<p>Dazu kommen Transport- und Entsorgungskosten: 15-30 EUR/m³ für unbelasteten Bodenaushub, deutlich mehr bei kontaminiertem Material.</p>

<h2>Kanalbau</h2>
<p>Der laufende Meter Kanalbau kostet je nach Tiefe und Durchmesser:</p>
<ul>
<li><strong>Hausanschluss</strong> (DN 150, 1-2m Tiefe): 80-150 EUR/m</li>
<li><strong>Sammelkanal</strong> (DN 300, 2-3m Tiefe): 150-250 EUR/m</li>
<li><strong>Hauptkanal</strong> (DN 500+, 3m+ Tiefe): 250-500 EUR/m</li>
</ul>

<h2>Leitungsverlegung (Wasser, Gas, Strom)</h2>
<ul>
<li><strong>Wasserleitung</strong>: 60-120 EUR/m (inkl. Graben und Verfüllung)</li>
<li><strong>Gasleitung</strong>: 80-150 EUR/m</li>
<li><strong>Stromkabel</strong>: 40-80 EUR/m (ohne Kabel)</li>
</ul>

<h2>Beispielrechnung: Erschließung Einfamilienhaus</h2>
<p>Für ein durchschnittliches Einfamilienhaus fallen typischerweise an:</p>
<ul>
<li>Baugrube ausheben (200m³): ca. 5.000-7.000 EUR</li>
<li>Kanalanschluss (15m): ca. 1.500-2.500 EUR</li>
<li>Wasser-/Gasanschluss (20m): ca. 2.500-4.000 EUR</li>
<li>Stromanschluss (25m): ca. 1.500-2.500 EUR</li>
<li><strong>Gesamt Tiefbau: ca. 10.500-16.000 EUR</strong></li>
</ul>

<h2>So sparen Sie bei Tiefbauarbeiten</h2>
<p>Holen Sie mehrere Angebote ein. Bündeln Sie die Gewerke wenn möglich bei einem Unternehmen — ein Tiefbauer der Kanal und Leitungen zusammen verlegt, ist günstiger als zwei getrennte Firmen. Und: Planen Sie den Zeitpunkt. Im Winter sind viele Tiefbauer weniger ausgelastet.</p>',
            ],
            [
                'title' => 'Baugrubensicherung: Methoden, Pflichten und Kosten im Überblick',
                'slug' => 'baugrubensicherung-methoden-pflichten',
                'excerpt' => 'Eine sichere Baugrube ist Pflicht — und teurer als viele denken. Welche Verfahren es gibt und wann welches zum Einsatz kommt.',
                'category' => 'Normen & Vorschriften',
                'tags' => ['Tiefbau', 'Sicherheit', 'Baurecht'],
                'meta_title' => 'Baugrubensicherung — Methoden, Kosten und gesetzliche Pflichten',
                'meta_description' => 'Böschung, Spundwand oder Verbau? Welche Baugrubensicherung wann nötig ist, was sie kostet und welche Vorschriften gelten.',
                'body' => '<h2>Warum Baugrubensicherung keine Option, sondern Pflicht ist</h2>
<p>Ab einer Aushubtiefe von 1,25 Metern ist eine Sicherung der Baugrube gesetzlich vorgeschrieben (DIN 4124). Bei bindigen Böden kann schon ab 0,80 Meter ein Verbau nötig sein. Die Unfallstatistik zeigt: Baugrubenunfälle sind nach wie vor eine der häufigsten Todesursachen auf Baustellen.</p>

<h2>Die gängigsten Verfahren</h2>

<h3>1. Böschung</h3>
<p>Das einfachste Verfahren: Die Baugrubenwände werden abgeböscht, sodass sie von selbst stabil bleiben. Der Böschungswinkel richtet sich nach der Bodenart — bei Sand flacher als bei Lehm. Vorteil: Günstig. Nachteil: Braucht viel Platz.</p>

<h3>2. Verbau mit Kanaldielen</h3>
<p>Stahl- oder Holzdielen werden seitlich in den Boden getrieben und mit Steifen gegeneinander verspannt. Standard bei Leitungsgräben bis 4 Meter Tiefe. Kosten: 30-80 EUR/m² Wandfläche.</p>

<h3>3. Spundwandverbau</h3>
<p>Stahlprofile (Spundbohlen) werden ineinandergreifend in den Boden gerammt oder vibriert. Geeignet für tiefe Baugruben und bei hohem Grundwasserspiegel. Kosten: 100-250 EUR/m² — plus Rammgerät.</p>

<h3>4. Bohrpfahlwand</h3>
<p>Für besonders tiefe oder enge Baugruben: Überschnittene Bohrpfähle bilden eine wasserdichte Wand. Teuer (200-400 EUR/m²), aber bei innerstädtischen Projekten oft die einzige Option.</p>

<h2>Wer ist verantwortlich?</h2>
<p>Die Verantwortung liegt beim Bauherrn und beim ausführenden Unternehmen gemeinsam. Der Bauherr muss ein Bodengutachten beauftragen, das Unternehmen muss die Sicherung fachgerecht ausführen. Bei Unfällen haften beide.</p>

<h2>Unser Tipp</h2>
<p>Sparen Sie nicht an der Baugrubensicherung. Ein Bodengutachten kostet 1.500-3.000 EUR und gibt Planungssicherheit. Ohne Gutachten planen Tiefbauer mit dem schlechtesten Fall — und das wird teurer als das Gutachten.</p>',
            ],
            [
                'title' => 'Hochbau vs. Tiefbau: Unterschiede, Berufe und Karrierechancen',
                'slug' => 'hochbau-vs-tiefbau-unterschiede',
                'excerpt' => 'Was genau ist der Unterschied zwischen Hoch- und Tiefbau? Welche Berufe gibt es, und wo sind die Karrierechancen am besten?',
                'category' => 'Ratgeber',
                'tags' => ['Hochbau', 'Tiefbau'],
                'meta_title' => 'Hochbau vs. Tiefbau — Unterschiede, Berufe und Gehälter 2026',
                'meta_description' => 'Was unterscheidet Hochbau von Tiefbau? Berufsbilder, Gehälter und Karrierewege im Vergleich.',
                'body' => '<h2>Die grundlegende Unterscheidung</h2>
<p><strong>Hochbau</strong> umfasst alles, was über der Geländeoberfläche gebaut wird: Wohnhäuser, Bürogebäude, Hallen, Brückenaufbauten. <strong>Tiefbau</strong> bezeichnet alles unterhalb der Geländeoberfläche: Kanäle, Tunnel, Fundamente, Straßenunterbau, Leitungsgräben.</p>
<p>In der Praxis verschwimmen die Grenzen: Ein Bauunternehmen, das ein Haus baut, macht auch die Fundamentarbeiten (Tiefbau). Ein Straßenbauunternehmen baut auch Bordsteine und Gehwege (technisch Hochbau).</p>

<h2>Berufe im Hochbau</h2>
<ul>
<li><strong>Maurer</strong>: Mauerwerk, Putz, Beton — der Klassiker. Einstiegsgehalt ca. 2.800-3.200 EUR/Monat.</li>
<li><strong>Betonbauer</strong>: Schalung, Bewehrung, Betonierarbeiten. Gehalt ähnlich wie Maurer, mit Spezialisierung mehr.</li>
<li><strong>Zimmerer</strong>: Dachkonstruktionen, Holzbau. Einstieg ca. 2.600-3.000 EUR.</li>
<li><strong>Polier/Vorarbeiter</strong>: Baustellenleitung, Koordination. 3.800-5.000 EUR/Monat.</li>
<li><strong>Bauleiter</strong>: Projektverantwortung, Budgetkontrolle. 4.500-7.000 EUR/Monat.</li>
</ul>

<h2>Berufe im Tiefbau</h2>
<ul>
<li><strong>Tiefbaufacharbeiter</strong>: Erdarbeiten, Kanalverlegung. Einstieg ca. 2.600-3.000 EUR.</li>
<li><strong>Kanalbauer</strong>: Spezialist für Abwassersysteme. 2.800-3.500 EUR.</li>
<li><strong>Rohrleitungsbauer</strong>: Verlegung von Trinkwasser- und Gasleitungen. 2.800-3.600 EUR.</li>
<li><strong>Straßenbauer</strong>: Fahrbahndecken, Pflasterarbeiten. 2.700-3.200 EUR.</li>
<li><strong>Baumaschinenführer</strong>: Bagger, Radlader, Planierraupe. 3.000-4.000 EUR — je nach Schein und Erfahrung.</li>
</ul>

<h2>Karrierechancen 2026</h2>
<p>Beide Bereiche suchen händeringend Fachkräfte. Der demografische Wandel trifft den Bau besonders hart — viele erfahrene Poliere und Meister gehen in Rente. Wer eine Ausbildung im Bau macht und sich weiterqualifiziert (Vorarbeiter → Polier → Meister → Bauleiter), hat hervorragende Aufstiegschancen und praktisch eine Jobgarantie.</p>

<h2>Fazit</h2>
<p>Ob Hoch- oder Tiefbau — beide Bereiche bieten sichere, gut bezahlte Arbeit. Tiefbau ist körperlich anspruchsvoller und wetterabhängiger, bietet aber oft höhere Zuschläge. Hochbau ist vielseitiger und hat mehr Gestaltungsspielraum. Am Ende kommt es darauf an, was Ihnen mehr liegt: nach oben bauen oder nach unten graben.</p>',
            ],
            [
                'title' => 'Nachhaltigkeit im Tiefbau: Recycling-Baustoffe und CO2-Reduktion',
                'slug' => 'nachhaltigkeit-tiefbau-recycling',
                'excerpt' => 'Auch im Tiefbau wird Nachhaltigkeit wichtiger. Welche Recycling-Baustoffe sich eignen und wo CO2 eingespart werden kann.',
                'category' => 'Baupraxis',
                'tags' => ['Tiefbau', 'Nachhaltigkeit'],
                'meta_title' => 'Nachhaltiger Tiefbau — Recycling-Baustoffe und CO2-Einsparung',
                'meta_description' => 'Recycling-Schotter, RC-Beton und emissionsarme Maschinen: So wird Tiefbau nachhaltiger. Praxisbeispiele und Kostenvergleich.',
                'body' => '<h2>Warum Nachhaltigkeit im Tiefbau an Bedeutung gewinnt</h2>
<p>Der Bausektor verursacht rund 40% des weltweiten CO2-Ausstoßes. Im Tiefbau sind es vor allem der Transport von Baumaterialien, der Dieselverbrauch der Maschinen und die Entsorgung von Aushub, die ins Gewicht fallen. Kommunen und öffentliche Auftraggeber fordern zunehmend Nachhaltigkeitsnachweise in Ausschreibungen.</p>

<h2>Recycling-Baustoffe im Tiefbau</h2>

<h3>RC-Schotter und RC-Kies</h3>
<p>Aufbereiteter Bauschutt als Ersatz für Naturstein. Einsatz: Verfüllung, Frostschutzschichten, Unterbau. Qualität wird durch Güteüberwachung sichergestellt. Preis: 20-40% günstiger als Primärmaterial.</p>

<h3>RC-Beton</h3>
<p>Beton aus recyceltem Zuschlag. Für unterirdische Bauwerke (Schächte, Fundamente) mittlerweile bauaufsichtlich zugelassen. Die Druckfestigkeit entspricht der von Normalbeton bis C30/37.</p>

<h3>Aufbereiteter Bodenaushub</h3>
<p>Statt Bodenaushub auf die Deponie zu fahren und Neuboden anzuliefern, kann aufbereiteter Boden als Verfüllmaterial wiederverwendet werden. Spart Transport, Deponie-Gebühren und CO2.</p>

<h2>Emissionsarme Baumaschinen</h2>
<p>Elektrische Minibagger und Kompaktlader sind bereits marktreif. Für große Erdbewegungsmaschinen gibt es Hybridlösungen und HVO-Diesel (aus Pflanzenöl, bis 90% CO2-Reduktion). Einige Kommunen verlangen bereits emissionsarme Maschinen bei innerstädtischen Baustellen.</p>

<h2>Was Bauherren tun können</h2>
<ul>
<li>In der Ausschreibung RC-Baustoffe explizit zulassen oder fordern</li>
<li>Kurze Transportwege bevorzugen (lokale Lieferanten)</li>
<li>Bodenaushub auf dem Grundstück wiederverwenden lassen</li>
<li>Bei öffentlichen Projekten: Nachhaltigkeitskriterien in die Wertung aufnehmen</li>
</ul>

<h2>Fazit</h2>
<p>Nachhaltiger Tiefbau ist kein Widerspruch — oft ist er sogar günstiger als konventionelle Methoden. Recycling-Baustoffe sparen Material- und Entsorgungskosten, kurze Wege sparen Diesel. Der Trend geht klar in Richtung Kreislaufwirtschaft — und Bauunternehmen, die das früh umsetzen, haben einen Wettbewerbsvorteil.</p>',
            ],
            [
                'title' => 'Abbrucharbeiten: Genehmigungen, Ablauf und typische Kosten',
                'slug' => 'abbrucharbeiten-genehmigungen-kosten',
                'excerpt' => 'Bevor der Bagger kommt, braucht es Genehmigungen, Schadstoffgutachten und einen Plan. Was Sie über Abbrucharbeiten wissen müssen.',
                'category' => 'Ratgeber',
                'tags' => ['Abbruch', 'Kosten', 'Baurecht'],
                'meta_title' => 'Abbrucharbeiten — Genehmigungen, Ablauf und Kosten 2026',
                'meta_description' => 'Was kostet ein Abbruch? Welche Genehmigungen braucht man? Ablauf von der Planung bis zur Entsorgung — praxisnah erklärt.',
                'body' => '<h2>Abbruch ist mehr als "kaputt machen"</h2>
<p>Kontrollierter Rückbau ist ein Fachgebiet mit eigenen Regeln, Genehmigungen und Risiken. Wer ein Gebäude abreißen möchte, muss einiges beachten — von der Baugenehmigung bis zur Schadstoffentsorgung.</p>

<h2>Schritt 1: Abbruchgenehmigung beantragen</h2>
<p>In den meisten Bundesländern ist eine Abbruchgenehmigung erforderlich. Sie wird bei der Bauaufsichtsbehörde beantragt. Benötigte Unterlagen:</p>
<ul>
<li>Lageplan und Fotos des Gebäudes</li>
<li>Abbruchkonzept (Art und Weise des Rückbaus)</li>
<li>Schadstoffgutachten (Asbest, KMF, PAK, PCB)</li>
<li>Standsicherheitsnachweis für Nachbargebäude</li>
<li>Entsorgungskonzept</li>
</ul>
<p>Bearbeitungszeit: 4-8 Wochen. Kosten: 200-1.500 EUR je nach Gemeinde und Objektgröße.</p>

<h2>Schritt 2: Schadstoffuntersuchung</h2>
<p>Vor jedem Abbruch muss ein Schadstoffgutachten erstellt werden. Besonders bei Gebäuden vor 1990 finden sich häufig Asbest (Fassadenplatten, Dacheindeckung, Rohrisolierung) und KMF (Mineralwolle). Die Sanierung von Schadstoffen vor dem Abbruch ist Pflicht und kann erhebliche Zusatzkosten verursachen.</p>

<h2>Schritt 3: Der eigentliche Abbruch</h2>
<p>Je nach Gebäude und Lage kommen verschiedene Verfahren zum Einsatz:</p>
<ul>
<li><strong>Maschineller Abbruch</strong>: Bagger mit Abbruchzange, Hydraulikhammer. Standard für freistehende Gebäude.</li>
<li><strong>Rückbau von Hand</strong>: Bei beengten Verhältnissen oder wenn Nachbargebäude geschützt werden müssen.</li>
<li><strong>Sprengung</strong>: Nur bei sehr großen Bauwerken, selten und genehmigungspflichtig.</li>
</ul>

<h2>Typische Kosten</h2>
<ul>
<li><strong>Einfamilienhaus</strong> (unterkellert, 120m²): 15.000-35.000 EUR</li>
<li><strong>Mehrfamilienhaus</strong> (3-4 Etagen): 40.000-100.000 EUR</li>
<li><strong>Gewerbehalle</strong> (500m²): 20.000-50.000 EUR</li>
<li><strong>Schadstoffsanierung</strong>: 5.000-30.000 EUR zusätzlich</li>
</ul>
<p>Der größte Kostentreiber sind Schadstoffentsorgung und der Transportweg zur Deponie.</p>

<h2>Unser Tipp</h2>
<p>Beauftragen Sie zertifizierte Abbruchunternehmen. Diese kennen die Genehmigungsverfahren, haben die nötige Versicherung und entsorgen fachgerecht. Billigangebote von nicht-zertifizierten Firmen können teuer werden — insbesondere wenn Schadstoffe unsachgemäß entsorgt werden.</p>',
            ],
            [
                'title' => 'Ausschreibung im Bauwesen: So vergleichen Sie Angebote richtig',
                'slug' => 'ausschreibung-bauwesen-angebote-vergleichen',
                'excerpt' => 'Drei Angebote für die gleiche Leistung — und trotzdem nicht vergleichbar? Wir erklären, wie Sie Bauangebote richtig lesen und bewerten.',
                'category' => 'Baupraxis',
                'tags' => ['Ausschreibung', 'Kosten'],
                'meta_title' => 'Bauangebote vergleichen — Anleitung für Bauherren',
                'meta_description' => 'So vergleichen Sie Bauangebote richtig: Leistungsverzeichnis verstehen, versteckte Kosten erkennen, Preisspiegel erstellen.',
                'body' => '<h2>Das Problem: Äpfel mit Birnen vergleichen</h2>
<p>Sie haben drei Angebote für den Erdaushub eingeholt. Firma A bietet 18.000 EUR, Firma B 24.000 EUR und Firma C 21.000 EUR. Ist Firma A der klare Sieger? Nicht unbedingt. Denn möglicherweise hat Firma A die Entsorgung nicht eingepreist, Firma B hat bereits den Verbau kalkuliert und Firma C bietet die beste Leistung zum mittleren Preis.</p>

<h2>Schritt 1: Leistungsverzeichnis (LV) erstellen</h2>
<p>Die wichtigste Grundlage für vergleichbare Angebote ist ein detailliertes Leistungsverzeichnis. Es beschreibt jede einzelne Position mit Menge, Einheit und Beschreibung. Ohne LV bekommt jeder Anbieter eine andere Vorstellung davon, was gefordert ist.</p>

<h2>Schritt 2: Einheitspreise prüfen</h2>
<p>Vergleichen Sie nicht nur die Gesamtsumme, sondern die Einheitspreise pro Position. Ein auffällig niedriger Einheitspreis bei einer Hauptposition kann bedeuten, dass der Anbieter bei den Nachträgen zulangen wird.</p>

<h2>Schritt 3: Auf Vollständigkeit achten</h2>
<p>Häufig fehlende Positionen in Angeboten:</p>
<ul>
<li>Baustelleneinrichtung und -räumung</li>
<li>Bodengutachten und Vermessung</li>
<li>Entsorgung von belastetem Material</li>
<li>Wasserhaltung bei hohem Grundwasser</li>
<li>Winterbaumaßnahmen</li>
</ul>

<h2>Schritt 4: Preisspiegel erstellen</h2>
<p>Tragen Sie alle Angebote in eine Tabelle ein — Position für Position. So sehen Sie auf einen Blick, wo welcher Anbieter teurer oder günstiger ist. Auffällige Abweichungen (mehr als 30% vom Durchschnitt) sollten Sie hinterfragen.</p>

<h2>Fazit</h2>
<p>Das günstigste Angebot ist selten das beste. Investieren Sie Zeit in ein sauberes Leistungsverzeichnis und einen Preisspiegel — das spart Ihnen im Projektverlauf ein Vielfaches an Nachträgen und Ärger.</p>',
            ],
            [
                'title' => 'Straßenbau: Aufbau, Schichten und warum manche Straßen länger halten',
                'slug' => 'strassenbau-aufbau-schichten',
                'excerpt' => 'Warum hat die eine Straße nach 5 Jahren Schlaglöcher und die andere hält 20 Jahre? Ein Blick unter die Fahrbahndecke.',
                'category' => 'Baupraxis',
                'tags' => ['Tiefbau', 'Kosten'],
                'meta_title' => 'Straßenbau erklärt — Schichten, Materialien und Haltbarkeit',
                'meta_description' => 'Wie ist eine Straße aufgebaut? Frostschutz, Tragschicht, Binderschicht, Deckschicht — und warum jede Schicht wichtig ist.',
                'body' => '<h2>Der Aufbau einer Straße — mehr als nur Asphalt</h2>
<p>Was Sie als Autofahrer sehen, ist nur die oberste Schicht — die Deckschicht. Darunter verbergen sich drei bis fünf weitere Schichten, die gemeinsam dafür sorgen, dass die Straße Verkehrslasten trägt, Wasser ableitet und bei Frost nicht aufbricht.</p>

<h2>Die Schichten von unten nach oben</h2>

<h3>1. Planum (Untergrund)</h3>
<p>Der gewachsene Boden wird verdichtet und profiliert. Seine Tragfähigkeit bestimmt, wie dick die darüberliegenden Schichten sein müssen.</p>

<h3>2. Frostschutzschicht (FSS)</h3>
<p>30-50 cm Kies oder Schotter. Verhindert Frostschäden, indem sie Wasser nach unten ableitet. Ohne Frostschutz drückt gefrierendes Wasser die Fahrbahn nach oben — Schlaglöcher entstehen.</p>

<h3>3. Tragschicht</h3>
<p>Kies-Sand-Gemisch oder Schotter, verdichtet. Verteilt die Verkehrslasten gleichmäßig auf den Untergrund. 15-25 cm dick.</p>

<h3>4. Binderschicht (Asphalttragschicht)</h3>
<p>Erste Asphaltschicht, 8-14 cm dick. Grobes Mineralgemisch mit Bitumen. Verteilt die Lasten weiter und bildet eine ebene Grundlage für die Deckschicht.</p>

<h3>5. Deckschicht</h3>
<p>Die Fahrbahnoberfläche. 3-4 cm feiner Asphalt. Muss griffig, eben und wasserableitend sein. Verschiedene Varianten: Splittmastixasphalt (SMA) für Hauptstraßen, Asphaltbeton (AC) für Nebenstraßen.</p>

<h2>Warum manche Straßen länger halten</h2>
<p>Die häufigsten Ursachen für vorzeitige Schäden:</p>
<ul>
<li><strong>Zu dünne Frostschutzschicht</strong>: Spart kurzfristig Geld, verursacht langfristig Frostschäden</li>
<li><strong>Schlechte Verdichtung</strong>: Jede Schicht muss mit Walzen verdichtet und mit dem Plattendruckversuch geprüft werden</li>
<li><strong>Mangelhafte Entwässerung</strong>: Stehendes Wasser ist der größte Feind jeder Straße</li>
<li><strong>Falsche Materialwahl</strong>: Günstiger Recycling-Schotter kann funktionieren — muss aber die geforderte Kornverteilung haben</li>
</ul>

<h2>Fazit</h2>
<p>Guter Straßenbau beginnt unter der Oberfläche. Wer bei Frostschutz und Verdichtung spart, zahlt in 5-10 Jahren ein Vielfaches für die Sanierung. Achten Sie bei Angeboten darauf, dass alle Schichten mit Dicke und Material spezifiziert sind — und lassen Sie die Verdichtung prüfen.</p>',
            ],
        ];
    }

    // ─── FAQs ─────────────────────────────────────────────────────────

    private function seedFaqs(): void
    {
        if (FAQ::count() > 0) {
            $this->command->warn('FAQs existieren bereits — überspringe.');
            return;
        }

        $faqs = $this->getFaqs();
        foreach ($faqs as $index => $faq) {
            FAQ::create([
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'page' => $faq['page'],
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }

        $this->command->info("  → " . count($faqs) . " FAQs erstellt");
    }

    private function getFaqs(): array
    {
        return [
            // Startseite
            [
                'question' => 'Ist die Nutzung des Portals für Bauherren kostenlos?',
                'answer' => 'Ja, komplett. Sie können alle Firmeneinträge durchsuchen, Bewertungen lesen, Kontaktdaten einsehen und Angebote anfragen — ohne Registrierung und ohne Kosten. Das Portal finanziert sich über optionale Premium-Einträge der Bauunternehmen.',
                'page' => 'home',
            ],
            [
                'question' => 'Was kostet ein Firmeneintrag für mein Bauunternehmen?',
                'answer' => 'Der Basiseintrag ist dauerhaft kostenlos: Firmenname, Adresse, Kontaktdaten, Beschreibung und Logo. Mit Premium (9,90 EUR/Monat oder 99 EUR/Jahr) bekommen Sie hervorgehobene Platzierung, Bildergalerie, Öffnungszeiten, Statistiken, Antworten auf Bewertungen und die Möglichkeit, Stellenanzeigen zu veröffentlichen.',
                'page' => 'home',
            ],
            [
                'question' => 'Wie finde ich ein Bauunternehmen in meiner Nähe?',
                'answer' => 'Nutzen Sie die Suchleiste auf der Startseite. Geben Sie ein, was Sie suchen — zum Beispiel "Tiefbau", "Kanalbau" oder "Abbruch" — und Ihren Ort. Die Ergebnisse können Sie nach Kategorie, Bewertung und Entfernung filtern.',
                'page' => 'home',
            ],
            [
                'question' => 'Sind die Bewertungen auf dem Portal echt?',
                'answer' => 'Jede Bewertung wird von unserem Team geprüft, bevor sie veröffentlicht wird. Spam, Beleidigungen und offensichtliche Fake-Bewertungen werden aussortiert. Trotzdem können wir nicht zu 100% garantieren, dass jede Bewertung von einem tatsächlichen Kunden stammt — aber wir tun unser Bestes.',
                'page' => 'home',
            ],
            [
                'question' => 'Kann ich eine Bewertung abgeben, ohne mich zu registrieren?',
                'answer' => 'Ja. Sie brauchen keine Registrierung. Wählen Sie Ihre Sternebewertung, schreiben Sie Ihren Erfahrungsbericht und geben Sie optional Ihren Namen an. Anonyme Bewertungen sind ebenfalls möglich.',
                'page' => 'home',
            ],
            [
                'question' => 'Wie kann ich meinen Firmeneintrag übernehmen?',
                'answer' => 'Rufen Sie Ihr Firmenprofil auf und klicken Sie auf "Ist das Ihr Unternehmen?". Nach Registrierung und kurzer Verifizierung (z.B. Gewerbeanmeldung) gehört der Eintrag Ihnen. Das dauert in der Regel weniger als 48 Stunden.',
                'page' => 'home',
            ],
            [
                'question' => 'Was bringt mir Premium konkret?',
                'answer' => 'Mit Premium stehen Sie in den Suchergebnissen über den kostenlosen Einträgen. Dazu können Sie auf Bewertungen antworten, bis zu 20 Bilder Ihrer Bauprojekte hochladen, Öffnungszeiten anzeigen, Stellenanzeigen veröffentlichen und detaillierte Statistiken zu Ihren Profilaufrufen einsehen.',
                'page' => 'home',
            ],

            // FAQ-Seite
            [
                'question' => 'Kann ich auf Bewertungen antworten?',
                'answer' => 'Ja — als Premium-Nutzer. Im Dashboard finden Sie unter "Bewertungen" alle eingegangenen Meinungen. Dort können Sie direkt antworten, um sich für positives Feedback zu bedanken oder bei Kritik Ihre Sicht darzustellen.',
                'page' => 'faq',
            ],
            [
                'question' => 'Wie veröffentliche ich eine Stellenanzeige?',
                'answer' => 'Stellenanzeigen sind ein Premium-Feature. Im Dashboard unter "Stellenanzeigen" können Sie einen Job erstellen: Titel, Beschreibung, Beschäftigungsart (Vollzeit, Teilzeit, Ausbildung, Praktikum) und optional eine Gehaltsspanne. Ihre Anzeige erscheint auf Ihrem Firmenprofil und in der Jobbörse. Sie läuft nach 30 Tagen automatisch ab.',
                'page' => 'faq',
            ],
            [
                'question' => 'Welche Kategorien gibt es für Bauunternehmen?',
                'answer' => 'Unser Portal deckt den gesamten Hoch- und Tiefbau ab: Hochbau, Tiefbau, Straßenbau, Kanalbau, Erdbau, Betonbau, Stahlbau, Abbrucharbeiten, Fundamentbau, Rohrleitungsbau und mehr. Sie können Ihrem Eintrag mehrere Kategorien zuweisen.',
                'page' => 'faq',
            ],
            [
                'question' => 'Kann ich mein Premium-Abo jederzeit kündigen?',
                'answer' => 'Ja, ohne Wenn und Aber. Im Dashboard unter "Einstellungen" können Sie kündigen. Ihr Premium-Status bleibt bis zum Ende des bezahlten Zeitraums aktiv. Danach wechseln Sie automatisch zum kostenlosen Basiseintrag.',
                'page' => 'faq',
            ],
            [
                'question' => 'Wie lade ich Bilder meiner Bauprojekte hoch?',
                'answer' => 'Als Premium-Nutzer können Sie bis zu 20 Fotos in Ihre Galerie hochladen — plus Logo und Cover-Bild. Gehen Sie im Dashboard zu "Profil bearbeiten". Unterstützt werden JPG, PNG und WebP. Zeigen Sie Ihre besten Referenzprojekte!',
                'page' => 'faq',
            ],
            [
                'question' => 'Was bedeutet das Premium-Badge?',
                'answer' => 'Das Badge zeigt, dass der Firmeninhaber seinen Eintrag aktiv pflegt. Premium-Einträge haben mehr Informationen: Bildergalerien, Öffnungszeiten, Antworten auf Bewertungen. Für Sie als Auftraggeber ein gutes Zeichen — der Betrieb nimmt seinen Auftritt ernst.',
                'page' => 'faq',
            ],
            [
                'question' => 'Gibt es eine App?',
                'answer' => 'Derzeit nicht. Das Portal ist aber vollständig für Smartphones optimiert. Tipp: Speichern Sie die Seite als Lesezeichen auf Ihrem Startbildschirm — dann haben Sie quasi eine App.',
                'page' => 'faq',
            ],
            [
                'question' => 'Wie trage ich mein Bauunternehmen ein?',
                'answer' => 'Klicken Sie auf "Firma eintragen" und folgen Sie dem Formular: Firmenname, Adresse, Kontaktdaten, Kategorien — fertig. Sie können direkt ein Logo hochladen und eine Beschreibung hinterlegen. Ihr Eintrag ist sofort sichtbar.',
                'page' => 'faq',
            ],
        ];
    }
}
