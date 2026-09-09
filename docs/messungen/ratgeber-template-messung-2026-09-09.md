# Messprotokoll Ratgeber-Template (Ticket #34)

Datum: 09.09.2026
Gemessene Seite: `/ratgeber/heizungstausch-forderung-in-bayern-was-gilt-2026`
(Tenant 1, `sanitaer.test`, Entwurf 13 mit Kurzantwort, Key-Facts, FAQ, Quellen,
Regionalblock als H2 im Fliesstext — `region_scope = state`, `DE-BY`).

## 1. Messaufbau

Es gibt keine erreichbare Staging-Instanz. Gemessen wurde deshalb lokal gegen
einen Aufbau, der die beiden Eigenschaften nachbildet, die das Ergebnis
bestimmen: gebaute Assets und komprimierte Auslieferung.

```bash
npm run build                                     # Pflicht, siehe Abschnitt 6
php artisan content:… / Publisher::publish()      # Entwurf 13 veroeffentlicht
APP_ENV=t34 PHP_CLI_SERVER_WORKERS=10 \
  php artisan serve --host=127.0.0.1 --port=8099  # ohne Debugbar, APP_DEBUG=false
node /tmp/t34_proxy.mjs                           # gzip-Proxy auf 8098
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new \
  --remote-debugging-port=9222 --user-data-dir=… \
  --host-resolver-rules="MAP sanitaer.test 127.0.0.1:8098"
npx lighthouse "http://sanitaer.test/ratgeber/<slug>" --port=9222 \
  --only-categories=performance,seo,accessibility,best-practices
```

Der gzip-Proxy ist noetig, weil `artisan serve` nicht komprimiert. Ohne
Kompression laedt das CSS-Bundle mit 419 KB statt 64 KB, der Performance-Wert
faellt allein dadurch von 100 auf 87 (FCP 3,2 s statt 1,2 s). Jede reale
Auslieferung ueber nginx komprimiert; der komprimierte Lauf ist der
aussagekraeftige.

Fuer die Messung mit Titelbild wurden drei WebP-Varianten (640/960/1440,
16:9, 2,5–6,9 KB) in `assets_json.hero.variants` eingetragen, weil der
Entwurf kein von #16 erzeugtes Bild hat. Alle Hilfsdaten (Bilddateien,
Anzeigen-Platzhalter, HowTo-Schritte) wurden nach der Messung wieder entfernt.

## 2. Lighthouse Mobile

Lighthouse 13.4.1, Formfaktor mobil (412 px), Standard-Drosselung.

| Kategorie | Wert | Ziel | Ergebnis |
|---|---|---|---|
| Performance | 100 (Median aus 7 Laeufen; Spanne 87–100) | ≥ 90 | erfuellt |
| SEO | 100 | 100 | erfuellt |
| Accessibility | 97 | ≥ 95 | erfuellt |
| Best Practices | 78 | — | Messartefakt, siehe unten |

Metriken des Referenzlaufs:

| Metrik | Wert |
|---|---|
| First Contentful Paint | 1,2 s |
| Largest Contentful Paint | 1,4 s |
| Speed Index | 1,5 s |
| Total Blocking Time | 0 ms |
| Cumulative Layout Shift | 0,006 |

Die Spanne 87–100 stammt aus dem lokalen Aufbau, nicht aus der Seite: in zwei
von sieben Laeufen sprang LCP auf 3,1 bzw. 4,0 s, ohne dass eine Anfrage laenger
als 75 ms dauerte. CLS, TBT und alle Audits blieben dabei unveraendert.

`is-on-https` und `redirects-http` schlagen fehl, weil lokal ueber HTTP gemessen
wurde. Das ist der einzige Grund fuer Best Practices 78.

Offene Abwertung in Accessibility: `color-contrast` (Gewicht 7). Betroffen sind
Portal-Bausteine, nicht die Ratgeber-Bloecke selbst — Details in Ticket #82.

## 3. Layout-Shifts

CLS 0,006 in allen sieben Laeufen, Ziel < 0,1 deutlich erfuellt.

- Titelbild: `<img>` mit `width`, `height`, `srcset`, `sizes="100vw"`,
  `fetchpriority="high"`, `loading="eager"`. Kein Beitrag zum CLS.
- Anzeigenplaetze: gemessen mit Platzhaltern, die ihren Inhalt erst nach
  1,5 s einsetzen. `content_after_intro` (`min-h-[250px]`, ab lg `min-h-[90px]`),
  `mobile_sticky_bottom` (`min-h-[50px]`) und `footer_above` reservieren ihre
  Hoehe vorab, kein Beitrag zum CLS.
