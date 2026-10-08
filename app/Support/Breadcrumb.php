<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\Job;

/**
 * Brotkrumen der Stadt- und Profilseiten (#6).
 *
 * Liefert die Eintraege als Liste von ['label' => ..., 'url' => ...]; jeder
 * Eintrag traegt eine absolute URL, auch der letzte (die Seite selbst). Die
 * sichtbare Navigation (x-sun.breadcrumb) und die BreadcrumbList im JSON-LD
 * sollen dieselbe Liste lesen, damit es keinen zweiten Aufbau gibt.
 */
final class Breadcrumb
{
    /**
     * Startseite > {Branche} in {Stadt} > Firmenname
     *
     * @return array<int, array{label: string, url: string}>
     */
    public static function forCompany(Company $company): array
    {
        $plural = (string) config('themes.sun-v2.search.branch_plural');
        $city = $company->city;

        return [
            self::home(),
            $city instanceof City
                ? ['label' => "{$plural} in {$city->name}", 'url' => CityUrl::show($city)]
                : ['label' => $plural, 'url' => route('portal.companies.index')],
            ['label' => (string) $company->name, 'url' => (string) $company->portal_url],
        ];
    }

    /**
     * Startseite > {Stadt}
     *
     * @return array<int, array{label: string, url: string}>
     */
    public static function forCity(City $city): array
    {
        return [
            self::home(),
            ['label' => (string) $city->name, 'url' => CityUrl::show($city)],
        ];
    }

    /**
     * Startseite > Preise (#17)
     *
     * @return array<int, array{label: string, url: string}>
     */
    public static function forPricing(): array
    {
        return [
            self::home(),
            ['label' => __('premium.pricing.crumb'), 'url' => route('portal.premium.pricing')],
        ];
    }

    /**
     * Startseite > {branche_plural} [> Suchbegriff oder Ort] (#32)
     *
     * Der dritte Eintrag ist der Krumen der Suchergebnisseite
     * (App\Themes\SunV2\SearchViewComposer::crumb) und traegt die volle URL
     * samt Filtern, damit er sich von der parameterlosen Liste unterscheidet.
     *
     * @return array<int, array{label: string, url: string}>
     */
    public static function forCompanySearch(?string $crumb = null): array
    {
        $items = [
            self::home(),
            ['label' => __('portal.search.crumb'), 'url' => route('portal.companies.index')],
        ];

        if ($crumb !== null && $crumb !== '') {
            $items[] = ['label' => $crumb, 'url' => request()->fullUrl()];
        }

        return $items;
    }

    /**
     * Startseite > Leistungen (#32)
     *
     * @return array<int, array{label: string, url: string}>
     */
    public static function forCategories(): array
    {
        return [
            self::home(),
            ['label' => __('portal.layout.footer.services'), 'url' => route('portal.categories.index')],
        ];
    }

    /**
     * Startseite > Leistungen > {Leistung} (#32)
     *
     * @return array<int, array{label: string, url: string}>
     */
    public static function forCategory(Category $category): array
    {
        return [
            ...self::forCategories(),
            ['label' => (string) $category->name, 'url' => route('portal.categories.show', $category->slug)],
        ];
    }

    /**
     * Startseite > Staedte [> {Bundesland}] (#32)
     *
     * Mit ?land= ist die Landesliste eine eigene, indexierbare Seite, deshalb
     * die dritte Stufe mit dem Slug in der URL.
     *
     * @return array<int, array{label: string, url: string}>
     */
    public static function forCities(?string $land = null, ?string $landSlug = null): array
    {
        $items = [
            self::home(),
            ['label' => __('portal.layout.header.cities'), 'url' => route('portal.cities.index')],
        ];

        if ($land !== null && $land !== '') {
            $items[] = [
                'label' => $land,
                'url' => route('portal.cities.index', $landSlug !== null && $landSlug !== '' ? ['land' => $landSlug] : []),
            ];
        }

        return $items;
    }

    /**
     * Startseite > Stellenanzeigen (#32)
     *
     * @return array<int, array{label: string, url: string}>
     */
    public static function forJobs(): array
    {
        return [
            self::home(),
            ['label' => __('portal.layout.header.jobs'), 'url' => route('portal.jobs.index')],
        ];
    }

    /**
     * Startseite > Stellenanzeigen > {Stellentitel} (#33)
     *
     * @return array<int, array{label: string, url: string}>
     */
    public static function forJob(Job $job): array
    {
        return [
            ...self::forJobs(),
            ['label' => (string) $job->title, 'url' => route('portal.jobs.show', $job->slug)],
        ];
    }

    /**
     * @return array{label: string, url: string}
     */
    private static function home(): array
    {
        return ['label' => __('portal.layout.breadcrumb.home'), 'url' => route('home')];
    }
}
