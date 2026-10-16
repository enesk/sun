# Werbeplätze SUN-V2 — Slot-Katalog und Architektur

Stand: 2026-10-16 · Katalog **v4** (`config('ad-slots.version')`) · Vorhaben SUN-AD (Ticket #1)

Dieses Dokument ist der Architektur-Freeze für das Werbeplatz-System des Themes
`sun-v2`. Es legt fest, **wo** Werbung erscheinen darf (Abschnitt 2–3), **wie**
die Belegung gespeichert wird (4), **wer** sie rendert (5), **wie** der visuelle
Editor funktioniert (6) und **wann** gecacht und ausgerollt wird (8, 11).

Freigabe des Katalogs: siehe Abschnitt 12.

---

## 1. Abgrenzung zum alten System

Es gibt heute schon Werbeplätze: `App\Models\Portal\AdSlot` in der **Tenant-DB**
(`database/migrations/tenant/2026_03_17_000001_create_ad_slots_table.php`), die
Komponente `App\View\Components\AdSlot` mit `resources/views/components/ad-slot.blade.php`
und die Filament-Seite `AdSlotDistribution` zum Verteilen von Code-Schnipseln
zwischen Portalen.

Eigenschaften des Altsystems, die das neue ersetzt:

| Alt | Neu |
| --- | --- |
| Rohes HTML/Script je Tenant, per Hand eingefügt | Werbemittel (`ad_units`) zentral, Provider rendert |
| Positionen als freie Strings in der DB | fester Katalog in `config/ad-slots.php`, versioniert |
| Pflege je Portal, Verteilung per Export/Import | eine Belegung, Portal-Auswahl über Pivot |
| CLS-Höhen im Komponenten-Code (`CLS_DIMENSIONS`) | `min_height_mobile` / `min_height_desktop` im Katalog |
| „tablet" als dritte Geräteklasse | nur `mobile` / `desktop` / `all` (Grenze: `lg`, 1024 px) |
| Keine Vorschau, keine Zeiträume, keine Prioritäten | Editor-Modus, Zeitraum, Priorität, Status |

Das Altsystem bleibt bis zum Abschluss von SUN-AD-011 (Ticket #11) in Betrieb
und läuft parallel; die Abschaltung ist Teil des Rollouts (Abschnitt 10).

Nicht Teil des Katalogs — bewusste Entscheidungen:

* **AdSense Auto Ads** (`position="auto_ads"` in `layouts/sun.blade.php`) ist
  kein Anker: das Skript platziert selbst, außerhalb jeder Reservierung. Auto
  Ads bleiben eine Tenant-Einstellung (`ad_settings.adsense_auto_ads`), kein
  Slot. Ausgespielt werden sie von `App\View\Components\Sun\AutoAds`
  (`<x-sun.auto-ads />`, Ticket #18): Loader plus
  `<meta name="adsense-auto-client">`, entdoppelt gegen den Loader der Anker
  über `AdHeadRegistry` und `AdSenseProvider::LOADER_HANDLE`. Bedingungen:
  `ads_enabled`, `adsense_auto_ads`, wirksame Publisher-ID, Einwilligung, kein
  Editor-Modus, keine werbefreie Seite — und nicht auf den Routen in
  `config('ad-slots.adsense.auto_ads.blocked_routes')` (Ratgeber, CLS-Vorgabe
  #100). Solange das Altsystem die Position `auto_ads` im Portal noch selbst
  ausliefert, hält sich die Komponente zurück; mit dessen Abschaltung (#11)
  übernimmt sie ohne weiteren Eingriff. Gepflegt wird der Schalter in
  Einstellungen › Werbung (SUN-AD-010).
* **Mobiles Haftband am unteren Rand** (`mobile_sticky_bottom`) ist ein Overlay
  und kein Anker zwischen Sektionen. Auf dem Firmenprofil kollidiert es mit der
  dortigen Aktionsleiste (`pb-24 lg:pb-0`). Bleibt v1 außen vor.
* **Blog, Stellen, Portal-Kategorien, Konto-Seiten, Rechtsseiten, Kasse** haben
  in v1 keine Anker. Blog und Stellen sind Kandidaten für v2.

---

## 2. Was ein zulässiger Anker ist

Ein Anker ist eine Stelle im Markup, an der ein `<x-ad-slot>` als **direktes
Geschwisterkind** eingefügt werden kann, ohne die Geometrie der Nachbarn zu
verändern. Konkret gilt:

**Erlaubt**

1. **Zwischen zwei abgeschlossenen Sektionen.** Der Anker steht nach einem
   `</section>` bzw. nach dem schließenden Tag eines Blocks auf derselben Ebene
   und vor dem nächsten. Er darf eigene Außenabstände mitbringen.
2. **Nach dem n-ten Listenelement** in einem einspaltigen Listencontainer
   (`flex flex-col gap-*`). Der Anker ist dort ein weiteres Kind; der `gap`
   sorgt für den Abstand, ein zusätzliches `margin` ist verboten.
3. **Als Kind einer Seitenspalte** (`<aside>` mit `flex flex-col gap-*`), auch
   innerhalb des klebenden Wrappers.
4. **Zwischen zwei `<p>`-Blöcken auf oberster Ebene** des Ratgeber-Textes
   (`in_article`, Abschnitt 3.4).

**Verboten**

1. **Innerhalb einer Karte** (`.card`). Eine Anzeige in der Karte sieht wie
   Inhalt des Betriebs aus — das ist bei AdSense ein Verstoß gegen die
   Platzierungsregeln und redaktionell irreführend.
2. **In einem Grid mit mehr als einer Spalte.** Ein zusätzliches Kind
   verschiebt die gesamte Folgereihe (`grid-cols-2`, `grid-cols-3`,
   `grid-cols-[1fr_18rem]`).
3. **In einem Flex-Container mit Geschwistern in derselben Zeile**
   (`flex` ohne `flex-col`), inklusive der horizontal scrollenden Kartenreihen
   (`overflow-x-auto scroll-snap`).
4. **Oberhalb der Falte vor dem ersten Inhalt.** Kein Anker vor der H1, keiner
   vor der Kurzantwort eines Ratgebers (Block 2), keiner zwischen Kurzantwort
   und Kernaussagen.
5. **Direkt vor oder nach einem primären Aufruf**: Anfrage-Dialog-Auslöser,
   Verweisleiste Firmensuche im Ratgeber (Block 7), CTA-Box (Block 10). Sonst
   sind Fehlklicks nicht mehr von echten Klicks zu trennen.
6. **Zwischen zwei Ankern ohne Inhalt dazwischen.** Es gibt nie zwei Anker in
   Folge; `home_after_cities` und `home_before_footer` sind durch den SEO-Text
   getrennt.

**Layout-Shift.** Jeder Anker reserviert seine Höhe schon im ersten HTML, über
`min-height` — nie `height`. AdSense garantiert bei `responsive` keine Höhe;
mit `height` würde ein größeres Format abgeschnitten, ohne Reservierung springt
das Layout beim Nachladen. Die Werte stehen im Katalog, nicht im Komponenten-Code.

**Werbefreiheit.** Ein Anker rendert nichts, wenn

* das Portal `ads_enabled = false` hat (Tenant-Setting, SUN-AD-010),
* der Betrieb das Premium-Merkmal `App\Enums\PremiumFeature::AdFree` hat
  (gilt für den Seitentyp `company`, heute `@unlesspremiumFeature`),
* der Editor-Modus aktiv ist (dann nur der Rahmen, Abschnitt 6),
* die Einwilligung ausdrücklich abgelehnt wurde und `on_denied = collapse`
  gilt (Abschnitt 9). Liegt **noch keine** Entscheidung vor, rendert der
  Anker dagegen: der Platz wird reserviert, das Werbemittel wartet im
  `<template>` (SUN-AD-009).

---

## 3. Katalog v1

Quelle: `config/ad-slots.php`. `version` dort ist `4`. Jeder Eintrag hat
`key`, `label`, `page_type`, `devices`, `formats`, `min_height_mobile`,
`min_height_desktop`, `sort`, `themes` — und optional `routes` (Abschnitt
3.1a).

Die Schlüssel sind **stabil** — sie landen in `ad_placements.slot_key`. Ein
Anker wird nie umbenannt, nur aufgenommen oder mit Migration entfernt. Jede
inhaltliche Änderung erhöht `version` (Cache-Schlüssel, Abschnitt 8).

### 3.1 Seitentypen

`App\Ads\Enums\AdPageType` — abgeleitet aus dem **Routennamen**, nicht aus dem
View-Namen; Views werden pro Theme überschrieben, Routennamen nicht
(`AdPageType::fromRoute()`).

| Seitentyp | Routen | View (Theme sun-v2) |
| --- | --- | --- |
| `home` | `home` | `pages/home.blade.php` |
| `search` | `portal.companies.index` | `pages/companies/index.blade.php` |
| `company` | `portal.companies.show`, `portal.companies.show.city` | `pages/companies/show.blade.php` |
| `city` | `portal.cities.show` | `pages/cities/show.blade.php` |
| `guide` | `guide.show` | `guide/show.blade.php` |
| `guide_index` | `guide.index`, `guide.category` | `guide/index.blade.php`, `guide/category.blade.php`, Rückfall: `pages/blog/index.blade.php`, `pages/blog/category.blade.php` |

`guide.category` ist bewusst `guide_index` und kein eigener Typ: beide Seiten
sind Kartenlisten mit optionaler Seitenspalte und tragen dieselben Anker. Die
Vorschauroute `guide.preview` bekommt keine Anzeigen.

**Rückfall auf die Blog-Views (Ticket #23).** Solange ein Portal keine
Ratgeber-Kategorien veröffentlicht hat, gibt `GuideController` die Seite an
`PublicBlogController` ab — der Routenname bleibt `guide.index` bzw.
`guide.category`, der Seitentyp also `guide_index`. Die Anker stehen deshalb
auch in `pages/blog/index.blade.php` und `pages/blog/category.blade.php`.
Nicht jeder Anker steht in jeder der vier Views:

| Anker | Views | `routes` |
| --- | --- | --- |
| `guide_index_after_first_block` | alle vier | — (alle Routen) |
| `guide_index_in_feed_5` | `guide/category`, beide Rückfall-Views (Schnitt zwischen den Kartenblöcken) | — (alle Routen) |
| `guide_index_sidebar_sticky` | nur `guide/category` — die anderen drei Views sind einspaltig | `['guide.category']` |
| `guide_index_before_footer` | alle vier | — (alle Routen) |

`portal.blog.search`, `portal.blog.tag` und `portal.blog.editorial` bleiben in
v1 ohne Seitentyp: noindex-Listen mit geringem Aufkommen bzw. eine
Vertrauensseite ohne Werbung, alle drei schon auf der Auto-Ads-Sperrliste (#23).

### 3.1a Routen-Dimension (Ticket #27)

Ein Seitentyp bündelt mehrere Routen — aber nicht jeder Anker steht auf jeder
davon. `guide_index_sidebar_sticky` braucht eine Seitenspalte, und die gibt es
nur auf `guide.category`; auf `/ratgeber` lief eine Belegung darauf ins Leere,
obwohl das Panel den Anker anbot. Darum führt ein Slot optional `routes`:

```php
[
    'key' => 'guide_index_sidebar_sticky',
    'page_type' => AdPageType::GUIDE_INDEX->value,
    'routes' => ['guide.category'],   // fehlt der Schlüssel: alle Routen des Seitentyps
    …
]
```

Entschieden gegen einen eigenen Seitentyp `guide_category`: der hätte eine
Migration auf `ad_placements.page_type`, einen zweiten Katalog-Abschnitt und
eine zweite Vorschauroute gebraucht — und die drei anderen Anker des Typs
doppelt geführt, obwohl sie auf beiden Seiten identisch sitzen. `routes` ist
eine Einschränkung innerhalb des bestehenden Typs; die Spalte `page_type`
bleibt unberührt, es gibt keine Migration.

Der Seitentyp bleibt die Klammer: ein `routes`-Eintrag, der nicht zu den
Routen des Seitentyps gehört, wird verworfen (`AdSlotCatalog::routesOf()`).

| Frage | Methode |
| --- | --- |
| Auf welchen Routen steht der Anker? | `AdSlotCatalog::routesOf()` |
| Steht er auf dieser Route? | `AdSlotCatalog::supportsRoute()` |
| Nur auf einem Teil der Routen seines Typs? | `AdSlotCatalog::isRouteRestricted()` |
| Anker dieser Route (theme-gebunden) | `AdSlotCatalog::forRoute($route, $theme)` |

**Auswahllisten und Panel.** `AdSlotCatalog::options($theme, $route)` und
`AdThemeContext::slotOptions($tenant, $route)` nehmen die Route als dritte
Dimension: mit Route bleiben nur die Anker dieser Seite übrig, ohne Route
trägt ein routengebundener Anker den Zusatz „nur auf …" (so in der
Werbeplatz-Resource und ihren Filtern). Der Editor (#7) hat ein Feld **Seite**,
sobald der Seitentyp mehr als eine Route trägt; es steuert die Beispielseite
(`PreviewUrlResolver::resolve($tenant, $pageType, $route)`) **und** die
Ankerliste: ein Anker, der hier nicht gerendert wird, trägt die Kennzeichnung
„nicht auf dieser Seite" samt Angabe, wo er steht. Er verschwindet bewusst
nicht — ein fehlender Anker wäre nicht erklärbar.

**Datenprüfungen bleiben routenfrei** (`SlotKeyInCatalog`, der Guard in
`AdPlacement`): eine Belegung gehört dem Anker, nicht der Route. Dieselbe
Trennung wie beim Theme (Abschnitt 3.2a).

**Was die Routen-Dimension nicht deckt:** den Rückfall auf die Blog-Views. Ob
`guide.index` die Ratgeber- oder die Blog-Übersicht rendert, entscheidet der
Controller nach dem Inhalt des Portals, nicht die Route — `guide_index_in_feed_5`
steht in der Rückfall-Übersicht, in `guide/index.blade.php` dagegen nicht und
behält deshalb alle Routen. Diesen Fall macht weiter nur der Abgleich mit
`slots:ready` sichtbar („Im Katalog, aber nicht in der geladenen Seite").

**Dritte Dimension: Inhalt.** Fünf Anker stehen im Markup innerhalb ihrer
Sektion und fehlen mit ihr — unabhängig von Route und Theme. Sie führen
`requires`, einen Satz, den das Panel anzeigt (Ticket #22, Abschnitt 6.3).

### 3.2 Geräte, Formate, Status

`App\Ads\Enums\AdDevice`: `mobile` | `desktop` | `all`. Grenze ist der
`lg`-Haltepunkt (1024 px) — dort erscheinen im Theme die Seitenspalten.
Sichtbarkeit über `AdDevice::visibilityClass()` (`lg:hidden` / `hidden lg:block`),
nicht über JavaScript: ein per JS versteckter Slot hat schon geladen.

Formate (Schlüssel aus `config('ad-slots.formats')`):
`responsive`, `rectangle_300x250`, `leaderboard_728x90`, `in_feed`, `in_article`.
`responsive` heißt „so breit wie die Spalte" und **nicht** „so hoch wie
AdSense will": die Höhe ist die Reservierung des Ankers (Abschnitt 5.1, #32).

`App\Ads\Enums\AdProviderType`: `adsense` | `html`.
`App\Ads\Enums\AdPlacementStatus`: `draft` → `active` ↔ `paused`, dazu
`expired`. **`expired` wird nicht aktiv gesetzt, sondern errechnet**: eine
Belegung mit `ends_at` in der Vergangenheit gilt als abgelaufen, egal was in
der Spalte steht. Der Resolver liefert nur `active` innerhalb des Zeitraums.

### 3.2a Theme-Dimension

Ein Anker existiert nur in dem Theme, in dessen Views er eingebaut ist. Der
Seitentyp kommt aus dem Routennamen und ist darum theme-unabhängig — das
Markup ist es nicht. Jeder Slot führt deshalb `themes`, die Liste der
Theme-Slugs, in denen sein Anker steht; fehlt der Schlüssel, gilt
`defaults.themes`. Für Katalog v4 ist das `['sun-v2']`: die 32 Anker sind
gegen das Markup von sun-v2 geschnitten.

Das aktive Theme eines Portals steht im Tenant-Attribut `theme.active`
(`ThemeManager::getTenantTheme()`, Vorgabe `default`). **Maßgeblich ist die
Produktion**, dort am 16.10.2026 über alle 23 Portale nachgezählt (nur
Lesezugriff): **21 laufen `sun-v2`, 2 laufen `default`** — 43
tierarztportal.com und 51 firmenfreund.net, und genau diese beiden sind am
Tenant als nicht ausgeliefert gekennzeichnet (`Tenant::NOT_DELIVERED`,
Entscheidung vom 14.10.2026, Variante A, Ticket #16). Der Katalog mit
`themes => ['sun-v2']` deckt damit jedes ausgelieferte Portal ab.

Die **lokale** Central-DB führt an dieser Stelle noch den alten Stand (18
`default`, 5 `sun-v2`) und ist kein Produktionsbefund. Sie bleibt bewusst so:
die Messung stummer Slots aus Ticket #14 (`ads:anker-bericht`) ist mit dieser
Verteilung reproduzierbar.

Zwei Zugriffsarten, die nicht verwechselt werden dürfen:

| Frage | Methode | Antwort für `home_after_hero` im Theme `default` |
| --- | --- | --- |
| Steht der Schlüssel im Katalog? | `AdSlotCatalog::exists()` | ja |
| Wird er in diesem Portal gerendert? | `AdSlotCatalog::existsForTheme()` | nein |

**Auswahllisten fragen theme-gebunden** (`AdSlotCatalog::options($theme)`,
`AdThemeContext::slotOptions()`) — eine Position, die im Portal stumm bleibt,
darf nicht wählbar sein. **Datenprüfungen fragen theme-frei**
(`AdPlacement`-Guard, `SlotKeyInCatalog`): sonst würde das Umstellen eines
Themes bestehende Belegungen unspeicherbar machen. Das Theme eines Portals
liefert ausschließlich `App\Ads\Support\AdThemeContext`.

Baut ein Ticket den Anker in ein weiteres Theme ein, gehört der Slug in
`themes` und `version` wird erhöht.

**Entschieden (16.10.2026, Ticket #16):** Die Anker bleiben ausschließlich in
`sun-v2`; in `default`/`starter` werden sie **nicht** nachgezogen. Die beiden
`default`-Portale 43 tierarztportal.com und 51 firmenfreund.net werden bewusst
ohne Werbeplätze geführt, solange sie nicht ausgeliefert sind — ihre Domains
zeigen an der Cloudflare-Kante auf ein anderes Angebot, es gibt dort also
keinen Abruf zu vermarkten. Sie sind damit kein Teil des Rollouts
(Abschnitt 11). Wird eines der beiden später wieder ausgeliefert, ist der
Weg die Theme-Umstellung auf `sun-v2` (ein Tenant-Attribut), nicht das
Nachziehen von 32 Ankern in zwei weitere Theme-Ordner.

### 3.2b Alt-System: dieselbe Lücke, gemessen

Die Positionen des Werbesystems der ersten Generation (`config/ad-positions.php`,
Anker `<x-ad-slot position="…">`) haben keine Theme-Dimension. Folge: in den
fünf sun-v2-Portalen sind **20 von 28 aktiven Slots stumm** — die Positionen
`after_breadcrumb`, `content_before_intro`, `after_change_request`,
`after_photos` und `sidebar_after_claim` gibt es nur in `default`/`starter`.

Diese Zahl ist die lokale Messung (5 sun-v2-Portale, siehe 3.2a). Auf der
Produktion laufen 21 Portale `sun-v2`, dort ist der stumme Anteil des
Altbestands entsprechend größer; nachgezählt wird er in Ticket #15, der Umgang
damit bei der Übernahme in #17.

`App\Ads\Support\ThemeAnchorIndex` misst das statt es zu pflegen: es scannt
die wirksame View-Menge eines Themes (eigene Views, dazu die nicht
überschriebenen Views aus `default` — ohne fremde Layouts, die ein Theme mit
eigenem `layouts/` nie rendert) nach Ankern. Darauf setzen auf:

* das Portal-Backoffice `/verwaltung/werbung` — bietet nur Positionen mit
  Anker an und weist bestehende stumme Slots aus;
* `php artisan ads:anker-bericht [--stumm] [--theme=]` — Bericht je Portal:
  Theme, aktive Slots, davon stumm, betroffene Positionen. Nur Lesezugriff.

### 3.3 Die 32 Anker

Mindesthöhen in Pixel; `0` heißt: auf diesem Gerät wird der Anker nicht
gerendert.

**Startseite (`home`)**

| sort | key | Label | Geräte | Formate | mobil | Desktop |
| --- | --- | --- | --- | --- | --- | --- |
| 10 | `home_after_hero` | unter dem Suchbereich | all | responsive, leaderboard_728x90 | 100 | 90 |
| 20 | `home_after_services` | nach den Leistungen | all | responsive, rectangle_300x250 | 280 | 250 |
| 30 | `home_after_top_rated` | nach den Top-Betrieben | all | responsive, rectangle_300x250 | 280 | 250 |
| 40 | `home_after_guide` | nach den Ratgeber-Artikeln | all | responsive, rectangle_300x250 | 280 | 250 |
| 50 | `home_after_cities` | nach den Städten | all | responsive, rectangle_300x250 | 280 | 250 |
| 60 | `home_before_footer` | über der Fußzeile | all | responsive, leaderboard_728x90 | 100 | 90 |

**Firmensuche (`search`)**

| sort | key | Label | Geräte | Formate | mobil | Desktop |
| --- | --- | --- | --- | --- | --- | --- |
| 10 | `search_above_results` | über der Ergebnisliste | all | responsive, leaderboard_728x90 | 100 | 90 |
| 20 | `search_in_feed_3` | nach dem 3. Ergebnis | all | in_feed, responsive, rectangle_300x250 | 280 | 250 |
| 30 | `search_in_feed_8` | nach dem 8. Ergebnis | all | in_feed, responsive, rectangle_300x250 | 280 | 250 |
| 40 | `search_sidebar_sticky` | Seitenspalte, klebend | desktop | responsive, rectangle_300x250 | 0 | 600 |
| 50 | `search_after_pagination` | unter der Blätterleiste | all | responsive, rectangle_300x250 | 280 | 250 |
| 60 | `search_before_footer` | über der Fußzeile | all | responsive, leaderboard_728x90 | 100 | 90 |

**Firmenprofil (`company`)**

| sort | key | Label | Geräte | Formate | mobil | Desktop |
| --- | --- | --- | --- | --- | --- | --- |
| 10 | `company_after_header_card` | unter der Kopf-Karte (mobil) | mobile | responsive, rectangle_300x250 | 280 | 0 |
| 20 | `company_after_about` | nach „Über uns" | all | responsive, rectangle_300x250 | 280 | 250 |
| 30 | `company_after_reviews` | nach den Bewertungen | all | responsive, rectangle_300x250 | 280 | 250 |
| 35 | `company_sidebar_top` | Seitenspalte, oben | desktop | responsive, rectangle_300x250 | 0 | 250 |
| 40 | `company_sidebar_sticky` | Seitenspalte, klebend | desktop | responsive, rectangle_300x250 | 0 | 600 |
| 50 | `company_before_footer` | über der Fußzeile | all | responsive, leaderboard_728x90 | 100 | 90 |

**Stadtseite (`city`)**

| sort | key | Label | Geräte | Formate | mobil | Desktop |
| --- | --- | --- | --- | --- | --- | --- |
| 10 | `city_above_results` | über der Ergebnisliste | all | responsive, leaderboard_728x90 | 100 | 90 |
| 20 | `city_in_feed_3` | nach dem 3. Ergebnis | all | in_feed, responsive, rectangle_300x250 | 280 | 250 |
| 30 | `city_after_pagination` | unter der Blätterleiste | all | responsive, rectangle_300x250 | 280 | 250 |
| 40 | `city_before_footer` | über der Fußzeile | all | responsive, leaderboard_728x90 | 100 | 90 |

Die Stadtseite hat keine Seitenspalte (keine Geodaten im Datenmodell, #146) —
darum kein `city_sidebar_sticky`.

**Ratgeber-Artikel (`guide`)**

| sort | key | Label | Geräte | Formate | mobil | Desktop |
| --- | --- | --- | --- | --- | --- | --- |
| 10 | `guide_after_key_facts` | nach den Kernaussagen | all | responsive, rectangle_300x250 | 280 | 250 |
| 20 | `guide_in_article_1` | im Text, nach dem 3. Absatz | all | in_article, responsive | 280 | 250 |
| 30 | `guide_in_article_2` | im Text, nach dem 8. Absatz | all | in_article, responsive | 280 | 250 |
| 40 | `guide_sidebar_top` | Seitenspalte, oben | desktop | responsive, rectangle_300x250 | 0 | 250 |
| 50 | `guide_sidebar_sticky` | Seitenspalte, klebend | desktop | responsive, rectangle_300x250 | 0 | 600 |
| 60 | `guide_before_footer` | über der Fußzeile | all | responsive, leaderboard_728x90 | 100 | 90 |

**Ratgeber-Übersicht und -Kategorie (`guide_index`)**

| sort | key | Label | Geräte | Formate | mobil | Desktop |
| --- | --- | --- | --- | --- | --- | --- |
| 10 | `guide_index_after_first_block` | nach dem ersten Themenblock | all | responsive, rectangle_300x250 | 280 | 250 |
| 20 | `guide_index_in_feed_5` | nach der 5. Themenkarte | all | in_feed, responsive, rectangle_300x250 | 280 | 250 |
| 30 | `guide_index_sidebar_sticky` | Seitenspalte, klebend | desktop | responsive, rectangle_300x250 | 0 | 600 |
| 40 | `guide_index_before_footer` | über der Fußzeile | all | responsive, leaderboard_728x90 | 100 | 90 |

`guide_index_sidebar_sticky` ist auf `routes => ['guide.category']` begrenzt
(Abschnitt 3.1a); die übrigen drei gelten für beide Routen des Seitentyps.

### 3.4 `in_article`: Absatzzählung

Die beiden `guide_in_article_*`-Anker werden **serverseitig** gesetzt, nicht im
Template. Der Ratgeber-Hauptteil wird heute schon geteilt:
`App\Guide\Services\TocBuilder::splitForRegionalBlock()` schneidet an der
dritten `<h2>` und `guide/show.blade.php` rendert `body_before` /
`body_after` (Blöcke 6 und 7).

Für `in_article` kommt eine zweite Methode daneben, nach demselben Muster:

```php
/**
 * Teilt den Text nach dem n-ten <p>-Block auf oberster Ebene.
 * Zählt nur direkte Kinder des Wurzelknotens — <p> in <blockquote>,
 * <li>, <table> oder <figure> zählen nicht mit. Reicht die Zahl der
 * Absätze nicht, bleibt der zweite Teil leer und der Anker entfällt.
 *
 * @return array{0: string, 1: string}
 */
public function splitAfterParagraph(string $html, int $paragraph): array
```

Regeln:

* Nur `<p>` auf oberster Ebene, über `DOMXPath` (`/div[@id="toc-root"]/p`) —
  derselbe Wurzelknoten wie in `TocBuilder::innerHtml()`.
* Ein Anker fällt weg, wenn nach ihm weniger als **zwei** Absätze folgen. Sonst
  steht die Anzeige praktisch am Textende und doppelt `guide_before_footer`.
* Die Schnitte sind absteigend zu setzen (erst 8, dann 3), damit die Zählung
  der Absätze nicht durch den ersten Schnitt verschoben wird.
* Der Schnitt darf nie in `body_after` fallen, also nie hinter die
  Verweisleiste Firmensuche (Block 7) — siehe Anker-Regel 2.5.

---

## 4. Datenmodell — Central-DB, Tenant-Scope über Pivot

### 4.1 Entscheidung

**Die gesamte Werbe-Konfiguration liegt in der Central-DB.** Nicht in den
Tenant-DBs, auch nicht gespiegelt.

Begründung:

* Enes pflegt ein Werbemittel **einmal** und wählt die Portale dazu. Im alten
  System lag es je Tenant-DB — deswegen gibt es überhaupt die Filament-Seite
  `AdSlotDistribution` mit Export/Import und `BulkAdSlotImportJob`. Diese ganze
  Maschine fällt mit der Central-DB weg.
* Der Editor (SUN-AD-007) zeigt eine Slot-Liste über **alle** Portale hinweg
  und schaltet den Tenant per Auswahl um. Aus dem zentralen Panel 21+
  Tenant-Verbindungen aufzubauen, nur um eine Liste zu zeigen, ist nicht
  tragbar.
* Tenant-DBs bekommen laufend neue Portale (`tenants:migrate`). Eine zentrale
  Tabelle braucht keine Migration je Portal.

Preis der Entscheidung, bewusst in Kauf genommen: die Ausspielung im
Tenant-Kontext muss auf die zentrale Verbindung zurückgreifen. Das heißt
konkret, dass die Modelle **ohne** `TenantConnection` laufen und die
`central`-Verbindung explizit setzen — und dass der Resolver cachen muss
(Abschnitt 8), weil sonst jede Portal-Seite eine Abfrage auf die zentrale DB
auslöst.

### 4.2 Tabellen (SUN-AD-003, Ticket #3)

Alle in `database/migrations/` (zentral), nicht in `database/migrations/tenant/`.
Alle Modelle liegen in `app/Ads/Models/` und setzen `CentralConnection` —
auch im Tenant-Kontext wird zentral gelesen.

**`ad_units`** — das Werbemittel. Was ausgespielt wird.

| Spalte | Typ | Hinweise |
| --- | --- | --- |
| `id` | id | |
| `name` | string | Name in der Verwaltung |
| `provider` | string | `AdProviderType` |
| `config_json` | json | providerspezifisch, Schema siehe unten |
| `is_active` | bool | Not-Aus für das Werbemittel über alle Belegungen |
| `archived_at` | timestamp, null | gesetzt = aus dem Verkehr gezogen, in keiner neuen Belegung wählbar (#10, Abschnitt 9a) |
| `timestamps` | | |

Index: `['provider', 'is_active']`, `['archived_at', 'name']`.

`config_json` statt eigener Spalten je Provider: ein dritter Provider
(Ticket #4) bringt dann kein `ALTER TABLE` mit. Das Schema prüft
`App\Ads\Support\AdUnitConfigSchema` — im Formular über die Rule
`App\Ads\Rules\ValidAdUnitConfig`, beim Speichern noch einmal im Model
(`AdUnit::booted()`), damit nichts Ungültiges in der Tabelle landen kann:

| Provider | Pflicht | Optional |
| --- | --- | --- |
| `adsense` | `slot_id` (nur Ziffern, 6–20 Stellen) | `format`, `layout_key` |
| `html` | `code` (max. **20 kB**, in Bytes gemessen) | `format`, `width`, `height` |

`format` ist bei beiden ein Schlüssel aus `config('ad-slots.formats')`;
fehlt er, gilt `responsive`. Unbekannte Schlüssel werden beim Speichern
verworfen (`AdUnitConfigSchema::normalize()`).

**`ad_placements`** — die Belegung. Wo, wann, mit welcher Priorität.

| Spalte | Typ | Hinweise |
| --- | --- | --- |
| `id` | id | |
| `ad_unit_id` | fk → `ad_units`, cascade | |
| `page_type` | string | `AdPageType`; redundant zu `slot_key`, das Model füllt sie aus dem Katalog |
| `slot_key` | string | Schlüssel aus dem Katalog; **keine** FK, der Katalog ist Config |
| `device` | string | `AdDevice`, Wunsch der Belegung; der Anker verengt (`AdDevice::narrowTo()`) |
| `status` | string | `AdPlacementStatus` |
| `priority` | unsignedSmallInteger | **größer gewinnt**; bei Gleichstand die neuere Belegung |
| `starts_at` | timestamp, null | null = sofort |
| `ends_at` | timestamp, null | null = unbefristet |
| `applies_to_all_tenants` | bool | true = alle Portale, Pivot bleibt leer |
| `created_by` | fk → `users`, null on delete | |
| `timestamps` | | |

Indizes:

* `['slot_key', 'status', 'device']` — der Zugriffspfad des Resolvers.
* `['status', 'starts_at', 'ends_at']` — für die Fälligkeitsprüfung und den
  Befehl, der abgelaufene Belegungen auf `expired` schreibt.
* `['page_type', 'status']` — die Slot-Liste je Seitentyp im Editor (#7).

Kein Unique-Index über Anker/Gerät/Portal: mehrere Belegungen pro Anker sind
ausdrücklich erlaubt, die Auswahl trifft 4.3.

`slot_key` und `device` prüft `AdPlacement::booted()` gegen den Katalog
(`App\Ads\Support\AdSlotCatalog`); im Formular tun das dieselben Prüfungen
als `SlotKeyInCatalog` und `DeviceFitsSlot`.

**`ad_placement_tenant`** — der Tenant-Scope.

| Spalte | Typ |
| --- | --- |
| `ad_placement_id` | fk → `ad_placements`, cascade |
| `tenant_id` | fk → `tenants`, cascade |

Primärschlüssel über beide Spalten, zusätzlicher Index auf `tenant_id`.
`tenants.id` ist in diesem Projekt ein `bigint` (siehe
`2024_06_16_091235_create_tenants_table`), nicht der String aus der
stancl-Vorgabe — der Pivot folgt der echten Tabelle.

**`ad_settings`** — Werbe-Einstellungen je Portal, genau eine Zeile je Tenant.

| Spalte | Typ | Hinweise |
| --- | --- | --- |
| `tenant_id` | fk → `tenants`, unique, cascade | |
| `ads_enabled` | bool, default false | Freigabeschalter; aus heißt: in diesem Portal erscheint nichts |
| `adsense_publisher_id` | string, null | leer = `config('ad-slots.adsense.publisher_id')` aus `ADSENSE_PUBLISHER_ID` |
| `ads_txt_extra` | text, null | Zusatzzeilen für ads.txt dieser Domain (#10) |
| `updated_by` | fk → `users`, null on delete | |

Eigene Tabelle statt Attributen auf `tenants.data` (abweichend von einem
früheren Stand dieses Dokuments): #10 listet die Werte aller Portale in einer
Abfrage, und der Resolver kann sie joinen, statt 21 Tenant-Modelle zu laden.
`AdSettingSeeder` legt die Zeilen für alle bestehenden Portale mit
`ads_enabled = false` an — nach der Migration erscheint nirgends Werbung, bis
jemand sie freigibt.

### 4.3 Auflösung

Für ein Portal und einen Seitentyp gilt pro Slot **genau eine** Belegung:

1. `status = active`, Werbemittel `is_active = true`,
2. `starts_at` null oder vergangen, `ends_at` null oder künftig,
3. `applies_to_all_tenants = true` **oder** der Tenant steht im Pivot,
4. Gerät passt (`AdDevice::fitsInto()`),
5. davon die mit **größter** `priority`, bei Gleichstand die größte `id`
   (also die neueste Belegung).

In Scopes: `AdPlacement::query()->deliverable()->forTenant($tenant)
->forSlot($slotKey, $device)->resolutionOrder()->first()`. Die Rangfolge steht
ausschließlich in `scopeResolutionOrder()`.

Eine portalgenaue Belegung (über Pivot) schlägt eine `applies_to_all_tenants`-Belegung
nicht automatisch — das regelt allein `priority`. Das ist Absicht: sonst gibt
es zwei Rangordnungen, von denen eine unsichtbar ist. Die Verwaltung (Ticket
#8) bietet portalgenauen Belegungen daher die höhere Vorgabe-Priorität an:
20 gegen 10 netzweit (Abschnitt 6.4).

---

## 5. Provider-Interface

`app/Ads/Contracts/AdProvider.php` (SUN-AD-003, Ticket #4, umgesetzt):

```php
interface AdProvider
{
    /** Schlüssel: steht so in `ad_units.provider` und in der Config. */
    public function key(): string;

    /** Bezeichnung für Formulare und Tabellen (#8/#10). */
    public function label(): string;

    /**
     * Felder der providerspezifischen Konfiguration — eine Beschreibung,
     * kein Filament-Code. Die Resource (#10) baut die Komponenten daraus,
     * validateConfig() leitet die Regeln daraus ab.
     *
     * Je Feld: name, label, type (text|textarea|number|select), required,
     * optional help, options, rules, messages, placeholder.
     *
     * @return list<array<string, mixed>>
     */
    public function configSchema(): array;

    /** @return list<string> Fehlermeldungen; leer = gültig */
    public function validateConfig(array $config): array;

    /** Auf die bekannten Schlüssel eingedampfte Konfiguration. */
    public function normalizeConfig(array $config): array;

    /**
     * Braucht die Ausspielung eine Einwilligung? Entscheidet, ob
     * <x-sun.ad-slot> den Platzhalter oder das Werbemittel rendert
     * (SUN-AD-009).
     */
    public function requiresConsent(AdUnit $unit): bool;

    /**
     * Kann dieses Werbemittel hier ausgespielt werden? Prüft die harten
     * Voraussetzungen des Providers — bei AdSense die Publisher-ID des
     * Portals, bei HTML Markup samt Größe. Liefert false, wenn der Anker
     * leer bleiben muss.
     */
    public function supports(AdUnit $unit, AdRenderContext $context): bool;

    /**
     * Rendert den Inhalt des reservierten Containers — ohne den Container
     * selbst und ohne Höhenreservierung, die setzt die Komponente aus dem
     * Katalog. Liefert null, wenn nichts auszuspielen ist.
     */
    public function render(AdUnit $unit, AdRenderContext $context): ?RenderedAd;
}
```

Zwei Abweichungen vom Entwurf dieses Abschnitts, beide bewusst (#4):

* **`key(): string` statt `type(): AdProviderType`** und `ad_units.provider`
  ohne Enum-Cast. Mit Enum-Cast wäre ein dritter Provider ohne Änderung an
  `AdUnit` nicht möglich gewesen — `AdProviderType` ist seither nur noch die
  Konstantenliste der beiden eingebauten Arten.
* **`?RenderedAd` statt `?Htmlable`.** `RenderedAd` *ist* `Htmlable`, trägt
  aber zusätzlich die Head-Abhängigkeiten (unten) und Diagnose-Meta für
  `ads:resolve` und den Editor.

`configSchema()` ist der Grund, warum `AdUnitConfigSchema` seit #4 nur noch
eine Durchreiche zur Registry ist: Formular, Pflichtprüfung beim Speichern und
Provider lesen dieselbe Liste, sie können nicht auseinanderlaufen.

### 5.0 RenderedAd und Head-Abhängigkeiten

```php
final readonly class RenderedAd implements Htmlable
{
    public function __construct(
        public string $html,                 // Fragment, ohne Container
        public array $headDependencies = [], // list<AdHeadDependency>
        public string $providerKey = '',
        public ?int $adUnitId = null,
        public ?int $placementId = null,     // setzt der Resolver
        public array $meta = [],
    ) {}
}

final readonly class AdHeadDependency
{
    public function __construct(
        public string $handle,          // Schlüssel der Entdoppelung
        public string $src,
        public array $attributes = [],
        public bool $requiresConsent = true,
    ) {}
}
```

Der Provider **gibt kein Skript aus**, er meldet es an. Die Komponente (#5)
sammelt die Abhängigkeiten aller Anker einer Seite, wirft Doppelte anhand von
`handle` weg und schreibt sie einmal in den Layout-Stack `head` — sechs Anker
laden den AdSense-Loader also genau einmal (SUN-AD-004).

`AdRenderContext` ist ein readonly DTO (`app/Ads/Dto/`) und trägt alles, was
der Provider braucht, ohne selbst zu fragen:

```php
final readonly class AdRenderContext
{
    public function __construct(
        public string $slotKey,
        public AdPageType $pageType,
        public AdDevice $device,
        public string $tenantId,
        public ?string $adsensePublisherId,
        public bool $consentGranted,
        public bool $editorMode,
        public bool $lazy,
        public int $minHeightMobile,
        public int $minHeightDesktop,
    ) {}
}
```

Registrierung über `config('ad-slots.providers')` — Schlüssel = Wert in
`ad_units.provider`, Wert = Klasse:

```php
'providers' => [
    AdProviderType::ADSENSE->value => AdSenseProvider::class,
    AdProviderType::HTML->value => HtmlProvider::class,
],
```

Nachgeschlagen wird ausschließlich über `App\Ads\AdProviderRegistry`
(Singleton, gebunden im `AdsServiceProvider`). Models, Resolver, Komponente und
Editor kennen keine Provider-Klasse. Ein neuer Provider ist eine Klasse plus
eine Zeile dort — Beispiel in 5.3.

### 5.1 AdSenseProvider

* Rendert `<ins class="adsbygoogle" data-ad-client="ca-pub-…" data-ad-slot="…">`
  plus den Aufruf von `adsbygoogle.push({})`.
* `data-ad-client` kommt **immer** aus dem Tenant-Attribut, nie aus dem
  Werbemittel. Ein Werbemittel ohne passende Publisher-ID beim Tenant wird
  nicht ausgespielt (`supports()` → false).
* Das Loader-Skript `adsbygoogle.js` wird **einmal pro Seite** eingebunden, nicht
  pro Slot (Entdopplung über `AdHeadRegistry`), und erst nach Einwilligung:
  ohne sie steht es in einem `<template data-ad-head>` und wird von
  `resources/js/ads.js` nachgezogen (Abschnitt 9).
* Format → AdSense-Attribute: `responsive` → **keine** Format-Angabe,
  `style="width:100%;height:<Reservierung>px"`; `in_feed` →
  `data-ad-format="fluid"` + `data-ad-layout-key`; `in_article` →
  `data-ad-format="fluid"` + `data-ad-layout="in-article"`; feste Größen →
  `style="width:…px;height:…px"` **innerhalb** des reservierten Containers.
* `responsive` bekam bis #32 `data-ad-format="auto"` +
  `data-full-width-responsive="true"` und keine Höhe. Damit bestimmt **AdSense**
  die Höhe (gemessen 250 px) und sprengt die Reservierung eines Banner-Ankers:
  CLS 0,136 auf `home_after_hero` (Abschnitt 9.5). Ohne Format-Angabe gilt die
  CSS-Größe des `<ins>` — Breite der Spalte, Höhe aus dem Katalog. Der Anker
  gibt die Höhe vor, das Werbemittel füllt sie; `data-full-width-responsive`
  entfällt, es betrifft nur `auto`.
* Die Höhe ist die **kleinere** der beiden Gerätehöhen des Ankers, wenn
  derselbe Anzeigenplatz mobil und auf dem Desktop läuft (ein Container, ein
  Fragment, Abschnitt 7.1): 90 px bei `home_after_hero` (100 mobil / 90
  Desktop). Eine Höhe 0 im Katalog heißt „diesen Anker gibt es auf dem Gerät
  nicht" und zählt nicht mit. Fluide Formate (`in_feed`, `in_article`)
  behalten ihre freie Höhe — sie stehen nur auf Ankern mit 250/280 px.
* `requiresConsent()` ist immer `true`; der Wert wandert über
  `RenderedAd::$requiresConsent` zur Komponente, die daraufhin
  `data-ad-consent` setzt (Abschnitt 9).
* Der Einwilligungsstand aus dem Kontext ist **keine** Abbruchbedingung des
  Providers mehr (#9) — gerendert wird immer, zurückgehalten wird im Markup.
* Im Editor-Modus rendert der Provider nichts (Abschnitt 6).

### 5.2 HtmlProvider — Sandbox-Regeln

Das HTML in `ad_units.config_json->code` kommt aus der Verwaltung und ist **nicht
vertrauenswürdig**: wer es einträgt, soll keine Skripte in den Portal-Origin
bekommen (kein Zugriff auf Session-Cookies, kein Lesen des DOM, keine
Formular-Übernahme).

Harte Regeln:

1. **Immer ein `<iframe sandbox>`.** Das Markup wird nie in die Portalseite
   geschrieben — auch nicht mit CSP-Nonce. Das unterscheidet den HtmlProvider
   vom Altsystem, das `CspNonce::inject()` auf den Code legt und ihn direkt
   einfügt.
2. **`sandbox` ohne `allow-same-origin`.** Erlaubt ist ausschließlich
   `allow-scripts allow-popups allow-popups-to-escape-sandbox`. Ohne
   `allow-same-origin` ist der iframe ein opaker Origin: kein Zugriff auf
   Cookies, `localStorage` oder das übergeordnete Dokument. `allow-scripts`
   **und** `allow-same-origin` zusammen wären identisch mit „keine Sandbox" —
   diese Kombination ist verboten.
   **Die Sandbox trennt Origins, nicht Netzwerkziele.** `allow-scripts` ist
   gesetzt, ein Skript im Rahmen darf also beliebige Hosts ansprechen und
   dabei IP-Adresse, Browser-/Gerätedaten und Zeitpunkt übertragen. Der
   Rahmen ist eine Grenze gegen das Portal, keine gegen Dritte — daraus
   folgt Regel 8.
3. **Zustellung über eine eigene Route**, nicht über `srcdoc`:
   `GET /_ads/html/{adUnit}` mit eigenen Antwort-Headern
   (`X-Frame-Options` entfällt, statt dessen `frame-ancestors` auf die
   Portal-Domains; `Content-Security-Policy: sandbox`;
   `Cross-Origin-Resource-Policy: same-site`). `srcdoc` erbt die CSP des
   Elterndokuments und würde das Nonce-Modell aus `config/csp.php`
   unterwandern.
4. **`frame-src`/`child-src` der Portal-CSP** muss diese Route freigeben.
   Sie liegt auf der Portal-Domain selbst, das vorhandene `frame-src 'self'`
   aus `config/csp.php` deckt sie also ab — eingetragen wird nichts.
5. **Keine Höhenverhandlung per `postMessage`.** Der iframe bekommt die feste
   Breite und Höhe aus der Werbemittel-Konfiguration (beide sind seit #4
   **Pflichtfelder**), dazu `max-width:100%`, damit er auf 360 px nur kleiner
   wird und nie querläuft. Er darf seine Größe nicht ändern: eine
   nachträgliche Höhenänderung ist genau der Layout-Shift, den das ganze
   System vermeiden soll.
6. **`loading="lazy"` und `referrerpolicy="no-referrer"`** auf dem iframe,
   `title` aus dem Namen des Werbemittels (Barrierefreiheit).
7. **Keine Filterung des HTML beim Speichern.** Sanitizing erzeugt nur das
   falsche Gefühl von Sicherheit und bricht legitime Werbemittel; die Sandbox
   ist die Grenze. Dafür ist das Anlegen von `ad_units` auf die
   Admin-Rolle beschränkt (SUN-AD-010) und wird protokolliert.
8. **Einwilligung je Werbemittel über `config_json->third_party`** (#36).
   `yes` (Vorgabe, auch bei fehlender Angabe) heißt: `requiresConsent()`
   ist `true`, der Rahmen liegt bis zur Einwilligung in einem `<template>`
   und lädt nichts. `no` heißt: nur eigene Inhalte, der Rahmen lädt
   sofort. Das Feld ist ein Auswahlfeld im Formular, formal nicht
   pflichtig — `normalizeConfig()` schreibt beim Speichern `yes` fest, wenn
   nichts gewählt wurde, damit Altbestände nicht aus der Ausspielung
   fallen (`supports()` prüft `validateConfig()`). Falsch gesetzt heißt
   Datenübertragung an Dritte ohne Einwilligung (§ 25 Abs. 1 TDDDG,
   Art. 6 Abs. 1 lit. a DSGVO) **und** ein fehlender Absatz in der
   Datenschutzerklärung — beides hängt an diesem einen Wert
   (Abschnitt 9.4).

Umgesetzt in #4: `App\Ads\Providers\HtmlProvider` rendert ausschließlich das
iframe, `App\Ads\Http\Controllers\AdHtmlController` stellt das Dokument zu.
Die Route steht in `routes/tenant.php` **ohne** `web`-Gruppe — keine Session,
kein CSRF-Cookie im Rahmen — und antwortet mit

```
Content-Security-Policy: sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox; frame-ancestors 'self'
Cross-Origin-Resource-Policy: same-site
Referrer-Policy: no-referrer
X-Robots-Tag: noindex, nofollow
Cache-Control: public, max-age=3600
```

Die Sandbox steht damit zweimal und voneinander unabhängig: als Attribut am
iframe und als Antwort-Header. Die `src` trägt einen kurzen Abdruck des
Markups (`?v=…`), deshalb darf der Browser lange halten und sieht eine
Änderung trotzdem sofort. Ein abgeschaltetes oder gelöschtes Werbemittel
antwortet mit 404 — auch in Rahmen, die noch offen stehen.

### 5.3 Ein dritter Provider — Beispiel `AffiliateProvider`

Angenommen, es soll ein Partnerlink-Werbemittel geben: ein Bild mit Link,
Zielseite und Partner-ID aus der Konfiguration, ohne fremdes Skript und ohne
Einwilligung. Dafür ist zu tun — **zwei** Dinge:

**1. Die Klasse** `app/Ads/Providers/AffiliateProvider.php`:

```php
class AffiliateProvider extends AbstractAdProvider
{
    public function key(): string
    {
        return 'affiliate';          // Wert in ad_units.provider
    }

    public function label(): string
    {
        return __('Partnerlink');
    }

    public function configSchema(): array
    {
        return [
            ['name' => 'url', 'label' => __('Zielseite'), 'type' => 'text',
             'required' => true, 'rules' => ['required', 'url', 'max:2048']],
            ['name' => 'image', 'label' => __('Bild-URL'), 'type' => 'text',
             'required' => true, 'rules' => ['required', 'url', 'max:2048']],
            ['name' => 'alt', 'label' => __('Bildbeschreibung'), 'type' => 'text',
             'required' => true, 'rules' => ['required', 'string', 'max:160']],
            $this->formatField(),
        ];
    }

    public function requiresConsent(AdUnit $unit): bool
    {
        // Das Bild kommt von einem fremden Host: der Abruf überträgt
        // IP-Adresse und Gerätedaten dorthin, auch ohne Cookie. Die Vorgabe
        // aus AbstractAdProvider ist `true` — diese Methode entfällt also.
        return true;
    }

    public function render(AdUnit $unit, AdRenderContext $context): ?RenderedAd
    {
        if (! $this->supports($unit, $context)) {
            return null;
        }

        $config = (array) $unit->config_json;

        return new RenderedAd(
            html: '<a '.$this->attributes([
                'href' => $config['url'],
                'rel' => 'sponsored nofollow noopener',
                'target' => '_blank',
            ]).'><img '.$this->attributes([
                'src' => $config['image'],
                'alt' => $config['alt'],
                'loading' => $context->lazy ? 'lazy' : 'eager',
                'class' => 'block mx-auto max-w-full h-auto',
            ]).'></a>',
            providerKey: $this->key(),
            adUnitId: (int) $unit->getKey(),
        );
    }
}
```

`AbstractAdProvider` erledigt `validateConfig()` und `normalizeConfig()` aus
dem Schema und liefert `attributes()` zum maskierten Ausgeben — zu schreiben
sind nur Schlüssel, Bezeichnung, Felder und Markup.

**2. Die Zeile in `config/ad-slots.php`:**

```php
'providers' => [
    AdProviderType::ADSENSE->value => AdSenseProvider::class,
    AdProviderType::HTML->value => HtmlProvider::class,
    'affiliate' => AffiliateProvider::class,   // neu
],
```

Fertig. **Nicht** anzufassen: Migrationen (`provider` ist ein String,
`config_json` ein JSON-Feld), `AdUnit`, `AdPlacement`, `AdSlotResolver`, die
Komponente, der Editor, die Filament-Resources. Das Formular in #10 baut sich
aus `configSchema()`, die Auswahlliste aus `AdProviderRegistry::options()`, die
Pflichtprüfung beim Speichern aus `validateConfig()`.

Zu prüfen bleibt nur das Übliche: lädt der Provider fremde Hosts, gehören sie
in `config/csp.php` (#9) — **und** `requiresConsent()` muss `true` sein. Nicht
erst, wenn er Cookies setzt: schon der Request an einen fremden Host
überträgt IP-Adresse und Gerätedaten (#36). Ein Sandbox-iframe ändert daran
nichts, er trennt Origins und keine Netzwerkziele. Die Vorgabe in
`AbstractAdProvider` ist `true`; wer sie auf `false` dreht, muss belegen
können, dass vor der Einwilligung kein Request den eigenen Server verlässt.

---

## 6. Editor-Modus im Portal

Ziel (Ticket #1): Enes öffnet im Backend eine **echte** Portalseite, sieht alle
Anker als Rahmen, klickt einen an und belegt ihn.

### 6.1 Token-Flow

```
Filament-Seite „Werbeplätze" (Panel admin, zentral)
  │  App\Filament\Admin\Pages\AdPlacementEditor (Ticket #7)
  │  Admin angemeldet, Zugang über AdsAdminAccess
  │
  │  App\Ads\EditorToken::url($tenant, $path, $pageType)
  │    → https://<portal-domain><pfad>
  │        ?ad_editor_expires=…&ad_editor_signature=…&ad_editor_page_type=…
  │    gebunden an Host UND Pfad, 30 Minuten (ad-slots.editor_token_minutes)
  ▼
<iframe src="…">  (Mobil 390 px / Desktop 1280 px, skaliert per transform)
  │
  ▼
Middleware-Alias 'ad-editor' (app/Ads/Http/Middleware/AdEditorMode.php),
hängt an der ganzen Tenant-Routengruppe — es gibt KEINE eigene Editor-Route
  ├─ kein Token in der Adresse  → normale Seite
  ├─ Token faul oder abgelaufen → normale Seite (nie 403: eine alte Adresse
  │                               im Verlauf darf keine Fehlerseite zeigen)
  └─ Token gültig → AdEditorMode::enable(), danach die angeforderte Seite
```

**Warum kein `URL::temporarySignedRoute()`** (so stand es hier bis 16.10.2026):
die Vorschau zeigt beliebige Portalseiten, und eine Suchseite bringt eigene
Parameter mit. Laravels Signatur gilt für eine *benannte* Route samt
vollständiger Query und würde bei jedem Filter brechen. {@see EditorToken}
signiert deshalb nur Host, Pfad und Ablauf; die übrige Query bleibt frei, weil
sie Inhalte wählt und keine Rechte (der Modus ist lesend).

**Panel-Seite (#7), was sie hält:** gewähltes Portal (geteilt mit
Einstellungen › Werbung über `AdsAdminPortal`), Seitentyp, die Route (Feld
**Seite**, nur sichtbar bei einem Seitentyp mit mehreren Routen, Ticket #27),
Gerät und den Pfad der Beispielseite. Den Pfad ermittelt `App\Ads\Preview\PreviewUrlResolver`
im Portalkontext — letzte freigeschaltete Firma, Stadt mit den meisten
Betrieben, neuester Ratgeber-Artikel —, überschreibbar im Formular. Gibt es
keinen solchen Inhalt, bleibt die Vorschau leer und sagt es; eine geratene
Adresse würde im iframe eine 404 zeigen und wie ein Fehler des Werkzeugs
aussehen. Die Ankerliste rechts kommt aus `App\Ads\Preview\EditorSlotList`
(Theme des Portals, Zustand je Gerät, Route der gezeigten Seite, ohne Cache)
— ein Anker, der auf dieser Route nicht steht, trägt dort „nicht auf dieser
Seite" (Abschnitt 3.1a). Einen Endpunkt, der Tokens
ausgibt, gibt es nicht: die Adresse entsteht serverseitig beim Rendern, und
„Vorschau neu laden" ist eine Livewire-Aktion.

Regeln, die der Modus erzwingt:

* **`<meta name="robots" content="noindex,nofollow">`** im Layout, zusätzlich
  der Antwort-Header `X-Robots-Tag: noindex, nofollow`. Eine signierte URL ist
  nicht geheim — sie darf nie in den Index.
* **Kein AdSense.** `AdSenseProvider::render()` liefert im Editor-Modus `null`;
  das Loader-Skript wird nicht eingebunden. Ein Impression auf einer
  Vorschauseite ist bei Google ein ungültiger Klick-/Impression-Vorfall.
* **Kein Analytics.** `partials/analytics.blade.php` rendert nichts (dieselbe
  Prüfung wie `app()->isProduction()` heute); kein Turnstile, keine
  Lead-Statistik, kein `data-stats-*`-Versand.
* **Kein Cookie-Banner**, keine Einwilligungs-Abfrage — im Editor gilt
  `consentGranted = false` und es wird ohnehin nichts Fremdes geladen.
* **Kein Schreibzugriff.** Die Signatur gilt nur für `GET`; die Middleware
  lehnt alles andere mit 405 ab. Belegungen werden im Panel gespeichert, nicht
  im iframe.
* **Alle Anker rendern einen Rahmen** — auch leere und auch solche, deren
  Bedingung sonst nicht greift: `ads_enabled = false`, werbefreies
  Premium-Profil, Seitenspalte ohne Geodaten. Sonst fehlen im Editor genau die
  Plätze, die Enes belegen will.
* **Der Rahmen hat die Katalog-Mindesthöhe**, trägt Label und Schlüssel und ist
  per Tastatur erreichbar (`<button>`, nicht `<div onclick>`).

Keine Rolle, keine Session, kein Gast-Login im Tenant-Kontext: der Portal-Teil
kennt den Admin nicht. Die Signatur ist der ganze Nachweis — darum die kurze
Gültigkeit und das `noindex`.

### 6.2 postMessage-Protokoll

Die Brücke läuft zwischen dem iframe (Portal-Origin, Tenant-Domain) und der
Filament-Seite (zentrale Domain) — also **cross-origin**. Beide Seiten geben
`targetOrigin` immer explizit an, nie `'*'`, und prüfen `event.origin` gegen die
erwartete Gegenseite. Jede Nachricht ist ein Objekt mit
`{ type, payload, catalogVersion }`; eine Nachricht mit abweichender
`catalogVersion` wird verworfen und das Panel lädt neu.

**Portal → Panel**

| Typ | Nutzlast | Wann |
| --- | --- | --- |
| `slots:ready` | `{ pageType, device, slots: [{ key, label, minHeight, filled, placementId\|null }] }` | einmal nach `DOMContentLoaded`; sagt dem Panel, welche Anker die Seite tatsächlich hat |
| `slot:clicked` | `{ key, device, pageType }` | Klick oder Enter/Space auf einem Anker-Rahmen; das Panel öffnet die Belegung. `device` kommt aus dem **Container** des Rahmens (`mobile`/`desktop`/`all`), nicht aus der Fensterbreite — auf einem Anker mit `devices: all` kann je Gerät eine andere Belegung gewinnen |

**Panel → Portal**

| Typ | Nutzlast | Wirkung im Portal |
| --- | --- | --- |
| `slot:highlight` | `{ key \| null }` | hebt den Rahmen hervor und scrollt ihn in den Blick; `null` hebt alles auf |
| `placement:updated` | `{ key, filled, label }` (dazu `device`, `status` als Auskunft) | aktualisiert Beschriftung und Zustand des Rahmens — **ohne** Reload, damit die Scrollposition bleibt. `filled` und `label` sind der **neu aufgelöste** Stand des Ankers (#8), nicht der gerade gespeicherte Datensatz: nach dem Pausieren kann eine andere Belegung nachrücken |
| `device:changed` | `{ device: 'mobile' \| 'desktop' }` | nur Information; die echte Umschaltung ist die Breite des iframes, nicht eine Klasse. Das Portal antwortet mit einem neuen `slots:ready`, weil sich die Ankerliste geräteabhängig ändert |

`device:changed` ändert bewusst **nichts** am Rendering: die Vorschau muss echt
sein. Das Panel setzt die iframe-Breite (390 px bzw. 1280 px), die Media
Queries des Themes tun den Rest. Eine per JS gesetzte „mobile"-Klasse würde
eine Vorschau zeigen, die es im Browser nicht gibt.

Das Portal-Skript ist ein **eigener** Vite-Eintrag,
`resources/views/themes/sun-v2/js/ad-editor.js` (kein Modul von `app.js`): das
Layout bindet es allein im Editor-Modus ein, ein normaler Besucher lädt davon
kein Byte. Es hängt an den `data-ad-*`-Attributen des Rahmens — keine
Inline-Skripte, die CSP hat kein `'unsafe-inline'` für `script-src` (siehe
`docs/turnstile.md` Abschnitt 14). Die Herkunft der Gegenseite steht am
`<body>` (`data-ad-editor-origin`); fehlt sie, läuft die Brücke nicht an.

### 6.3 Umgesetzt in Ticket #6

| Stelle | Aufgabe |
| --- | --- |
| `App\Ads\EditorToken` | Adresse erzeugen und prüfen, Herkunft, Katalogversion |
| `App\Ads\Http\Middleware\AdEditorMode` | Token prüfen, Modus einschalten, Altsystem stummschalten, `X-Robots-Tag` |
| `bootstrap/app.php`, `routes/tenant.php` | Alias `ad-editor` an der ganzen Portal-Routengruppe |
| `components/sun/ad-slot-editor-frame.blade.php` | der Rahmen: `<button>`, Label, Schlüssel, Gerät, Zustand |
| `resources/views/themes/sun-v2/js/ad-editor.js` | postMessage-Brücke, Hervorhebung, Zustand ohne Reload |
| `layouts/sun.blade.php` | `noindex`, kein Analytics, kein Cookie-Hinweis, Bundle nur hier |
| `ResolvesTurnstileConfig::shouldRender()` | kein Turnstile in der Vorschau |
| `AdEditorMode::enable()` | `scoped` statt `instance` — sonst klebt der Modus in einem Langläufer über die Anfrage hinaus |

Nicht im Editor-Modus sichtbar sind Anker, die in einer **Inhaltsbedingung**
stecken. Der Rückfall von `/ratgeber` auf `pages/blog/index.blade.php` trug am
16.10.2026 ebenfalls keinen `guide_index`-Anker; seit Ticket #23 stehen die
Anker auch in den beiden Rückfall-Views (Abschnitt 3.1).

#### Inhaltsbedingung `requires` (Ticket #22)

Fünf Anker stehen im Markup **innerhalb** ihrer Sektion und fehlen mit ihr:

| Anker | steht in | Bedingung |
| --- | --- | --- |
| `home_after_services` | `pages/home.blade.php` | `@if($sun['services'] !== [])` |
| `home_after_top_rated` | `pages/home.blade.php` | `@if($sun['topRated']->isNotEmpty())` |
| `home_after_guide` | `pages/home.blade.php` | `@if(config('themes.sun-v2.nav.guide') && $latestPosts->isNotEmpty())` |
| `home_after_cities` | `pages/home.blade.php` | `@if($sun['cities']->isNotEmpty())` |
| `company_after_about` | `pages/companies/show.blade.php` | `@if($profile['paragraphs'] !== [])` |

**Entschieden (16.10.2026): sie bleiben in der Bedingung.** Zöge man sie
heraus, stünden auf einer inhaltsarmen Startseite bis zu vier Banner
unmittelbar hintereinander — ein Anker gehört laut Katalogregel zwischen zwei
abgeschlossene Sektionen, und bei AdSense sind Anzeigen ohne Inhalt dazwischen
ein Richtlinienverstoß. Ein Rahmen, der nur im Editor erscheint und in der
Auslieferung nie, wäre außerdem eine falsche Vorschau.

Damit trotzdem nichts still verschwindet, führt der Slot `requires`: einen
Satz, der die Bedingung nennt. Verwendet wird er an drei Stellen:

* `AdSlotCatalog::requirementOf()` — Zugriff auf den Satz.
* `EditorSlotList` gibt ihn als Feld `requires` an die Ankerliste; die Zeile
  zeigt „Erscheint nur, wenn: …".
* Der Warnhinweis „Im Katalog, aber nicht in der geladenen Seite" nennt zu
  jedem fehlenden Anker den Grund — und sagt ausdrücklich *kein Grund im
  Katalog hinterlegt*, wenn keiner da ist. Das ist dann ein fehlender Anker im
  Markup und gehört gemeldet (dieselbe Fehlerklasse wie #13, #14, #19).

Gegenprobe am 16.10.2026 auf `sanitaerfinden.com` (Portal mit Leistungen,
Betrieben und Städten, aber ohne Ratgeber-Beiträge und mit einem Profil ohne
Beschreibungstext): `home_after_guide` und `company_after_about` fehlen in der
Seite, beide mit Grund; alle übrigen Anker beider Seitentypen sind da.
Wächter: `tests/Feature/Ads/SlotContentRequirementTest.php`.

### 6.4 Konfigurationspanel (Ticket #8)

Ein Klick auf einen Anker — in der Liste oder im iframe — öffnet unter der
Ankerliste das Panel dieses Ankers: oben die Belegungen, die das gezeigte
Portal betreffen (netzweite eingeschlossen, in der Rangfolge der Auflösung),
darunter das Formular.

| Stelle | Aufgabe |
| --- | --- |
| `App\Livewire\Admin\AdPlacementPanel` | Liste, Formular, Statuswechsel, Hinweise, Meldung an die Brücke |
| `resources/views/livewire/admin/ad-placement-panel.blade.php` | Darstellung |
| `App\Ads\Actions\SavePlacement` | Speichern samt Pivot und Cache, Konflikt- und Formatprüfung |
| `App\Ads\Forms\ProviderConfigForm` | Filament-Felder aus `AdProvider::configSchema()` — geteilt mit der Werbemittel-Resource (#10) |
| `AdsAdminPortal::names()` | Portal-Namen aus der Central-DB, fünf Minuten im Cache |

Sechs Entscheidungen, die zum Verständnis gehören:

* **Eine Komponente je Anker.** Der Editor bindet das Panel mit einem `:key`
  aus Portal, Anker und Gerät ein; ein Wechsel baut es neu auf, statt den
  Formularstand des vorigen Ankers mitzunehmen.
* **Geräte kommen aus dem Katalog.** Ein desktop-only Anker (`sidebar_*`)
  bietet genau eine Angabe an, abgeschaltet und mit Begründung — das Model
  verengt beim Speichern ohnehin (Abschnitt 3.2), und eine Wahl, die
  stillschweigend überschrieben wird, ist keine.
* **Portale: Schalter oder Liste.** „Alle Portale" setzt
  `applies_to_all_tenants` und leert den Pivot; sonst stehen dort die
  gewählten Portale. „alle außer der Auswahl" dreht die Auswahl um — im Netz
  der häufigere Fall als zwanzig Haken. In der Liste steht „Alle" oder
  „n Portale" mit den Namen im Tooltip.
* **Vorgabe-Priorität hängt am Scope:** portalgenau 20, netzweit 10
  (Abschnitt 4.3). Eine portalgenaue Belegung schlägt eine netzweite nicht von
  selbst; die Vorgabe sorgt dafür, dass der häufige Fall ohne Nachrechnen
  stimmt.
* **Konflikt und Format sind Hinweise, keine Sperren.** Zwei aktive Belegungen
  mit **gleicher** Priorität sind fast immer ein Versehen — dann entscheidet
  die größere `id`, also die Eingabereihenfolge. Ebenso ein Werbemittel, dessen
  Format der Anker nicht reserviert (Abschnitt 7, CLS 0,0442 in der Messung).
  Beides wird vor dem Speichern gemeldet und blockiert nicht: zulässig ist es,
  und der Altbestand (#15) muss speicherbar bleiben.
* **„Gespeichert, ausgespielt wird hier aber nichts."** Bleibt der Anker trotz
  aktiver Belegung leer, sagt das Panel den Grund: `ads_enabled` des Portals
  steht aus, der Zeitraum läuft nicht, das Werbemittel ist abgeschaltet oder
  eine andere Belegung hat die höhere Priorität. Ohne diesen Satz sucht man den
  Fehler im Formular.

Das Werbemittel wird im Panel inline angelegt (`createOptionForm`); die Felder
baut `ProviderConfigForm` aus dem Schema des Providers, geprüft wird über
`AdProvider::validateConfig()` beim Speichern von `ad_units`. Ein dritter
Provider (Abschnitt 5.3) bleibt damit eine Klasse plus eine Config-Zeile —
auch für dieses Panel.

Gepflegt wird **nur** hier; `App\Filament\Admin\Resources\AdPlacements`
(#10) bleibt die lesende Massen-Ansicht mit den Status-Aktionen.

---

## 7. Blade-Komponente

`<x-ad-slot key="home_after_services" />` (SUN-AD-004, Ticket #5). Der Name
kollidiert mit der alten Komponente `App\View\Components\AdSlot`, die über
`position` angesprochen wird. Lösung: die neue Komponente heißt
`<x-sun.ad-slot>` im Namensraum des Themes, solange beide Systeme parallel
laufen, und wird in SUN-AD-011 auf `<x-ad-slot>` umbenannt, wenn das
Altsystem fällt.

Umgesetzt in Ticket #5:

| Stelle | Aufgabe |
| --- | --- |
| `App\View\Components\Sun\AdSlot` | Katalog, Werbefreiheit, Auflösung je Gerät, Container |
| `resources/views/components/sun/ad-slot.blade.php` | Anker-Kommentar, Container, `<template>`, `@push('head')` |
| `App\Ads\Support\ListSlotHelper` | Listen-Anker (`*_in_feed_*`) im Loop |
| `App\Ads\Support\AdHeadRegistry` | Entdoppelung der `<head>`-Skripte je Anfrage (`scoped`) |
| `App\Ads\Support\AdEditorMode` | Schalter für den Editor-Modus; gesetzt wird er in #6 |
| `resources/js/ads.js` (`initSlotAds`) | Einsetzen beim Heranscrollen, `adsbygoogle.push()` |
| `.ad-slot` / `.ad-slot__area` in `themes/sun-v2/css/app.css` | Höhenreservierung, `max-width` für fremde Rahmen |
| `TocBuilder::splitAfterParagraphs()` | Schnitt für `guide_in_article_*` (Abschnitt 3.4) |

Aufgaben der Komponente, in dieser Reihenfolge:

1. Slot aus dem Katalog holen; unbekannter Schlüssel → Exception in `local`
   und `testing`, stilles Nichts in `production`.
2. Werbefreiheits-Gründe prüfen (Abschnitt 2, Ende) — außer im Editor-Modus.
   Dazu gehört das Premium-Merkmal `AdFree`: es wird über `:subject="$company"`
   übergeben und von der Komponente geprüft, **nicht** mehr mit
   `@unlesspremiumFeature` um jeden Block in der View.
3. Resolver fragen (Abschnitt 8) — getrennt für `mobile` und `desktop`.
4. Container mit `min-height` aus dem Katalog und
   `AdDevice::visibilityClass()` rendern — **immer**, wenn eine Belegung
   existiert, auch bevor das Werbemittel geladen ist und auch dann, wenn es
   noch auf die Einwilligung wartet (Abschnitt 9).
5. Unterhalb der Falte wird erst beim Heranscrollen eingesetzt. Anker mit
   `sort = 10` und alle `*_sidebar_top` gelten als oberhalb der Falte
   (`data-ad-eager`) und laden sofort.

**Der Anker steht immer im Markup**, auch ohne Belegung — als HTML-Kommentar
`<!-- ad-slot: key -->`. Der Editor-Modus (#6) findet daran jede Position, und
ein Kommentar hat keine Geometrie: ohne Belegung ist die Seite Byte für Byte
die von heute, abgesehen von dem Kommentar.

### 7.1 Zwei Container, ein `<template>`

Für einen Anker mit `devices: all` fragt die Komponente den Resolver zweimal —
mobil und Desktop können verschiedene Belegungen gewinnen (Abschnitt 8).
Gewinnt **dieselbe** Belegung (der Normalfall), entsteht **ein** Container
ohne Sichtbarkeitsklasse; nur bei verschiedenen Belegungen entstehen zwei,
einer mit `lg:hidden`, einer mit `hidden lg:block`. Eine Mindesthöhe `0` im
Katalog heißt „auf diesem Gerät gibt es den Anker nicht": so rendern die
`*_sidebar_*`-Anker mobil nichts und `company_after_header_card` nur mobil.

Das Fragment des Providers steht **nicht** direkt im Container, sondern in
einem `<template data-ad-fragment>`. Eingesetzt wird es von
`initSlotAds()` (`resources/js/ads.js`), sobald der Container sichtbar in den
Viewport kommt — IntersectionObserver mit 200 px Vorlauf. Das löst drei Dinge
mit einem Mechanismus:

* **Nachladen** unter der Falte: vorher lädt nichts.
* **Gerätetrennung**: ein `display:none`-Container schneidet den Observer ab,
  die mobile Fassung lädt also nie auf dem Desktop.
* **`adsbygoogle.push({})`** füllt immer den nächsten *unbelegten*
  `<ins>`-Block in Dokumentreihenfolge — es gibt keine API, den Block zu
  benennen. Weil nur der sichtbare Block überhaupt im DOM landet, kann der
  Aufruf nicht auf einem ausgeblendeten Block landen.

### 7.2 Höhenreservierung ohne Tailwind-Klasse

Die Mindesthöhen stehen im Katalog, nicht im Komponenten-Code — eine Klasse
wie `min-h-[280px]` müsste dafür zur Bauzeit im Quelltext stehen. Die
Komponente setzt darum zwei CSS-Variablen am Container
(`--ad-mh`, `--ad-mh-lg`), die Regel dazu steht einmal im Theme:

```css
.ad-slot__area { min-height: var(--ad-mh, 0px); }
@media (min-width: 64rem) { .ad-slot__area { min-height: var(--ad-mh-lg, var(--ad-mh, 0px)); } }
```

Die Reservierung gilt für den Anker, nicht für die Seite: setzt das
AdSense-Konto zusätzlich Auto Ads ein, kann die Seite als Ganzes die Grenze
„CLS < 0,1" reißen, ohne dass ein Anker gewachsen ist (Abschnitt 9.6, #46).

Reserviert wird auf der **Fläche**, nicht auf dem Rahmen: das Wort „Anzeige"
darf nicht von der Höhe der Anzeige abgehen. `iframe`, `ins` und `img` in der
Fläche bekommen `max-width: 100 %`, damit ein fremder Rahmen aus dem
HtmlProvider die Seite bei 360 px nicht quer laufen lässt (#18).

### 7.3 Listen-Anker

`App\Ads\Support\ListSlotHelper` beantwortet im Loop die Frage „gehört hinter
dieses Element ein Anker?" — und nur die. Ob dort etwas läuft, bleibt Sache
der Komponente, sonst fände der Editor die leeren Positionen nicht.

```blade
@php($feed = \App\Ads\Support\ListSlotHelper::make([
    3 => 'search_in_feed_3',
    8 => 'search_in_feed_8',
], $companies->count()))

@forelse($companies as $company)
  <x-sun.listing-card :company="$company" />
  @if($slot = $feed->keyAfter($loop->iteration))
    <x-sun.ad-slot :key="$slot" />
  @endif
@endforelse
```

`minFollowing` (Vorgabe 2) hält die Regel „nie kurz vor dem Listenende"
(Anker-Regel 2.6): `search_in_feed_8` erscheint erst ab 10 Treffern auf der
Seite. Die Kategorieseite des Ratgebers übergibt `1` und behält damit ihre
bestehende Bedingung „nur ab 6 Karten".

### 7.4 Gemessen (Ticket #5, 16.10.2026)

Protokoll mit Aufbau, Rohdaten und den 360-px-Aufnahmen:
`docs/messungen/werbeplaetze-cls-2026-10-16.md`.

Gemessen auf Portal 30 (`elektrikerportal.com`, Theme `sun-v2`) mit **allen
32 Ankern belegt** — 27 mit einem Sandbox-iframe des HtmlProviders in
Katalogformat, 5 mit AdSense. Je Seite und Zustand drei Lighthouse-Läufe
mobil; „vorher" ist derselbe Codestand ohne Belegungen.

| Seite | Anker | CLS vorher | CLS nachher (mobil) | CLS nachher (1280 px) |
| --- | --- | --- | --- | --- |
| Startseite | 6 | 0,000 | 0,000 | 0,0001 |
| Firmensuche | 6 | 0,000 | 0,000 | 0,0004 |
| Firmenprofil | 6 | 0,000 | 0,000 | 0,0001 |
| Stadtseite | 4 | 0,000 | 0,000 | 0,0003 |
| Ratgeber-Artikel | 5 | 0,000 | 0,000 | 0,0002 |
| Ratgeber-Übersicht | 2 | 0,000 | 0,000 | 0,0000 |

360-px-Aufnahmen aller sechs Seitentypen mit belegten Ankern:
`docs/messungen/werbeplaetze-360/`.

Bei 360 px läuft keine Seite quer — geprüft mit einem **728 × 90**-iframe in
den Banner-Ankern, also einem Werbemittel doppelt so breit wie der Viewport;
`.ad-slot__area iframe { max-width: 100 % }` holt es auf die Spaltenbreite.
`scrollWidth` allein genügt dafür nicht (`overflow-x: clip`, #18), die Seite
wurde zusätzlich wirklich gezogen.

**Nachtrag #32 (16.10.2026): `responsive` hält die Reservierung jetzt ein.**
Der Lauf oben hatte auf den Banner-Ankern feste Katalogformate. Mit einem
AdSense-Werbemittel im Format `responsive` auf `home_after_hero` (100 px
mobil) lag CLS bei **0,136** — AdSense wählte 250 px (Abschnitt 9.5). Seit #32
bekommt `responsive` die Höhe der Reservierung in den `style` und keine
Format-Angabe mehr; derselbe Lauf ergibt **CLS 0,0274** auf der Startseite und
**0,000** auf dem Firmenprofil, und in beiden Fällen ist kein Werbeplatz-Anker
mehr an einer Verschiebung beteiligt (der Rest ist der Google-CMP-Dialog
`div.fc-stacks`). Protokoll: `docs/messungen/ads-cls-responsive-2026-10-16.md`.

**Ein Befund, der #8 betrifft:** der erste Durchlauf lief versehentlich mit
einem 300 × 250-Werbemittel auf **jedem** Anker, also auch auf den
Banner-Ankern mit 90/100 px Reservierung. Die Firmensuche kam damit auf
**CLS 0,0442**, verschoben wurde die Ergebnisliste unter
`search_above_results`. Die Höhenreservierung hält das Layout also nur
ruhig, solange das Werbemittel ein Format hat, das der Anker zulässt — die
Formatprüfung beim Anlegen einer Belegung (#8) ist damit Teil des
CLS-Versprechens, nicht Komfort. Umgesetzt als Hinweis im Panel
(Abschnitt 6.4) — sperren würde den Altbestand (#15) unspeicherbar machen.

---

## 8. Caching

Der Resolver (`App\Ads\AdSlotResolver`, Ticket #4) löst **pro Seitentyp und
Tenant** auf, nicht pro Slot: eine Portal-Seite hat bis zu sechs Anker, und
sechs Abfragen auf die zentrale DB pro Aufruf sind nicht tragbar.

```
Schlüssel:  ads:v{catalogVersion}:{tenantId}:{pageType}
Wert:       ads_enabled, Publisher-ID, [slotKey][gerät] => Belegung (Arrays)
Dauer:      config('ad-slots.cache_ttl'), Vorgabe 1 Stunde
```

Die Karte ist je Anker **nach Gerät** geschlüsselt (`all`/`mobile`/`desktop`):
ein Anker mit `devices: all` kann mobil und auf dem Desktop von
verschiedenen Belegungen gewonnen werden, und die Komponente rendert beide
Varianten (#5). `ads_enabled` und die Publisher-ID liegen in derselben
Karte — sonst käme je Seite eine zweite Abfrage auf `ad_settings` hinzu.

Der Schlüssel `all` heißt **„läuft auf beiden Geräten"** und ist nur besetzt,
wenn der Katalog-Anker `devices: all` zulässt **und** die gewinnende Belegung
`device = all` hat (Ticket #20). Sonst bleibt er leer — also

* auf einem gerätegebundenen Anker (`company_after_header_card` ist
  `devices: mobile`, `company_sidebar_sticky` ist `devices: desktop`) immer,
* auf einem Anker mit `devices: all`, sobald eine gerätegebundene Belegung die
  höhere Priorität hat (`desktop` Prio 80 schlägt `all` Prio 10 → `all` leer,
  `desktop` belegt, `mobile` mit der netzweiten Belegung).

Wer mit `AdDevice::ALL` fragt — das ist der Vorgabewert von `resolve()`,
`resolution()`, `context()` und von `ads:resolve` —, bekommt dann **nichts**
statt einer Belegung, die nur für ein Gerät gebucht ist. Das ist Absicht:
ein leerer Anker ist billiger als eine Ausspielung außerhalb der gebuchten
Geräteklasse, die bei AdSense eine falsche Impression ist.

Richtig gefragt wird deshalb **je Gerät**: ein Anker mit `devices: all` wird
zweimal aufgelöst (`mobile` und `desktop`), und beide Varianten werden
gerendert — genau das tut die Komponente (#5) über
`AdSlotCatalog::deviceOf()` und die Mindesthöhen; gewinnt beidseitig dieselbe
Belegung, wird daraus ein Container ohne Sichtbarkeitsklasse. `ads:resolve`
ohne Geräteangabe benennt den Fall und nennt die je Gerät gewinnenden
Belegungen.

Eine Änderung an `build()` ändert die gespeicherte Form der Karte, nicht den
Cache-Schlüssel: danach `AdCache::forgetAll()` aufrufen oder
`config('ad-slots.version')` anheben.

Im Cache liegt die **Auflösung** (`App\Ads\Dto\AdResolution`: Placement-ID,
Werbemittel, Provider, Konfiguration, Gerät, Priorität), nie gerendertes HTML:
Einwilligung, Editor-Modus und Publisher-ID wechseln je Anfrage und dürfen
nicht eingefroren werden. Gerendert wird bei jedem Aufruf, das kostet keine
Abfrage.

Zu beachten:

* **Der Schlüssel enthält den Tenant.** Der Cache ist in diesem Projekt nicht
  mandantengetrennt; ohne Tenant im Schlüssel sehen Portale fremde Belegungen.
* **Der `file`-Store wechselt trotzdem je Tenant**
  (`App\Support\Tenancy\TenantUrlBootstrapper`). Ein im Tenant-Kontext geschriebener Schlüssel lässt
  sich aus dem Admin-Panel **nicht** mit `Cache::forget()` leeren — dafür
  `$tenant->run(fn () => Cache::forget($key))`, Muster `TenantText::forgetFor()`.
  Die Verwaltung muss nach jeder Änderung an `ad_units`, `ad_placements` oder
  dem Pivot für **jeden betroffenen Tenant** und **jeden Seitentyp** leeren.
  Bei `applies_to_all_tenants = true` sind das alle Portale — das gehört in einen Job,
  nicht in den Request.
* **`version` aus dem Katalog steht im Schlüssel.** Eine Katalog-Änderung
  entwertet alle Einträge, ohne dass irgendwo geleert werden muss.
* **Im Editor-Modus wird nicht gecacht**, weder gelesen noch geschrieben —
  sonst zeigt die Vorschau einen alten Stand.
* Zusätzlich hält der Resolver das Ergebnis **pro Request**: er ist `scoped`
  gebunden und merkt sich die Karte je `{tenantId}:{pageType}`. Bei
  `TenancyInitialized`/`TenancyEnded` fliegt sie raus, damit in Horizon oder
  `tenants:run` kein Portal die Karte des vorigen sieht.

Umgesetzt in #4:

| Stelle | Aufgabe |
| --- | --- |
| `App\Ads\Support\AdCache` | Schlüssel, Dauer, Leeren je Portal (zentral **und** im Portal-Kontext über `$tenant->run()`) |
| `App\Ads\Observers\AdCacheInvalidator` | hängt an `ad_units`, `ad_placements`, `ad_settings`; bis 5 Portale sofort im Request, darüber im Job |
| `App\Ads\Jobs\FlushAdCache` | leert viele oder alle Portale im Hintergrund |

Zwei Grenzen, die die Verwaltung (#8/#10) kennen muss:

1. **Der Pivot `ad_placement_tenant` löst kein Model-Ereignis aus.** Nach
   `tenants()->sync(...)` ist `AdCacheInvalidator::forPlacement($placement,
   $vorherigePortalIds)` aufzurufen — mit den Portalen **vor** und **nach**
   der Änderung, sonst behält ein abgewähltes Portal seine Anzeige.
2. **`deleting`, nicht `deleted`.** Beim Löschen eines Werbemittels räumt die
   Datenbank seine Belegungen per `ON DELETE CASCADE` weg; danach ist nicht
   mehr feststellbar, welche Portale betroffen waren.

Nachgewiesen: Belegung pausieren → nächster Seitenaufruf ohne Anzeige, ohne
`cache:clear`; wieder aktivieren → Anzeige wieder da (Ticket #4).

### 8.1 `ads:resolve` — nachsehen, was läuft

```
php artisan ads:resolve {portal} {slot} {device=all}
                        [--no-consent] [--editor] [--fresh]
```

`{portal}` ist ID, UUID oder Domain. Der Befehl läuft im Kontext des Portals
(`$tenant->run()`), damit Cache-Store und Zustell-URL dieselben sind wie im
echten Seitenaufruf, und zeigt Portal, Anker, Katalogversion, die gewonnene
Belegung mit Priorität, das gerenderte Fragment, die Head-Abhängigkeiten und
die Meta-Werte des Providers.

Bleibt der Anker leer, benennt er den Grund: Portal nicht freigegeben
(`ads_enabled`), keine aktive Belegung im Zeitraum für dieses Gerät, oder
Provider rendert nicht (AdSense ohne Publisher-ID oder im Editor-Modus;
eigenes HTML ohne Code oder Größe). Steht `--no-consent`, wird das Fragment
gezeigt und als zurückgehalten gekennzeichnet — so wie es im Browser im
`<template>` liegt. `--fresh` leert vorher den
Cache dieses Portals, `--editor` schaltet in den Editor-Modus (ohne Cache,
ohne AdSense).

Mit dem Vorgabewert `all` bleibt die Ausgabe auf gerätegebundenen Ankern leer
(siehe oben, Ticket #20); der Befehl weist dann darauf hin und nennt, was je
Gerät gewinnt. Für die Abnahme eines solchen Ankers ist `mobile` bzw.
`desktop` mitzugeben.

---

## 9. Einwilligung und CSP (SUN-AD-009, Ticket #9)

### 9.1 Die Regel

**Ohne Einwilligung in Marketing-Cookies geht kein Request an Google.** Der
reservierte Platz steht trotzdem schon im HTML — mit Katalog-Höhe und der
Zeile „Anzeige" —, damit nach der Einwilligung nichts springt.

Drei Zustände, nicht zwei. Der Unterschied entscheidet über das Layout:

| Zustand | Cookie `cookie_consent_marketing` | Platz | Werbemittel |
| --- | --- | --- | --- |
| `granted` | `1` | reserviert | lädt |
| `pending` | fehlt | reserviert | wartet im `<template>` |
| `denied` | `0` | klappt ein bzw. bleibt reserviert (`on_denied`) | lädt nie |

`pending` reserviert, weil die Einwilligung noch im selben Besuch kommen kann
und der Container dann nicht wachsen darf. `denied` ist eine Entscheidung —
dort gibt es nichts mehr, wofür Platz zu halten wäre, also klappt der
Container ein (`ad-slots.consent.on_denied`, Vorgabe `collapse`, umstellbar
über `ADS_CONSENT_ON_DENIED=reserve`).

Einstellungen: `config/ad-slots.php` → `consent`. Serverseitig liest sie
**eine** Stelle: `App\Ads\Support\AdConsent` (Cookie-Name, Zustand,
`on_denied`). Das Cookie ist Klartext — der Hinweis schreibt es im Browser —
und steht darum in der Ausnahmeliste von `EncryptCookies`
(`bootstrap/app.php`, #21). Ohne diese Ausnahme liefert
`request()->cookie()` immer `null`, und `null` sieht aus wie „nicht
eingewilligt": der Fehler tarnt sich als korrektes Verhalten
(`tests/Feature/Ads/ConsentCookieTest.php`).

### 9.2 Wer entscheidet was

```
Provider          requiresConsent($unit)      AdSense: immer true,
  ↓                                           eigenes HTML: config_json->third_party
  ↓                                           ('yes' = Vorgabe → true, #36)
RenderedAd        ->requiresConsent           wandert mit, auch durch den Resolver
  ↓
Komponente        data-ad-consent="marketing" Fragment bleibt im <template>,
<x-sun.ad-slot>   <template data-ad-head>     Loader bleibt im <template>
  ↓
resources/js/ads.js                           setzt beides ein, sobald
  ↑                                           consent-adapter.js zustimmt
resources/js/consent-adapter.js                'consent:marketing'
```

Der Provider rendert seit #9 **immer** — das Gate liegt in der Komponente und
im Browser, nicht im Provider. Nur so greift eine Einwilligung im laufenden
Besuch ohne Reload: das Markup muss schon da sein, nur inert. Ein `<template>`
ist genau das — der Browser lädt daraus nichts, auch keine `src`.

`resources/js/consent-adapter.js` ist die **einzige** Stelle, die das
Einwilligungs-Werkzeug der Portale kennt. Ein Wechsel (eigenes Werkzeug → CMP
eines Anbieters) betrifft diese Datei und sonst nichts; `ads.js` und
`analytics.js` fragen nur den Adapter. Gemeldet wird über `melde()` aus beiden
Hinweis-Fassungen (`resources/js/app.js` für default/starter,
`themes/sun-v2/js/modules/cookie-consent.js` für sun-v2); der Adapter liest
den Zustand danach aus den Cookies — nicht aus dem übergebenen Objekt, damit
Server und Browser dasselbe sagen.

**Auto Ads ziehen nicht nach.** `<x-sun.auto-ads>` rendert ohne Einwilligung
gar nicht und bleibt bis zum nächsten Seitenaufruf aus: Auto Ads platzieren
außerhalb jeder Reservierung, eine Einblendung nach dem Klick auf „Alle
akzeptieren" würde das Layout verschieben. Die Anker mit Reservierung ziehen
nach, Auto Ads nicht.

**Das Altsystem ist mit gesichert.** `resources/views/components/ad-slot.blade.php`
(Tabelle `ad_slots`, läuft bis #11 parallel) hielt sein AdSense-Markup vorher
gar nicht zurück. Es benutzt jetzt denselben Weg: ohne Einwilligung in
`<template data-ad-code>` plus `data-ad-consent`, bei `denied` + `collapse`
rendert die Komponente nicht (`App\View\Components\AdSlot::shouldRender()`).

**Fremdes Tracking-HTML aus der Verwaltung läuft denselben Weg (#28).**
`config('app.tracking_scripts')` — ein Pixel, ein Tag-Manager-Behälter, ein
Werbe-Schnipsel — ging vorher ungefragt heraus; das Nonce aus `CspNonce` löste
nur die CSP-Frage, nicht die Einwilligungs-Frage. Seit #28 liegt der Block in
einer eigenen Komponente:

```
config('app.tracking_scripts')            Textfeld in der Verwaltung
config('app.tracking_scripts_consent')    'marketing' (Vorgabe) | 'statistics'
  ↓
App\Analytics\TrackingScriptsConsent     granted | pending | denied
  ↓
components/tracking-scripts.blade.php     granted -> direkt ausgeben
                                          pending -> <template data-consent-script="…">
                                          denied  -> gar nichts ausgeben
  ↓
resources/js/consent-scripts.js           setzt die Vorlage ein, sobald
                                          consent-adapter.js zustimmt
```

Eingebunden in beiden `analytics.blade.php` (sun-v2 und die SaaSykit-Seiten),
die Kategorie ist konfigurierbar, weil nur der Mensch weiß, was er einträgt —
Vorgabe ist die strengere (`marketing`). Reserviert wird hier kein Platz:
Tracking-Schnipsel sind unsichtbar, es gibt nichts, was springen könnte.

### 9.3 CSP

Die CSP liegt an einer Stelle: `config/csp.php` +
`App\Http\Middleware\ContentSecurityPolicy` (docs/turnstile.md 14). Die
Google-Ad-Hosts stehen dort als Liste je Direktive, nicht als Literal in einer
View:

| Direktive | Hosts (Stand #9) |
| --- | --- |
| `script-src` | `pagead2.googlesyndication.com`, `tpc.googlesyndication.com`, `partner.googleadservices.com`, `adservice.google.com`, `googleads.g.doubleclick.net`, `securepubads.g.doubleclick.net`, `fundingchoicesmessages.google.com`, `ep2.adtrafficquality.google` |
| `frame-src` | `googleads.g.doubleclick.net`, `tpc.googlesyndication.com`, `pagead2.googlesyndication.com`, `ep2.adtrafficquality.google`, `fundingchoicesmessages.google.com`, `www.google.com` |
| `connect-src` | `pagead2.googlesyndication.com`, `googleads.g.doubleclick.net`, `securepubads.g.doubleclick.net`, `tpc.googlesyndication.com`, `fundingchoicesmessages.google.com`, `ep1.adtrafficquality.google`, `ep2.adtrafficquality.google`, `csi.gstatic.com`, `www.google.com` |
| `img-src` | über `https:` abgedeckt — eine eigene Hostliste wäre hier nur Pflege ohne Gewinn |

Google selbst empfiehlt inzwischen **keine** Hostliste, sondern Nonce plus
`'strict-dynamic'`. Das setzt voraus, dass jedes Skript der Seite ein Nonce
trägt — auch die aus Vite —, und das ist hier nicht so. Solange bleibt es bei
Hosts; was fehlt, zeigt die Sammelstelle aus #51:

```bash
php artisan csp:berichte          # Exit 70 = Befunde
```

Darum gilt für den Rollout (#11): **`CSP_MODE=report` in die `.env`, bevor der
Config-Cache neu gebaut wird.** Die Codevorgabe ist `enforce`; ein
`config:cache` auf der Produktion schaltet die Policy sonst in einem Schlag
auf allen Portalen durchsetzend.

Weiter gilt unverändert:

* `script-src` hat kein `'unsafe-inline'`. `adsbygoogle.push()` läuft als
  externes Modul an einem `data`-Attribut, nicht als Inline-Block.
* Eigenes HTML bekommt **kein** Nonce — es läuft im Sandbox-iframe
  (Abschnitt 5.2). Das ist der Unterschied zum Altsystem, dessen Schnipsel aus
  der Verwaltung das Nonce des Requests bekommen
  (`App\Services\Security\CspNonce`).

### 9.4 Datenschutzerklärung

Der Abschnitt „Werbung mit Google AdSense" liegt **einmal** im Repository:
`resources/views/legal/partials/adsense-privacy.blade.php`. Er wird nicht in
die 23 Tenant-DBs kopiert — ein Rechtstext, der die Ausspielung beschreibt,
muss sich mit der Ausspielung ändern können, und das kann er nur an einer
Stelle.

Angehängt wird er beim Rendern, vor der Theme-Schicht:
`App\Ads\Support\AdSensePrivacySection::appendTo()`, aufgerufen in
`App\Http\Controllers\Portal\StaticPageController::datenschutz()`. Damit

* steht er in allen drei Themes ohne eigenen Einbau,
* läuft er in sun-v2 durch dieselbe Abschnittszerlegung wie der gepflegte Text
  (eigene Sprungmarke, Eintrag im Inhaltsverzeichnis),
* und werden seine Platzhalter (`[PORTAL_NAME]`) wie im übrigen Rechtstext von
  `TenantBrandingService::resolveLegalPlaceholders()` aufgelöst.

Der Abschnitt hat seit #35 **zwei Teile**, die unabhängig voneinander
erscheinen (`AdSensePrivacySection::sections()`):

| Teil | erscheint, wenn | Wortlaut freigegeben |
| --- | --- | --- |
| „Werbung mit Google AdSense" (h2) | `ads_enabled` **und** wirksame Publisher-ID (`adsenseActive()`) und der gepflegte Text enthält „AdSense" nicht selbst | #26 |
| „Weitere Werbemittel" (h3, ohne AdSense-Teil h2) | `ads_enabled` **und** eine lieferbare HTML-Belegung, deren Werbemittel einwilligungspflichtig ist (`htmlActive()`) und der gepflegte Text enthält „Weitere Werbemittel" nicht selbst | #35 |

Beide Wortlaute sind mit Datenschutz-Fokus durchgesehen (Rechtsstand
16.10.2026). Änderungen daran nur mit erneuter Durchsicht.

Die Verdrängung durch den gepflegten Text greift je Teil: dass ein Portal
AdSense selbst beschreibt, sagt nichts darüber, dass es auch Werbemittel
Dritter einbindet — der HTML-Teil wird deshalb nur von seiner **eigenen**
Überschrift verdrängt. Spielt ein Portal nur eigene Werbemittel aus, steht
„Weitere Werbemittel" als h2 (eigene Sprungmarke in sun-v2), und der Satz zum
Widerruf verweist nicht nach oben, sondern nennt den Weg selbst.

`active()` ist seit #35 die Oder-Verknüpfung beider Teile — die drei
Cookie-Hinweise (`themes/*/views/partials/.../cookie-consent.blade.php`)
fragen damit, ob die Kategorie „Marketing" den Werbe-Text bekommt.
`htmlActive()` fragt **eine** Abfrage auf `ad_placements` und liegt unter
`AdCache::htmlConsentKey()` im Cache (eine Stunde, von
`AdCache::forgetTenant()` mit vergessen) — die Methode hängt am Cookie-Hinweis
jeder Seite.

#### Eigenes HTML: Absatz liegt bereit, hängt an #36 (#29, #35)

Der zweite Provider
(`App\Ads\Providers\HtmlProvider`, Abschnitt 5.2) liefert beliebiges Markup
aus, und die Sandbox trägt `allow-scripts`: sie hält das Werbemittel vom
Portal-Origin fern, **nicht** vom Netz. Ein solches Werbemittel kann Requests
an beliebige Dritte stellen. Der Absatz „Weitere Werbemittel" beschreibt
diesen Fall seit #35, und `AdSensePrivacySection` löst ihn nicht mehr über die
Publisher-ID aus — ein Portal mit ausschließlich eigenen Werbemitteln bekommt
seinen Werbe-Abschnitt.

Stand 16.10.2026 ist das **nicht akut**: auf der Produktion gibt es die
zentralen Tabellen `ad_units`/`ad_placements` noch nicht (nachgezählt über
`php artisan db:show --database=central`), der übernommene Altbestand (#15)
ist durchweg AdSense. Lokal steht genau ein Demo-Werbemittel mit
`provider = html`.

Ausgespielt wird der Absatz aber nur für Werbemittel, die wirklich eine
Einwilligung brauchen — er sagt „erst nach Einwilligung" zu, und ein falscher
Satz in der Datenschutzerklärung ist schlimmer als ein fehlender. Deshalb
hängt `htmlActive()` an der Einwilligungspflicht des Werbemittels und nicht
an „irgendeiner HTML-Belegung".

**Was die Einwilligungspflicht entscheidet (#36).** `requiresConsent()` des
HtmlProviders lieferte bis #36 hart `false`, Begründung: „Sandbox-iframe ohne
Netzzugriff auf Dritte". Das war falsch — die Sandbox trennt Origins, nicht
Netzwerkziele (Abschnitt 5.2 Regel 2). Seit #36 entscheidet der Schalter
`config_json->third_party` je Werbemittel:

| `third_party` | `requiresConsent()` | Rahmen | Absatz „Weitere Werbemittel" |
|---|---|---|---|
| `yes` (Vorgabe, auch leer) | `true` | lädt erst nach der Einwilligung | erscheint |
| `no` | `false` | lädt sofort | erscheint nicht |

Technik und Rechtstext hängen damit an **einem** Wert: `htmlActive()` fragt
denselben Provider, der das Gate setzt. Ein Eigenbanner ohne Drittbezug läuft
weiter vor der Einwilligung, ein Drittanbieter-Tag nicht. Lokal nachgespielt
(#35: Probe mit erzwungenem `requiresConsent() === true` → Portal 27
`html=true`, mit dem Stand vor #36 `html=false` auf allen 23 Portalen — das
Demo-Werbemittel hat kein `third_party` und ist seit #36 von selbst
einwilligungspflichtig).

Dazu die Auslieferungsregel am Formular:

> Lädt ein Werbemittel mit `provider = html` Skripte oder Inhalte Dritter,
> muss der Schalter „Lädt dieses Werbemittel Inhalte Dritter?" auf „Ja"
> stehen — dann lädt der Rahmen erst nach der Einwilligung, und der Absatz
> in der Datenschutzerklärung erscheint automatisch.

Die Regel steht nicht nur hier, sondern im Panel an der Stelle, an der die Art
gewählt wird: `App\Ads\Forms\ProviderConfigForm::htmlLegalRule()` hängt sie an
den Hinweis über den HTML-Feldern — sichtbar in der Werbemittel-Resource (#10)
und beim Inline-Anlegen im Konfigurationspanel (#8).

Der Absatz selbst steht im Partial (`$showHtml`), der AdSense-Wortlaut aus #26
ist dabei unverändert geblieben. Was aus dem Vorschlag in #35 angepasst wurde:
der Einleitungssatz nennt AdSense nur, wenn der AdSense-Teil daneben steht, und
der Satz zum Widerruf verweist nur dann „wie oben beschrieben".

**Cookie-Tabelle (#30).** Der Abschnitt „8.1 Technisch notwendige Cookies" in
`App\Constants\TenantLegalDefaults::DATENSCHUTZ` führte ein Cookie
`cookie_consent`; gesetzt werden tatsächlich vier
(`cookie_consent_given`, `cookie_consent_statistics`,
`cookie_consent_marketing` und — nur bei gesperrtem `localStorage` —
`cookie_consent_preferences`, siehe
`themes/sun-v2/js/modules/cookie-consent.js`). Die Tabelle führt sie als einen
Eintrag mit dem Namensmuster `cookie_consent_*` auf.

Bestandsportale pflegen ihren Text selbst
(`branding.datenschutz`), deshalb der Nachzug nur über
`php artisan tenants:datenschutz:cookie-names-backfill` (Trockenlauf ist
Vorgabe, Schreiben mit `--write`). Das Kommando fasst **eine** Tabellenzeile an
und nur dann, wenn Zweck und Dauer noch wortgleich dem Standardtext
entsprechen; abweichend gepflegte Zeilen werden gemeldet, nicht überschrieben.
Lokal: 23 × „Cookie-Namen korrigiert" im Trockenlauf.

AdSense-Cookies stehen bewusst **nicht** namentlich in der Tabelle (#26) —
Google gibt Namen und Laufzeiten nicht stabil an; der AdSense-Abschnitt
beschreibt sie dem Zweck nach.

### 9.5 Nachmessen (Netzwerk-Mitschnitt vor/nach Einwilligung)

`scripts/csp-messung.mjs` kann seit #9 Cookies vor dem ersten Request setzen —
damit ist der Mitschnitt in beiden Richtungen ein Befehl. Vorbereitung wie in
`docs/messungen/csp-browser-messung-*.md`:

```bash
npm run build                                   # public/build/ ist nicht versioniert
APP_ENV=messung php artisan serve --port=8123    # .env.messung mit CSP_MODE=report
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"   --headless=new --remote-debugging-port=9222   --host-resolver-rules="MAP elektrikerportal.com 127.0.0.1:8123"
```

Seitenliste (`/tmp/seiten-einwilligung.json`):

```json
[
  { "name": "Startseite ohne Einwilligung", "url": "http://elektrikerportal.com/" },
  { "name": "Startseite mit Einwilligung", "url": "http://elektrikerportal.com/",
    "cookies": { "cookie_consent_given": "1", "cookie_consent_marketing": "1" } },
  { "name": "Einwilligung im Besuch", "url": "http://elektrikerportal.com/",
    "klick": "[data-cookie-action=\"all\"]" },
  { "name": "Abgelehnt", "url": "http://elektrikerportal.com/",
    "cookies": { "cookie_consent_given": "1", "cookie_consent_marketing": "0" } }
]
```

```bash
node scripts/csp-messung.mjs --seiten=/tmp/seiten-einwilligung.json   --aus=docs/messungen/ads-einwilligung-mitschnitt.json
```

Zu erwarten ist je Seite:

| Seite | `fremdeHosts` | `pruefung` |
| --- | --- | --- |
| ohne Einwilligung | **kein** `*.googlesyndication.com`, `*.doubleclick.net`, `*.adtrafficquality.google` | `wartendeAnker > 0`, `offeneFragmente > 0`, `offeneHeadVorlagen > 0`, `adsbygoogleGeladen false` |
| mit Einwilligung | `pagead2.googlesyndication.com` u. a. | `adsbygoogleGeladen true`, `eingesetzteAnzeigen > 0`, `offeneFragmente 0` |
| Einwilligung im Besuch | wie „mit Einwilligung", aber **erst nach** dem Klick | `offeneHeadVorlagen 0`, `eingeklappt 0` |
| abgelehnt | keine Google-Hosts | `eingeklappt > 0` (bei `on_denied=collapse` rendert das Altsystem gar nicht) |

Dazu gehört ein CLS-Wert nach der Einwilligung im laufenden Besuch (< 0,1) —
gemessen wie in Abschnitt 7.4, Skript `scripts/ads-einwilligung-cls.mjs`.

Zwei Dinge gehören zum Aufbau, sonst misst der Lauf etwas anderes als sein
Name sagt (#25):

* **Je Seite Cookies *und* `localStorage` leeren.** Der Cookie-Hinweis führt
  die Entscheidung in `localStorage` und schreibt die drei Cookies beim
  nächsten Seitenaufbau daraus neu — ein Fall „ohne Einwilligung" nach einem
  Fall mit Einwilligung ist sonst stillschweigend eingewilligt. Beide Skripte
  machen das inzwischen selbst (`Storage.clearDataForOrigin`).
* **Schrittweise scrollen, nicht springen.** Ein Sprung an das Seitenende
  lässt die Anker in der Mitte nie den Viewport schneiden; ihr Werbemittel
  bleibt im `<template>`.

Einen fünften Fall gibt es seit #33: **Entscheidung in `localStorage`, Cookies
leer** (Cookie nach 365 Tagen abgelaufen oder vom Browser geräumt). Der Server
rendert den Platzhalter, der Cookie-Hinweis zieht die Cookies nach und meldet
die Einwilligung — ohne Klick, denn der Hinweis erscheint nicht. Zu messen mit

```bash
node scripts/ads-einwilligung-cls.mjs --url=http://elektrikerportal.test/ --vorentschieden=alles
```

Erwartet wird `offeneFragmenteAmEnde 0`, `offeneHeadVorlagenAmEnde 0`,
`wartendeAnker 0` — also Ausspielung im **selben** Seitenaufruf. Vor #33 blieb
hier alles im `<template>` liegen: `initCookieConsent()` meldete, bevor
`initAds()` seinen Lauscher registriert hatte. `melde()` in
`resources/js/consent-adapter.js` merkt die Meldung jetzt, und
`beiEntscheidung(..., { sofort: false })` holt eine verpasste Meldung nach.

### Gemessen am 16.10.2026 (Ticket #25)

Protokoll mit Aufbau, Abweichungen und Begründungen:
`docs/messungen/ads-einwilligung-2026-10-16.md`. Rohdaten:
`docs/messungen/ads-einwilligung-mitschnitt.json`,
`ads-einwilligung-cls.json`, `ads-einwilligung-cls-festes-format.json`.

Portal 30 (`elektrikerportal.com`, Theme `sun-v2`, Publisher-ID
`ca-pub-1901800422608466`), Chrome 155 headless, `CSP_MODE=report`. Belegt:
Startseite mit einem AdSense- und einem HtmlProvider-Werbemittel, Firmenprofil
mit vier AdSense-Werbemitteln aus der Übernahme (#15). Vier Fälle je Seite.

| Fall | `fremdeHosts` (Ist) | `pruefung` (Ist) |
| --- | --- | --- |
| ohne Einwilligung | **keiner** — nur `elektrikerportal.com`, kein Google-Host, auch kein GTM/GA | Start: `wartendeAnker 1`, `offeneFragmente 1`, `offeneHeadVorlagen 1`, `adsbygoogleGeladen false` · Profil: `wartendeAnker 4`, `offeneFragmente 4`, `offeneHeadVorlagen 1` |
| mit Einwilligung | `pagead2.googlesyndication.com`, `fundingchoicesmessages.google.com`, `fonts.googleapis.com`, `fonts.gstatic.com` | `adsbygoogleGeladen true`, `eingesetzteAnzeigen 2` (Start) bzw. `4` (Profil), `offeneHeadVorlagen 0` |
| Einwilligung im Besuch | dieselben, **erst nach** dem Klick | `offeneHeadVorlagen 0`, `eingeklappt 0`, `eingesetzteAnzeigen 2` |
| abgelehnt | **keiner** | `eingeklappt 1` (Start) bzw. `4` (Profil), `offeneHeadVorlagen 1`, `adsbygoogleGeladen false` |

**CSP: kein Verstoß** in `script-src`, `frame-src`, `connect-src` oder
`img-src` — über alle acht Läufe, in keiner der drei Quellen. An
`config/csp.php` ist nichts nachzutragen. Die einzigen Meldungen sind
`font-src http://fonts.gstatic.com/...` aus dem Google-Einwilligungscode und
ein Artefakt des lokalen `http`-Aufbaus: die Policy erlaubt
`https://fonts.gstatic.com`, auf den Portalen läuft derselbe Request über
`https`. `php artisan csp:berichte --portal=elektrikerportal.com` endet mit
Exit 0; die Einträge ohne Portal-Filter stammen aus der Safari-Sichtprüfung
vom 14.–16.10. auf den Herd-Domains.

**CLS nach der Einwilligung im laufenden Besuch — ein Befund.** Vor dem Klick
verschiebt sich nichts (0,000, die Reservierung steht). Nach dem Klick
entscheidet das Format des Werbemittels:

| Anker | Format | Reservierung | CLS nach Klick (#25) | CLS nach Klick (nach #32) |
| --- | --- | --- | --- | --- |
| `home_after_hero` | AdSense `responsive` | 100 px | **0,136** | **0,027** |
| `company_after_header_card` | AdSense `responsive` | 280 px | 0,024 | **0,000** |
| `home_after_hero` | AdSense `leaderboard_728x90` | 100 px | 0,000 | 0,000 |

Bis #32 bekam `responsive` `data-ad-format="auto"` und keine Höhe im `style` —
AdSense bestimmte sie selbst (hier 250 px) und sprengte die Reservierung eines
Banner-Ankers. Mit festem Katalogformat blieb der Container auf seiner Höhe und
CLS auf 0,000.

**Gelöst in #32 (Weg 2 des Tickets):** `responsive` bekommt die Höhe der
Reservierung in den `style` und keine Format-Angabe (Abschnitt 5.1) — der Anker
gibt die Höhe vor. Nachgemessen am 16.10.2026 mit demselben Aufbau und
demselben Werbemittel (`Demo AdSense responsiv`, Anzeigenblock dieses Laufs):
`home_after_hero` bleibt vor und nach dem Klick bei 130 px Containerhöhe,
`company_after_header_card` bei 310 px; die verbleibenden 0,027 auf der
Startseite stammen vom Google-CMP-Dialog (`div.fc-stacks`, `div.fc-footer`),
kein Anker ist an einer Verschiebung beteiligt. `responsive` bleibt damit auf
jedem Anker wählbar, ohne die Zusage „CLS < 0,1" zu brechen. Protokoll:
`docs/messungen/ads-cls-responsive-2026-10-16.md`, Rohdaten
`docs/messungen/ads-cls-responsive-begrenzt-2026-10-16.json` und
`…-profil-2026-10-16.json`.

Der Messwert ist nur innerhalb des sichtbaren Bereichs etwas wert: CLS bewertet
nur ihn, ein Nachzug weit unter dem Falz erreicht die Metrik nicht. Das Skript
holt den wartenden Anker darum vor dem Klick in den Blick.

### 9.6 Auto Ads auf Kontoseite — die CLS-Zusage gilt für die Anker (#46)

**Befund (Pilot #31 auf `bodenlegerfinden.com`, 16.10.2026).** Auf dem Konto
hinter der Publisher-ID `ca-pub-2563111817853634` sind **Auto Ads auf
Kontoseite** aktiv. Sobald irgendein belegter Anker nach der Einwilligung den
Loader `adsbygoogle.js?client=ca-pub-…` auf die Seite bringt, setzt Google
zusätzlich **eigene, nicht reservierte** Anzeigen ein. Ohne Anker kommt kein
Loader auf die Seite — darum hatten `/firmen` und der Ratgeber-Artikel vor den
Pilot-Belegungen 0 Anzeigen und keinen Loader.

Der Schalter `ad_settings.adsense_auto_ads` (#18) steuert das **nicht**. Er
schaltet allein unseren eigenen Auto-Ads-Loader, also das `<meta
name="adsense-auto-client">` und den `enable_page_level_ads`-Push aus
`resources/js/ads.js`. Auf `bodenlegerfinden.com` stand er während des Pilots
auf `false`, und die unreservierten Anzeigen kamen trotzdem.

**Geltungsbereich der Zusage.** Die Zusage des Moduls lautet: *der Anker gibt
die Höhe vor* (Abschnitt 7.2, 5.1, #32). Sie gilt für die Anker des Katalogs
und ist dort gemessen — nicht für die Seite als Ganzes. Mit aktiven Auto Ads
kann die **Seite** die Grenze „CLS < 0,1" aus `docs/ad-slots-golive.md` §3.3
reißen, obwohl kein Anker gewachsen ist. Das Modul kann das nicht abfangen:
die Auto Ads hängen am Loader, und den braucht jeder belegte Anker.

**Messung mit aktiven Auto Ads — eigene Zeile.** Getrennt von den
Anker-Messungen in 7.4, 9.5 und 3.2b geführt, weil hier nicht das Modul
gemessen ist, sondern die Seite mit fremden Einschüben:

| Datum | Portal / Seite | Breite | Anzeigen auf der Seite | davon reserviert | CLS Anker-Anteil | CLS gesamt |
| --- | --- | --- | --- | --- | --- | --- |
| 16.10.2026 | 27 `/firmen` (search), Ausreißerlauf | 360 px | **6** | 2 | 0,0339 | **0,1872** (Anker-Fenster 0,1298) |
| 16.10.2026 | 27 `/firmen`, 3 Wiederholungen | 360 px | 2 bis 6 | 2 | 0,0000 | 0,0000 |
| 16.10.2026 | 27 `/12-parkett-loni` (company) | 360 px | 4 | 3 | 0,0000 | 0,0000 |
| 16.10.2026 | 27 `/ratgeber/…` (guide) | 360 px | 2 | 1 | 0,0000 | 0,0000 |

Rohdaten: `docs/messungen/werbeplaetze-golive/pilot-bodenlegerfinden-com-nachher-mobil-2026-10-16.json`,
`…-suche-wiederholungen-2026-10-16.json`; Auswertung
`docs/messungen/werbeplaetze-golive/pilot-bodenlegerfinden-2026-10-16.md` §4.1.
Der Ausreißer ist nicht reproduzierbar, und die größten Einzelverschiebungen
darin haben als Quelle „(entfernt)" — den verschwindenden Google-CMP-Dialog.
Die Zeile bleibt trotzdem stehen: sie ist der einzige Lauf, in dem vier
unreservierte Anzeigen zusätzlich auf der Seite standen, und damit der Beleg,
dass die Seite die Grenze reißen **kann**.

**Im Code ist es nicht lösbar — geprüft.** Es gibt keinen Parameter am
Loader-Aufruf und kein Meta-Tag, mit dem eine Seite die Auto Ads des Kontos
ablehnt:

* `enable_page_level_ads` ist die Gegenrichtung. Der Push schaltet Auto Ads
  von der Seite aus **ein**; ihn weglassen oder auf `false` setzen nimmt dem
  Konto nichts. Genau das ist auf 27 der Fall: kein Push, trotzdem Auto Ads.
* `overlays: { bottom: false }` in `resources/js/ads.js` wirkt nur auf unseren
  eigenen Push, nicht auf die Kontoeinstellung.
* Der Loader kennt neben `client` keinen Schalter für die Formatauswahl. Auto
  Ads werden allein im Konto konfiguriert, je Site: Formate einzeln an/aus
  (In-Page-Einschübe, In-Article, In-Feed, Anchor, Vignette), dazu
  URL-Ausschlüsse je Seite und Bereichsausschlüsse über CSS-Selektoren.
  Quellen: [Auto ads settings](https://support.google.com/adsense/answer/9305577),
  [Exclude specific pages](https://support.google.com/adsense/answer/9262311),
  [Exclude areas](https://support.google.com/adsense/answer/12626543).

**Entschieden: Auto Ads bleiben an** (Enes, 16.10.2026, Ticket #51; #50 ist
als gegenstandslos geschlossen). Im AdSense-Konto wird **nichts** abgeschaltet
und nichts auf Formate begrenzt — auf keiner Site, unabhängig vom Befund oben.
Das ist eine Ertragsentscheidung und keine offene Frage; wer sie wieder
aufmacht, braucht einen neuen Beschluss von Enes, nicht dieses Dokument.

Daraus folgt für das Modul:

* **Die Zusage „der Anker gibt die Höhe vor" gilt für die Anker** und ist dort
  gemessen (7.2, 9.5, #32). Die Grenze „CLS < 0,1" aus
  `docs/ad-slots-golive.md` §3.3 ist damit dauerhaft auf den **Anker-Anteil**
  zu lesen.
* **Der Seitenwert wird trotzdem weiter gemessen und getrennt geführt** — die
  Tabelle oben ist dafür die Stelle. Ein Seitenwert über 0,1 ist ein Befund,
  der an Enes gemeldet wird; er ist **kein** Abbruchgrund, kein Anlass, einen
  Anker zu entfernen, und keine Aufforderung, im Konto nachzustellen.
* **Ein Layoutsprung durch Auto Ads ist damit möglich und von uns nicht
  messbar verhinderbar.** Das ist bekannt und in Kauf genommen.

**Falls später doch nachgesteuert werden soll** — nur auf Entscheidung von
Enes, nicht als Auflage dieses Dokuments: im Konto je Site Formate einzeln
abschalten (besonders die In-Page-Einschübe zwischen bestehende Elemente),
Seiten per URL ausschließen, oder einen Bereichsausschluss über den stabilen
Selektor `div.ad-slot` setzen (Klasse aus
`resources/views/components/sun/ad-slot.blade.php`) — dann landen keine Auto
Ads **innerhalb** einer Reservierung. Google braucht bis zu einer Stunde, bis
eine geänderte Einstellung auf der Site greift. Im Code gibt es diesen Hebel
nicht (siehe oben).

---

## 9a. Verwaltung im Admin-Panel (SUN-AD-010, Ticket #10)

Drei Stellen im Panel, alle nur für Betreiber
(`App\Ads\Support\AdsAdminAccess`: Administrator mit dem Recht
`update settings` — dieselbe Hürde wie beim Bot-Schutz).

| Stelle | Klasse | Zweck |
| --- | --- | --- |
| Tenancy › Werbemittel | `App\Filament\Admin\Resources\AdUnits\AdUnitResource` | `ad_units` anlegen, bearbeiten, archivieren |
| Tenancy › Alle Placements | `App\Filament\Admin\Resources\AdPlacements\AdPlacementResource` | `ad_placements` filtern und in Masse umstellen |
| Einstellungen › Werbung | `App\Filament\Admin\Pages\AdSettings` | `ad_settings` je Portal |

**Provider-abhängiges Formular.** Die Felder von `config_json` kommen aus
`AdProvider::configSchema()` und werden in einer Section gebaut, deren
`schema`-Closure am gewählten Provider hängt. Alle Provider gleichzeitig zu
rendern und wegzuschalten geht nicht: `format` hat jeder, der Statuspfad wäre
doppelt belegt. Ein dritter Provider (Abschnitt 5.3) braucht damit keine
Änderung an der Resource.

**Vorschau.** AdSense wird im Panel **nie** geladen — eine Impression auf einer
Verwaltungsseite ist bei Google ein ungültiger Vorfall (Abschnitt 6); dort
steht ein Platzhalter mit Anzeigenblock-ID und Format. Eigenes HTML läuft in
einem `<iframe sandbox>` über die eigene Route
`App\Ads\Http\Controllers\AdUnitPreviewController` (`/werbemittel/{adUnit}/vorschau`,
Routenname `admin.ads.unit-preview`). Getrennt von der Portal-Route
`portal.ads.html`, weil die nur auf Portal-Domains läuft, öffentlich ist und
nur aktive Werbemittel ausliefert — die Vorschau zeigt auch Entwürfe und
Archiviertes. `srcdoc` bleibt auch hier ausgeschlossen (Abschnitt 5.2, Regel 3).

**Archivieren statt löschen.** `ad_placements.ad_unit_id` hängt per
`ON DELETE CASCADE` am Werbemittel: Löschen nimmt jede Belegung mit, auch die
laufende. Deshalb

* Löschen nur, solange **keine** Belegung daran hängt (`AdUnit::isDeletable()`),
* sonst `archived_at` setzen (Migration `2026_10_19_000006`). `is_active` fällt
  dabei mit — das stellt das Model zu, nicht die Oberfläche. Ein archiviertes
  Werbemittel ist in keiner neuen Belegung wählbar
  (`AdUnit::scopeSelectable()`), und `AdPlacement` weist eine neue Zuordnung ab;
  eine bestehende Belegung lässt sich weiter pausieren.

`archived_at` statt `is_active` oder `SoftDeletes`: `is_active` ist der Not-Aus
für ein laufendes Werbemittel, `deleted_at` würde die Zeile aus jeder
Standardabfrage nehmen — auch aus der, mit der der Resolver das Werbemittel
einer bestehenden Belegung lädt.

**Massenänderung.** Die Placement-Tabelle ist lesend, mit einer Ausnahme: den
Statusübergängen aus `AdPlacementStatus::canTransitionTo()`, einzeln und als
Bulk-Aktion (Pausieren/Aktivieren). Abgelaufene Belegungen bleiben außen vor —
sie brauchen einen neuen Zeitraum, und den setzt das Konfigurationspanel (#8).
Jede Zeile wird einzeln gespeichert, damit der Observer greift; ein `update()`
auf dem Query feuert keine Model-Ereignisse und ließe Portal-Karten im Cache
stehen.

**Publisher-ID.** Gespeichert wird die Form mit Präfix (`ca-pub-` + 16
Ziffern) — genau sie geht als `data-ad-client` in den Anzeigenblock. Eingeben
darf man beide Schreibweisen, `App\Ads\Support\AdSensePublisherId` normalisiert
und prüft. Der Altbestand wurde mit dem weicheren Muster `\d{10,20}`
übernommen (11.1); eine abweichende Länge fällt beim nächsten Speichern auf.

### 9a.1 `ads.txt` je Portal-Domain

Route `portal.ads-txt` in `routes/tenant.php`, Controller
`App\Http\Controllers\AdsTxtController`, Inhalt aus
`App\Ads\Support\AdsTxtBuilder`. Sie steht **oben** in der Gruppe bei
`robots.txt` und `llms.txt` — die Catch-all-Routen am Ende
(`/{citySlug}/{companySlug}`, `/{companySlug}`) würden den Aufruf sonst als
Stadtseite lesen.

Aufbau, in dieser Reihenfolge:

1. `google.com, pub-…, DIRECT, f08c47fec0942fa0` aus der **wirksamen**
   Publisher-ID (eigene, sonst `config('ad-slots.adsense.publisher_id')`) — nur
   wenn das Portal freigegeben ist,
2. die Zeilen aus `ad_settings.ads_txt_extra` (ohne Leerzeilen und
   Kommentare).

Doppelte Zeilen fallen weg: der Altbestand bringt die Google-Zeile oft in
`ads_txt_extra` mit. Verglichen wird dafür nicht die Zeichenkette, sondern die
Autorisierung — `GOOGLE.COM`, `ca-pub-…` und ein Kommentar dahinter sind
dieselbe Zeile (#41). Bleibt nichts übrig, antwortet die Route **404** — eine
leere `ads.txt` liest Google als „kein autorisierter Verkäufer" und schaltet
damit Anzeigen ab, eine fehlende Datei ist der Zustand wie vor dem Umstieg.

`ads_enabled = false` nimmt jede Autorisierung der **wirksamen** Publisher-ID:
die gebaute Google-Zeile aus Schritt 1 und — seit #41 — auch eine gleichlautende
Zeile aus `ads_txt_extra`. Grund: `ads:legacy-takeover` hat den kompletten
`ads_txt_content` der Tenant-DB nach `ads_txt_extra` übernommen, und darin steht
bei 13 Portalen genau die eigene Google-Zeile; ohne den Filter blieb die
Autorisierung für Google stehen, obwohl der Hauptschalter aus war — die
Notbremse aus `docs/ad-slots-golive.md` §5 hätte nur halb gewirkt. Alle anderen
Zusatzzeilen bleiben, dort stehen fremde Vermarkter; eine `google.com`-Zeile mit
einer **fremden** Kennung zählt dazu. Verglichen wird in
`AdSensePublisherId::isGoogleLineFor()` feldweise und ohne Groß-/Kleinschreibung,
Kommentar hinter `#` abgeschnitten, `pub-…` und `ca-pub-…` gleichwertig — der
Altbestand schreibt dieselbe Zeile in jeder Variante. `ads:golive:check` meldet
den Rest als WARN („aus, aber eigene Google-Zeile in den Zusatzzeilen").

Gecacht wird eine Stunde (`config('ad-slots.cache_ttl')`, Schlüssel
`AdCache::adsTxtKey()`); `AdCache::forgetTenant()` vergisst ihn zusammen mit den
aufgelösten Belegungen. Der Hauptschalter wirkt deshalb sofort — in den Ankern
und in `ads.txt`.

---

## 10. Views, in die die Anker eingebaut werden (SUN-AD-004)

Alle Pfade unten liegen unter `resources/views/themes/sun-v2/` — und nur dort;
darum steht in `config/ad-slots.php` bei jedem Slot `themes => ['sun-v2']`
(Abschnitt 3.2a). Wird ein Anker zusätzlich in `default`/`starter` gesetzt,
muss der Slug dort ergänzt werden, sonst bleibt der Anker leer, weil die
Auswahl ihn dem Portal nicht anbietet.

Zeilennummern beziehen sich auf `629a4a30` und dienen der Orientierung; die
Stellenangabe („nach Sektion X") ist bindend.

**Abweichung in #5, bewusst:** wo unten „ersetzt den Block …" steht, wurde der
Anker des Katalogs **neben** den alten Block gesetzt, nicht an seine Stelle.
Grund: `ads_enabled` steht nach dem Seeder überall auf `false`, der neue Anker
spielt also bis zum Rollout (#11) nichts aus — hätte #5 die alten Blöcke
entfernt, wäre mit dem Deploy auf 21 sun-v2-Portalen die laufende Vermarktung
still gewesen. Jeder Altblock ist mit `{{-- Altsystem, faellt in SUN-AD-011 --}}`
markiert; sie fallen zusammen mit `App\View\Components\AdSlot` in Abschnitt
11.1, und zwar an derselben Stelle, an der der neue Anker schon steht. Doppelt
ausgespielt wird in der Zwischenzeit nur, wenn ein Portal eine Belegung des
neuen Systems **und** einen aktiven Alt-Slot auf derselben Position hat — das
ist genau der Pilotschritt aus Abschnitt 11, in dem die Altblöcke fallen.

### `resources/views/themes/sun-v2/views/pages/home.blade.php`

| Slot | Stelle |
| --- | --- |
| `home_after_hero` | nach `</section>` des Hero-/Suchbereichs (Z. 88), vor der Sektion „Leistungen" (Z. 92) |
| `home_after_services` | nach `</section>` der Sektion „Leistungen" (Z. 107) |
| `home_after_top_rated` | nach `</section>` der Sektion „Top-bewertete Betriebe" (Z. 121) |
| `home_after_guide` | nach `</section>` der Sektion „Ratgeber" (Z. 153) — **ersetzt** den Block Z. 156–164 mit `position="home_content"` |
| `home_after_cities` | nach `</section>` der Sektion „Städte" (Z. 204) |
| `home_before_footer` | nach `</section>` der SEO-Text-Sektion (Z. 217), vor `@endsection` |

Das CTA-Band (Z. 167–188) bekommt keinen Anker davor und keinen danach —
Anker-Regel 2.5.

### `resources/views/themes/sun-v2/views/pages/companies/index.blade.php`

| Slot | Stelle |
| --- | --- |
| `search_above_results` | nach dem Filter-Panel `#filter-city` (Z. 95), vor dem Grid-Wrapper (Z. 97) — **außerhalb** des Grids |
| `search_in_feed_3` | im `@forelse`, nach dem 3. `<x-sun.listing-card>`, als direktes Kind von `flex flex-col gap-4` — **ersetzt** den Block Z. 104–109 |
| `search_in_feed_8` | ebenda, nach dem 8. Element; nur wenn mindestens 10 Treffer auf der Seite stehen |
| `search_sidebar_sticky` | im `<aside>`, letztes Kind von `sticky top-36 flex flex-col gap-4` — **ersetzt** den Block Z. 130–137. **Achtung:** das `<aside>` steht heute unter `@if($hasMap)`. Die Bedingung wird zu „Karte vorhanden **oder** Seitenspalten-Anker belegt bzw. Editor-Modus", sonst fehlt der Anker auf allen Portalen ohne Geodaten |
| `search_after_pagination` | nach `<x-sun.pagination>` (Z. 114), als letztes Kind der Ergebnisspalte |
| `search_before_footer` | nach `</section>` der SEO-Text-Sektion (Z. 164), vor `@endsection` |

### `resources/views/themes/sun-v2/views/pages/companies/show.blade.php`

| Slot | Stelle |
| --- | --- |
| `company_after_header_card` | nach `</section>` der Kopf-Karte (Z. 94), als Kind von `flex flex-col gap-6` — **ersetzt** den Block Z. 96–104 |
| `company_after_about` | nach `</section>` der Sektion „Über uns" (Z. 156) |
| `company_after_reviews` | nach `</section>` der Sektion „Bewertungen" (Z. 264) |
| `company_sidebar_top` | erstes Kind des `<aside>` (Z. 321), über der Kontakt-/Anfrage-Karte — wie `sidebar_top` im Alt-Theme (Ticket #19) |
| `company_sidebar_sticky` | im `<aside>` (Z. 312), letztes Kind — **ersetzt** den Block Z. 329–336 |
| `company_before_footer` | nach `</section>` „Weitere Einträge am Ort" (Z. 376), vor `</div>` des `container-portal` |

Die Sektion „Eintrag übernehmen" (Z. 302–306) und die mobile Aktionsleiste
(Z. 380–390) bekommen keinen Anker daneben. Das `@unlesspremiumFeature(…
AdFree)` um die beiden alten Blöcke zieht in die Komponente — nicht in jede
View.

### `resources/views/themes/sun-v2/views/pages/cities/show.blade.php`

| Slot | Stelle |
| --- | --- |
| `city_above_results` | nach dem Filter-Panel `#city-filter-sort` (Z. 96), vor dem Listen-Wrapper (Z. 99) |
| `city_in_feed_3` | im `@forelse`, nach dem 3. `<x-sun.listing-card>` — **ersetzt** den Block Z. 104–109 |
| `city_after_pagination` | nach `<x-sun.pagination>` (Z. 137), als letztes Kind der Listenspalte |
| `city_before_footer` | nach `</section>` der SEO-Text-Sektion (Z. 193), vor `@endsection` |

Der Local-Hub-Block (`<x-city.intro>`, Z. 83) und die FAQ (`<x-city.faq>`,
Z. 143) bleiben ohne Anker: sie erscheinen nur auf Seite 1 und sind die
indexierungsrelevanten Teile der Stadtseite.

### `resources/views/themes/sun-v2/views/guide/show.blade.php`

| Slot | Stelle |
| --- | --- |
| `guide_after_key_facts` | nach `@include('guide.partials.key-facts')` (Z. 98) — **ersetzt** Z. 101 (`position="content_after_intro"`) |
| `guide_in_article_1` | serverseitig, nach dem 3. `<p>` auf oberster Ebene in `body_before` (Abschnitt 3.4) |
| `guide_in_article_2` | serverseitig, nach dem 8. `<p>` auf oberster Ebene in `body_before` |
| `guide_sidebar_top` | erstes Kind des `<aside>` (Z. 163) — **ersetzt** Z. 164 |
| `guide_sidebar_sticky` | im klebenden Wrapper des `<aside>`, nach dem Inhaltsverzeichnis — **ersetzt** Z. 171 |
| `guide_before_footer` | nach `</div>` des Grids, vor `@endsection` — **ersetzt** Z. 176 |

Vor der Kurzantwort (Block 2, Z. 84), vor dem Inhaltsverzeichnis (Block 4) und
rund um die Verweisleiste (Block 7, Z. 109) kommt nichts.

### `resources/views/themes/sun-v2/views/guide/index.blade.php`

| Slot | Stelle |
| --- | --- |
| `guide_index_after_first_block` | nach `</section>` des ersten gerenderten Blocks — Treffer (Z. 41), Einzelkategorie (Z. 50) oder Kategorie-Kacheln (Z. 59); genau einer davon greift |
| `guide_index_in_feed_5` | im `@foreach` der Themenkarten, nach der 5. Karte — **nur im einspaltigen Fall**; das Karten-Grid ist `sm:grid-cols-2`, dort entfällt der Anker (Anker-Regel 3.2). In #5 ist der Anker darum **nur** auf `guide/category.blade.php` gesetzt, dessen Themenliste `flex flex-col gap-4` ist |
| `guide_index_sidebar_sticky` | entfällt auf dieser View — die Übersicht hat keine Seitenspalte |
| `guide_index_before_footer` | vor `@endsection` — **ersetzt** Z. 73 (`position="footer_above"`) |

### `resources/views/themes/sun-v2/views/guide/category.blade.php`

| Slot | Stelle |
| --- | --- |
| `guide_index_after_first_block` | nach `</section>` der Themenliste, vor der Sektion „Weitere Themen" (Z. 53) |
| `guide_index_in_feed_5` | im `@foreach` der Themenkarten nach der 5. Karte — **ersetzt** den Block Z. 41–44; es bleibt bei der bestehenden Bedingung „nur wenn mindestens 6 Karten" |
| `guide_index_sidebar_sticky` | im `<aside>` (Z. 64), letztes Kind — **ersetzt** Z. 77. `guide_sidebar_top` ist kein `guide_index`-Anker; Z. 65 fällt weg |
| `guide_index_before_footer` | vor `@endsection` — **ersetzt** Z. 82 |

### Rückfall-Views `pages/blog/index.blade.php` und `pages/blog/category.blade.php`

Nachgezogen in Ticket #23 — beide Views rendern `guide.index` bzw.
`guide.category`, solange das Portal keine Ratgeber-Kategorien hat.

| Slot | Stelle |
| --- | --- |
| `guide_index_after_first_block` | `index`: als Kind des Flex-Containers nach „Kategorien"/„Meistgelesen", vor der Artikelliste (Abstand über `gap`, nicht über `wrap`). `category`: am Ende der Artikel-Sektion nach der Blätternavigation |
| `guide_index_in_feed_5` | am Schnitt zwischen den beiden Kartenblöcken, über `ListSlotHelper::make([5 => …], …, minFollowing: 1)`. Der Schnitt liegt dafür nach der **5.** statt nach der 3. Karte, damit Position und Schlüssel übereinstimmen; nie innerhalb des Rasters `sm:grid-cols-2 lg:grid-cols-3` |
| `guide_index_sidebar_sticky` | entfällt — beide Views sind auf allen Breiten einspaltig |
| `guide_index_before_footer` | als letztes Kind des Containers, nach der CTA-Sektion |

Der Altsystem-Anker `position="listing_between_results"` bleibt an derselben
Stelle stehen und fällt mit SUN-AD-011.

### `resources/views/themes/sun-v2/views/layouts/sun.blade.php`

Keine neuen Anker. Zu ändern:

* Robots-Angabe: der Editor-Modus setzt `$metaRobots` auf
  `noindex,nofollow` (Z. 38–40 werten die Variable schon aus), dazu den
  Antwort-Header `X-Robots-Tag` in der Middleware.
* `@include('partials.analytics')` (Z. 71) im Editor-Modus überspringen.
* AdSense-Loader einmalig, nach Einwilligung und nicht im Editor-Modus.
* `auto_ads` (Z. 74–77) und `mobile_sticky_bottom` (Z. 100–104) bleiben
  unverändert am Altsystem, bis SUN-AD-011 sie abräumt. Direkt darunter steht
  `<x-sun.auto-ads />` (#18) — stumm, solange der alte Platz aktiv ist, und
  ab dessen Abschaltung der einzige Weg zu Auto Ads. Dieselben zwei Zeilen
  stehen in `themes/default/views/layouts/app.blade.php` und
  `themes/starter/views/layouts/app.blade.php`; die 15 Portale mit Auto Ads
  verteilen sich über alle drei Themes.

---

## 11. Rollout

| Schritt | Ticket | Inhalt |
| --- | --- | --- |
| 1 | #2 (dieses) | Katalog, Enums, Architektur — **keine** Verhaltensänderung im Portal |
| 2 | #3 | Migrationen in der Central-DB, Modelle |
| 3 | #4 | Provider, Registry, Resolver mit Cache |
| 4 | #5 | `<x-sun.ad-slot>` und die Anker in den Views aus Abschnitt 10 |
| 5 | #6, #7, #8 | Editor-Modus, Filament-Seite, Konfigurationspanel |
| 6 | #9, #10 | Einwilligung, CSP, Werbemittel-Verwaltung, `ads.txt` |
| 6a | #15 | Übernahme des Altbestands in `ad_units`/`ad_placements`/`ad_settings` (11.1) |
| 7 | #11 | Pilot auf **einem** Portal, Core-Web-Vitals-Vergleich, dann alle; danach Altsystem abschalten |
| 8 | #12 | eigenes Impression-/Viewability-Tracking |

Bis Schritt 7 abgeschlossen ist, liefert das Altsystem aus. Neue Anker rendern
nur, wenn es für sie eine Belegung gibt — ein Portal ohne Belegung sieht
genauso aus wie vorher.

Schritt 7 im Einzelnen: Reihenfolge, Pilot, Stufen und Notbremse stehen in
`docs/ad-slots-golive.md`, die Sicherheitsseite in
`docs/ad-slots-security-review.md`, die Anker-Empfehlung in Abschnitt 13.
Maschinelle Prüfung der Vorbedingungen: `php artisan ads:golive:check`.

### 11.1 Übernahme des Altbestands (SUN-AD-015, Ticket #15)

Vor dem Umstieg wandert der Bestand des Altsystems in die neuen Tabellen.
Werkzeug: `php artisan ads:legacy-takeover` (`App\Ads\LegacyAdTakeover`),
mit `--dry-run` als Trockenlauf, `--tenants=` je Portal für den Pilot und
`--keep-disabled`, wenn `ads_enabled` noch nicht mitgesetzt werden soll.
Der Lauf ist idempotent: Werbemittel werden am Anzeigenblock, Belegungen an
(Werbemittel, Anker, Gerät) wiedererkannt; zentral schon gepflegte Werte
(Publisher-ID, `ads_txt_extra`) werden nie überschrieben, sondern gemeldet.

Gemessener Bestand (23 Portale): 118 Alt-Slots in 17 Portalen, 111 aktiv, alle
mit AdSense-Code, 7 belegte Positionen, 15 Portale mit `ads_txt_content`.
Seit Katalog v3 (Ticket #19) werden alle 111 aktiven Slots übernommen; vorher
fielen die 16 `sidebar_after_claim`-Slots weg.

**Abbildung der Positionen** (Quelle: `LegacyAdTakeover::SLOT_MAP`; alle
belegten Alt-Positionen liegen auf dem Firmenprofil):

| Alt-Position | Katalog-Anker | Hinweis |
| --- | --- | --- |
| `after_breadcrumb` | `company_after_header_card` | Anker ist **nur mobil** — auf dem Desktop entfällt diese Anzeige |
| `content_before_intro` | `company_after_about` | aus „vor dem Einleitungstext" wird „nach Über uns" |
| `after_photos` | `company_after_reviews` | |
| `after_change_request` | `company_before_footer` | letzter Inhaltsblock; die beiden daraus entstandenen Belegungen standen wegen #32 auf `paused` und sind seit 16.10.2026 wieder `active` (#40, s. u.) |
| `sidebar_top` | `company_sidebar_sticky` | Anker ist desktop-only |
| `sidebar_after_claim` | `company_sidebar_top` | zweiter Seitenspalten-Anker, mit Katalog v3 aufgenommen (Ticket #19); Anker ist desktop-only |
| `auto_ads` | — | kein Anker, sondern `ad_settings.adsense_auto_ads` (Abschnitt 1); Ausspielung `<x-sun.auto-ads />`, Ticket #18 |
| `mobile_sticky_bottom` | — | Overlay, in v1 bewusst draußen; im Bestand nirgends belegt |

**Werbemittel statt Einzelbelegungen.** Der Alt-Code ist je Position und
AdSense-Konto identisch, aber es gibt **zwei** Konten im Netz (8 bzw. 9
Portale mit Bestand). Darum entstehen 6 Anker × 2 Konten = **12 Werbemittel
mit je einer Belegung**, deren Portal-Scope im Pivot steht —
`applies_to_all_tenants` bleibt `false`, weil kein Anzeigenblock für alle
Portale gilt. Priorität der übernommenen Belegungen: 10, damit eine später in
#8 angelegte Belegung sie ohne Umweg überstimmt.

**Provider `adsense`, nicht `html`.** Aus dem Markup werden `data-ad-slot`,
`data-ad-layout-key` und das Format gezogen, das rohe Skript wird verworfen.
Als `html` übernommen wäre der Bestand schneller drin, liefe aber ohne
Einwilligungs-Gate (Abschnitt 9), ohne Entdoppelung des Loaders und ohne
Höhenreservierung. Die Publisher-ID landet in `ad_settings`, nicht im
Werbemittel — `data-ad-client` kommt immer aus den Portal-Einstellungen.

**`ads.txt`.** `ad_settings.ads_txt_content` der **Tenant**-DB wandert nach
`ad_settings.ads_txt_extra` der **Central**-DB. Zwei Tabellen gleichen Namens
auf verschiedenen Verbindungen, zwei Modelle gleichen Namens:
`App\Models\Portal\AdSetting` (Tenant) und `App\Ads\Models\AdSetting`
(Central).

**Freigabeschalter.** Nach dem Seeder steht `ads_enabled` überall auf `false`.
Der Lauf setzt ihn für jedes Portal, aus dem etwas übernommen wurde — sonst
wäre nach dem Umstieg netzweit Werbung aus, obwohl das Altsystem gerade
ausliefert. Für den Pilot nach Schritt 7 unterdrückt `--keep-disabled` das.

Nicht übernommen wird gemeldet, nicht geraten: abgeschaltete Alt-Slots,
Positionen ohne Entsprechung, Code ohne AdSense-Anzeigenblock, auf den Anker
verengte Gerätewahl und nicht lesbare Portal-Datenbanken stehen in der Tabelle
„Nicht uebernommen" des Laufs. Diese Liste ist die Abnahmeliste für #11.

**Nachzug 16.10.2026 (#40): `company_before_footer` ist wieder scharf.** Die
beiden Belegungen aus `after_change_request` (Placement 2, Konto
`ca-pub-2563111817853634`, Block `3828015317`, 8 Portale; Placement 8, Konto
`ca-pub-1901800422608466`, Block `4148391584`, 8 Portale) hatte #38 wegen des
CLS-Befunds aus #32 auf `paused` gesetzt. Mit der Lösung aus #32 gibt der Anker
die Höhe vor (`height:90px` im `<ins>`), der Grund ist weg — beide stehen
wieder auf `active`. Gemessen vorher und nachher: CLS **0,000** auf dem
Firmenprofil, Containerhöhe 130 px unverändert über die Einwilligung hinweg
(`docs/messungen/werbeplaetze-golive/cls-company-before-footer-2026-10-16.md`).
`ads:golive:check` auf der Produktion danach weiter 0 FAIL.

Doppelt ausgespielt wird dadurch nichts: `after_change_request` steht nur in
den Profil-Views der Themes `default` und `starter`, nicht in `sun-v2`. Von den
16 zugeordneten Portalen fährt allein 43 `tierarztportal.com` `default` — dort
rendert der neue Anker gar nicht (Entscheidung #16), dort liefert weiter das
Altsystem. Auf den übrigen 15 war die Position bisher leer.

**Abschalten des Altsystems** (Teil von #11): `x-ad-slot`-Aufrufe mit
`position=` entfernen, `App\View\Components\AdSlot`,
`resources/views/components/ad-slot.blade.php`, `App\Models\Portal\AdSlot`,
`AdSlotExportService`, `AdSlotImportService`, `BulkAdSlotImportJob` und die
Filament-Seite `AdSlotDistribution` löschen, Tenant-Tabelle `ad_slots` per
Tenant-Migration verwerfen. Erst **nach** der Abnahme, und die Codes aus den
Tenant-DBs vorher sichern.

---

## 12. Freigabe

Der Katalog v2 (Abschnitt 3.3), die Central-DB-Entscheidung (4.1) und die
Anker-Regeln (2) sind von Enes freizugeben. Offene Entscheidungen, die bewusst
so getroffen wurden und beim Lesen auffallen sollen:

1. **32 Anker** sind das Angebot, nicht die Belegung. Ein Portal kann mit drei
   Belegungen starten.
2. **`guide.category` ist `guide_index`**, kein eigener Seitentyp — drei der
   vier Anker sind identisch. Der vierte (`guide_index_sidebar_sticky`) ist
   seit Katalog v4 über `routes` auf diese Route begrenzt (Abschnitt 3.1a),
   nicht über einen zweiten Seitentyp.
3. **Kein mobiles Haftband und keine Auto Ads im Katalog** (Abschnitt 1).
4. **Blog und Stellen bleiben v1 außen vor**, obwohl sie heute schon
   `listing_between_results` nutzen.
5. **Portalgenaue Belegung schlägt `applies_to_all_tenants` nicht automatisch** — nur über
   `priority` (Abschnitt 4.3).
6. **Zwei Seitenspalten-Anker auf dem Firmenprofil** (Katalog v3, Ticket #19):
   `company_sidebar_top` oberhalb der Kontakt-Karte, `company_sidebar_sticky`
   als letztes Kind der Seitenspalte, beide desktop-only. Grund: das Altsystem
   pflegt dort netzweit zwei Positionen (`sidebar_top`, `sidebar_after_claim`,
   je 16 aktive Slots); mit nur einem Anker wären 16 Plätze beim Umstieg
   weggefallen, weil der Resolver je Anker und Gerät nur eine Belegung
   ausspielt. Die Zuordnung bleibt bewusst unverändert für `sidebar_top`
   (→ `company_sidebar_sticky`), damit die in #15 geschriebenen Belegungen
   stehen bleiben und ein erneuter Lauf idempotent ist. Vorbild der Form ist
   der Ratgeber (`guide_sidebar_top` + `guide_sidebar_sticky`).
7. **Anker nur in `sun-v2`** (Abschnitt 3.2a, Ticket #16): auf der Produktion
   laufen 21 der 23 Portale `sun-v2`, die beiden `default`-Portale sind nicht
   ausgeliefert und bleiben ohne Werbeplätze. `default`/`starter` bekommen
   keine Anker nachgezogen.

---

## 13. Slot-Empfehlung: welche Anker lohnen (SUN-AD-011, Ticket #11)

Der Katalog bietet 32 Anker an. Das ist das Angebot, nicht die Belegung — ein
Portal mit drei Belegungen ist der Normalfall, nicht der Notfall. Dieser
Abschnitt sagt, **welche** drei.

Die Empfehlung in §13.1 ist **begründet, nicht gemessen**: sie steht hier, damit
der Pilot (`docs/ad-slots-golive.md` §3) mit einer Hypothese startet und nicht
mit einem Gefühl. Die Messung trägt §13.2 nach; danach ersetzt die gemessene
Spalte die Begründung, nicht umgekehrt.

Was schon gemessen ist: der Layout-Shift. Mit **allen 32 Ankern belegt** liegt
CLS auf allen sechs Seitentypen bei 0.000, mobil und Desktop
(`docs/messungen/werbeplaetze-cls-2026-10-16.md`). Die Frage „welcher Anker
lohnt" ist damit keine CLS-Frage mehr, sondern eine Frage von Sichtbarkeit und
Ladezeit.

### 13.1 Vorab-Empfehlung (Stand 16.10.2026, vor dem Pilot)

Drei Dinge gehen in die Bewertung ein:

1. **Lage im Leseweg.** Ein Anker, an dem der Nutzer ohnehin vorbeiliest
   (`in_feed`, `in_article`, nach einem Inhaltsblock), wird gesehen. Ein Anker
   am Seitenende wird erreicht, wenn die Seite zu Ende gelesen wird — auf einer
   Suchseite mit Paginierung ist das die Minderheit.
2. **Gerät.** Die fünf `*_sidebar_*`-Anker sind **desktop-only** (Abschnitt
   3.2). In einem mobil-zuerst-Netz erreichen sie nur einen Teil des Verkehrs;
   ihr Beitrag hängt am Mobil/Desktop-Verhältnis des Portals und ist vor der
   Belegung in der Search Console nachzusehen.
3. **Nähe zum LCP-Element.** `home_after_hero` sitzt direkt unter dem
   Hero-Text, und der Hero-Text ist auf `sun-v2` das LCP-Element. Der Anker
   verschiebt nichts (Höhe ist reserviert), konkurriert aber um Bandbreite im
   kritischen Fenster.

| Anker | Gerät | Empfehlung vor dem Pilot | Grund |
| --- | --- | --- | --- |
| `company_after_about` | alle | **Start** | Firmenprofil ist die Inventarseite des Netzes — alle 111 aktiven Alt-Slots lagen dort (11.1); mitten im Leseweg |
| `guide_in_article_1` | alle | **Start** | im Text, nach dem ersten Absatzblock; Ratgeber-Besucher lesen linear |
| `search_in_feed_3` | alle | **Start** | zwischen Ergebnis 3 und 4, also im Blick vor dem ersten Scrollen auf der am stärksten besuchten Seite nach der Startseite |
| `company_after_reviews`, `company_after_header_card` | alle / mobil | Stufe 2 | gute Lage, aber drei Anker auf einem Profil sind viel; erst nach den Pilotzahlen |
| `guide_in_article_2`, `guide_after_key_facts` | alle | Stufe 2 | zweiter Anker im selben Artikel lohnt erst, wenn der erste trägt |
| `city_in_feed_3`, `search_in_feed_8` | alle | Stufe 2 | gleiche Form wie `search_in_feed_3`, geringere Reichweite |
| `home_after_services`, `home_after_top_rated`, `home_after_guide`, `home_after_cities` | alle | Stufe 3 | Startseite wird überflogen, nicht gelesen; vier Anker auf einer Seite sind zu viel — höchstens einer |
| `*_sidebar_top`, `*_sidebar_sticky` (5 Anker) | desktop | Stufe 3, portalabhängig | nur Desktop-Verkehr; der Altbestand pflegte dort netzweit zwei Positionen, das spricht dafür — aber erst nach dem Mobil/Desktop-Blick je Portal |
| `home_after_hero` | alle | **nicht im Pilot** | Nähe zum LCP-Element; wenn überhaupt, erst wenn LCP im Feld Luft hat |
| `*_before_footer` (5 Anker) | alle | **nicht im Pilot** | Seitenende, Leaderboard 90 px; die Sichtbarkeit ist hier erfahrungsgemäß am niedrigsten — genau der Fall, für den §13.3 die Abschaltregel hat |
| `search_after_pagination`, `city_after_pagination` | alle | **nicht im Pilot** | hinter der Paginierung; wer blättert, scrollt darüber hinweg |
| `guide_index_*` (4 Anker) | alle | **nicht im Pilot** | `/ratgeber` fällt auf vielen Portalen noch auf die Blog-View zurück, und `guide_index_sidebar_sticky` ist dort stumm (#27) |

Zusammengefasst: **drei Anker für den Pilot** — `company_after_about`,
`guide_in_article_1`, `search_in_feed_3`. Alle drei sind `all`, liegen im
Leseweg und reservieren 280 px mobil / 250 px Desktop.

### 13.2 Gemessen nach dem Pilot

Vorgesehen war der AdSense-Report je Anzeigenblock (drei Tage, ein Portal,
`docs/ad-slots-golive.md` §3.4). Gefüllt ist die Tabelle aus der **eigenen**
Zählung — sie misst dieselbe Schwelle, trennt die Anker sogar besser als der
Report (der kennt nur den Anzeigenblock) und braucht kein Konto. Die drei
kontogebundenen Spalten bleiben offen und sind als solche markiert.

**Gefüllt am 16.10.2026 (#31) aus der eigenen Zählung, nicht aus AdSense.**
Quelle ist `ad_impression_daily` (§14): gezählt wird gegen dieselbe Vorgabe wie
bei AdSense — 50 % der Containerfläche für eine zusammenhängende Sekunde. Die
Spalte „sichtbare Impressionen" ist damit echt; was der eigene Zähler zum
Zeitpunkt dieser Messung **nicht** hatte, war der Nenner (die ausgelieferten
Fragmente) und damit die Viewability-**Quote**. Den Nenner zählt das System
seit **Ticket #47** selbst (`ad_impression_daily.deliveries`, §14.1a) — die
Quote steht im Widget, sobald ein ganzes Fenster nach dem Deploy liegt. Nur
noch kontogebunden sind Klickrate und RPM.

**Nenner seit 16.10.2026, 13:25 UTC auf der Produktion** (#52): Migration
`…_000010_add_deliveries_to_ad_impression_tables` gelaufen, Assets neu gebaut,
echter Verkehr meldet `event=delivered`. Die Spalte „Viewability-Quote" unten
bleibt bis zum **23.10.2026** leer — jedes frühere Siebentagefenster enthält
Tage ohne Nenner und zeigt die Quote **zu hoch**, gekappt auf 100 %
(§13.3, vorletzter Absatz).
Stand und Quote in einem Aufruf: `scripts/ads-viewability-quote.sh`.

**Erste unverfälschte Ablesung, 16.10.2026, Stunde 14:00 UTC** (#52): 68 % über
alle Anker (201 / 294), je Anker 37–70 % bei einem Ausreißer
`company_sidebar_top` 22/22. Gelesen aus `ad_impression_buffer` — in einer
Stunde, die vollständig nach dem Deploy begonnen hat, hat jede Zeile ihren
Nenner. Sie steht **absichtlich nicht in der Tabelle unten**: eine Tageszeit,
Grundmengen von 14 bis 152 Fragmenten je Anker, und damit keine Grundlage für
§13.3 — auch nicht für `company_sidebar_sticky` (37 %, unter der 40-%-Schwelle).
Sie zeigt nur, dass die Quote ab jetzt aus eigener Kraft messbar ist. Beleg:
`docs/messungen/werbeplaetze-golive/viewability-nenner-2026-10-16.txt`.

Portal: **alle 15 freigegebenen** (nicht ein einzelnes, s. u.) · Zeitraum:
16.10.2026 12:09–13:01 UTC, erste gezählte Stunde des Netzes · 391
Sichtkontakte · abgelesen von Kay (#31)

| Anker | Gerät | sichtbare Impr. (eigene Zählung) | Reichweite gegen den obersten Anker | Viewability-Quote (AdSense) | RPM | Entscheidung |
| --- | --- | --- | --- | --- | --- | --- |
| `company_after_header_card` | mobil | 192 | 100 % (Bezug) | offen (Konto) | offen | **behalten** — trägt das Meiste |
| `company_sidebar_top` | Desktop | 63 | 100 % (Bezug Desktop) | offen (Konto) | offen | **behalten** |
| `company_after_reviews` | mobil / Desktop | 50 / 20 | 26 % / 32 % | offen (Konto) | offen | behalten, zweitbester Content-Anker |
| `company_sidebar_sticky` | Desktop | 26 | 41 % | offen (Konto) | offen | behalten |
| `company_after_about` | mobil / Desktop | 17 / 7 | 9 % / 11 % | offen (Konto) | offen | behalten, aber kleine Grundmenge: der Anker steht nur auf Profilen **mit** Beschreibungstext |
| `company_before_footer` | mobil / Desktop | 10 / 6 | 5 % / 10 % | offen (Konto) | offen | beobachten — unter 10 % Reichweite, der schwächste belegte Anker |
| `guide_in_article_1` | — | noch keine Zählung | — | offen (Konto) | offen | im Pilot seit 16.10.2026 12:40 UTC belegt (Portal 27) |
| `search_in_feed_3` | — | noch keine Zählung | — | offen (Konto) | offen | wie vor |

Drei Dinge zum Lesen der Tabelle:

* **Reichweite ist nicht Viewability.** Die Spalte setzt den Anker gegen den
  obersten Anker derselben Seite und sagt, welcher Anteil der Besucher ihn
  überhaupt zu sehen bekommt. Die 40-%-Regel aus §13.3 ist eine Quote
  „sichtbar / ausgeliefert" und darf auf diese Spalte **nicht** angewendet
  werden. Den Nenner zählt das System seit #47 selbst (§14.1a); für die hier
  abgelesene Stunde gab es ihn noch nicht, darum bleibt die Spalte
  „Viewability-Quote" in dieser Tabelle offen. Die Quote aus dem Konto trägt
  #48 nach — zum Abgleich der beiden Zahlen gegeneinander.
* **Die Tabelle deckt den Altbestand ab, nicht die drei Pilot-Anker.** Belegt
  hat der Takeover nur Profil-Anker; `guide_in_article_1` und
  `search_in_feed_3` laufen erst seit dem Pilot auf Portal 27
  (`docs/messungen/werbeplaetze-golive/pilot-bodenlegerfinden-2026-10-16.md`).
  Ihre Zahlen trägt der nächste Abgleich nach.
* Impressionen auf dem zwischenzeitlich pausierten `company_before_footer` sind
  kein Fehler: aufgelöste Karten halten eine Stunde (`AdCache`), und seit #40
  ist der Anker wieder `active`.

### 13.3 Entscheidungsregel

Die Quote kommt aus der **eigenen** Zählung (§14.1a, Ticket #47): Spalte
„Sichtbar" im Widget „Werbeplätze — eigene Impressionen", je Anker und je
Portal, gerechnet als `impressions / deliveries` über das gewählte Fenster.
Das Widget färbt sie nach genau diesen Schwellen. Ein Gedankenstrich heißt
„kein Nenner im Fenster" — nicht 0 %; das ist der Fall für jedes Fenster, das
noch Tage vor der Einführung des Nenners enthält.

Gefährlicher als der Gedankenstrich ist die **halbe** Quote: enthält das
Fenster Tage von vor dem 16.10.2026, zählen deren Zeilen mit `deliveries = 0`
nicht in den Nenner, ihre Sichtkontakte aber in den Zähler. Das Verhältnis
steigt über 1, und `AdImpressionStats::quote()` kappt bei 100 % — die Quote
liest sich also **zu hoch**, nicht zu niedrig, und zwar meist als glatte
100 %. Gemessen am 16.10.2026, 14:09 UTC: 699 Sichtkontakte / 156
ausgelieferte Fragmente, rechnerisch 448 %, angezeigt 100 %. Unter der Regel
„über 70 % ⇒ `applies_to_all_tenants`" würde das jeden Anker netzweit
ausrollen. Darum erst ab dem 23.10.2026 (7 Tage) bzw. 15.11.2026 (30 Tage)
nach §13.3 entscheiden; `scripts/ads-viewability-quote.sh` rechnet das Datum
aus und verweigert vorher die Freigabe (Exit 70). Eine glatte 100 % ist auch
später ein Grund, „Tage mit Daten im Fenster" gegenzulesen.

* **Viewability unter 40 %** ⇒ Anker abschalten, nicht „laufen lassen". Er
  kostet Ladezeit, erzeugt Requests an Google und verwässert den Kontowert.
  Abschalten heißt: Belegung im Panel auf `paused` — der Anker bleibt im
  Katalog, die Höhe bleibt reserviert, es wird nur nichts ausgespielt.
* **Viewability über 70 %** ⇒ der Anker trägt. Dann ist der nächste Schritt
  nicht ein weiterer Anker auf derselben Seite, sondern derselbe Anker auf
  weiteren Portalen (`applies_to_all_tenants`).
* **Zwischen 40 % und 70 %** ⇒ stehen lassen, aber keinen zweiten Anker auf
  derselben Seite belegen. Der AdSense-Report kennt den Anzeigenblock, nicht
  den Anker — bei netzweiten Belegungen mischt er Portale. Portalgenau
  liefert die Quote nur das eigene Zählen (§14): `ad_impression_daily` je
  Portal, Anker, Belegung und Gerät, ablesbar im Widget „Werbeplätze — eigene
  Impressionen".
* **Höchstens drei Anker je Seitentyp.** Nicht aus Höflichkeit: jeder weitere
  Block ist ein weiterer Request im kritischen Fenster, und Googles
  Sichtbarkeitswert sinkt mit der Anzahl der Blöcke auf einer Seite.

---

## 14. Eigenes Impression-/Viewability-Zählen (SUN-AD-012, Ticket #12)

Der AdSense-Report kennt den Anzeigenblock, nicht den Anker — bei netzweiten
Belegungen mischt er Portale (§13.3), und für Direktbuchungen über den
`HtmlProvider` gibt es ihn gar nicht. Deshalb zählt das System selbst, gegen
dieselbe Vorgabe wie AdSense: **50 % der Containerfläche für eine
zusammenhängende Sekunde im Viewport** (MRC-Vorgabe für Display).

Gezählt werden **zwei** Größen je Anker, weil eine allein keine Quote ergibt
(Ticket #47):

| Spalte | Was sie zählt | Rolle |
| --- | --- | --- |
| `deliveries` | Fragment ausgeliefert — eingesetzt, bevor die Sichtbarkeitsfrist läuft | Nenner |
| `impressions` | Sichtkontakt: 50 % der Fläche, eine zusammenhängende Sekunde | Zähler |

`impressions / deliveries` ist die Viewability-**Quote** und damit die Größe,
an der die Entscheidungsregel aus §13.3 hängt.

Abschaltbar an einer Stelle:

```php
// config/ad-slots.php
'impression_tracking' => env('ADS_IMPRESSION_TRACKING', true),
```

`false` heißt vollständig aus: die Komponente gibt kein `data-ad-placement`
aus, `resources/js/ads.js` richtet keinen Beobachter ein, es geht kein Beacon
los, der stündliche Job verdichtet nichts und das Widget ist unsichtbar. Die
Tabellen bleiben stehen — Abschalten ist kein Datenverlust.

### 14.1 Weg einer Impression

```
Fragment eingesetzt                     → Meldung event=delivered
Container im Blick (≥ 50 %, ≥ 1 s)      → Meldung event=viewable
   → resources/js/ads.js, ein IntersectionObserver für alle Anker
   → gebündeltes navigator.sendBeacon an POST /_ads/impressions
   → App\Ads\Http\Controllers\AdImpressionController (ohne 'web', 60/min je IP)
   → App\Ads\Support\AdImpressionRecorder: beide Zähler je Portal/Stunde/
     Anker/Belegung/Gerät in ad_impression_buffer hochzählen (ein Statement)
   → stündlich App\Ads\Jobs\AggregateAdImpressions: abgeschlossene Stunden
     nach ad_impression_daily, Puffer leeren, alte Tageszeilen löschen
   → App\Ads\Support\AdImpressionStats → AdImpressionsWidget im Panel
```

Gezählt wird **erst nach dem Einsetzen des Werbemittels** (`setzeSlotEin()`).
Ein Platz, der auf die Einwilligung wartet, ist eine leere Reservierung und
keine Impression; ein eingeklappter Platz (`on_denied = collapse`) kommt nie
dorthin. Im Editor-Modus (§6) wird grundsätzlich nicht gezählt.

Das gemeldete Gerät ist das **tatsächliche** (`matchMedia('(min-width:
1024px)')`), nicht die Gerätewahl des Ankers: eine Belegung mit `device = all`
rendert einen Container für beide, und ohne diese Messung wäre der Vergleich
Mobil/Desktop für sie blind.

### 14.1a Der Nenner: ausgelieferte Fragmente (Ticket #47)

`delivered` wird in `beobachteImpression()` gemeldet — unmittelbar nach
`setzeSlotEin()`, also **bevor** die Sichtbarkeitsfrist läuft. Das ist genau
der Moment, in dem das Werbemittel im DOM steht und seine Requests losgehen;
eine leere Reservierung, ein Platz, der auf die Einwilligung wartet, und ein
eingeklappter Platz kommen nie dorthin und zählen darum auch nicht in den
Nenner.

Fünf Festlegungen, die zusammenhängen:

* **Dieselbe Bedingung wie der Sichtkontakt.** Ohne
  `IntersectionObserver` wird auch der Nenner nicht gezählt. Sonst würde ein
  solcher Browser den Nenner hochzählen, ohne je einen Sichtkontakt melden zu
  können — die Quote des Ankers wäre nach unten verfälscht, und an ihr hängt
  die Abschaltregel.
* **Je Seitenaufruf einmal je Art.** Der Sperrschlüssel in `ads.js` ist
  `slot:placement:event`, im Recorder sind Auslieferung und Sichtkontakt zwei
  Spalten derselben Zeile.
* **Zwei Spalten, keine zweite Tabelle.** `ad_impression_buffer` und
  `ad_impression_daily` führen beide Zähler; Aggregationsstufe,
  Eindeutigkeitsschlüssel und Pruning sind unverändert, und die Zusagen aus
  §14.2 bleiben wortgleich: kein Zeitstempel je Ereignis, keine IP, keine
  Sitzung.
* **`deliveries ≥ impressions` ist die Erwartung, keine Zusage.** Ein Fragment
  kann in der einen Stunde eingesetzt und erst in der nächsten sichtbar
  werden; am Rand eines Fensters steht der Zähler dann ohne seinen Nenner.
  `AdImpressionStats::quote()` kappt deshalb bei 100 %.
* **Altzeilen haben `deliveries = 0`.** Das ist richtig und nicht „unbekannt
  als Null": vorher wurde nichts ausgeliefert *gezählt*. Die Quote wird darum
  nur ausgewiesen, wo der Nenner größer als 0 ist — sonst ein Gedankenstrich,
  nie 0 %.

Nebenwirkung auf die Grenzen des Beacons: ein Seitenaufruf meldet jetzt bis zu
doppelt so viele Einträge, deshalb steht `impressions.max_entries` auf **40**
(vorher 20) und der Rumpf bleibt bei 8 kB (40 Meldungen ≈ 3 kB).

### 14.2 Warum es keine Rohdaten gibt

Es gibt **keine Tabelle mit einer Zeile je Ereignis**. Die kleinste
gespeicherte Einheit ist ein Zähler über eine Stunde, ein Portal, einen Anker,
eine Belegung und eine Geräteklasse:

| Tabelle | Inhalt | Lebensdauer |
| --- | --- | --- |
| `ad_impression_buffer` | zwei Zähler (`impressions`, `deliveries`) je Portal/**Stunde**/Anker/Belegung/Gerät | bis zum nächsten stündlichen Lauf |
| `ad_impression_daily` | zwei Zähler je Portal/**Tag**/Anker/Belegung/Gerät | `ad-slots.impressions.retention_days` (400 Tage) |

Daraus ist kein Einzelbesuch ablesbar. Der Rumpf des Beacons führt nur
Katalogschlüssel, Belegungs-Id, Geräteklasse und die Ereignisart
(`delivered`/`viewable`) — **keine Kennung, keine Sitzung, kein
Wiedererkennungsmerkmal**, und in den Zähltabellen landet davon nur die
Summe. Deshalb ist das Zählen einwilligungsfrei, und deshalb
läuft die Route bewusst **ohne** `'web'`: eine Session würde ein Cookie
setzen und genau diese Aussage kaputt machen.

Zwei Dinge gehören dazu gesagt, weil sie sonst mit einem Blick in die
DevTools widerlegt sind (Review #39, `docs/ad-slots-security-review.md`
§13.1):

* **Cookies.** `navigator.sendBeacon()` schickt seinen Request nach
  Spezifikation mit credentials mode *include*; der Browser legt die
  Portal-Cookies also bei. Die Anwendung **liest und speichert keines** — auf
  der Route steht keine Cookie- oder Session-Middleware, und die Antwort
  trägt kein `Set-Cookie` (nachgewiesen in der Abnahme #37).
* **IP.** In den Zähldaten steht keine. Im nginx-Zugriffslog steht sie wie bei
  jedem anderen Request — seit #43 die echte Besucheradresse und nicht mehr
  die Cloudflare-Edge (`docs/echte-besucher-ip.md`) —, und das Rate-Limit
  braucht sie im Schlüssel.

Abgesichert ist der offene Endpunkt stattdessen so:

* `throttle:60,1` an der Route (Laravel hasht die Adresse für den Schlüssel,
  gespeichert wird sie nicht). Seit **#43** ist das ein Limit **je Besucher**,
  auch hinter Cloudflare: der äußere nginx-Block löst `CF-Connecting-IP` auf,
  `$request->ip()` ist die echte Adresse (`docs/echte-besucher-ip.md`,
  abgenommen 16.10.2026). Vorher zählte die Edge-Adresse, ein Eimer für viele
  Besucher (Befund S3 des Reviews #39). Unverändert gilt: ein 429 verliert
  Impressionen unsichtbar, weil `sendBeacon` keine Antwort auswertet.
* höchstens `ad-slots.impressions.max_entries` Meldungen je Aufruf, Rumpf hart
  auf 8 kB begrenzt
* innerhalb **eines** Beacons zählt jede Kombination aus Anker, Belegung,
  Gerät und Ereignisart genau einmal. Über mehrere Aufrufe hinweg greift nur das Rate-Limit:
  ein präparierter Absender kommt damit auf 60 × `max_entries` Meldungen je
  Minute.
* unbekannte Katalogschlüssel und Geräte außer `mobile`/`desktop` fallen weg
* als Ereignisart zählt nur das Literal `delivered` in den Nenner; jeder andere
  Wert — auch ein fehlender — gilt als Sichtkontakt. So bleibt eine Meldung aus
  einem Browser mit dem alten, noch zwischengespeicherten `ads.js` das, was sie
  war; umgekehrt würde der Rückfall den Nenner aufblähen
* **Herkunftstest** (#42): `Sec-Fetch-Site` muss `same-origin` sein; fehlt der
  Header (Browser ohne Fetch-Metadata), muss der Host aus `Origin` die
  Portal-Domain sein. Fehlen beide, wird verworfen. Ohne das könnte eine
  fremde Seite ohne CSRF-Token per Formular-POST mit `text/plain` zählen —
  ohne Preflight, CORS verhindert das nicht. Ein Probe-Aufruf von Hand (wie in
  der Abnahme #37) braucht deshalb `-H 'Origin: https://<portal-domain>'`,
  sonst erreicht er den Zähler gar nicht.
* **die gemeldete `placement_id` muss es geben** (#42): aktiv, diesem Portal
  zugeordnet und auf genau diesem Anker. Geprüft wird gegen eine Liste der
  gültigen Ids je Portal, die
  `ad-slots.impressions.placement_cache_ttl` Sekunden (Vorgabe 300) gecacht
  wird (`AdCache::impressionPlacementsKey()`, eine Abfrage je Fenster statt je
  Beacon). Unbekannt heißt nicht „verworfen", sondern `0` — das bedeutet in
  diesen Tabellen schon „Belegung nicht mehr bekannt", und zwischen
  Auslieferung und Beacon kann eine Belegung pausiert worden sein. Damit ist
  die Zeilenzahl auf Anker × Gerät × (echte Belegungen + 1) gedeckelt statt
  auf 2^64 frei wählbare Werte je Anker. Geprüft wird `active()` +
  `forTenant()`, bewusst nicht `deliverable()`: ein in derselben Sekunde
  abgelaufener Zeitraum soll eine echte Impression nicht zur Null-Belegung
  machen.
* Portal ist immer der Tenant der Anfrage, nie ein Wert aus dem Rumpf
* bekannte Bot-Kennungen (`App\AntiSpam\Support\BotTraffic`) zählen nicht
* geantwortet wird immer `204`, auch auf Unsinn — wie bei den CSP-Berichten

`placement_id` ist bewusst **ohne** Fremdschlüssel und nicht nullable: eine
gelöschte Belegung darf ihre Geschichte nicht mitnehmen (sonst wäre der
Vergleich vorher/nachher nach jeder Umbuchung weg), und `0` heißt „Belegung
nicht mehr bekannt". Ein `NULL` wäre hier schädlich — MySQL zählt NULLs im
Unique-Index als verschieden, der Zähler würde sich bei jedem Lauf
vervielfachen.

### 14.3 Verdichtung

```
Schedule::job(new App\Ads\Jobs\AggregateAdImpressions)->hourlyAt(7)->onOneServer();
php artisan ads:aggregate-impressions   # von Hand, sofort, mit Ausgabe
```

Verdichtet werden nur **abgeschlossene** Stunden; die laufende bleibt liegen,
sonst wäre ein Portal halb verdichtet, während der nächste Besucher dieselbe
Zeile hochzählt. Geschrieben wird mit `ON DUPLICATE KEY UPDATE impressions =
impressions + VALUES(impressions), deliveries = deliveries +
VALUES(deliveries)` — hochzählen, nicht setzen: ein zweiter Lauf
derselben Stunde findet den Puffer leer und ändert nichts, ein Lauf mitten im
Tag überschreibt die früheren Stunden nicht. Der Job ist zusätzlich
`ShouldBeUnique` und steht mit `onOneServer` im Scheduler, weil ein echter
Doppellauf die Zahlen verdoppeln würde.

Derselbe Lauf löscht Tageszeilen jenseits der Aufbewahrung.

### 14.4 Auswertung im Panel

`App\Filament\Admin\Widgets\AdImpressionsWidget` (Dashboard, nur für Betreiber
des Netzes, `AdsAdminAccess`):

* Summen der letzten **7 oder 30 Tage** (Schalter im Widget)
* je Anker: Mobil, Desktop, Sichtkontakte, **Ausgeliefert**, **Sichtbar**
  (die Quote), Anteil mobil — absteigend nach Sichtkontakten
* darunter dieselben Summen **je Portal**
* die Spalte „Sichtbar" ist nach §13.3 gefärbt: unter 40 % rot, über 70 %
  grün, dazwischen neutral. Ein Gedankenstrich heißt „kein Nenner im Fenster",
  nicht 0 % (Ticket #47)
* Portalfilter ist der Dashboard-Filter `tenant_id` (dieselbe Quelle wie beim
  Premium-Umsatz-Widget); mit gesetztem Portal entfällt die Portaltabelle
* gelesen wird ausschließlich `ad_impression_daily`, nie der Puffer — die
  Zahlen sind damit bis zur letzten abgeschlossenen Stunde vollständig und
  danach stabil; 60 Sekunden gecacht
* beide Gruppenabfragen laufen Index-only über die abdeckenden Indizes
  `ad_impr_daily_day_slot_cover2` (`day, slot_key, device, impressions,
  deliveries`) und `ad_impr_daily_day_tenant_cover2` (`day, tenant_id, device,
  impressions, deliveries`) — die `…_cover`-Fassungen ohne `deliveries` sind
  mit #47 ersetzt worden, weil die Abfrage mit `sum(deliveries)` über sie nicht
  mehr Index-only gelaufen wäre —,
  der Portalfilter über das Präfix des Unique-Index. Ohne sie wählt MySQL ab
  einem nennenswerten Fensteranteil den Full Scan — Messung und Begründung in
  `docs/ad-slots-security-review.md` §13.7 (Ticket #44)

Damit ist §13.3 vollständig ohne AdSense-Report entscheidbar: die Quote, an der
die 40-/70-%-Regel hängt, steht im Widget und ist portal- und ankergenau. Ein
Anker mit vielen Impressionen und keiner Wirkung ist ein Kandidat für
`paused`, ein Anker mit hohem Mobilanteil gehört nicht in die Seitenspalte.

### 14.5 Dateien

| Datei | Rolle |
| --- | --- |
| `config/ad-slots.php` | `impression_tracking`, `impressions.*` |
| `resources/js/ads.js` | Beobachter, Bündelung, Beacon |
| `resources/views/components/sun/ad-slot.blade.php` | `data-ad-placement`, `data-ad-track` |
| `app/View/Components/Sun/AdSlot.php` | `$trackImpressions`, `trackAttribute()` |
| `app/Ads/Http/Controllers/AdImpressionController.php` | Annahme des Beacons |
| `app/Ads/Support/AdImpressionRecorder.php` | Prüfung und Zähler im Puffer |
| `app/Ads/Support/AdCache.php` | `impressionPlacementsKey()` — gültige Belegungs-Ids je Portal |
| `app/Ads/Jobs/AggregateAdImpressions.php` | stündliche Verdichtung, Pruning |
| `app/Console/Commands/AdsAggregateImpressionsCommand.php` | Verdichtung von Hand |
| `app/Console/Commands/AdsImpressionAbnahmeCommand.php` | Abnahmezahlen des Widgets im Terminal (#37) |
| `app/Ads/Support/AdImpressionStats.php` | Auswertung für das Widget |
| `app/Filament/Admin/Widgets/AdImpressionsWidget.php` | Darstellung im Panel |
| `database/migrations/…_create_ad_impression_daily_table.php` | Tagesaggregat |
| `database/migrations/…_create_ad_impression_buffer_table.php` | Stundenpuffer |
| `database/migrations/…_add_covering_indexes_to_ad_impression_daily_table.php` | abdeckende Indizes des Widgets (#44) |
| `database/migrations/…_add_deliveries_to_ad_impression_tables.php` | Nenner `deliveries` in beiden Tabellen, Indizes nachgezogen (#47) |

### 14.6 Abnahme auf der Produktion (#37)

Seit dem 16.10.2026 zählt die Produktion echt: die beiden zentralen Tabellen
sind migriert, `impression_tracking` ist über die Codevorgabe `true` (die
`.env` dort hat den Schlüssel nicht), und `POST /_ads/impressions` antwortet
mit **204 ohne `Set-Cookie`** — der Nachweis für „consent-unabhängig, keine
personenbezogenen Daten".

Die Kette läuft bis zum Widget durch, belegt am ersten Tag: der Scheduler hat
um 13:07 UTC verdichtet (`ad_impression_daily` 74 Zeilen / **391
Impressionen**, mobil 269 / desktop 122, 14 Portale, sechs Anker), im Puffer
liegt nur noch die laufende Stunde. Auch die vier Zusagen des Widgets stehen
damit auf echten Zahlen — Anker nach Gesamtzahl, Mobil gegen Desktop,
Portalfilter `tenant_id`, Umschaltung 7/30 Tage. Offen ist allein die
**Laufzeit**: das Siebentagefenster ist am 23.10.2026 reif, und der Vergleich
mit dem AdSense-Report braucht Kontozugang (#48).

Ohne Browser auslesen — dieselbe Quelle wie das Widget, nur Lesezugriff:

```bash
php artisan ads:impressionen-abnahme                     # beide Fenster, alle Portale
php artisan ads:impressionen-abnahme --tenant=52         # wie der Dashboard-Filter
php artisan ads:impressionen-abnahme --tenant=52 --tage=7 --adsense=1234
```

Das Kommando zeigt zusätzlich **Tage mit Daten im Fenster** (Reife des
Siebentagefensters) und rechnet mit `--adsense=` die Abweichung zum Report:
über **Faktor 2** meldet es „Befund" und endet mit Fehlercode.

Messprotokoll:
`docs/messungen/werbeplaetze-golive/impression-abnahme-2026-10-16.txt`,
Restschritte als Checkliste in `docs/ad-slots-golive.md` §6.3.
