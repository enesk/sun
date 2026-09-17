<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Support\CityUrl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Robots-Direktive und Canonical fuer Verzeichnisseiten (#7).
 *
 * Einzige Stelle, die fuer /firmen und /staedte/{slug} entscheidet, was
 * indexiert wird. Der Controller ruft forCompanyListing() bzw. forCityPage(),
 * die Layouts lesen robots() und canonical() und fallen nur dann auf ihre
 *
 * @section-Werte zurueck, wenn hier nichts gesetzt ist.
 *
 * Bewusst KEINE robots.txt-Sperre fuer /firmen?: Google muss das noindex lesen
 * koennen, sonst bleiben die URLs ohne Snippet im Index.
 *
 * Als scoped gebunden (AppServiceProvider), damit unter Queue/Octane kein
 * Zustand in die naechste Anfrage wandert.
 */
final class SeoService
{
    public const INDEX = 'index, follow';

    public const NOINDEX = 'noindex, follow';

    private ?string $robots = null;

    private ?string $canonical = null;

    /** @var array<int, array<string, mixed>> JSON-LD-Bloecke fuer den <head> (#8) */
    private array $jsonLd = [];

    /**
     * /firmen: ohne Query-Parameter indexierbar mit Self-Canonical. Mit
     * mindestens einem Parameter noindex; Canonical auf die Stadtseite, wenn
     * ?city= eine vorhandene Stadt mit Slug trifft, sonst auf /firmen.
     */
    public function forCompanyListing(Request $request, ?City $city = null): void
    {
        $listingUrl = route('portal.companies.index');

        if ($request->query() === []) {
            $this->set(self::INDEX, $listingUrl);

            return;
        }

        $canonical = $request->filled('city') && $city !== null && filled($city->slug) && ! City::isPlaceholderName($city->name)
            ? CityUrl::show($city)
            : $listingUrl;

        $this->set(self::NOINDEX, $canonical);
    }

    /**
     * /staedte/{slug}: ohne Parameter indexierbar. Seite 2+, Suchbegriff,
     * Sortierung oder Filter liefern noindex; der Canonical zeigt immer auf
     * die parameterlose Seite 1.
     *
     * @param  array{faqs?: list<array{question: string, answer: string}>}|null  $localHub  Ergebnis von CityContentResolver::forCity()
     */
    public function forCityPage(Request $request, City $city, ?LengthAwarePaginator $companies = null, ?array $localHub = null): void
    {
        $indexable = self::isIndexableCityPage($request);

        $this->set($indexable ? self::INDEX : self::NOINDEX, CityUrl::show($city));

        // ItemList nur auf der indexierbaren Seite 1 (#9)
        if ($indexable && $companies !== null && $companies->currentPage() === 1) {
            $this->addJsonLd(app(StructuredDataService::class)->forCityList(
                $city,
                collect($companies->items()),
                (int) ($city->getAttribute('companies_count') ?? $companies->total()),
            ));
        }

        // FAQPage aus derselben Liste, die die Seite sichtbar rendert (#12)
        if ($indexable && $localHub !== null) {
            $this->addJsonLd(app(StructuredDataService::class)->forCityFaq($city, $localHub['faqs'] ?? []));
        }
    }

    /**
     * Nur die parameterlose Stadtseite ist indexierbar. Dieselbe Regel
     * entscheidet, ob der Local Hub (Intro, Stadtteile, FAQ) gerendert wird.
     */
    public static function isIndexableCityPage(Request $request): bool
    {
        return $request->query() === [];
    }

    /**
     * Preisseite /premium (#17): ohne Parameter indexierbar; die Auswahl von
     * Stadt/Branche (?stadt=, ?branche=) liefert noindex mit Canonical auf
     * die parameterlose Seite. Title und Description setzt pricingMeta().
     */
    public function forPricingPage(Request $request): void
    {
        $this->set($request->query() === [] ? self::INDEX : self::NOINDEX, route('portal.premium.pricing'));
    }

    /**
     * @return array{title: string, description: string}
     */
    public function pricingMeta(string $portalName): array
    {
        return [
            'title' => __('premium.pricing.meta_title', ['portal' => $portalName]),
            'description' => __('premium.pricing.meta_description', ['portal' => $portalName]),
        ];
    }

    /**
     * BreadcrumbList aus den Eintraegen der sichtbaren Brotkrumen (#9).
     * Aufgerufen von der Komponente x-sun.breadcrumb selbst, damit JSON-LD und
     * Navigation garantiert dieselbe Liste lesen.
     *
     * @param  array<int, array{label: string, url?: string|null}>  $items
     */
    public function forBreadcrumbs(array $items): void
    {
        foreach ($this->jsonLd as $block) {
            if (($block['@type'] ?? null) === 'BreadcrumbList') {
                return;
            }
        }

        $this->addJsonLd(app(StructuredDataService::class)->forBreadcrumbs($items));
    }

    /**
     * Title, Description und H1 der Stadtseite aus den Tenant-Templates (#10).
     * Per-Stadt-Overrides aus city_contents haben Vorrang, siehe
     * CityMetaTemplates::forCity().
     *
     * @return array{title: string, description: string, heading: string}
     */
    public function cityMeta(City $city): array
    {
        return app(CityMetaTemplates::class)->forCity($city);
    }

    /**
     * Firmenprofil: LocalBusiness-JSON-LD aus dem StructuredDataService (#8).
     * Robots/Canonical bleiben hier unberuehrt, die setzt die Profil-Blade.
     */
    public function forCompanyProfile(Company $company): void
    {
        $this->addJsonLd(app(StructuredDataService::class)->forCompany($company));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addJsonLd(array $data): void
    {
        if ($data !== []) {
            $this->jsonLd[] = $data;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function jsonLd(): array
    {
        return $this->jsonLd;
    }

    public function robots(): ?string
    {
        return $this->robots;
    }

    public function canonical(): ?string
    {
        return $this->canonical;
    }

    private function set(string $robots, string $canonical): void
    {
        $this->robots = $robots;
        $this->canonical = $canonical;
    }
}
