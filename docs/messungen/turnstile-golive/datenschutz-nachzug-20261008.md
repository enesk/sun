# Datenschutztext der fünf fehlenden Portale nachgezogen (#49)

Datum: 2026-10-08, abends
Produktion: `sun`, `/home/sanitaerfinden/htdocs/sanitaerfinden.dev`, Stand `a0bc8016`
Bezug: §1.3 von `docs/turnstile-golive.md`, blockierte Stufe 1 für fahrschulefinder.de

## Befund

`tenants:datenschutz:turnstile-backfill` übersprang fünf Portale, weil sie gar keinen
Datenschutztext hatten. `tenants:legal:seed` (Trockenlauf) nennt den Grund — es waren
die fünf jüngsten Tenants des Netzes, bei denen die netzweiten Betreiberangaben nie
nachgezogen wurden:

| ID | Portal | Domain | fehlte |
|----|--------|--------|--------|
| 29 | FahrschuleFinder | fahrschulefinder.de | Straße, PLZ, Ort, verantwortliche Person |
| 33 | Malerfinder.de | malerfinder.de | Straße, PLZ, Ort, E-Mail, Telefon, verantwortliche Person |
| 49 | SanitaerFinder | sanitaerfinder.com | wie 33 |
| 54 | Klempner | klempner.firmenfreund.de | wie 33 |
| 58 | Schlüsseldienst | schluesseldienstportal.com | wie 33 |

Weder Impressum noch Datenschutz waren vorhanden — es war also nicht nur ein leeres
Feld. Alle 18 übrigen Portale: `vollständig — nichts zu tun`.

## Durchführung

Je Schritt erst Trockenlauf, dann `--write`, immer eingeschränkt auf die fünf IDs
(`--tenant=29 --tenant=33 --tenant=49 --tenant=54 --tenant=58`), als
`sudo -u sanitaerfinden php artisan …`:

1. `tenants:address:seed` → 4 × Anschrift gesetzt, 1 × Felder ergänzt
   (Betreiberanschrift aus `TenantOperatorDefaults`)
2. `tenants:contact:seed` → 4 × Angaben gesetzt, 1 × Felder ergänzt; die eigene
   Telefonnummer von FahrschuleFinder (`+491511550944`) blieb unangetastet
3. `tenants:legal:seed` → 5 × `befüllt: Impressum, Datenschutz`
4. `tenants:datenschutz:turnstile-backfill`, ohne und mit `--write`
   → **23 × vorhanden, 0 × übersprungen** (Soll des Tickets)

Der Turnstile-Abschnitt musste nicht getrennt angehängt werden:
`TenantLegalDefaults::DATENSCHUTZ` enthält Abschnitt 3.1 bereits, der Backfill ist
nach Schritt 3 nur noch die Gegenprobe. Geschrieben wurde ausschließlich additiv —
keines der vier Kommandos überschreibt einen nicht leeren Wert.

## Gegenprobe

Platzhalterauflösung je Tenant über `TenantBrandingService::resolveLegalPlaceholders()`:

```
FahrschuleFinder   laenge= 15450 turnstile=ja offene-platzhalter=keine
Malerfinder.de     laenge= 15341 turnstile=ja offene-platzhalter=keine
SanitaerFinder     laenge= 15341 turnstile=ja offene-platzhalter=keine
Klempner           laenge= 15317 turnstile=ja offene-platzhalter=keine
Schlüsseldienst    laenge= 15349 turnstile=ja offene-platzhalter=keine
```

Alle fünf enthalten `<h3>3.1 Bot-Schutz mit Cloudflare Turnstile</h3>`; das Impressum
löst ebenfalls ohne offene Platzhalter auf.

Im Netz:

| URL | HTTP | Turnstile-Abschnitt |
|-----|------|---------------------|
| https://fahrschulefinder.de/datenschutz | 200 | ja (Anschrift `Karlsruherstr. 31` aufgelöst) |
| https://malerfinder.de/datenschutz | 200 | ja |
| https://sanitaerfinder.com/datenschutz | 200 | ja |
| https://klempner.firmenfreund.de/datenschutz | 200 | ja |
| https://schluesseldienstportal.com/datenschutz | 200 | ja |

Damit ist der Datenschutz-Blocker für Stufe 1 weg; offen bleibt vor Stufe 1 nur
`TURNSTILE_ALERT_RECIPIENTS` (§1.5).
