<?php

declare(strict_types=1);

namespace App\Content\Sources\Support;

use App\Content\Sources\TenantContext;
use App\Models\Portal\City;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Erkennt Ort und Bundesland in einem freien Text (#9, Vorarbeit fuer #11).
 *
 * Die Geo-Liste ist keine eigene Datenhaltung: Staedte kommen aus der
 * Tenant-Tabelle `cities`, die das Portal ohnehin fuehrt, Bundeslaender aus
 * config('content.regions.states') ueber den StateCatalog. Die Staedte werden
 * je Mandant zwischengespeichert, weil ein Connector-Lauf einige tausend
 * Suchanfragen durchsieht.
 *
 * Bundeslaender werden als ISO-3166-2-Code zurueckgegeben ('DE-BY') — das ist
 * seit #33 das einzige Vokabular fuer region_code bei region_scope 'state'.
 * Staedte behalten ihren Slug, region_scope unterscheidet beides.
 *
 * Gematcht wird auf Wortgrenzen, damit "Essen" in "Essen bestellen" nicht als
 * Stadt durchgeht; laengere Ortsnamen gewinnen vor kuerzeren ("Frankfurt am
 * Main" vor "Frankfurt"). Ein Treffer wird nur zurueckgegeben, wenn der
 * Mandant den passenden Regions-Zuschnitt ueberhaupt zulaesst.
 */
final class RegionResolver
{
    public const SCOPE_NATIONAL = 'national';

    public const SCOPE_STATE = 'state';

    public const SCOPE_CITY = 'city';

    private const CACHE_SECONDS = 3600;

    /**
     * Anzeigename eines Bundesland-Codes, oder null bei unbekanntem Code.
     *
     * Erwartet wird der ISO-Code; alte Slug-Schreibweisen ('bayern') und
     * ausgeschriebene Namen werden ueber den Katalog mitaufgeloest, damit
     * Bestandswerte in Einstellungen nicht ohne Label dastehen.
     */
    public static function stateLabel(string $code): ?string
    {
        $iso = StateCatalog::exists($code) ? $code : StateCatalog::fromText($code);

        return $iso === null ? null : StateCatalog::name($iso);
    }

    /**
     * Erster Treffer als [scope, code, label]; ohne Treffer 'national'.
     *
     * Muss im Tenant-Kontext aufgerufen werden (Staedte liegen in der
     * Tenant-DB).
     *
     * @return array{scope: string, code: ?string, label: ?string}
     */
    public function resolve(string $text, TenantContext $context): array
    {
        $haystack = $this->normalize($text);

        if ($haystack === '') {
            return $this->national();
        }

        if ($context->allowsRegionScope(self::SCOPE_CITY)) {
            foreach ($this->cities($context) as $needle => $city) {
                if ($this->contains($haystack, $needle)) {
                    return [
                        'scope' => self::SCOPE_CITY,
                        'code' => $city['code'],
                        'label' => $city['label'],
                    ];
                }
            }
        }

        if ($context->allowsRegionScope(self::SCOPE_STATE)) {
            $iso = $this->matchState($haystack);

            if ($iso !== null) {
                return ['scope' => self::SCOPE_STATE, 'code' => $iso, 'label' => StateCatalog::name($iso)];
            }
        }

        return $this->national();
    }

    /**
     * Ortsliste des Mandanten, nach Laenge absteigend sortiert.
     *
     * @return array<string, array{code: string, label: string}>
     */
    public function cities(TenantContext $context): array
    {
        return Cache::remember(
            "content:geo:cities:{$context->tenantId}",
            self::CACHE_SECONDS,
            function (): array {
                if (! Schema::connection((new City)->getConnectionName())->hasTable('cities')) {
                    return [];
                }

                $cities = [];

                City::query()
                    ->select(['name', 'slug', 'administrative_area_level_1'])
                    ->orderByRaw('CHAR_LENGTH(name) DESC')
                    ->cursor()
                    ->each(function (City $city) use (&$cities): void {
                        $name = trim((string) $city->name);

                        // Sehr kurze Ortsnamen ("Aue", "Ulm") erzeugen in
                        // Suchanfragen zu viele Fehltreffer.
                        if (mb_strlen($name) < 4) {
                            return;
                        }

                        $needle = $this->normalize($name);

                        if ($needle === '' || isset($cities[$needle])) {
                            return;
                        }

                        $cities[$needle] = [
                            'code' => Str::limit((string) ($city->slug ?: Str::slug($name)), 32, ''),
                            'label' => $name,
                        ];
                    });

                return $cities;
            },
        );
    }

    /**
     * ISO-Code des Bundeslands in einem bereits normalisierten Text.
     *
     * Der laengste Treffer gewinnt, sonst wuerde "sachsen anhalt" als
     * "Sachsen" durchgehen.
     */
    private function matchState(string $haystack): ?string
    {
        $best = null;
        $bestLength = 0;

        foreach (StateCatalog::needles() as $iso => $needles) {
            foreach ($needles as $needle) {
                if (mb_strlen($needle) > $bestLength && $this->contains($haystack, $needle)) {
                    $best = $iso;
                    $bestLength = mb_strlen($needle);
                }
            }
        }

        return $best;
    }

    /**
     * @return array{scope: string, code: null, label: null}
     */
    private function national(): array
    {
        return ['scope' => self::SCOPE_NATIONAL, 'code' => null, 'label' => null];
    }

    /**
     * Treffer nur an Wortgrenzen — sonst waere "Ahlen" in "Zahlen" enthalten.
     */
    private function contains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        return (bool) preg_match('/(?:^|\s)'.preg_quote($needle, '/').'(?:$|\s)/u', $haystack);
    }

    /**
     * Kleinschreibung, Umlaute aufgeloest, Bindestriche und Satzzeichen zu
     * Leerzeichen. Damit findet "waermepumpe koeln" auch "Köln".
     */
    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        $value = strtr($value, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        ]);

        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
