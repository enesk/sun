# GA4 Consent Mode v2 (Advanced) — Review und Abnahme (#6)

Datum: 20.10.2026 · Prüfer: Dimitri (Lead-Dev) · Umfang: #2, #3, #4, #5

Geprüft wurde ausschliesslich im Labor: Quelltext, lokal gerendertes HTML
(sun.test), Antwort-Header und eine Simulation des Bundle-Verhaltens.
Es wurde kein Google-Dienst, kein Konto, kein Dashboard und kein Prüf-Tool
angesprochen (AGENTS.md, Abschnitt "Externe Dienste und Konten").

## 1. Scope

Der Arbeitsbaum enthält zwei Vorhaben gleichzeitig. Zur Umstellung gehören
genau diese zwölf Dateien:

| Datei | Ticket | Art |
| --- | --- | --- |
| `resources/views/components/analytics-meta.blade.php` | #2, #4 | Consent-Default, gtag.js, config |
| `app/Support/ConsentState.php` (neu) | #4 | serverseitige Signale |
| `resources/js/analytics.js` | #2, #3 | GA4-Laden raus, `consent update` rein, GTM bleibt |
| `resources/js/consent-adapter.js` | #3 | nur Kommentar |
| `resources/js/app.js` | #3 | nur Kommentar |
| `resources/views/themes/sun-v2/js/modules/cookie-consent.js` | #3 | nur Kommentar |
| `resources/views/themes/sun-v2/views/partials/analytics.blade.php` | #2 | nur Kommentar |
| `config/csp.php` | #2 | nur Kommentar |
| `app/Http/Middleware/ContentSecurityPolicy.php` | #2 | nur Kommentar |
| `app/Constants/TenantLegalDefaults.php` | #5 | Rechtstext |
| `app/Console/Commands/TenantDatenschutzConsentModeBackfillCommand.php` (neu) | #5 | Backfill |

Alles übrige im Arbeitsbaum (`AGENTS.md`, `app/Ads/*`,
`AdsImpressionAbnahmeCommand`, `docs/ad-slots*`,
`docs/messungen/werbeplaetze-golive/*`, `resources/js/ads.js`, beide
`ad-slot.blade.php`, `themes/sun-v2/css/app.css`, die vier Seiten-Views,
`routes/tenant.php`, `scripts/ads-*.sh`, `tests/Feature/Ads/…`) gehört zum
Werbeplätze-Vorhaben, lag vor dieser Umstellung im Baum und ist **nicht Teil
dieses PR**. Der PR ist dateiweise auf die Tabelle oben zu beschneiden.

* Keine Änderung am Werbeplatz-System oder am AdSense-Laden: ✔ geprüft, der
  Consent-Mode-Scope fasst `app/Ads/*`, `ads.js` und die ad-slot-Views nicht an.
* Keine Test-Dateien im Scope: ✔ (die untracked Ads-Testdatei gehört zum
  anderen Vorhaben).
* Keine Google-API-Aufrufe, keine GTM-Konfiguration: ✔ GTM bleibt in
  `analytics.js` unverändert und lädt weiter erst nach der Einwilligung.

## 2. Reihenfolge im `<head>` (lokal gerendert, sun.test)

Zeichenpositionen im `<head>` einer echten Antwort (Tenant mit Prüfkennung
`G-PRUEF12345`, nur lokal gesetzt und danach zurückgenommen):

```
4309  <meta name="ga-id" content="G-PRUEF12345">
4711  gtag('consent', 'default', Object.assign({ wait_for_update: 500 }, consentVorgabe));
      … Cookie-Abgleich für gecachtes HTML …
6493  gtag('set', 'url_passthrough', true);
6536  <script async src="https://www.googletagmanager.com/gtag/js?id=G-PRUEF12345">
6709  gtag('config', "G-PRUEF12345");
```

Default steht vor gtag.js — das Kriterium "`gcs` fehlt ganz" kann damit nicht
eintreten. Beide Inline-Blöcke tragen das Nonce des Requests, und es ist
dasselbe wie im `Content-Security-Policy`-Header derselben Antwort
(`nonce-BPtsD2qI1ItJ88Rre75HUfEo`).

## 3. Serverseitiger Default je Cookie-Zustand

| Cookies | gerendertes `consentVorgabe` | erwartetes `gcs` |
| --- | --- | --- |
| keine | alles `denied`, `security_storage: granted` | G100 |
| `given=1, statistics=0, marketing=0` | alles `denied` | G100 |
| `given=1, statistics=1, marketing=1` | `analytics/ad_*/functionality = granted` | G111 |
| `given=1, statistics=1, marketing=0` | `analytics + functionality granted`, `ad_* denied` | G101 |
| `given=true` (manipuliert) | alles `denied` (fail closed) | G100 |

