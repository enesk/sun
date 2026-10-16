# Werbung und Einwilligung — Netzwerk-Mitschnitt und CLS

**Datum:** 16.10.2026 · **Ticket:** #25 (Beleg zur Definition of Done von #9)
· **Codestand:** `dfcd1073` plus die Arbeit aus #9

Gemessen wird die Zusage aus `docs/ad-slots.md` 9.1: **ohne Einwilligung geht
kein Request an Google**, nach der Einwilligung läuft die Ausspielung, bei
ausdrücklicher Ablehnung klappt der reservierte Platz ein — und eine
Einwilligung mitten im Besuch verschiebt das Layout nicht.

Rohdaten:

* `ads-einwilligung-mitschnitt.json` — Netzwerk-Mitschnitt, vier Fälle auf zwei
  Seitentypen (`scripts/csp-messung.mjs`)
* `ads-einwilligung-cls.json` — CLS bei Einwilligung im laufenden Besuch,
  Werbemittel im Format `responsive` (`scripts/ads-einwilligung-cls.mjs`)
* `ads-einwilligung-cls-festes-format.json` — dieselbe Messung mit einem
  Werbemittel im festen Katalogformat `leaderboard_728x90`

## 1. Aufbau

Keine Staging-Umgebung, deshalb lokal — Aufbau wie
`csp-browser-messung-2026-10-08.md`:

```bash
npm run build                                     # public/build/ ist nicht versioniert
APP_ENV=messung PHP_CLI_SERVER_WORKERS=10 \
  php artisan serve --host=127.0.0.1 --port=8123  # .env.messung mit CSP_MODE=report, APP_DEBUG=false

"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new \
  --remote-debugging-port=9222 --user-data-dir=/tmp/t25_chrome \
  --host-resolver-rules="MAP elektrikerportal.com 127.0.0.1:8123"

node scripts/csp-messung.mjs --seiten=/tmp/t25_seiten.json \
  --aus=docs/messungen/ads-einwilligung-mitschnitt.json --warten=8000

node scripts/ads-einwilligung-cls.mjs \
  --url="http://elektrikerportal.com/,http://elektrikerportal.com/27684-blosfeld-telekommunikation-u-elektro" \
  --aus=docs/messungen/ads-einwilligung-cls.json
```

Chrome 155 headless. Die Portal-Domain wird von Chrome auf den lokalen Server
gemappt; der Tenant behält seine echte Domain (`elektrikerportal.com`), es gibt
lokal keinen Tenant `elektriker.test`.

* **Portal:** Tenant 30 (`elektrikerportal.com`, Theme `sun-v2`), für die
  Messung `ads_enabled=1` und Publisher-ID `ca-pub-1901800422608466`; danach
  wieder auf den Stand davor zurückgesetzt (`ads_enabled=0`, Publisher-ID
  `null`).
