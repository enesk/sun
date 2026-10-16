<?php

declare(strict_types=1);

namespace App\Ads\Support;

use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdPageType;

/**
 * Lesezugriff auf den Anker-Katalog aus config/ad-slots.php (SUN-AD-002).
 *
 * Der Katalog ist Code, keine Daten — es gibt keine Tabelle dafuer und keinen
 * Fremdschluessel darauf. Dass ein in der Datenbank gespeicherter `slot_key`
 * noch existiert und die Geraetewahl dazu passt, entscheidet allein diese
 * Klasse; Models (#3), Rules (#8) und Resolver (#4) fragen hier.
 *
 * Bewusst statisch und ohne eigenen Cache: config() liefert im Betrieb aus
 * dem Config-Cache, ein zweiter Zwischenspeicher brauchte nur Invalidierung.
 *
 * Zweite Dimension neben dem Seitentyp ist das Theme (Ticket #14): ein Anker
 * existiert nur in den Views des Themes, in das er eingebaut wurde. Die
 * theme-freien Methoden ({@see self::exists()}) beantworten "steht der
 * Schluessel im Katalog", die theme-gebundenen ({@see self::existsForTheme()})
 * "wird er in diesem Portal ueberhaupt gerendert". Auswahllisten fragen immer
 * die theme-gebundene Variante, Datenpruefungen die freie — sonst wuerde das
 * Umstellen eines Themes bestehende Belegungen unspeicherbar machen.
 *
 * Dritte Dimension ist die Route (Ticket #27): ein Seitentyp buendelt alle
 * Routen mit demselben Seitenaufbau, aber nicht jeder Anker steht in jeder
 * dieser Routen. Steht ein Slot nur in einem Teil, fuehrt er `routes`; fehlt
 * der Schluessel, gilt er fuer alle Routen seines Seitentyps
 * ({@see self::routesOf()}). Wie beim Theme gilt: Auswahllisten fragen
 * routengebunden, Datenpruefungen routenfrei — eine Belegung gehoert dem
 * Anker, nicht der Route.
 *
 * @phpstan-type SlotArray array{key: string, label: string, page_type: string, devices: string, formats: list<string>, min_height_mobile: int, min_height_desktop: int, sort: int, themes: list<string>, routes?: list<string>, requires?: string}
 */