Damit trägt der **erste** Hit eines Folgeaufrufs nach Zustimmung bereits den
erteilten Stand, ohne auf das Bundle zu warten.

## 4. Verhalten im laufenden Besuch (Simulation von `initAnalytics()`)

DOM-Stub, `resources/js/analytics.js` + `consent-adapter.js` unverändert:

| Fall | gtag-Aufrufe | nachgeladene Skripte |
| --- | --- | --- |
| keine Entscheidung | – | – |
| "Nur notwendige" | – (Default `denied` bleibt stehen) | – |
| "Alle akzeptieren" | genau **ein** `consent update`, alles `granted` | – |
| nur Statistik | ein Update, `ad_* denied` | – |
| Entscheidung aus früherem Besuch | ein Update, alles `granted` | – |
| GA4 + GTM, Zustimmung | ein Update | `gtm.js` |
| Portal ohne GA4-Kennung | – | – |

Beide Kategorien melden getrennt, das Update läuft trotzdem nur einmal
(Dedupe über `letztesUpdate`). GA4 wird nirgends nachgeladen — es steht im
`<head>`.

## 5. Portal ohne Property-ID

`https://sun.test/` ohne Tenant-Kennung (globaler Wert `G-XXXXX` gilt als
Platzhalter, `AnalyticsResolver::isPlatzhalter()`): kein `ga-id`-Meta, kein
`gtag.js`, kein Inline-Block, kein Google-Skript im Dokument. ✔

## 6. CSP

`script-src` enthält `https://www.googletagmanager.com` und das Nonce;
`connect-src` enthält `https://*.google-analytics.com` und
`https://*.analytics.google.com`, die `collect`-Hits laufen also nicht gegen
die Policy. `img-src https:` deckt den Pixel-Rückfall. ✔

**Hinweis (kein Blocker):** Wird das Portal-HTML irgendwann an der Kante
vollständig gecacht ("Cache Everything"), passt das gecachte Nonce nicht mehr
zum Header der Antwort und die CSP blockiert den gesamten GA4-Block — nicht
nur den Default. Der Cookie-Abgleich im Block fängt nur den falschen
Vorbelegungszustand, nicht diesen Fall. Solange kein Full-Page-Cache aktiv ist
(im Repo gibt es keinen), ist das nur eine Fussangel für später.

## 7. Datenschutzerklärung (#5)

* Vorlage `TenantLegalDefaults::DATENSCHUTZ` enthält den Consent-Mode-Absatz
  direkt hinter dem Rechtsgrundlage-Absatz von 8.2. ✔
* `php artisan tenants:datenschutz:consent-mode-backfill` (Trockenlauf):
  19 "vorhanden", 3 "übersprungen — kein GA4", 1 "ergänzt" (der Testtenant mit
  der Prüfkennung). Keine "unklar"-Fälle. ✔
* Frontend `https://sun.test/datenschutz`: Absatz steht an der richtigen
  Stelle, "Stand" auf Oktober 2026 gezogen. ✔ (danach zurückgerollt)
* Wortlaut in Vorlage und Backfill-Kommando ist identisch. ✔

## 8. Prüfungen

`php -l` (3 Dateien), `node --check` (`analytics.js`, `consent-adapter.js`),
`vendor/bin/pint --test` auf die drei PHP-Dateien: alles ohne Befund. Keine
Test-Suite gestartet, keine Testdatei angelegt.

## 9. Offen für Enes — Browserschritt und GA4-Einstellung

Die vier Kriterien, die einen echten `collect`-Request voraussetzen, bleiben
offen: sie brauchen einen Browser auf einem Portal mit echter Property-ID und
damit einen Aufruf gegen Google. Laut AGENTS.md geschieht das nur mit
Zustimmung von Enes, nicht im Alleingang. Ablauf, wenn freigegeben:

1. Inkognito, DevTools → Netzwerk, Filter `collect`.
   Erwartung: `gcs=G100`, keine `_ga`/`_ga_*`-Cookies.
2. "Alle akzeptieren": nächster `collect` mit `gcs=G111`, `_ga`/`_ga_*` gesetzt.
3. Neues Inkognito-Fenster, "Nur notwendige": `gcs=G100`, keine `_ga`-Cookies.
4. Nach Zustimmung neu laden: **erster** `collect` der neuen Seite trägt schon
   `gcs=G111` (deckt sich mit Abschnitt 3).

Dazu der Hinweis aus der Definition of Done: die **GA4-Berichtsidentität auf
"Kombiniert"** setzen. Das ist Handarbeit im GA4-Konto und nicht Teil dieser
Tickets.
