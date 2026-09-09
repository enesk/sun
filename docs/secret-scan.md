# Secret-Scan

Ticket: #98 (Folge aus #88)
Stand: 2026-09-09

Vier Google-Places-Schlüssel sind nacheinander im Klartext ins Repository
gelangt, jeder über dieselbe `env()`-Zeile mit Vorgabewert. Aufgefallen ist das
erst Jahre später beim Security-Review zu #26. Damit sich das nicht wiederholt,
prüft ein Scan jetzt vor dem Commit und noch einmal in der CI.

## Der Scanner

`scripts/secret-scan.php` — reines PHP, kein Laravel-Bootstrap, keine
Composer-Abhängigkeit. Er läuft auch dann, wenn `vendor/` oder `.env` fehlen.

```
php scripts/secret-scan.php              # alle versionierten Dateien (~0,3 s)
php scripts/secret-scan.php --staged     # nur die vorgemerkten Änderungen
php scripts/secret-scan.php pfad/zur/datei.php
composer run secrets:scan                # gleichbedeutend mit dem ersten Aufruf
```

Exit-Code 0 = sauber, 1 = Fund, 2 = Aufrufproblem.

### Was er erkennt

| Regel | Muster |
| --- | --- |
| `google-api-key` | `AIza…` mit 35 Folgezeichen |
| `anthropic-key` | `sk-ant-…` |
| `openai-key` | `sk-…`, `sk-proj-…`, `sk-svcacct-…` |
| `stripe-live-key` | `sk_live_…`, `rk_live_…` (Testschlüssel sind erlaubt) |
| `private-key-block` | `-----BEGIN … PRIVATE KEY-----` |
| `aws-access-key` | `AKIA…`, `ASIA…` |
| `google-service-account` | `"type": "service_account"` |
| `slack-token`, `github-token` | `xoxb-…`, `ghp_…` |
| `app-key` | `APP_KEY=base64:…` |
| `env-default-secret` | `env('X', '<literal>')` mit nichtleerem Vorgabewert |

Die letzte Regel greift nur bei schlüsselartigen Variablennamen (`KEY`, `SECRET`,
`TOKEN`, `PASSWORD`, `CREDENTIAL`, `PRIVATE`, `SALT`, `CERT`, `AUTH_CODE`, `DSN`)
und ignoriert harmlose Vorgaben: leer, `null`/`true`/`false`, Zahlen, URLs,
Pfade und Platzhalter wie `your-…`. Genau der Fall aus #88 —
`env('GOOGLE_PLACES_API_KEY', 'AIzaSy…')` in `app/Console/Commands/GetCompanies.php` —
schlägt damit doppelt an, über das Muster und über die Vorgabewert-Regel.

Übersprungen werden `vendor/`, `node_modules/`, `public/build/`, `storage/`,
Sperrdateien und Binärformate.

### Fehlalarm entschärfen

Zwei Wege, beide mit Begründung:

* Eine einzelne Zeile: Kommentar `secret-scan:ignore` in dieselbe Zeile.
* Eine ganze Datei: Zeile `pfad:regelname` in `.secret-scan-allow`
  (Pfad darf mit `*` enden, `*` als Regel deckt alle ab).

Eingetragen ist bisher nur `.env.testing:app-key` — ein erfundener Testschlüssel,
der an keinem Konto hängt.

## Wo der Scan greift

**Pre-Commit-Hook** (`.githooks/pre-commit`) prüft die vorgemerkten Änderungen
und bricht den Commit ab. Aktiviert wird er einmalig:

```
composer run hooks:install    # setzt core.hooksPath auf .githooks
```

`composer install` erledigt das über `post-install-cmd` von selbst; ohne
Git-Verzeichnis bleibt das Skript still. Umgehen geht mit `git commit --no-verify`
und braucht einen guten Grund.

**CI** (`.github/workflows/secret-scan.yml`) scannt bei jedem Push und jedem Pull
Request den gesamten versionierten Stand plus `.env.example`. Das ist die
verbindliche Zusage — der Hook wirkt nur auf Rechnern, auf denen er installiert
ist.

## `.env.example`

Neu angelegt und versioniert. Sie führt alle erwarteten Variablen ohne Werte,
`GOOGLE_PLACES_API_KEY` eingeschlossen, und wird vom Scan mitgeprüft. Reine
Feineinstellungen der Content-Pipeline stehen weiterhin nur in
`config/content.php`; sie hätten in der Beispieldatei mit leerem Wert sogar
geschadet, weil ein leerer `.env`-Eintrag den Vorgabewert aus der Config
überschreibt.

`.gitignore` ignoriert seit #98 zusätzlich alle `.env.*`-Dateien mit Ausnahme von
`.env.example` und `.env.testing`. Damit können lokale Messvarianten wie
`.env.lighthouse` nicht mehr versehentlich mitcommittet werden.

## Grenze

Der Scan sieht den Arbeitsstand, nicht die Versionsgeschichte. Was einmal
committet wurde, bleibt in der Historie und gilt als kompromittiert — der
Schlüssel muss dann beim Anbieter gelöscht und neu ausgestellt werden
(#88, #97, `docs/messungen/places-key-rotation-anleitung.md`).
