# CLS mit AdSense-Format `responsive` nach der Begrenzung auf die Reservierung

**Datum:** 16.10.2026 · **Ticket:** #32 (Nachzug zum Befund aus #25,
`ads-einwilligung-2026-10-16.md` Abschnitt 4) · **Codestand:** `dfcd1073` plus
die Arbeit aus #9 und #32

Gemessen wird dieselbe Zusage wie in #25: eine Einwilligung **im laufenden
Besuch** verschiebt das Layout nicht (CLS < 0,1) — diesmal mit einem
AdSense-Werbemittel im Format `responsive` auf einem Banner-Anker, dem Fall,
der vorher 0,136 ergab.

Rohdaten:

* `ads-cls-responsive-begrenzt-2026-10-16.json` — Startseite (`home_after_hero`)
* `ads-cls-responsive-begrenzt-profil-2026-10-16.json` — Firmenprofil
  (`company_after_header_card`)

## 1. Aufbau

Identisch zu #25 (`ads-einwilligung-2026-10-16.md` Abschnitt 1), Chrome 155
headless, Viewport 360 × 800 mobil:

```bash
APP_ENV=messung PHP_CLI_SERVER_WORKERS=10 \
  php artisan serve --host=127.0.0.1 --port=8123   # .env.messung: CSP_MODE=report, APP_DEBUG=false

"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new \
  --remote-debugging-port=9222 --user-data-dir=/tmp/t32_chrome \
  --host-resolver-rules="MAP elektrikerportal.com 127.0.0.1:8123"

node scripts/ads-einwilligung-cls.mjs \
  --url="http://elektrikerportal.com/" \
  --aus=docs/messungen/ads-cls-responsive-begrenzt-2026-10-16.json
```

* **Portal:** Tenant 30 (`elektrikerportal.com`, Theme `sun-v2`), für die
  Messung `ads_enabled=1` und Publisher-ID `ca-pub-1901800422608466`; danach
  wieder auf den Stand davor (`ads_enabled=0`, Publisher-ID `null`).
* **Belegungen:** `home_after_hero` mit dem Werbemittel „Demo AdSense
  responsiv" (`format: responsive`) — dieselbe Belegung wie im Lauf aus #25.
  Für das Firmenprofil wurde dasselbe Werbemittel vorübergehend auf
  `company_after_header_card` gelegt (Belegung danach gelöscht), weil die vier
  Belegungen aus #25 nach dem Lauf zurückgenommen worden waren.
* Der Resolver cacht je Seitentyp und Portal: vor jedem Lauf
  `php artisan ads:resolve 30 <slot> mobile --fresh`, sonst misst der Lauf das
  Fragment von vorher.

## 2. Ausgeliefertes Fragment

Vorher (#25) und jetzt, dasselbe Werbemittel:

```html
<!-- bis #32: AdSense bestimmt die Hoehe selbst (gemessen 250 px) -->
<ins class="adsbygoogle block w-full" style="display:block"
     data-ad-format="auto" data-full-width-responsive="true" …></ins>

<!-- seit #32: Hoehe aus der Reservierung des Ankers -->
<ins class="adsbygoogle block w-full" style="display:block;width:100%;height:90px" …></ins>
```

90 px, nicht 100: `home_after_hero` reserviert 100 px mobil und 90 px auf dem
Desktop, und derselbe Anzeigenplatz läuft auf beiden Geräten — ein Container,
ein Fragment. Genommen wird darum die kleinere der beiden Höhen, damit das
Werbemittel in **keine** der beiden Reservierungen hineinwächst. Auf
`company_after_header_card` (mobil 280, Desktop 0 = kein Anker) sind es
280 px.

## 3. Ist-Werte

| Seite | Anker | Reservierung | CLS vor Klick | CLS nach Klick | Containerhöhe vor/nach |
| --- | --- | --- | --- | --- | --- |
| Startseite | `home_after_hero` | 100 px mobil | 0,000 | **0,0274** | 130 px / 130 px |
| Firmenprofil | `company_after_header_card` | 280 px mobil | 0,000 | **0,000** | 310 px / 310 px |

Zum Vergleich der Lauf aus #25 mit demselben Werbemittel: Startseite **0,136**
(Container 130 → 390 px), Firmenprofil 0,024.

**Die verbleibenden 0,0274 sind kein Werbeplatz.** Die einzige Verschiebung des
Laufs hat die Quellen `div.fc-stacks`, `div.fc-footer`, `button.fc-faq-header`
und `p` — der Google-CMP-Dialog (`fundingchoicesmessages.google.com`), der nach
der Einwilligung seine eigene Höhe ändert. Kein `[data-ad-anchor]` steht in den
Quellen einer Verschiebung, auf keiner der beiden Seiten.

Gegenprobe aus denselben Rohdaten: `offeneFragmente 0`, `offeneHeadVorlagen 0`,
`eingeklappt 0`, `eingesetzteAnzeigen 2` (der zweite `ins`-Block ohne
`data-ad-slot` ist der 0 px hohe Block, den `adsbygoogle.js` selbst anlegt —
siehe #25) und Containerhöhe unverändert gegenüber dem Stand vor dem Klick.

## 4. Was damit **nicht** belegt ist

* Das Werbemittel trägt die Demo-Anzeigenblock-ID `1234567890`, bekommt also
  keine echte Füllung. Belegt ist die **Geometrie**: der Container hält seine
  Höhe, egal was AdSense liefert — vorher wuchs er mit derselben ID auf 390 px.
  Dass eine echte Füllung in 90 px passt, entscheidet AdSense über die
  Anzeigengröße des fixen Blocks; ein Nachweis mit echten Blöcken gehört in
  die Abnahme auf der Produktion (#31).
* Gemessen wurde mobil bei 360 px. Der Desktop-Fall derselben Anker steht in
  `docs/ad-slots.md` 7.4 (1280 px, ≤ 0,0004).
