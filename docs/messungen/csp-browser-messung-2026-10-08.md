# CSP im echten Browser nachgemessen (Ticket #28)

Datum: 08.10.2026
Grundlage: `docs/turnstile.md` Abschnitt 14, Nachzug zu #21.
Rohdaten: `csp-browser-report-2026-10-08.json` (Lauf vor der Korrektur),
`csp-browser-enforce-2026-10-08.json` (Lauf nach der Korrektur, Modus
`enforce`).
Messwerkzeug: `scripts/csp-messung.mjs` (CDP, ohne zusaetzliche Pakete).

## 1. Messaufbau

Keine erreichbare Staging-Instanz, deshalb lokal gegen denselben Aufbau wie
`auto-ads-cls-messung-2026-09-09.md`:

```bash
npm run build                                 # public/build/ ist nicht versioniert
# .env.t28: APP_ENV=t28 APP_DEBUG=false CSP_MODE=report
#           GOOGLE_TRACKING_ID="G-XXXXX"  TRACKING_SCRIPTS="<GTM-Schnipsel GTM-TESTCSP>"
APP_ENV=t28 PHP_CLI_SERVER_WORKERS=10 php artisan serve --host=127.0.0.1 --port=8128

"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new \
  --remote-debugging-port=9222 --user-data-dir=/tmp/t28_chrome \
  --host-resolver-rules="MAP elektriker.test 127.0.0.1:8128, MAP sanitaer.test 127.0.0.1:8128"

node scripts/csp-messung.mjs --seiten=/tmp/t28_seiten.json --aus=/tmp/csp.json --warten=8000
```

Chrome 154 headless. Das Skript schneidet je Seite drei Quellen mit:
`securitypolicyviolation` im Dokument, das Browser-Log (`Log.entryAdded`, faengt
auch Unterrahmen) und Anfragen mit `blockedReason: "csp"`. Jede Seite wird nach
dem Laden bis zum Seitenende gescrollt, damit die nachladenden Werbeplaetze
(`[data-lazy-ad]`) anlaufen; optional klickt das Skript einen Ausloeser
(`"klick": "[data-open-lead]"` fuer den Anfrage-Dialog).

Gegenprobe je Seite, damit ein Lauf ohne Verstoesse nicht bloss bedeutet, dass
nichts geladen hat: Anzahl Turnstile-Skripte, `adsbygoogle.loaded`, eingesetzte
`ins.adsbygoogle`, verbliebene `template[data-ad-code]`, `dataLayer`-Laenge,
`typeof Livewire`, `typeof Alpine` sowie alle angefragten Fremd-Hosts.

### Gemessene Seiten

Je einmal fuer das sun-v2-Portal `elektriker.test` (Tenant 19) und das
Default-Theme-Portal `sanitaer.test` (Tenant 1): Startseite, Suche
(`/firmen?q=…`), Firmenprofil, Registrierung (`/register`), Eintragung
(`/eintragen`), Checkout (`/checkout/plan/premium-monthly`). Dazu auf sun-v2
Ratgeber, Premium-Preisseite und das Firmenprofil mit geoeffnetem
Anfrage-Dialog, auf dem Default-Theme Ratgeber und Staedteuebersicht.

### Werbung und Tracking waren waehrend der Messung echt

* Tenant 19 hat sieben `ad_slots` mit echtem AdSense-Code
  (`ca-pub-1901800422608466`), darunter `auto_ads`. Fuer die Messung **aktiv
  gesetzt**, danach wieder auf inaktiv — Stand vorher und nachher: 0 von 7 aktiv.
* GA4 ueber `GOOGLE_TRACKING_ID`, Tag Manager ueber `TRACKING_SCRIPTS`
  (Schnipsel mit Behaelter `GTM-TESTCSP`) — beides nur in `.env.t28`, die nach
  der Messung geloescht wurde.
* Das Default-Theme liest GA4/GTM nicht aus der `.env`, sondern aus
  `settings.google_analytics_id` / `settings.google_tag_manager_id` des Tenants.
  Fuer zwei Zusatzlaeufe bei Tenant 1 gesetzt, danach wieder `null`.
* Der Anfrage-Dialog erscheint nur mit Funnel-Snapshot. Das lokale
  Funnel-Token von Tenant 19 ist verfallen (HTTP 404), fuer den Dialog-Lauf
  wurde kurzzeitig das Token aus `LEADS_ELEKTRIKER_FUNNEL_TOKEN` eingetragen
  und danach das alte wieder gesetzt.

## 2. Befunde des ersten Laufs (CSP_MODE=report)

