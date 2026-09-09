# Messprotokoll Layout-Shift der Ratgeber-Seitenspalte (Ticket #91)

Datum: 09.09.2026
Gemessene Seite: `/ratgeber/heizungstausch-forderung-in-bayern-was-gilt-2026`
(Tenant 1, `sanitaer.test`, Theme `starter`, Beitrag 19 — dieselbe Seite wie in
`ratgeber-template-messung-2026-09-09.md`).
Rohdaten: `ratgeber-sidebar-cls-2026-09-09.json`.

Anlass: In #34 war `sidebar_sticky` nicht messbar, weil die Ratgeber-Seite den
Platz nicht auslieferte. Seit #83 stehen `sidebar_top` und `sidebar_sticky` in
`pages/blog/_sidebar-article.blade.php`.

## 1. Messaufbau

Es gibt weiterhin keine erreichbare Staging-Instanz (#90 ist mit dem Ergebnis
geschlossen, dass Staging-Host und Konten Handarbeit von Enes bleiben). Gemessen
wurde deshalb wie in #34 lokal gegen einen Aufbau, der gebaute Assets und
komprimierte Auslieferung nachbildet:

```bash
npm run build                                     # Pflicht, siehe Abschnitt 5
APP_ENV=t91 APP_DEBUG=false APP_URL=http://sanitaer.test \
  PHP_CLI_SERVER_WORKERS=10 php artisan serve --host=127.0.0.1 --port=8099
node /tmp/t34_proxy.mjs                           # gzip-Proxy auf 8098
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new \
  --remote-debugging-port=9222 --user-data-dir=/tmp/t91_chrome \
  --host-resolver-rules="MAP sanitaer.test 127.0.0.1:8098"
npx lighthouse@13 "http://sanitaer.test/ratgeber/<slug>" --port=9222 \
  --only-categories=performance [--preset=desktop]
```

Lighthouse 13.4.1, Chrome 152. Mobil 412 px mit Standard-Drosselung, Desktop
1350 px.

### Anzeigenplaetze fuer die Messung

Tenant 1 hatte bereits einen aktiven Platz `sidebar_top`, aber keinen
`sidebar_sticky`. Fuer die Messung wurde in `ad_slots` des Mandanten

- `sidebar_top` voruebergehend auf einen Platzhaltercode umgestellt und
- ein aktiver Platz `sidebar_sticky` angelegt,

beide mit demselben Code wie in #34: ein leeres `<div>`, das nach 1,5 s per
`setTimeout` einen 300x250-Block einsetzt. Das ist der ungünstigste Fall fuer
CLS — der Anzeigeninhalt kommt nach dem ersten Aufbau der Seite.

Nach der Messung wurde `sidebar_top` aus dem Backup zurueckgeschrieben und der
Testplatz `sidebar_sticky` geloescht. Der Bestand der Tabelle ist unveraendert.

## 2. Ergebnis: die beiden Seitenspalten-Plaetze

Je drei Laeufe, `auto_ads` deaktiviert (Begruendung in Abschnitt 3).

| Formfaktor | CLS | Ziel | Performance |
|---|---|---|---|
| Desktop (1350 px) | 0,0167 in 3 von 3 Laeufen | < 0,1 | 100 / 100 / 99 |
| Mobil (412 px) | 0,0062 in 3 von 3 Laeufen | < 0,1 | 90 / 99 / 99 |

Ziel aus `design/ratgeber-template.md`, Abschnitt 5, ist erfuellt.

Gemessene Shifts, vollstaendig:

- Desktop: `header.header-floating > … > nav.hidden` (0,01669) beim Alpine-Start
  und der Pfeil des Inhaltsverzeichnisses `svg.ratgeber-toc__chevron`
  (0,0000044, gerundet 0).
- Mobil: `header.header-floating > … > button.md:hidden` (0,00618) — derselbe
  Menue-Button wie in #34.

**`sidebar_top` und `sidebar_sticky` tragen 0 zum CLS bei**, obwohl beide ihren
Inhalt erst nach 1,5 s einsetzen. Die reservierte Geometrie aus
`app/View/Components/AdSlot.php` (`min-w-[300px] min-h-[250px]` bzw.
`lg:min-w-[300px] lg:min-h-[250px]`) traegt. Auf Mobil entfaellt die Spalte
unter 1024 px vollstaendig; dort sind beide Plaetze nicht im Layout.

Beide verbleibenden Shifts gehoeren zum Portal-Header, nicht zum
Ratgeber-Template, und liegen einzeln wie zusammen weit unter dem Zielwert.

## 3. Befund: `auto_ads` bricht das Ziel

Tenant 1 hat einen aktiven Platz `auto_ads` mit echtem AdSense-Code
(`ca-pub-2563111817853634`). Der laedt im Test tatsaechlich von
`pagead2.googlesyndication.com`, setzt einen Anker-Banner und schreibt dabei
`padding-bottom` auf das `<body>`. Ergebnis mit eingeschaltetem `auto_ads`:

| Formfaktor | CLS je Lauf | betroffener Knoten |
|---|---|---|
| Desktop | 1,0167 / 0,0167 / 1,0167 | `body.min-h-screen` mit `style="padding-bottom: 335px"`, Score 1,0 |
| Mobil | 0,0062 / 0,0062 / 0,0062 / 0,5432 | derselbe `body`-Knoten, Score 0,537 |

Der Effekt tritt nicht bei jedem Lauf auf (2 von 3 Desktop-, 1 von 4
Mobil-Laeufen); die Anzeigenauslieferung selbst antwortet auf der Testdomain mit
403, der Anker-Banner wird trotzdem eingehaengt. Wenn er kommt, ist das Ziel
< 0,1 um das Zehnfache verfehlt.

Das ist ein anderer Fehler als der dieses Tickets: `auto_ads` wird in
`resources/views/components/ad-slot.blade.php` bewusst ohne CLS-Container
ausgegeben, weil Auto Ads ihre Position selbst bestimmen. Dafuer ist Ticket #100
angelegt. Die Zahlen in Abschnitt 2 sind deshalb ohne `auto_ads` gemessen — sie
beantworten die Frage des Tickets nach den beiden Seitenspalten-Plaetzen.

## 4. Was diese Messung nicht ist

Kein Lauf auf einer oeffentlichen Staging-URL. Der Ticket-Text verlangt das,
die Voraussetzung dafuer (#90, Schritt 1) ist aber weiterhin offen und liegt
ausserhalb des Repositories. Was der lokale Aufbau nicht nachbildet: HTTPS,
HTTP/2, echte Latenz zum Anzeigenserver und ein freigeschaltetes AdSense-Konto.
Auf das Ergebnis fuer `sidebar_top` und `sidebar_sticky` wirkt keiner dieser
Punkte — die reservierte Hoehe steht im CSS und ist von der Auslieferung
unabhaengig. Fuer den Befund aus Abschnitt 3 gilt das nicht: dort kann eine
echte Auslieferung mit freigeschalteten Anzeigen andere und groessere Shifts
ergeben.

## 5. Build

`public/build` war beim Start dieser Messung veraltet: die Regeln der
Seitenspalte aus #83 (`.ratgeber-sidebar`, `.ratgeber-sidebar__sticky`) fehlten
im Bundle, obwohl #85 den Stand aufgefrischt hatte. Ohne sie waere die Messung
wertlos gewesen. `npm run build` wurde deshalb vor der Messung ausgefuehrt; das
neue Bundle `assets/app-Dot5BQzO.css` enthaelt alle sechs Klassen.
