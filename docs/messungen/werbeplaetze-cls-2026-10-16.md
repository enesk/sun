# Werbeplätze SUN-V2 — CLS und 360-px-Aufnahmen

**Datum:** 16.10.2026 · **Ticket:** #5 (`<x-sun.ad-slot>`, Anker in allen
Seitentypen) · **Codestand:** `629a4a30` plus die Änderungen aus #5

Gemessen wird die Frage aus der Abnahme von #5: **bleibt das Layout ruhig,
wenn ein Anker belegt ist** — und bleibt die Seite ohne Belegung so, wie sie
heute ist. Grundlage ist der Messaufbau aus #34/#91 (siehe
`docs/messungen/` und den Gedächtnis-Eintrag `lighthouse-messaufbau-portal-lokal`).
Rohdaten: `werbeplaetze-cls-2026-10-16/` (Lighthouse-JSON je Lauf),
Aufnahmen: `werbeplaetze-360/`.

## 1. Aufbau

Es gibt keine Staging-Umgebung, gemessen wird darum lokal:

```
npm run build                      # Pflicht, sonst fehlen die .ad-slot-Regeln
php -S 127.0.0.1:8099 -t public <router>   # ohne Debugbar
node <gzip-proxy> : 8098 -> 8099   # der eingebaute Server komprimiert nicht
Chrome --headless=new --remote-debugging-port=9222 \
  --host-resolver-rules="MAP elektriker.test 127.0.0.1:8098"
npx lighthouse@13 <url> --port=9222 [--preset=desktop] --only-categories=performance
```

* **Portal:** Tenant 30 (`elektrikerportal.com`), Theme `sun-v2`. Für die Dauer
  der Messung lief seine Domain auf `elektriker.test` (von Herd verlinkt) und
  wurde danach zurückgestellt.
* **Belegungen:** alle 32 Anker des Katalogs v3 belegt,
  `applies_to_all_tenants`, Priorität 100. 27 davon mit einem
  **HtmlProvider**-Werbemittel (300 × 250, Sandbox-iframe über
  `/_ads/html/{adUnit}`), 5 mit einem **AdSense**-Werbemittel
  (`search_in_feed_3`, `city_in_feed_3`, `guide_in_article_1`,
  `guide_in_article_2`, `home_after_services`).
* **Einwilligung:** Lighthouse läuft ohne Cookie. Die AdSense-Anker stehen
  darum als **reservierter, leerer Container** da (so wie vor der
  Einwilligung, Abschnitt 9 der Architektur), die HTML-Anker sind gefüllt.
  Damit deckt ein Lauf beide Zustände ab.
* **Ratgeber-Seiten:** kein lokales sun-v2-Portal hat einen ausgelieferten
  Ratgeber (`GuidePageData::categoryTiles()` ist überall leer, `/ratgeber`
  fällt auf den Blog zurück). Für die Messung wurden in der Tenant-DB eine
  Ratgeber-Kategorie angelegt und drei veröffentlichte Beiträge daran
  gehängt; beides wurde danach zurückgenommen.
* **Streuung:** je Seite und Zustand drei Läufe mobil. LCP und der
  Performance-Wert streuen im lokalen Aufbau stark, CLS ist stabil — darum ist
  hier **nur CLS** die Aussage.

## 2. CLS, mobil (Lighthouse-Vorgabe „mobile", 3 Läufe)

| Seite | Anker auf der Seite | CLS vorher (3 Läufe) | CLS nachher (3 Läufe) |
| --- | --- | --- | --- |
| Startseite (`/`) | 6 | 0.000 | 0.000 |
| Firmensuche (`/firmen`) | 6 | 0.000 | 0.000 |
| Firmenprofil | 6 | 0.000 | 0.000 |
| Stadtseite (`/staedte/hamburg`) | 4 | 0.000 | 0.000 |
| Ratgeber-Artikel | 5 | 0.000 | 0.000 |
| Ratgeber-Übersicht (`/ratgeber`) | 2 | 0.000 | 0.000 |

## 3. CLS, Desktop (`--preset=desktop`, 1 Lauf)

Lighthouse ist im lokalen Aufbau auf diesen Seiten zu langsam für einen
zweiten vollständigen Durchlauf (einzelne Läufe über 10 Minuten auf dem
Firmenprofil). Der Desktop-Fall wurde darum mit derselben Metrik, aber ohne
Lighthouse gemessen: `PerformanceObserver({type: 'layout-shift'})` in einem
`<iframe width="1280">`, dazu ein geskripteter Durchlauf durch die Seite,
damit die nachgeladenen Anker wirklich einsetzen.