| Direktive | Fehlende Quelle | Woher | Betroffene Seiten |
|---|---|---|---|
| `frame-src` | `https://pagead2.googlesyndication.com` | AdSense rahmt seine Anzeigenbloecke selbst ein (`/pagead/ads`, `zrt_lookup.html`) | alle sun-v2-Seiten mit Werbung |
| `frame-src` | `https://ep2.adtrafficquality.google` | Sichtbarkeitspruefung (Sodar) von AdSense | alle sun-v2-Seiten mit Werbung |
| `connect-src` | `https://ep1.adtrafficquality.google` | Sodar holt `getconfig/sodar?…` | alle sun-v2-Seiten mit Werbung |
| `frame-src` | `https://maps.google.com` | Kartenausschnitt der Firmendetailseite in den Themes default/starter (`pages/companies/show.blade.php`) | default Firmenprofil |
| `style-src` | `https://fonts.googleapis.com` | Poppins in `components/layouts/partials/head.blade.php` | `/register` beider Portale |
| `font-src` | `https://fonts.gstatic.com` | dieselben Schriftschnitte (50 Dateien) | `/register` beider Portale |

Alles davon entsteht zur Laufzeit und war serverseitig nicht sichtbar — genau
die Luecke, die das Ticket beschreibt. `script-src` hatte **keinen** einzigen
Verstoss: kein Inline-Block, keine fremde Skriptquelle, auch nicht aus den
nachgeladenen AdSense-Ketten.

## 3. Korrektur in `config/csp.php`

Nachgetragen, ohne `'unsafe-inline'`:

* `frame-src`: `pagead2.googlesyndication.com`, `ep2.adtrafficquality.google`,
  `maps.google.com`
* `connect-src`: `ep1.adtrafficquality.google`
* `style-src`: `fonts.googleapis.com`
* `font-src`: `fonts.gstatic.com`

## 4. Zweiter Lauf, Modus `enforce`

13 Seiten, **0 Verstoesse, 0 Log-Zeilen, 0 Konsolenfehler**. Die Gegenprobe
zeigt, dass dabei tatsaechlich alles lief:

| Seite | Werbung geladen | Anzeigen-Rahmen | offene Vorlagen | Turnstile | Livewire | gtag |
|---|---|---|---|---|---|---|
| sun-v2 Startseite | ja | 1 | 0 | – | – | ja |
| sun-v2 Suche | ja | 1 | 0 | – | – | ja |
| sun-v2 Firmenprofil | ja | 1 | 0 | – | ja | ja |
| sun-v2 Registrierung | – | – | 0 | 1 | ja | ja |
| sun-v2 Eintragung | ja | 1 | 0 | – | ja | ja |
| sun-v2 Checkout | ja | 1 | 0 | 1 | ja | ja |
| sun-v2 Profil + Anfrage-Dialog | ja | 1 | 0 | – | ja | ja |
| default Startseite/Suche/Profil/Eintragung | – | – | 0 | – | ja | – |
| default Registrierung | – | – | 0 | 1 | ja | ja |
| default Checkout | – | – | 0 | 1 | ja | – |

Die vier offenen Punkte des Tickets im Einzelnen:

1. **Skripte, die erst zur Laufzeit entstehen.** AdSense laedt ueber
   `pagead2.googlesyndication.com` nach und rahmt seine Bloecke selbst ein;
   Sodar kommt ueber `ep1`/`ep2.adtrafficquality.google`. Alle vier Hosts sind
   jetzt in der Policy, `adsbygoogle.loaded` ist `true` und jede Seite mit
   Werbung hat einen gerenderten Anzeigen-Rahmen.
2. **`connect-src`.** Gemessen und erlaubt: `region1.google-analytics.com`
   (GA4-Messwerte, von `https://*.google-analytics.com` gedeckt),
   `ep1.adtrafficquality.google`, `leads.widimedia.com` (POST `/sessions` beim
   Oeffnen des Anfrage-Dialogs, abgeleitet aus `config('leads.api_url')`) und
   die Livewire-Updates auf eigener Herkunft.
3. **`frame-src`.** AdSense-Rahmen und Sodar-Rahmen nachgetragen, Maps-Rahmen
   nachgetragen. Der Turnstile-Rahmen trat im Lauf nicht auf: mit dem
   Testschluessel `1x00000000000000000000AA` richtet `turnstile.render()` nur
   Container und Antwortfeld ein, `window.turnstile` ist ein Objekt. Die
   Herkunft `challenges.cloudflare.com` steht ohnehin abgeleitet in `frame-src`,
   `script-src` und `connect-src`; das Skript selbst wurde geladen.
4. **Nonce in geklonten Werbe-Schnipseln.** Siehe Nachtrag in Abschnitt 7:
   die hier gemessenen Seiten lieferten gar keine `template[data-ad-code]`,
   `offeneVorlagen: 0` war also trivial erfuellt. Der Klon-Pfad ist erst im
   Nachlauf vom 08.10. belegt.