final class AdSlotCatalog
{
    /**
     * Alle Anker, nach Schluessel.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $slots = [];

        foreach ((array) config('ad-slots.slots', []) as $slot) {
            if (! is_array($slot) || ! is_string($slot['key'] ?? null)) {
                continue;
            }

            $slots[$slot['key']] = $slot + config('ad-slots.defaults', []);
        }

        return $slots;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $slotKey): ?array
    {
        return self::all()[$slotKey] ?? null;
    }

    public static function exists(string $slotKey): bool
    {
        return self::find($slotKey) !== null;
    }

    /**
     * Anker eines Seitentyps, in Editor-Reihenfolge (`sort`).
     *
     * @param  string|null  $theme  Theme-Slug; null liefert den ganzen Katalog.
     * @param  string|null  $route  Routenname; null liefert alle Routen des Seitentyps.
     * @return array<string, array<string, mixed>>
     */
    public static function forPageType(AdPageType $pageType, ?string $theme = null, ?string $route = null): array
    {
        $slots = array_filter(
            $theme === null ? self::all() : self::forTheme($theme),
            fn (array $slot) => ($slot['page_type'] ?? null) === $pageType->value,
        );

        if ($route !== null) {
            $slots = array_filter(
                $slots,
                fn (array $slot, string $key) => self::supportsRoute($key, $route),
                ARRAY_FILTER_USE_BOTH,
            );
        }

        uasort($slots, fn (array $a, array $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

        return $slots;
    }

    /**
     * Anker, die auf dieser Route gerendert werden — der Seitentyp kommt aus
     * dem Routennamen. Unbekannte Route => leer.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function forRoute(string $route, ?string $theme = null): array
    {
        $pageType = AdPageType::fromRoute($route);

        return $pageType === null ? [] : self::forPageType($pageType, $theme, $route);
    }

    /**
     * Routen, auf denen der Anker steht. Ohne eigenen Eintrag sind das alle
     * Routen seines Seitentyps; Eintraege, die nicht zum Seitentyp gehoeren,
     * fallen weg — der Seitentyp bleibt die Klammer (Ticket #27).
     *
     * @return list<string>
     */
    public static function routesOf(string $slotKey): array
    {
        $pageType = self::pageTypeOf($slotKey);

        if ($pageType === null) {
            return [];
        }

        $eigene = array_values(array_filter(
            (array) (self::find($slotKey)['routes'] ?? []),
            fn ($route) => is_string($route) && $pageType->hasRoute($route),
        ));

        return $eigene === [] ? $pageType->routes() : $eigene;
    }

    /** Steht der Anker auf dieser Route? */
    public static function supportsRoute(string $slotKey, string $route): bool
    {
        return in_array($route, self::routesOf($slotKey), true);
    }

    /**
     * Woran haengt der Anker inhaltlich? (Ticket #22)
     *
     * Ein Satz, wenn der Anker im Markup innerhalb einer Sektions-Bedingung
     * steht — „Es gibt Ratgeber-Beitraege …" —, sonst null. Der Anker wird
     * bewusst nicht aus der Bedingung gezogen (Begruendung in
     * config/ad-slots.php); dafuer sagt das Panel, warum er auf einer Seite
     * fehlen kann, statt ihn still verschwinden zu lassen.
     */
    public static function requirementOf(string $slotKey): ?string
    {
        $wert = self::find($slotKey)['requires'] ?? null;

        return is_string($wert) && $wert !== '' ? $wert : null;
    }

    /**
     * Steht der Anker nur auf einem Teil der Routen seines Seitentyps? Dann
     * gehoert der Zusatz "nur auf …" an die Beschriftung.
     */
    public static function isRouteRestricted(string $slotKey): bool
    {
        $pageType = self::pageTypeOf($slotKey);

        return $pageType !== null && count(self::routesOf($slotKey)) < count($pageType->routes());
    }

    /**
     * Beschriftung einer Route; ohne Eintrag der Routenname selbst.
     *
     * Der Routenname enthaelt Punkte — `config('ad-slots.route_labels.guide.category')`
     * suchte darum eine Verschachtelung, die es nicht gibt. Gelesen wird
     * deshalb die ganze Karte.
     */
    public static function routeLabel(string $route): string
    {
        $label = ((array) config('ad-slots.route_labels', []))[$route] ?? null;

        return is_string($label) && $label !== '' ? $label : $route;
    }

    /**
     * Beschriftungen der Routen eines Ankers, in Katalogreihenfolge.
     *
     * @return list<string>
     */
    public static function routeLabelsOf(string $slotKey): array
    {
        return array_map(self::routeLabel(...), self::routesOf($slotKey));
    }

    /** Seitentyp eines Ankers; null, wenn der Schluessel nicht (mehr) im Katalog steht. */
    public static function pageTypeOf(string $slotKey): ?AdPageType
    {
        $slot = self::find($slotKey);

        return $slot === null ? null : AdPageType::tryFrom((string) ($slot['page_type'] ?? ''));
    }

    /** Geraeteklasse, die der Anker zulaesst. Unbekannter Anker => ALL. */
    public static function deviceOf(string $slotKey): AdDevice
    {
        $slot = self::find($slotKey);

        return AdDevice::tryFrom((string) ($slot['devices'] ?? '')) ?? AdDevice::ALL;
    }

    /** Passt die Geraetewahl einer Belegung in diesen Anker (docs/ad-slots.md 3.2)? */
    public static function allowsDevice(string $slotKey, AdDevice $device): bool
    {
        return $device->fitsInto(self::deviceOf($slotKey));
    }

    /**
     * Formate, die der Anker zulaesst — Schluessel aus `ad-slots.formats`.
     *
     * @return list<string>
     */
    public static function formatsOf(string $slotKey): array
    {
        $slot = self::find($slotKey);

        return array_values(array_filter(
            (array) ($slot['formats'] ?? []),
            fn ($format) => is_string($format),
        ));
    }

    /**
     * Alle im Projekt bekannten Formatschluessel.
     *
     * @return list<string>
     */
    public static function formatKeys(): array
    {
        return array_keys((array) config('ad-slots.formats', []));
    }

    /**
     * Themes, in denen der Anker eingebaut ist. Unbekannter Anker => leer.
     *
     * @return list<string>
     */
    public static function themesOf(string $slotKey): array
    {
        $slot = self::find($slotKey);

        if ($slot === null) {
            return [];
        }

        return array_values(array_unique(array_filter(
            (array) ($slot['themes'] ?? []),
            fn ($theme) => is_string($theme) && $theme !== '',
        )));
    }

    /** Steht der Anker in den Views dieses Themes? */
    public static function supportsTheme(string $slotKey, string $theme): bool
    {
        return in_array($theme, self::themesOf($slotKey), true);
    }

    /** Anker im Katalog **und** im Theme vorhanden — die Frage der Auswahllisten. */
    public static function existsForTheme(string $slotKey, string $theme): bool
    {
        return self::exists($slotKey) && self::supportsTheme($slotKey, $theme);
    }

    /**
     * Alle Anker, die in diesem Theme gerendert werden.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function forTheme(string $theme): array
    {
        return array_filter(
            self::all(),
            fn (array $slot, string $key) => self::supportsTheme($key, $theme),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Theme-Slugs, fuer die der Katalog ueberhaupt Anker kennt.
     *
     * @return list<string>
     */
    public static function themes(): array
    {
        $themes = [];

        foreach (array_keys(self::all()) as $key) {
            foreach (self::themesOf($key) as $theme) {
                $themes[$theme] = true;
            }
        }

        return array_keys($themes);
    }

    /** Katalogversion; steht im Cache-Schluessel des Resolvers (#4). */
    public static function version(): int
    {
        return (int) config('ad-slots.version', 1);
    }

    /**
     * Auswahlliste fuer Filament (#7/#8), gruppiert nach Seitentyp.
     *
     * Mit Theme-Slug bleiben nur die Anker uebrig, die in diesem Portal
     * wirklich gerendert werden — eine stumme Position soll nicht waehlbar
     * sein (Ticket #14).
     *
     * Mit Routenname bleiben zusaetzlich nur die Anker dieser Route uebrig
     * (Ticket #27). Ohne Routenname bleiben alle Anker des Seitentyps
     * waehlbar — eine Belegung gehoert dem Anker, nicht der Route —, aber
     * ein routengebundener Anker traegt den Zusatz "nur auf …", damit
     * niemand ihn fuer die ganze Seitengruppe haelt.
     *
     * @return array<string, array<string, string>>
     */
    public static function options(?string $theme = null, ?string $route = null): array
    {
        $options = [];

        foreach (AdPageType::cases() as $pageType) {
            foreach (self::forPageType($pageType, $theme, $route) as $key => $slot) {
                $options[$pageType->label()][$key] = self::optionLabel($key, $slot, $route);
            }
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $slot
     */
    private static function optionLabel(string $key, array $slot, ?string $route): string
    {
        $label = (string) ($slot['label'] ?? $key);

        if ($route !== null || ! self::isRouteRestricted($key)) {
            return $label;
        }

        return $label.' '.__('(nur :routes)', [
            'routes' => implode(', ', self::routeLabelsOf($key)),
        ]);
    }
}