- `sidebar_sticky` **wird auf der Ratgeber-Seite nicht ausgeliefert** und war
  deshalb nicht messbar. Die Ratgeber-Sidebar (`pages/blog/_sidebar.blade.php`)
  enthaelt weder `sidebar_top` noch `sidebar_sticky` — siehe Ticket #83.
  Nachgeholt seit #83: beide Plaetze stehen jetzt in
  `pages/blog/_sidebar-article.blade.php` und sind gemessen, Ergebnis in
  `ratgeber-sidebar-cls-messung-2026-09-09.md` (Ticket #91).
- Einziger gemessener Shift: der Menue-Button im Header
  (`header.header-floating > … > button.md:hidden`, Score 0,0062), ausgeloest
  beim Alpine-Start. Gehoert zum Portal-Layout, nicht zum Ratgeber-Template.

## 4. Strukturierte Daten

Google Rich Results Test hat keine oeffentliche Schnittstelle und braucht
entweder eine oeffentlich erreichbare URL oder eine Eingabe von Hand in der
Oberflaeche. Beides ist ohne Staging nicht moeglich; dieser Schritt bleibt offen
(Ticket #84). Ersatzweise geprueft wurde mit dem Schema-Markup-Validator
(`validator.schema.org`, dieselbe Parser-Basis) plus einem Abgleich gegen die
dokumentierten Google-Anforderungen.

Ergebnis: **0 Fehler, 0 Warnungen**, vier Objekte.

| Typ | Pflichtfelder | Bemerkung |
|---|---|---|
| Article | vollstaendig | headline, image, datePublished, dateModified, author, publisher, inLanguage, wordCount, citation, speakable, publishingPrinciples |
| BreadcrumbList | vollstaendig | 3 ListItems mit position, name, item |
| FAQPage | vollstaendig | 5 Fragen, alle mit `acceptedAnswer.text` |
| HowTo | vollstaendig | nur mit testweise gesetzten Schritten geprueft, 3 `HowToStep` mit position/name/text |

Anmerkungen:

- Kein Artikel im Bestand bringt `outline_json.howto` mit. Fuer die Pruefung
  wurden drei Schritte gesetzt und danach wieder entfernt. Google zeigt HowTo
  seit September 2023 nicht mehr als Rich Result an; das Markup bleibt korrekt,
  liefert aber keine Suchergebnis-Darstellung mehr.
- `Article.image` und `og:image` uebernahmen die URL unveraendert aus
  `assets_json.hero.variants`. Im gemessenen Graphen stand deshalb
  `"image": "/t34/hero-1440.webp"` — relativ. Die Annahme, der Produktivpfad
  (`AssetStorage::url()`) liefere immer absolute URLs, traegt nicht: die Platte
  `public` baut ihre URL aus `APP_URL`, ohne gesetzten Wert entsteht ein Pfad.
  Seit #84 loest `ArticleSeoService::meta()` relative Bildpfade gegen den Host
  der Anfrage auf; og:image, twitter:image und `Article.image` sind damit auf
  jeder Domain absolut. Durchfuehrung des Google-Laufs:
  `rich-results-test-anleitung.md`.
- `Organization` traegt weder `logo` noch `sameAs`, weil Tenant 1 keine
  Betreiberdaten hinterlegt hat. Datenluecke, kein Template-Fehler — Ticket #78.

Der ausgelieferte Graph liegt als `ratgeber-jsonld-2026-09-09.json` daneben.

## 5. Gegenprobe ohne JavaScript

Geprueft am ausgelieferten HTML (kein Skript ausgefuehrt) und am gebauten CSS.

- Inhaltsverzeichnis: `<details class="ratgeber-toc" open>`, 8 Sprungmarken,
  alle 8 Ziele als `id` im Dokument vorhanden.
- FAQ: 5 `<details>`, die Antworten stehen im Quelltext, der erste Eintrag ist
  offen. Kein Alpine, keine Klasse, die ohne JavaScript verbergen wuerde.
- Quellenliste: `<ol class="ratgeber-sources__list">` mit 2 Eintraegen, nicht in
  einem Aufklapper versteckt.
- Key-Facts-Tabelle und Regionalblock stehen ebenfalls serverseitig im HTML.
- Weder `x-cloak` noch eine `opacity-0`-Startregel auf den Ratgeber-Klassen.

Alle drei geforderten Bausteine bleiben ohne JavaScript bedienbar.

## 6. Wichtig fuer die Wiederholung

Das eingecheckte Build-Artefakt unter `public/build/` war zum Messzeitpunkt vom
12.05. und enthielt **keine einzige `ratgeber-*`-Regel**. Ein Lauf gegen dieses
Artefakt misst eine ungestylte Seite (Accessibility 93 statt 97, weil
`link-in-text-block` mangels Unterstreichung der Quellenlinks fehlschlaegt).
Vor jeder Messung `npm run build` ausfuehren. Siehe Ticket #85.
