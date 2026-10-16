<?php

declare(strict_types=1);

namespace App\Ads\Preview;

use App\Ads\Enums\AdPageType;
use App\Guide\Services\GuidePageData;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\Post;
use App\Models\Portal\PostCategory;
use App\Models\Tenant;
use App\Services\CompanyUrlService;
use Throwable;

/**
 * Welche echte Portalseite zeigt die Vorschau? (Ticket #7)
 *
 * Der Editor zeigt keine Beispielseite, sondern die **echte** Seite des
 * gewaehlten Portals. Zu jedem Seitentyp gehoert dafuer ein Pfad, und drei der
 * sechs haengen an Inhalten des Portals: ein Firmenprofil braucht eine
 * freigeschaltete Firma, eine Stadtseite eine Stadt mit Betrieben, ein
 * Ratgeber-Artikel einen veroeffentlichten Beitrag. Die liegen in der
 * **Tenant**-Datenbank, das Admin-Panel laeuft ohne Portalkontext — gelesen
 * wird darum in {@see Tenant::run()}, einmal und sofort.
 *
 * Zurueck kommt immer ein Pfad, nie eine ganze Adresse: die Domain setzt
 * {@see \App\Ads\EditorToken} beim Signieren. Pfade kommen aus `route(…, [],
 * absolute: false)`, damit eine umbenannte Route hier nichts bricht.
 *
 * Findet sich kein Inhalt, ist die Antwort `null`. Die Oberflaeche sagt dann
 * "Fuer dieses Portal gibt es noch keine Seite dieses Typs" — sie darf nicht
 * eine Adresse raten, denn eine 404 im iframe sieht wie ein Fehler des
 * Editors aus.
 */
final class PreviewUrlResolver
{
    /**
     * Pfad der Vorschauseite, oder null, wenn das Portal keinen Inhalt dieses
     * Typs hat.
     *
     * @param  string|null  $route  Route des Seitentyps; null nimmt dessen
     *                              Vorgaberoute ({@see AdPageType::defaultRoute()}).
     */
    public function resolve(Tenant $tenant, AdPageType $pageType, ?string $route = null): ?string
    {
        try {
            return $this->imPortal($tenant, fn (): ?string => $this->path($pageType, $route));
        } catch (Throwable) {
            // Eine Portal-Datenbank, die nicht erreichbar ist oder deren
            // Schema hinterherhaengt, darf das Werkzeug nicht umwerfen.
            return null;
        }
    }

    /**
     * Alle Seitentypen auf einmal — ein Portalwechsel fragt sonst sechsmal
     * nacheinander in dieselbe Datenbank.
     *
     * @return array<string, string|null>
     */
    public function resolveAll(Tenant $tenant): array
    {
        try {
            return $this->imPortal($tenant, function (): array {
                $pfade = [];

                foreach (AdPageType::cases() as $pageType) {
                    $pfade[$pageType->value] = $this->path($pageType);
                }

                return $pfade;
            });
        } catch (Throwable) {
            return array_fill_keys(array_column(AdPageType::cases(), 'value'), null);
        }
    }

    /**
     * Ein Lesezugriff im Portalkontext — und danach wieder im Panel.
     *
     * `app.url` rahmt hier nichts mehr ein: das macht seit #24 zentral der
     * {@see \App\Support\Tenancy\TenantUrlBootstrapper}, der den zentralen
     * Wert beim Verlassen des Portalkontexts zurueckstellt.
     *
     * @template T
     *
     * @param  callable(): T  $lesen
     * @return T
     */
    private function imPortal(Tenant $tenant, callable $lesen): mixed
    {
        return $tenant->run($lesen);
    }

    /**
     * Laeuft im Portalkontext.
     *
     * Entschieden wird nach **Route**, nicht nach Seitentyp (Ticket #27):
     * `guide_index` buendelt /ratgeber und die Kategorieseite, und nur auf
     * der Kategorieseite steht die Seitenspalte. Eine Route ohne eigenen Fall
     * fuehrt auf den Pfad der Vorgaberoute ihres Seitentyps.
     */
    private function path(AdPageType $pageType, ?string $route = null): ?string
    {
        $route = $route !== null && $pageType->hasRoute($route) ? $route : $pageType->defaultRoute();

        return match ($route) {
            'home' => route('home', [], false),
            'portal.companies.index' => route('portal.companies.index', [], false),
            'guide.index' => route('guide.index', [], false),
            'guide.category' => $this->guideCategoryPath(),
            'portal.companies.show', 'portal.companies.show.city' => $this->companyPath(),
            'portal.cities.show' => $this->cityPath(),
            'guide.show' => $this->guidePath(),
            default => null,
        };
    }

    /**
     * Die zuletzt freigeschaltete Firma. `is_active` ist die Sichtbarkeit im
     * Portal — ein Profil in Quarantaene oder in Wartestellung liefert 404.
     */
    private function companyPath(): ?string
    {
        $company = Company::query()
            ->active()
            ->orderByDesc('id')
            ->first();

        return $company instanceof Company ? CompanyUrlService::path($company) : null;
    }

    /**
     * Die Stadt mit den meisten freigeschalteten Betrieben: dort ist die Seite
     * gefuellt, und gefuellte Listen zeigen auch die Listen-Anker.
     */
    private function cityPath(): ?string
    {
        $city = City::query()
            ->withCount(['companies' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('companies_count')
            ->orderBy('id')
            ->first();

        return $city instanceof City && $city->slug !== null
            ? route('portal.cities.show', ['slug' => $city->slug], false)
            : null;
    }

    /**
     * Eine Ratgeber-Kategorie mit Inhalt. Hat das Portal das themengetriebene
     * Ratgeber-System noch nicht gefuellt, faellt `guide.category` im
     * Controller auf die Blog-Kategorie zurueck — dann muss der Slug von
     * dort kommen, sonst liefert die Route 404.
     */
    private function guideCategoryPath(): ?string
    {
        $pages = app(GuidePageData::class);
        $tiles = $pages->available() ? $pages->categoryTiles() : [];
        $slug = $tiles === [] ? $this->blogCategorySlug() : (string) $tiles[0]['slug'];

        return $slug === null || $slug === ''
            ? null
            : route('guide.category', ['slug' => $slug], false);
    }

    /** Blog-Kategorie mit mindestens einem veroeffentlichten Beitrag. */
    private function blogCategorySlug(): ?string
    {
        $category = PostCategory::query()
            // Dieselben Bedingungen wie Post::scopePublished(), hier
            // ausgeschrieben: im Rumpf von whereHas() sieht die statische
            // Analyse nur einen Builder ueber Model und kennt den Scope nicht
            // (gleiches Muster wie GenerateSitemap).
            ->whereHas('posts', function ($query): void {
                $query->where('status', Post::STATUS_PUBLISHED)
                    ->where('published_at', '<=', now());
            })
            ->orderBy('id')
            ->first(['id', 'slug']);

        return $category instanceof PostCategory ? $category->slug : null;
    }

    private function guidePath(): ?string
    {
        $post = Post::query()
            ->published()
            ->orderByDesc('published_at')
            ->first();

        return $post instanceof Post && $post->slug !== null
            ? route('guide.show', ['slug' => $post->slug], false)
            : null;
    }
}