* **Belegungen:** Startseite `home_after_hero` (AdSense, `responsive`) und
  `home_after_services` (HtmlProvider, Sandbox-iframe, braucht **keine**
  Einwilligung); Firmenprofil vier AdSense-Anker aus der Übernahme (#15) mit
  echten Anzeigenblock-IDs — `company_after_header_card` (mobil),
  `company_after_reviews`, `company_before_footer`, `company_sidebar_sticky`
  (Desktop).
* **Vier Fälle je Seite:** ohne Einwilligung (keine Cookies), mit Einwilligung
  (Cookies vor dem ersten Request), Einwilligung im Besuch (Klick auf
  `[data-cookie-action="all"]`), abgelehnt (`cookie_consent_marketing=0`).

### Zwei Dinge am Messaufbau, die vorher falsche Werte ergaben

1. **Cookies allein reichen nicht.** Alle Ziele teilen das Browser-Profil, und
   der Cookie-Hinweis führt die Entscheidung in `localStorage`; beim nächsten
   Seitenaufbau schreibt er die drei Cookies daraus **neu**
   (`writeConsentCookies`). Ein Lauf „ohne Einwilligung" nach einem Lauf mit
   Einwilligung war damit stillschweigend eingewilligt. `scripts/csp-messung.mjs`
   und `scripts/ads-einwilligung-cls.mjs` leeren jetzt je Seite Cookies **und**
   `localStorage` (`Storage.clearDataForOrigin`).
2. **Springen statt scrollen lädt zu wenig.** Ein `scrollTo(0, scrollHeight)`
   lässt die Anker in der Seitenmitte nie den Viewport schneiden — ihr
   Werbemittel bleibt im `<template>` liegen und der Mitschnitt zeigt weniger
   Verkehr, als die Seite macht. `scripts/csp-messung.mjs` scrollt jetzt
   schrittweise.

## 2. Netzwerk-Mitschnitt (Ist-Werte)

Fremde Hosts und Gegenprobe je Fall, aus
`ads-einwilligung-mitschnitt.json`:

| Fall | Google-Hosts angesprochen | Gegenprobe |
| --- | --- | --- |
| Startseite ohne Einwilligung | **keine** (nur `elektrikerportal.com`) | `wartendeAnker 1`, `offeneFragmente 1`, `offeneHeadVorlagen 1`, `adsbygoogleGeladen false`, `eingesetzteAnzeigen 0` |
| Startseite mit Einwilligung | `pagead2.googlesyndication.com`, `fundingchoicesmessages.google.com`, `fonts.googleapis.com`, `fonts.gstatic.com` | `adsbygoogleGeladen true`, `eingesetzteAnzeigen 2`, `offeneFragmente 0`, `offeneHeadVorlagen 0` |
| Startseite Einwilligung im Besuch | dieselben, **erst nach dem Klick** | `offeneHeadVorlagen 0`, `eingeklappt 0`, `eingesetzteAnzeigen 2` |
| Startseite abgelehnt | **keine** | `eingeklappt 1`, `offeneHeadVorlagen 1`, `adsbygoogleGeladen false` |
| Firmenprofil ohne Einwilligung | **keine** | `wartendeAnker 4`, `offeneFragmente 4`, `offeneHeadVorlagen 1`, `adsbygoogleGeladen false` |
| Firmenprofil mit Einwilligung | wie oben | `adsbygoogleGeladen true`, `eingesetzteAnzeigen 4`, `offeneFragmente 1` |
| Firmenprofil Einwilligung im Besuch | wie oben, erst nach dem Klick | `offeneHeadVorlagen 0`, `eingeklappt 0`, `eingesetzteAnzeigen 2` |
| Firmenprofil abgelehnt | **keine** | `eingeklappt 4`, `offeneFragmente 4`, `offeneHeadVorlagen 1` |

Kein Fall ohne Einwilligung spricht **irgendeinen** fremden Host an — auch
`www.googletagmanager.com` und `region1.google-analytics.com` bleiben weg, weil
die Messung ebenfalls an der Einwilligung hängt (#14).

Drei Werte in der Tabelle sehen nach einer Abweichung von der Erwartung aus und
sind keine:

* **`offeneFragmente` ist mit Einwilligung nicht immer 0.** Was offen bleibt,
  ist der Anker der jeweils anderen Geräteklasse: `company_sidebar_sticky` ist
  `display:none` in der mobilen Breite und löst darum nie aus — genau so ist die
  Gerätetrennung gebaut (docs/ad-slots.md 7, Katalog v3).
* **`wartendeAnker` bleibt nach einer Einwilligung im Besuch stehen.** Das
  Attribut `data-ad-consent` schreibt der Server; `resources/js/ads.js` entfernt
  es beim Nachziehen nicht. Es ist eine Herkunftsangabe, kein Zustand — der
  Zustand steht in `offeneHeadVorlagen`/`offeneFragmente`.
* **Ohne Einwilligung bleibt nicht alles liegen.** Auf der Startseite ist eines
  der beiden Fragmente eingesetzt: `home_after_services` läuft über den
  HtmlProvider im Sandbox-iframe und braucht keine Einwilligung. Das Gate hängt
  am Werbemittel, nicht an der Seite.
* **Nach einer Einwilligung im Besuch füllt sich, was im Blick ist.** Auf dem
  Firmenprofil bleiben drei Fragmente offen, weil das Messskript erst bis zum
  Seitenende scrollt und dann klickt: die Anker weiter oben schneiden den
  Viewport nicht mehr und warten auf das nächste Vorbeikommen
  (IntersectionObserver). So verhält sich die Seite auch für einen Besucher.
* **`eingesetzteAnzeigen` ist um eins höher als die Anker mit AdSense.**
  `adsbygoogle.js` legt selbst einen zusätzlichen, 0 px hohen `ins`-Block ohne
  `data-ad-slot` an; im ausgelieferten HTML steht je Anker genau einer.

## 3. CSP

**Kein einziger Verstoß in den Direktiven, um die es bei #9 geht**
(`script-src`, `frame-src`, `connect-src`, `img-src`) — über alle acht Läufe,
weder im Dokument (`securitypolicyviolation`) noch im Browser-Log noch als
`blockedReason: "csp"`. An `config/csp.php` ist nichts nachzutragen.

Die einzigen Meldungen sind 40 × `font-src` auf
`http://fonts.gstatic.com/...` (Material Icons, Roboto, Open Sans), und zwar
nur in den Läufen **mit** Einwilligung. Das Portal selbst lädt keine
Google-Fonts; die Schriften holt der Einwilligungs-/Anzeigencode von Google
(`fundingchoicesmessages.google.com`) in das Hauptdokument. Blockiert wird die
**http**-Fassung — die Policy erlaubt `https://fonts.gstatic.com`, und der
lokale Messaufbau läuft über `http`, weshalb die eingebetteten Adressen dem
Seitenprotokoll folgen. Auf den Portalen (durchgehend `https`) fällt derselbe
Request unter den bestehenden `font-src`-Eintrag. Ein Produktionsbefund ist das
also nicht; nachzutragen ist nichts.

### `php artisan csp:berichte`

```
php artisan csp:berichte --portal=elektrikerportal.com   # INFO: keine Meldung, Exit 0
```

**Kein Google-Host als Verstoß.** Was in der Sammelstelle steht, stammt nicht
aus diesem Lauf, sondern aus der Safari-Sichtprüfung vom 14.–16.10. auf den
Herd-Domains (`script-src-elem inline` auf `elektriker.test` und `sun.test`,
`connect-src https://csi.gstatic.com` — dieser Host steht in `config/csp.php`
bereits in `connect-src`). Deshalb endet ein `csp:berichte` ohne `--portal`
weiter mit Exit 70.

Aus dem Chrome-Lauf selbst erreicht **keine** Meldung die Sammelstelle: die
Reporting-API verlangt einen sicheren Kontext, und `Reporting-Endpoints` zeigt
auf `https://elektrikerportal.com/csp-bericht`, während der Messaufbau `http`
spricht. Die Annahmestelle arbeitet (ein von Hand abgeschickter Bericht landet
mit HTTP 204 in `csp_reports`); für den lokalen Lauf ist der Mitschnitt im
Browser die belastbare Quelle, und er sieht auch die Unterrahmen.

## 4. CLS bei Einwilligung im laufenden Besuch

Gemessen mit `PerformanceObserver({type:'layout-shift'})` (ohne
`hadRecentInput`), Viewport 360 × 800 mobil, in drei Abschnitten: laden und
durchscrollen → wartenden Anker **in den Blick** holen → `[data-cookie-action="all"]`
klicken. Der Anker muss dabei sichtbar sein, sonst zählt die Metrik den Nachzug
gar nicht (CLS bewertet nur den sichtbaren Bereich) und der Wert wäre als Beleg
wertlos.

| Fall | CLS vor Klick | CLS nach Klick | Verschobenes Element |
| --- | --- | --- | --- |
| Startseite, `home_after_hero`, AdSense `responsive` | 0,000 | **0,136** | `section.section` unter dem Anker |
| Firmenprofil, `company_after_header_card`, AdSense `responsive` | 0,000 | **0,024** | `section.card` unter dem Anker |
| Startseite, `home_after_hero`, AdSense `leaderboard_728x90` | 0,000 | **0,000** | keines |

**Vor** dem Klick verschiebt sich nichts, in keinem Lauf — die Reservierung
steht, der Platzhalter hat seine Katalog-Höhe. Das Gate selbst ist also
layoutsicher.

**Nach** dem Klick hängt es am Format des Werbemittels, und das ist ein Befund:

* `home_after_hero` reserviert 100 px (mobil) für ein Banner. Ein
  AdSense-Werbemittel im Format `responsive` bekommt `data-ad-format="auto"` und
  **bestimmt seine Höhe selbst** — hier 250 px. Der Container wächst von 130 auf
  390 px, alles darunter rutscht: **CLS 0,136 > 0,1**, die Vorgabe der Abnahme
  ist damit verfehlt.
* Dasselbe Werbemittel im festen Katalogformat `leaderboard_728x90` bekommt
  `height:90px` in den `style` und passt in die Reservierung: Container 130 px
  vor und nach dem Klick, **CLS 0,000**.
* Auf dem Firmenprofil ist der Rest klein (0,024 bei 280 px Reservierung), aber
  aus demselben Grund vorhanden.

Es ist derselbe Mechanismus wie der Befund aus #5 (docs/ad-slots.md 7.4): die
Höhenreservierung hält das Layout ruhig, solange die Höhe des Werbemittels
feststeht. `responsive` ist in `config/ad-slots.php` auf **jedem** Anker
erlaubt, auch auf den 90/100-px-Bannern — dort kann es die Zusage „CLS < 0,1"
nicht halten. Nachgezogen wird das in einem eigenen Ticket (#32).
