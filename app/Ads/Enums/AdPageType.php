<?php

declare(strict_types=1);

namespace App\Ads\Enums;

/**
 * Seitentypen des Themes sun-v2, fuer die es einen Werbeplatz-Katalog gibt
 * (docs/ad-slots.md, Abschnitt 2). Ein Seitentyp buendelt alle Routen, die
 * denselben Seitenaufbau und damit dieselben Anker haben: die Ratgeber-
 * Uebersicht und die Ratgeber-Kategorie sind beide GUIDE_INDEX, die beiden
 * Profil-Routen (mit und ohne Stadt im Pfad) sind beide COMPANY.
 *
 * Seitentypen ohne Eintrag hier (Blog, Stellen, Kategorien, Konto-Seiten,
 * Rechtsseiten, Kasse) bekommen in v1 keine Anker.
 *
 * Ausdrueckliche Entscheidung zu den drei Nachbarrouten des Ratgebers
 * (Ticket #23): `portal.blog.search`, `portal.blog.tag` und
 * `portal.blog.editorial` bleiben in v1 ohne Seitentyp und damit ohne Anker.
 * Suche und Tag-Liste sind noindex-Seiten mit geringem Aufkommen; die
 * Redaktionsseite ist eine Vertrauensseite ohne Werbung. Alle drei stehen
 * ohnehin auf der Auto-Ads-Sperrliste (`ad-slots.adsense.auto_ads.blocked_routes`).
 * Ein Seitentyp dafuer waere ein eigener Katalog-Abschnitt samt Vorschau-URL
 * und gehoert, wenn ueberhaupt, in den Ausbau (#12).
 */
enum AdPageType: string
{
    case HOME = 'home';
    case SEARCH = 'search';
    case COMPANY = 'company';
    case CITY = 'city';
    case GUIDE = 'guide';
    case GUIDE_INDEX = 'guide_index';

    public function label(): string
    {
        return match ($this) {
            self::HOME => __('Startseite'),
            self::SEARCH => __('Firmensuche'),
            self::COMPANY => __('Firmenprofil'),
            self::CITY => __('Stadtseite'),
            self::GUIDE => __('Ratgeber-Artikel'),
            self::GUIDE_INDEX => __('Ratgeber-Übersicht'),
        };
    }

    /**
     * Routennamen, die diesen Seitentyp rendern. Der Resolver leitet den
     * Seitentyp aus dem Routennamen ab, nicht aus dem View-Namen — Views
     * werden pro Theme ueberschrieben, Routennamen nicht.
     *
     * @return list<string>
     */
    public function routes(): array
    {
        return match ($this) {
            self::HOME => ['home'],
            self::SEARCH => ['portal.companies.index'],
            self::COMPANY => ['portal.companies.show', 'portal.companies.show.city'],
            self::CITY => ['portal.cities.show'],
            self::GUIDE => ['guide.show'],
            // Beide Routen fallen ohne Ratgeber-Kategorien auf die Blog-Views
            // zurueck; die Anker stehen dort ebenfalls (#23).
            self::GUIDE_INDEX => ['guide.index', 'guide.category'],
        };
    }

    /**
     * Route, die die Vorschau eines Seitentyps ohne eigene Wahl zeigt — die
     * erste der Liste (Ticket #27).
     */
    public function defaultRoute(): string
    {
        return $this->routes()[0];
    }

    /** Traegt dieser Seitentyp mehr als eine Route? */
    public function hasMultipleRoutes(): bool
    {
        return count($this->routes()) > 1;
    }

    /** Rendert diese Route den Seitentyp? */
    public function hasRoute(string $routeName): bool
    {
        return in_array($routeName, $this->routes(), true);
    }

    public static function fromRoute(?string $routeName): ?self
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            if (in_array($routeName, $case->routes(), true)) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