Der nonce-behaftete GTM-Schnipsel aus `TRACKING_SCRIPTS` lief ebenfalls
(`dataLayer` enthaelt `gtm.start`, `gtm.js?id=GTM-TESTCSP` wurde angefragt).
Zu beachten: das von GTM selbst eingefuegte `<script>` traegt **kein** Nonce —
es laeuft nur, weil `www.googletagmanager.com` in `script-src` steht.

## 5. Was offen bleibt

* **Safari-Huelle ist nicht gemessen, WebKit schon.** `safaridriver`
  verweigert die Sitzung ("You must enable 'Allow remote automation' …"); das
  kann nur ein Mensch am Rechner freigeben. Punkt 4 (Nonce an dynamisch
  angelegten Skripten) ist stattdessen im Playwright-WebKit nachgemessen —
  0 Verstoesse, siehe Abschnitt 7 "Safari / WebKit" (#29). Ungemessen bleibt
  allein, was Safari ueber die Engine hinaus macht (Erweiterungen, ITP).
* **Echte GTM-Behaelter.** Gemessen wurde mit einem Behaelter ohne Tags. Wer in
  GTM ein Tag eines weiteren Anbieters einrichtet, laedt eine Herkunft nach, die
  in der Policy fehlt. Weg dafuer: `CSP_EXTRA_SCRIPT_SRC` /
  `CSP_EXTRA_CONNECT_SRC` / `CSP_EXTRA_FRAME_SRC` in der `.env` — kein Deploy,
  nur `php artisan config:clear`.
* **Anzeigen ohne Fuellung.** Auf `*.test` liefert AdSense keine echten
  Anzeigen aus. Die Ketten bis zum Rahmen sind gemessen, ein ausgeliefertes
  Werbemittel kann weitere Herkuenfte nachziehen. Darum fuer #13: nach dem
  Deploy einen Tag lang `CSP_MODE=report` auf einem Portal mit Werbung laufen
  lassen und die Konsole pruefen, erst dann `enforce`.

## 6. Rueckfahrt

Unveraendert ein Eintrag in der `.env`: `CSP_MODE=report` meldet nur,
`CSP_MODE=off` setzt keinen Header. Danach `php artisan config:clear`.

## 7. Nachtrag 08.10.2026: der Klon-Pfad war im Lauf aus Abschnitt 4 leer (#29)

Beim Vorbereiten der Safari-Sichtpruefung fiel auf, dass der erste Lauf den
Nonce an geklonten Skripten nicht wirklich geprueft hat. Grund ist der
Zuschnitt der Lazy-Positionen: `<template data-ad-code>` entsteht nur an
`App\View\Components\AdSlot::LAZY_POSITIONS`, also
`listing_detail_after_description` und `footer_above`.

* Tenant 19 (sun-v2) hat genau diese beiden Positionen **nicht** belegt — seine
  sieben Slots sitzen auf `sidebar_top`, `after_breadcrumb`, `after_photos`,
  `content_before_intro`, `sidebar_after_claim`, `after_change_request`,
  `auto_ads`. Im Theme sun-v2 kommt `footer_above` ausserdem nur in den
  Ratgeber-Views vor, und `/ratgeber` faellt ohne Ratgeber-Inhalte auf die
  Blog-Uebersicht zurueck (`GuideController::index()`).
* Die `offeneVorlagen: 0` aus Abschnitt 4 bedeuteten also nur, dass nie eine
  Vorlage im HTML stand.

### Nachgemessener Aufbau

Die Themes default/starter liefern `footer_above` im Layout und
`listing_detail_after_description` auf der Firmendetailseite. Also:
Mess-Portal `sanitaer.test` (Tenant 1, Default-Theme), dort ein zusaetzlicher
`ad_slot` auf `footer_above` mit dem **echten** AdSense-Code von Tenant 19
(`ca-pub-1901800422608466`) — die bestehenden Slots von Tenant 1 enthalten nur
Platzhaltertext ("MOBILE SIDEBAR AD") und laden nichts nach. Danach geloescht,
Stand vorher und nachher: 6 Slots, 5 aktiv.

Gemessen mit `scripts/csp-messung.mjs` in Chrome 154 headless, zweimal:
`CSP_MODE=report` und `CSP_MODE=enforce`. Rohdaten des enforce-Laufs:
`csp-browser-lazy-nonce-2026-10-08.json`.

| Seite | Verstoesse | adsSkripte | adsbygoogle.loaded | Anzeigen-Rahmen | offene Vorlagen |
|---|---|---|---|---|---|
| `sanitaer.test/` (footer_above) | 0 | 2 | true | 2 | 0 |
| `sanitaer.test/59399-…` (footer_above + listing_detail) | 0 | 2 | true | 2 | 0 |

Damit ist Punkt 4 fuer Chrome belegt: `resources/js/ads.js` legt die Skripte
ueber `neuesSkript()` neu an, setzt das Nonce ausdruecklich, und AdSense laeuft
unter `enforce` ohne einen einzigen `script-src`-Verstoss durch
(`pagead2.googlesyndication.com`, `ep1`/`ep2.adtrafficquality.google` werden
angefragt).

### Safari / WebKit (#29, 08.10.2026)

**Ergebnis: in WebKit gemessen, 0 Verstoesse, Nonce-Pfad belegt.** Nicht in
Safari selbst — `safaridriver` verweigert jede Sitzung ohne "Allow remote
automation" (nachgeprueft 08.10.2026, `AllowRemoteAutomation` ist in den
Safari-Preferences nicht gesetzt, das kann nur ein Mensch am Rechner
freigeben). Gemessen wurde darum dieselbe Engine ohne Safari-Huelle: der
WebKit-Build von Playwright (**WebKit 27.2**, headless).

