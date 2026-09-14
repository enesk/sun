# Messprotokoll Auto Ads ohne Anker-Banner (Ticket #111)

Datum: 09.09.2026
Grundlage: Vorgabe #100, Abschnitt 5. Aufbau uebernommen aus
`ratgeber-sidebar-cls-messung-2026-09-09.md`, Abschnitt 1.
Rohdaten: `auto-ads-cls-2026-09-09.json`.

## 1. Messaufbau

Weiterhin keine erreichbare Staging-Instanz (#90). Gemessen wurde lokal gegen
denselben Aufbau wie in #91 — gebaute Assets, gzip-Proxy, Chrome headless mit
Namensaufloesung auf die Testdomain:

```bash
APP_ENV=t111 APP_DEBUG=false APP_URL=http://sanitaer.test \
  PHP_CLI_SERVER_WORKERS=10 php artisan serve --host=127.0.0.1 --port=8099
node /tmp/t34_proxy.mjs                           # gzip-Proxy auf 8098
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new \
  --remote-debugging-port=9222 --user-data-dir=/tmp/t111_chrome \
  --host-resolver-rules="MAP sanitaer.test 127.0.0.1:8098"
npx lighthouse@13 "<URL>" --port=9222 --only-categories=performance [--preset=desktop]
```

Lighthouse 13, Chrome headless. Mobil 412 px mit Standard-Drosselung, Desktop
1350 px. `public/build` war aktuell (Bundle `app-Dot5BQzO.css` mit den
Ratgeber-Regeln aus #83); ein erneuter Build war nicht noetig.

Der Platz `auto_ads` von Tenant 1 (`ad_slots`, Zeile
`01km3cyq5g20fxm2wnb1gqjwyb`, echter AdSense-Code `ca-pub-2563111817853634`)
war waehrend aller neun Laeufe **aktiv**. Erst nach der Messung wurde er nach
Vorgabe #100, Abschnitt 3.3 auf inaktiv gesetzt.

## 2. Ratgeber-Seite

`/ratgeber/heizungstausch-forderung-in-bayern-was-gilt-2026`, je drei Laeufe.

| Formfaktor | CLS je Lauf | Ziel | Performance | Verursacher |
|---|---|---|---|---|
| Desktop (1350 px) | 0,0167 / 0,0167 / 0,0167 | < 0,1 | 100 / 99 / 100 | `header.header-floating > … > nav.hidden` (0,01669) |
| Mobil (412 px) | 0,0062 / 0,0062 / 0,0062 | < 0,1 | 88 / 99 / 91 | `header.header-floating > … > button.md:hidden` (0,00618) |

Der Knoten `body.min-h-screen` erscheint in **keinem** der sechs Laeufe als
Verursacher. Zum Vergleich #91, Abschnitt 3: dort 1,0167 (Desktop) und 0,5432
(Mobil) durch `padding-bottom: 335px` auf dem `<body>`.

Die verbleibenden Shifts gehoeren zum Portal-Header und sind identisch mit den
Werten aus #91 — die Auslieferungsregel hat sonst nichts veraendert.

### Ausgeliefertes HTML

```
curl -H "Host: sanitaer.test" http://127.0.0.1:8098/ratgeber/<slug>
  pagead2 = 0   adsbygoogle = 0   ca-pub = 0
```

Kein Auto-Ads-Skript im Dokument. Die Regel greift im Blade, nicht erst im
Browser.

## 3. Firmendetailseite (Ebene B, Overlay-Opt-out)

`/39783-heizung-luftung-sanitar-dietmar-frei`, drei Laeufe mobil.

| Lauf | CLS | Performance | Verursacher |
|---|---|---|---|
| 1 | 0,0062 | 89 | `header.header-floating > … > button.md:hidden` |
| 2 | 0,0062 | 85 | derselbe Knoten |
| 3 | 0,0062 | 83 | derselbe Knoten |

Auto Ads werden hier erwartungsgemaess ausgeliefert. Im HTML steht einmal je
Seite der Opt-out-Aufruf mit der aus dem Code gelesenen Kennung:

```
google_ad_client: "ca-pub-2563111817853634"
enable_page_level_ads: true
overlays: { bottom: false }
```

Netzwerkmitschnitt: `adsbygoogle.js` und `show_ads_impl` laden mit 200, die
Anzeigenanforderung `pagead/ads?client=ca-pub-…` antwortet wie in #91 mit
**403** (Testdomain, Konto nicht fuer sie freigeschaltet). Zwei
`gen_204?id=ach_evt`-Meldungen zeigen, dass das Skript Ankerkandidaten
bewertet; ein Anker wurde in keinem der drei Laeufe eingehaengt, `<body>`
blieb ohne `padding-bottom`.

**Bewertung.** Das ist ein Indiz, kein Beweis. Solange die Anzeigenanforderung
403 liefert, kann kein Lauf unterscheiden, ob der Opt-out gewirkt hat oder ob
schlicht keine Anzeige zurueckkam. Die Aussagekraft entsteht erst auf einer
freigeschalteten Domain. Ebene C aus Vorgabe #100 (Anker-Anzeigen im
AdSense-Konto abschalten) bleibt damit unveraendert Pflicht vor dem Go-Live und
ist durch diese Messung nicht ersetzt.

## 4. Werbeverwaltung

Nicht mit Lighthouse gemessen, sondern am Markup geprueft:

- Warnkasten steht im ausgelieferten Formular ohne `x-cloak` und ohne `hidden`;
  ohne JavaScript ist er sichtbar.
- `role="note"`, `aria-live="polite"`, `id="auto-ads-warning"`, vom Select ueber
  `aria-describedby` referenziert.
- Kopfzeile erbt `--dash-warning-strong` (#92400e) aus `.dash-flash-warning`,
  auf `--dash-warning-light` (#fffbeb) sind das 6,9:1. Fliesstext traegt
  `.dash-flash-body` (`--dash-text-primary`). Die Warnfarbe `--dash-warning`
  liegt nur auf Rahmen und Symbol.

## 5. Was diese Messung nicht ist

Kein Lauf auf einer oeffentlichen URL, kein HTTPS, kein HTTP/2, kein
freigeschaltetes AdSense-Konto. Fuer Abschnitt 2 wirkt das nicht: die
Auslieferungsregel entscheidet serverseitig, das Skript ist gar nicht erst im
Dokument. Fuer Abschnitt 3 gilt der Vorbehalt aus der Bewertung dort.

## 6. Zustand nach der Messung

`ad_slots` Zeile `01km3cyq5g20fxm2wnb1gqjwyb` (Tenant 1, `auto_ads`) steht
wieder auf `is_active = 0`. Kein weiterer Tenant fuehrt einen `auto_ads`-Platz.
