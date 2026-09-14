<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyOpeningHour;
use App\Support\CityUrl;
use App\Support\PhoneNumber;
use App\Support\TenantCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Schema.org-JSON-LD fuer Firmenprofile (#8).
 *
 * Einzige Quelle der strukturierten Daten einer Profilseite. Der @type kommt
 * je Portal aus config/tenant-schema-types.php, damit derselbe Code auf allen
 * Portalen den passenden LocalBusiness-Untertyp ausspielt.
 *
 * Grundsatz: nur Felder mit echten Daten. Leere Strings und null fallen raus,
 * aggregateRating nur mit freigegebenen Bewertungen (nie die importierten
 * Google-Werte aus companies.rating), Oeffnungszeiten nur wenn gespeichert.
 */
final class StructuredDataService
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * @return array<string, mixed>
     */
    public function forCompany(Company $company): array
    {
        // Profil-URL und Bild-URLs haengen am Host der Anfrage; ein Lauf auf
        // der Konsole kennt nur APP_URL und darf den Cache nicht befuellen.
        if (app()->runningInConsole()) {
            return $this->build($company);
        }

        return Cache::remember(
            self::cacheKey($company->id),
            (int) config('tenant-schema-types.cache_ttl', 21600),
            fn (): array => $this->build($company),
        );
    }

    /**
     * Stadtseite: ItemList der auf Seite 1 sichtbaren Betriebe (#9).
     *
     * Je Eintrag nur url + name — kein LocalBusiness, kein AggregateRating,
     * die stehen auf dem Profil. numberOfItems ist die Gesamtzahl aktiver
     * Betriebe der Stadt, nicht die Seitengroesse. Nur fuer die parameterlose
     * Seite 1 aufrufen; Seite 2+ und Filter sind noindex (SeoService, #7).
     *
     * Cache 1 h: Aenderungen an Sortierung oder Bewertung eines Betriebs
     * laufen ueber die TTL aus, eine gezielte Invalidierung gibt es nicht.
     *
     * @param  Collection<int, Company>  $companies
     * @return array<string, mixed>
     */
    public function forCityList(City $city, Collection $companies, ?int $total = null): array
    {
        if ($companies->isEmpty()) {
            return [];
        }

        $build = fn (): array => [
            '@'.'context' => 'https://schema.org',
            '@type' => 'ItemList',
            'url' => CityUrl::show($city),
            'numberOfItems' => $total ?? (int) ($city->getAttribute('companies_count') ?? $companies->count()),
            'itemListElement' => $companies->values()->map(fn (Company $company, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => $company->portal_url,
                'name' => (string) $company->name,
            ])->all(),
        ];

        if (app()->runningInConsole()) {
            return $build();
        }

        return Cache::remember(TenantCache::key("city.{$city->id}.jsonld.page1"), 3600, $build);
    }

    /**
     * Stadtseite: FAQPage aus den sichtbaren Fragen des Local Hubs (#12).
     *
     * Bekommt exakt die Liste, die auch x-city.faq rendert (Ergebnis von
     * CityContentResolver::forCity()['faqs']) — Frage und Antwort werden nicht
     * umformuliert, gekuerzt oder neu aufgeloest. Antworten sind Klartext mit
     * Absaetzen. Nur fuer die indexierbare Seite 1 aufrufen.
     *
     * @param  list<array{question: string, answer: string}>  $faqs
     * @return array<string, mixed>
     */
    public function forCityFaq(City $city, array $faqs): array
    {
        if ($faqs === []) {
            return [];
        }

        return [
            '@'.'context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'url' => CityUrl::show($city),
            'mainEntity' => array_map(fn (array $faq): array => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ], array_values($faqs)),
        ];
    }

    /**
     * BreadcrumbList aus denselben Eintraegen wie die sichtbare Navigation
     * (App\Support\Breadcrumb, gerendert von x-sun.breadcrumb). Positionen und
     * URLs werden 1:1 uebernommen, es gibt keinen zweiten Aufbau.
     *
     * @param  array<int, array{label: string, url?: string|null}>  $items
     * @return array<string, mixed>
     */
    public function forBreadcrumbs(array $items): array
    {
        $items = array_values($items);

        if (count($items) < 2) {
            return [];
        }

        return [
            '@'.'context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $item, int $index): array => $this->withoutEmpty([
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => (string) $item['label'],
                'item' => $item['url'] ?? null,
            ]), $items, array_keys($items)),
        ];
    }

    public static function forget(int $companyId): void
    {
        Cache::forget(self::cacheKey($companyId));
    }

    public static function cacheKey(int $companyId): string
    {
        return TenantCache::key("company.{$companyId}.jsonld");
    }

    public function businessType(): string
    {
        $default = (string) config('tenant-schema-types.default', 'LocalBusiness');
        $tenant = tenant();

        if ($tenant === null) {
            return $default;
        }

        $override = $tenant->getAttribute('schema_business_type');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        $domain = Str::lower((string) $tenant->getAttribute('domain'));
        $domains = (array) config('tenant-schema-types.domains', []);
        if (isset($domains[$domain])) {
            return $domains[$domain];
        }

        $slug = Str::slug((string) $tenant->getAttribute('name')).'-'.Str::slug($domain);
        foreach ((array) config('tenant-schema-types.needles', []) as $needle => $type) {
            if (str_contains($slug, (string) $needle)) {
                return $type;
            }
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Company $company): array
    {
        $company->loadMissing(['city', 'media', 'openingHours']);

        $profileUrl = $company->portal_url;

        return $this->withoutEmpty([
            '@'.'context' => 'https://schema.org',
            '@type' => $this->businessType(),
            '@id' => "{$profileUrl}#business",
            'name' => $company->name,
            'url' => $profileUrl,
            'mainEntityOfPage' => $profileUrl,
            'sameAs' => $this->website($company->website),
            'telephone' => PhoneNumber::toE164($company->tel),
            'image' => $this->image($company),
            'address' => $this->address($company),
            'geo' => $this->geo($company),
            'aggregateRating' => $this->aggregateRating($company),
            'openingHoursSpecification' => $this->openingHours($company),
        ]);
    }

    private function website(?string $website): ?string
    {
        $website = trim((string) $website);

        if ($website === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $website)) {
            $website = "https://{$website}";
        }

        return filter_var($website, FILTER_VALIDATE_URL) ? $website : null;
    }

    private function image(Company $company): ?string
    {
        $url = $company->logo_url ?: $company->cover_url;

        if (blank($url)) {
            return null;
        }

        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }

    /**
     * @return array<string, string>|null
     */
    private function address(Company $company): ?array
    {
        $street = trim("{$company->street} {$company->house_no}");
        $locality = $company->city?->name;

        if ($street === '' && blank($company->zipcode) && blank($locality)) {
            return null;
        }

        return $this->withoutEmpty([
            '@type' => 'PostalAddress',
            'streetAddress' => $street,
            'postalCode' => $company->zipcode,
            'addressLocality' => $locality,
            'addressCountry' => 'DE',
        ]);
    }

    /**
     * Koordinaten nur, wenn die Tenant-Datenbank sie an der Firma fuehrt —
     * die Stadtmitte aus cities waere ein falscher Standort.
     *
     * @return array<string, mixed>|null
     */
    private function geo(Company $company): ?array
    {
        $lat = $company->getAttribute('latitude');
        $lng = $company->getAttribute('longitude');

        if (! is_numeric($lat) || ! is_numeric($lng) || ((float) $lat === 0.0 && (float) $lng === 0.0)) {
            return null;
        }

        return [
            '@type' => 'GeoCoordinates',
            'latitude' => round((float) $lat, 6),
            'longitude' => round((float) $lng, 6),
        ];
    }

    /**
     * Aus den freigegebenen Bewertungen gerechnet, nicht aus companies.rating:
     * dort stehen beim Import die Google-Werte.
     *
     * @return array<string, mixed>|null
     */
    private function aggregateRating(Company $company): ?array
    {
        $stats = $company->approvedReviews()
            ->toBase()
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')
            ->first();

        $count = (int) ($stats->total ?? 0);

        if ($count < 1) {
            return null;
        }

        return [
            '@type' => 'AggregateRating',
            'ratingValue' => round((float) $stats->average, 1),
            'reviewCount' => $count,
            'bestRating' => 5,
            'worstRating' => 1,
        ];
    }

    /**
     * Tage mit denselben Zeiten werden zu einer Angabe zusammengefasst.
     * Geschlossene Tage und Tage ohne vollstaendige Zeiten entfallen.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function openingHours(Company $company): ?array
    {
        $groups = [];

        /** @var CompanyOpeningHour $hour */
        foreach ($company->openingHours->sortBy('day_of_week') as $hour) {
            $day = self::DAYS[$hour->day_of_week] ?? null;

            if ($day === null || $hour->is_closed || blank($hour->opens_at) || blank($hour->closes_at)) {
                continue;
            }

            $opens = substr((string) $hour->opens_at, 0, 5);
            $closes = substr((string) $hour->closes_at, 0, 5);
            $key = "{$opens}-{$closes}";

            $groups[$key] ??= [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => [],
                'opens' => $opens,
                'closes' => $closes,
            ];

            if (! in_array($day, $groups[$key]['dayOfWeek'], true)) {
                $groups[$key]['dayOfWeek'][] = $day;
            }
        }

        return $groups === [] ? null : array_values($groups);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutEmpty(array $data): array
    {
        return array_filter($data, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
