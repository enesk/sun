<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Constants\TenantConfigConstants;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Support\TenantCache;
use App\Themes\ThemeManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Title-, Description- und H1-Templates der Stadtseite /staedte/{slug} (#10).
 *
 * Einstieg ist SeoService::cityMeta(). Die Templates liegen als Tenant-Attribute
 * (TenantConfigConstants::SEO_CITY_*), gepflegt im Dashboard unter
 * "SEO-Templates" (App\Filament\Dashboard\Pages\SeoTemplates). Leere Felder
 * fallen auf DEFAULTS zurueck.
 */
final class CityMetaTemplates
{
    /**
     * Platzhalter samt Erklaerung fuer die Oberflaeche.
     *
     * @var array<string, string>
     */
    public const PLACEHOLDERS = [
        '{count}' => 'Anzahl aktiver Betriebe in der Stadt (nur in den Templates ab 3 Betrieben sinnvoll).',
        '{trade}' => 'Branchenbezeichnung im Plural, z. B. "Elektriker".',
        '{city}' => 'Name der Stadt.',
        '{year}' => 'Aktuelles Jahr.',
        '{portal}' => 'Name des Portals.',
    ];

    /**
     * Unter dieser Anzahl aktiver Betriebe gelten die Fallback-Templates ohne
     * {count}, damit kein "Die 1 besten ..." entsteht.
     */
    public const MIN_COUNT = 3;

    public const TITLE_MAX = 60;

    public const DESCRIPTION_MAX = 155;

    /**
     * Vorgaben, solange der Tenant nichts gepflegt hat. Die Fassung fuer
     * elektrikerportal.com schreibt der SeoTemplateSeeder.
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        TenantConfigConstants::SEO_CITY_TITLE => 'Die {count} besten {trade} in {city} ({year}) | {portal}',
        TenantConfigConstants::SEO_CITY_DESCRIPTION => 'Vergleiche {count} {trade} in {city}: Bewertungen, Kontakt und Öffnungszeiten auf einen Blick. Kostenlos anfragen.',
        TenantConfigConstants::SEO_CITY_HEADING => 'Die {count} besten {trade} in {city}',
        TenantConfigConstants::SEO_CITY_FALLBACK_TITLE => '{trade} in {city} finden ({year}) | {portal}',
        TenantConfigConstants::SEO_CITY_FALLBACK_DESCRIPTION => '{trade} in {city} finden: Bewertungen, Kontakt und Öffnungszeiten auf einen Blick. Kostenlos anfragen.',
        TenantConfigConstants::SEO_CITY_FALLBACK_HEADING => '{trade} in {city} finden',
    ];

    private const COUNT_TTL_SECONDS = 6 * 3600;

    private const TIMEZONE = 'Europe/Berlin';

    public function __construct(private readonly ThemeManager $themes) {}

    /**
     * Vorrang: city_contents.meta_title/meta_description der Stadt, dann das
     * Tenant-Template, dann DEFAULTS. Die H1 kommt immer aus dem Template.
     *
     * @return array{title: string, description: string, heading: string}
     */
    public function forCity(City $city): array
    {
        $count = $this->activeCompanyCount($city);

        [$titleKey, $descriptionKey, $headingKey] = $count < self::MIN_COUNT
            ? [TenantConfigConstants::SEO_CITY_FALLBACK_TITLE, TenantConfigConstants::SEO_CITY_FALLBACK_DESCRIPTION, TenantConfigConstants::SEO_CITY_FALLBACK_HEADING]
            : [TenantConfigConstants::SEO_CITY_TITLE, TenantConfigConstants::SEO_CITY_DESCRIPTION, TenantConfigConstants::SEO_CITY_HEADING];

        $values = [
            '{count}' => number_format($count, 0, ',', '.'),
            '{trade}' => $this->tradePlural(),
            '{city}' => (string) $city->name,
            '{year}' => (string) Carbon::now(self::TIMEZONE)->year,
            '{portal}' => (string) (tenant()?->name ?? config('app.name')),
        ];

        $override = $city->cityContent;

        return [
            'title' => self::truncate(
                filled($override?->meta_title) ? (string) $override->meta_title : $this->render($titleKey, $values),
                self::TITLE_MAX,
            ),
            'description' => self::truncate(
                filled($override?->meta_description) ? (string) $override->meta_description : $this->render($descriptionKey, $values),
                self::DESCRIPTION_MAX,
            ),
            'heading' => $this->render($headingKey, $values),
        ];
    }

    /**
     * Aktive Betriebe der Stadt, je Stadt gecacht: 6 Stunden, nie ueber den
     * Jahreswechsel hinaus, damit Anzahl und {year} gemeinsam frisch werden.
     */
    public function activeCompanyCount(City $city): int
    {
        $now = Carbon::now(self::TIMEZONE);
        $expires = $now->copy()->addSeconds(self::COUNT_TTL_SECONDS)
            ->min($now->copy()->addYear()->startOfYear());

        return (int) Cache::remember(
            TenantCache::key("seo.city.active_count.{$city->id}"),
            $expires,
            fn (): int => Company::active()->where('city_id', $city->id)->count(),
        );
    }

    /**
     * {trade}: Tenant-Einstellung, sonst Plural aus der Theme-Config, sonst "Firmen".
     */
    public function tradePlural(): string
    {
        $value = tenant()?->getAttribute(TenantConfigConstants::SEO_TRADE_PLURAL);

        if (filled($value)) {
            return trim((string) $value);
        }

        $theme = $this->themes->active()?->slug;

        return (string) (config("themes.{$theme}.search.branch_plural") ?: 'Firmen');
    }

    /**
     * Kuerzt am Wortende auf hoechstens $max Zeichen und entfernt haengende
     * Trenner (" |", " &", " -", ",", ":", offene Klammer).
     */
    public static function truncate(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max + 1);
        $space = mb_strrpos($cut, ' ');
        $cut = $space !== false ? mb_substr($cut, 0, $space) : mb_substr($text, 0, $max);

        return preg_replace('/[\s|&\-–—,:;\/(]+$/u', '', $cut) ?? $cut;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function render(string $key, array $values): string
    {
        $template = tenant()?->getAttribute($key);

        if (blank($template)) {
            $template = self::DEFAULTS[$key];
        }

        return trim(preg_replace('/\s+/u', ' ', strtr((string) $template, $values)) ?? '');
    }
}
