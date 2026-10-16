<?php

declare(strict_types=1);

namespace App\Ads\Support;

use App\Themes\ThemeManager;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Welche Anker stehen tatsaechlich in den Views eines Themes? (Ticket #14)
 *
 * Gilt fuer die **erste** Generation des Werbesystems: Positionsschluessel aus
 * `config/ad-positions.php`, Anker als `<x-ad-slot position="…">`. Der
 * Positionskatalog dort ist global, die Anker stehen aber je Theme — darum
 * laufen 20 aktive Slots in den sun-v2-Portalen ins Leere. Diese Klasse macht
 * den Unterschied messbar, statt ihn im Kopf zu fuehren: das Portal-Backoffice
 * bietet nur noch Positionen mit Anker an und warnt zu den uebrigen.
 *
 * Bewusst gemessen statt gepflegt: eine Liste von Hand laeuft dem Markup
 * hinterher. Die Theme-Dimension des neuen Katalogs steht dagegen in
 * `config/ad-slots.php` ({@see AdSlotCatalog::themesOf()}) — dort ist der
 * Anker Teil der Freigabe, hier ein Befund ueber Altbestand.
 *
 * Beruecksichtigt die Fallback-Kette des {@see \App\Themes\ThemeViewFinder}:
 * eine View, die das Theme nicht selbst mitbringt, kommt aus `default` — mit
 * deren Ankern. Geprueft wird darum die wirksame View-Menge, nicht der Ordner.
 */
final class ThemeAnchorIndex
{
    /** Cache-Dauer: die Anker aendern sich nur mit einem Deploy. */
    private const TTL_SECONDS = 86400;

    /**
     * Positionsschluessel mit Anker in diesem Theme.
     *
     * @return list<string>
     */
    public static function positions(string $theme): array
    {
        return Cache::remember(
            "ads:theme-anchors:{$theme}",
            self::TTL_SECONDS,
            fn () => self::scan($theme),
        );
    }

    /** Wird dieser Positionsschluessel im Theme ueberhaupt gerendert? */
    public static function has(string $theme, string $position): bool
    {
        return in_array($position, self::positions($theme), true);
    }

    /**
     * Positionen aus `config/ad-positions.php`, die in diesem Theme einen
     * Anker haben — Schluessel => Beschriftung, Reihenfolge des Katalogs.
     *
     * @return array<string, string>
     */
    public static function availablePositions(string $theme): array
    {
        $anchored = self::positions($theme);

        return array_filter(
            (array) config('ad-positions', []),
            fn ($label, $key) => in_array($key, $anchored, true),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Positionen aus dem Katalog, die in diesem Theme **kein** Pendant im
     * Markup haben — hier belegte Slots werden nie ausgeliefert.
     *
     * @return array<string, string>
     */
    public static function silentPositions(string $theme): array
    {
        $anchored = self::positions($theme);

        return array_filter(
            (array) config('ad-positions', []),
            fn ($label, $key) => ! in_array($key, $anchored, true),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Messung ohne Zwischenspeicher — fuer Pruefskripte und Kommandos.
     *
     * @return list<string>
     */
    public static function fresh(string $theme): array
    {
        Cache::forget("ads:theme-anchors:{$theme}");

        return self::positions($theme);
    }

    /**
     * @return list<string>
     */
    private static function scan(string $theme): array
    {
        $positions = [];

        foreach (self::effectiveViews($theme) as $path) {
            $contents = @file_get_contents($path);

            if ($contents === false) {
                continue;
            }

            preg_match_all(
                '/<x-ad-slot\b[^>]*?\bposition\s*=\s*"([a-z0-9_]+)"/s',
                $contents,
                $matches,
            );

            foreach ($matches[1] as $position) {
                $positions[$position] = true;
            }
        }

        return array_keys($positions);
    }

    /**
     * Wirksame View-Menge: eigene Views des Themes, dazu die Views aus
     * `default`, die das Theme nicht ueberschreibt.
     *
     * Ausgenommen sind fremde Layouts: bringt ein Theme eigene `layouts/`
     * mit, rendert es nie das Layout von `default` — dessen Anker (etwa
     * `header_below`) waeren sonst als vorhanden gezaehlt, obwohl die Datei
     * in diesem Portal nie durchlaufen wird. Fuer alle anderen Views gilt die
     * Kette: fehlt die View im Theme, kommt sie samt Ankern aus `default`.
     *
     * @return list<string>
     */
    private static function effectiveViews(string $theme): array
    {
        $own = self::viewsByRelativePath($theme);

        if ($theme === ThemeManager::DEFAULT_THEME) {
            return array_values($own);
        }

        $hasOwnLayouts = (bool) array_filter(
            array_keys($own),
            fn (string $relative) => str_starts_with($relative, 'layouts/'),
        );

        foreach (self::viewsByRelativePath(ThemeManager::DEFAULT_THEME) as $relative => $path) {
            if ($hasOwnLayouts && str_starts_with($relative, 'layouts/')) {
                continue;
            }

            $own[$relative] ??= $path;
        }

        return array_values($own);
    }

    /**
     * @return array<string, string>
     */
    private static function viewsByRelativePath(string $theme): array
    {
        $base = resource_path("views/themes/{$theme}/views");

        if (! is_dir($base)) {
            return [];
        }

        $views = [];

        foreach (Finder::create()->files()->in($base)->name('*.blade.php') as $file) {
            /** @var SplFileInfo $file */
            $views[str_replace('\\', '/', $file->getRelativePathname())] = $file->getPathname();
        }

        return $views;
    }
}
