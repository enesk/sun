<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Portal\City;
use App\Models\Portal\Company;

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
     * @return array{label: string, url: string}
     */
    private static function home(): array
    {
        return ['label' => 'Startseite', 'url' => route('home')];
    }
}