Aufbau wie im Chrome-Nachlauf oben, hergestellt und abgeraeumt vom selben
Skript, jetzt mit Messlauf statt Handgriff:

```bash
# einmalig, ausserhalb des Projekts (keine neue Projekt-Abhaengigkeit):
mkdir -p /tmp/webkit-messung && cd /tmp/webkit-messung && npm init -y \
  && npm i playwright \
  && PLAYWRIGHT_BROWSERS_PATH=/tmp/webkit-messung/browsers \
     npx playwright install webkit

# Messlauf (HOSTS=aus, weil Herds dnsmasq *.test schon auf 127.0.0.1 legt
# und /etc/hosts sonst sudo braeuchte):
HOSTS=aus scripts/csp-safari-sichtpruefung.sh --webkit
```

Mess-Portal `sanitaer.test:8129` (Tenant 1, Default-Theme), zusaetzlicher
`ad_slot` auf `footer_above` mit dem echten AdSense-Code von Tenant 19,
`CSP_MODE=enforce`, `APP_DEBUG=false`. Slot-Stand vorher und nachher: 6 Slots,
5 aktiv. Messskript: `scripts/csp-webkit-messung.mjs` (Playwright statt CDP,
schneidet `securitypolicyviolation`, Konsolenfehler, fehlgeschlagene Anfragen
und dieselbe Gegenprobe mit). Rohdaten: `csp-webkit-nonce-2026-10-08.json`.

| Seite | Verstoesse | Konsolenfehler | adsSkripte | adsbygoogle.loaded | Anzeigen-Rahmen | eingesetzte Anzeigen | offene Vorlagen | werbeSkripte |
|---|---|---|---|---|---|---|---|---|
| `sanitaer.test:8129/` (footer_above) | 0 | 0 | 2 | true | 2 | 2 | 0 | 2x "mit Nonce" |
| `sanitaer.test:8129/59399-…` (footer_above + listing_detail) | 0 | 0 | 2 | true | 2 | 2 | 0 | 2x "mit Nonce" |

Angefragte Fremd-Hosts, alle ohne Verstoss: `pagead2.googlesyndication.com`,
`ep1`/`ep2.adtrafficquality.google`, `unpkg.com` (Lucide im Default-Theme),
auf der Firmenseite zusaetzlich `maps.google.com`, `maps.googleapis.com`,
`maps.gstatic.com`, `www.google.com`. Livewire und Alpine sind geladen
(`typeof === "object"`).

**Gegenprobe der Messung selbst.** Damit "0 Verstoesse" nicht wieder nur
heisst, dass niemand hingeschaut hat (der Fehler aus Abschnitt 4), laeuft vor
jedem Messlauf `node scripts/csp-webkit-messung.mjs --selbsttest`: eine selbst
servierte Seite mit `default-src 'self'` und zwei gewollten Verstoessen. WebKit
meldet beide (`script-src-elem inline`, `img-src https://www.gstatic.com/…`),
der Lauscher greift also in dieser Engine. Bleibt der Selbsttest still, bricht
das Skript mit Exit 3 ab.

**Was damit nicht gemessen ist.** Safari selbst (Erweiterungen, ITP,
Lockdown-Modus) und `*.test` ohne echte Werbemittel — beides gilt wie in
Abschnitt 5 beschrieben. Die Engine-Frage aus Punkt 4, ob WebKit das per
`neuesSkript()` gesetzte Nonce akzeptiert, ist beantwortet: ja.
