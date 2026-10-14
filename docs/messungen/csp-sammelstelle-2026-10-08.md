# CSP-Sammelstelle nachgemessen (#51)

Datum: 08.10.2026
Gegenstand: `POST /csp-bericht`, `report-uri` + `report-to` + `Reporting-Endpoints`
Code: `app/Csp/`, `app/Http/Controllers/Portal/CspReportController.php`,
`App\Http\Middleware\ContentSecurityPolicy`, `config/csp.php`
Doku: `docs/turnstile.md` §14 „Sammelstelle fuer Verstoesse",
`docs/turnstile-golive.md` §1.5 Schritt 3

Gemessen wurde lokal gegen `php artisan serve` (Tenant 1, `sanitaer.test`,
`CSP_MODE=report`). Die Messaufbauten liegen nicht im Repo — sie bestehen aus
einem TLS-Vorsatz und einem CDP-/Playwright-Treiber in `/tmp`, beides
Werkzeugarbeit fuer diesen einen Beleg.

## 1. Header

```
Content-Security-Policy-Report-Only: … ; report-uri /csp-bericht; report-to csp-bericht
Reporting-Endpoints: csp-bericht="https://sanitaer.test/csp-bericht"
```

`report-uri` steht als relativer Pfad in der Policy — der Browser loest gegen
das Dokument auf und trifft immer das eigene Portal. Mit
`CSP_REPORT_ENABLED=false` fehlen beide Direktiven und der Header; gegengeprueft.

## 2. Chrome 154 (headless, CDP) — Weg `report-to`

Ein Verstoss im Browser erzeugt, Bericht kam als `application/reports+json` an
und wurde gespeichert:

| Portal | Direktive | Blockiert | Treffer | tenant_id |
| --- | --- | --- | --- | --- |
| sanitaer.test | img-src | http://sanitaer.test/images/favicon.ico | 1 | 1 |

**Befund zum Aufbau, der auf der Produktion zaehlt:** Chrome nimmt eine
Reporting-Gruppe nur mit **sicherer** Endpunkt-URL an. Der erste Lauf ueber
`http://sanitaer.test` lieferte **null** Berichte, obwohl das Dokument die
Verstoesse selbst meldete (`securitypolicyviolation` feuerte drei Mal) — und
auch `report-uri` sprang nicht ein, weil Chrome bei vorhandenem `report-to`
nicht darauf zurueckfaellt. Mit TLS davor kam der Bericht nach ~10 s.
Konsequenz im Code: `ContentSecurityPolicy::reportUrl()` erzwingt `https`,
sobald die Umgebung nicht `local` ist — hinter der Cloudflare-Kante kann der
Ursprung eine Anfrage als `http` sehen, und ein TrustProxies ist nicht
konfiguriert.

Zweiter Befund: Chrome liefert Berichte verzoegert und gebuendelt, und
entdoppelt selbst. Fuer eine Gegenprobe auf der Produktion heisst das:
mindestens eine Minute warten, nicht sofort `csp:berichte` erwarten.

## 3. WebKit 2370 (Safari-Engine, Playwright) — Weg `report-uri`

Safari selbst laesst sich lokal nicht fernsteuern (`safaridriver` verlangt
„Allow remote automation", nur ein Mensch gibt das frei — siehe #29). Gemessen
wurde dieselbe Engine ohne Safari-Huelle. Neun Inline-Verstoesse erzeugt, neun
`application/csp-report`-Berichte angekommen, **eine** Zeile mit Zaehler 9:

| Portal | Direktive | Blockiert | Treffer | tenant_id |
| --- | --- | --- | --- | --- |
| 127.0.0.1 | script-src-elem | inline | 9 | — (zentrale Domain) |

Damit ist das Akzeptanzkriterium „Entdopplung greift" zusaetzlich im echten
Browser belegt, nicht nur im Kernel-Durchlauf.

## 4. Kernel-Durchlauf, beide Formate

Ueber den echten HTTP-Kernel (Route, Throttle, Controller, Collector), je
Format ein Bericht, danach 100 gleiche und 5 Varianten mit Cache-Buster:

| Portal | Direktive | Blockiert | Treffer |
| --- | --- | --- | --- |
| sanitaer.test | script-src | inline | 101 |
| sanitaer.test | script-src-elem | https://boese.example.com/track.js | 6 |

* `application/csp-report` (`{"csp-report": …}`, Safari/Firefox) und
  `application/reports+json` (`[{"type":"csp-violation","body":{…}}]`, Chrome)
  landen beide als Zeile.
* `violated-directive: "script-src 'self'"` wird auf `script-src` gekuerzt —
  sonst waere jede Policy-Aenderung eine neue Zeile.
* Fuenf Aufrufe mit `?cb=0…4` auf derselben URL ergaben **eine** Zeile
  (Treffer 6, zusammen mit dem ersten Lauf): Query und Fragment fallen weg.
* `type: deprecation` im selben Bericht wird ignoriert.
* Antwort ist immer `204`, auch auf Unsinn.

## 5. Betrieb

```
php artisan csp:berichte            # Exit 70 = Befunde, Exit 0 = keine Meldung
php artisan csp:berichte --details --limit=30
php artisan csp:prune --dry-run
php artisan schedule:list | grep csp    # csp:prune 03:50
```

Gegengeprueft: `csp:berichte` meldet Exit 70 mit Befunden und Exit 0 ohne,
`csp:prune --days=1` loescht nichts, solange die Zeilen frische Treffer haben
(maßgeblich ist `last_seen_at`).

## 6. Was offen bleibt

* Safari **selbst** (nicht nur die Engine) bleibt ungemessen — dieselbe Luecke
  wie in #29, sie braucht einen Menschen am Rechner.
* Auf der Produktion ist der Weg noch nicht gelaufen: dort fehlt die Migration
  `2026_10_10_000001_create_csp_reports_table` und die Gegenprobe des Headers
  ueber die Cloudflare-Kante. Beides steht in `docs/turnstile-golive.md` §1.5
  Schritt 3.