| Seite | Anker | CLS bei 1280 px |
| --- | --- | --- |
| Startseite | 6 | 0,0001 |
| Firmensuche | 6 | 0,0004 |
| Firmenprofil | 6 | 0,0001 |
| Stadtseite | 4 | 0,0003 |
| Ratgeber-Artikel | 5 | 0,0002 |
| Ratgeber-Übersicht | 2 | 0,0000 |

Die Restwerte stammen nicht aus den Ankern — in keinem Lauf wies der
Beobachter einem Werbeplatz-Container eine Verschiebung zu.

## 4. Querlauf bei 360 px

`scrollWidth` allein genügt im Theme sun-v2 nicht: `overflow-x: clip` an `html`
und `body` (#18) verdeckt einen Überlauf. Darum wird die Seite in einem
`<iframe width="360">` geladen, mit `window.scrollTo(500, 0)` wirklich gezogen
und zusätzlich jedes Element in einem Anker auf seine Breite geprüft.

| Seite | `scrollWidth` | wirklich gezogen (`window.scrollX` nach `scrollTo(500, 0)`) | Elemente im Anker breiter als 360 px |
| --- | --- | --- | --- |
| Startseite | 345 | 0 | keine |
| Firmensuche | 345 | 0 | keine |
| Firmenprofil | 345 | 0 | keine |
| Stadtseite | 345 | 0 | keine |
| Ratgeber-Artikel | 345 | 0 | keine |
| Ratgeber-Übersicht | 345 | 0 | keine |

`scrollWidth` ist 345 statt 360, weil der Rahmen in Headless eine Bildlaufleiste
bekommt. Entscheidend ist die dritte Spalte: die Seite lässt sich nicht ziehen.
Geprüft wurde mit einem **728 × 90**-Sandbox-iframe in den Banner-Ankern —
also einem Werbemittel, das doppelt so breit ist wie der Viewport. Es wird
durch `.ad-slot__area iframe { max-width: 100 % }` auf die Spaltenbreite
gebracht.

## 5. Aufnahmen bei 360 px

Alle Aufnahmen mit belegten Ankern, Breite 360 px, in
`docs/messungen/werbeplaetze-360/`:

* Startseite (`/`) — `werbeplaetze-360/start-360.png`
* Firmensuche (`/firmen`) — `werbeplaetze-360/suche-360.png`
* Firmenprofil — `werbeplaetze-360/profil-360.png`
* Stadtseite (`/staedte/hamburg`) — `werbeplaetze-360/stadt-360.png`
* Ratgeber-Artikel — `werbeplaetze-360/ratgeber-360.png`
* Ratgeber-Übersicht (`/ratgeber`) — `werbeplaetze-360/ratgeber-index-360.png`

## 6. Befund

1. **CLS bleibt 0,000 auf allen sechs Seitentypen**, mobil wie Desktop, mit
   allen 32 Ankern belegt. Die Vorgabe der Abnahme (< 0,1) ist damit mit
   großem Abstand erfüllt, und sie ist nicht knapp erfüllt, sondern die
   Reservierung greift vollständig.
2. **Ohne Belegung ist die Seite unverändert.** Der Vorher-Lauf lief auf
   demselben Codestand, nur ohne Belegungen: die Seiten enthalten dann je
   Anker genau einen HTML-Kommentar und keinen Container.
3. **Eine Fehlbelegung verschiebt sehr wohl.** Der erste Nachher-Lauf lief mit
   einem 300 × 250-Werbemittel auf **allen** Ankern, also auch auf den
   Banner-Ankern mit 90/100 px Reservierung. Ergebnis auf der Firmensuche:
   **CLS 0,0442**, verschobenes Element
   `div.mt-6.grid.gap-8.xl:grid-cols-[1fr_18rem]` — die Ergebnisliste, nach
   unten gedrückt von `search_above_results`. Rohdatei:
   `fehlformat-mobile-suche-1.json`. Das ist kein Fehler der Komponente,
   sondern eine Belegung, die der Katalog gar nicht zulässt
   (`search_above_results` erlaubt nur `responsive` und
   `leaderboard_728x90`). **Für #8 heißt das: die Formatprüfung beim Anlegen
   einer Belegung ist nicht Komfort, sondern das, was CLS unten hält.**
4. **Nachladen greift.** Beim Laden ohne Scrollen ist nur ein Teil der Anker
   eingesetzt (`[data-ad-anchor][data-ad-geladen]`: 1–3 von 2–6); der Rest
   kommt beim Heranscrollen. Auf Seiten ohne Anker meldet die Konsole nichts —
   `initSlotAds()` steigt bei leerer Trefferliste sofort aus.
5. **Loader genau einmal.** Mit vier AdSense-Ankern auf der Firmensuche steht
   `adsbygoogle.js` einmal im `<head>` (geprüft per Abruf: `loader=1 ins=4`).
   Ohne AdSense-Belegung auf der Seite steht er gar nicht da (`loader=0`).
