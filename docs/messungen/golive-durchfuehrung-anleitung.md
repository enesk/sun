# Go-Live auf Produktion — Durchführungsanleitung und Protokoll

Status: **verbindlich für die Ausführung**
Tickets: #89 (Ausführung), #26 (Checkliste `docs/content-golive.md`)
Letzte Änderung: 2026-09-09

Diese Anleitung führt die Abschnitte 1 bis 6 von `docs/content-golive.md` auf
**Produktion** aus. Sie ersetzt keine Zeile dort, sondern sagt, in welcher
Reihenfolge, mit welchem Befehl und mit welcher Probe jeder Schritt abgehakt
wird. Die inhaltliche Sichtung der Artikel steht getrennt in
`golive-sichtpruefung-leitfaden.md` — hier geht es nur um den Betrieb.

Alles, was hier steht, braucht Zugriff auf den Produktionsserver, die
Search-Console-Properties und die Konten (Anthropic, Voyage, AdSense). Aus dem
Repository heraus ist kein Schritt ausführbar.

---

## 0. Ausgangsstand am 09.09.2026 (lokal erhoben)

`php artisan content:golive:check` lokal: **128 Prüfpunkte, 3 Fehler, 32
Warnungen, Exit-Code 1.** Die Fehler und die betriebsrelevanten Warnungen:

| Punkt | Befund | Abschnitt |
| --- | --- | --- |
| Pipeline eingeschaltet | `content.enabled` ist false (`CONTENT_PIPELINE_ENABLED`) | 1 |
| Search-Console-Property (Handwerker, Apotheke) | `gsc_property` leer, Portal aber aktiv | 1 |
| Voyage-Zugang | `VOYAGE_API_KEY` fehlt | 1 |
| Search-Console-Dienstkonto | `GOOGLE_SERVICE_ACCOUNT_JSON` nicht gesetzt | 1 |
| AdSense-Ertragsdaten | abgeschaltet, Kennzahlen bleiben null | 1 |
| Deploy-Ziel | `deploy.php` trägt noch `1.2.3.4` / `yourdomain.com` / `git@github.com:username/saasykit.git` | 3 |
| Auto-Live-Schwelle | 12 Portale stehen auf 80 statt 85 (setzt `content:rollout --threshold=85`) | 5 |

`php artisan content:rollout` lokal: 3 von 20 Portalen freigeschaltet
(Sanitär, Handwerker, Apotheke) — das ist der Staging-Stand aus #81 und
zugleich die vorgesehene Zusammensetzung der Woche 1 (ein YMYL-Portal, zwei
Handwerksportale).

**Diese Werte sind der lokale Stand, nicht der Produktionsstand.** Ein
Produktionsserver existiert noch nicht: `deploy.php` zeigt auf die
Starterkit-Platzhalter, alle 20 Portale laufen auf `.test`-Domains. Solange das
so ist, ist Abschnitt 1 dieser Anleitung nicht durchführbar.

### Drei unbrauchbare Domainwerte

`zahnarzt.test#` (Doppelkreuz am Ende), `geruestbuaer.gmbh` (Schreibfehler, kein
`.test`) und `schlusseldienst` (ohne Domainendung). Aus ihnen baut
`content:golive:check` bereits fehlerhafte IndexNow-Adressen. Vor der Woche 2
müssen sie auf die echten Produktionsdomains gesetzt werden, sonst schlagen
IndexNow-Meldung, `gsc_property` und Sitemap dieser drei Portale fehl.

---

## 1. Vorbedingungen (Checkliste Abschnitte 1 bis 4)

Reihenfolge: Zugänge, Budget, Betrieb, Alarme. Jede Zeile der Abschnitte 1 bis 4
von `docs/content-golive.md` abhaken, dann:

```
php artisan content:golive:check
```

**Probe:** Der Befehl endet mit Exit-Code 0 und meldet 0 Fehler. Warnungen sind
zulässig, müssen aber im Protokoll unten je Zeile begründet stehen.

## 2. Sicherung vor dem ersten Lauf

```
php artisan content:golive:backup
```

schreibt `posts` und `article_drafts` jedes Portals als NDJSON nach
`storage/app/backups/content/<datum>/`. Zusätzlich ein Datenbank-Backup des
Tages **außerhalb** des Servers ablegen.

**Probe:** Für den Stichtag liegt ein Verzeichnis mit je einer NDJSON-Datei pro
Portal, und das externe Backup ist an seinem Ablageort geprüft (Größe > 0,
Datum von heute).

## 3. Woche 1 — drei Portale freischalten

Zusammensetzung: ein YMYL-Portal, zwei Handwerksportale.

```
php artisan content:rollout --activate=<ymyl> --activate=<handwerk-1> --activate=<handwerk-2> --threshold=85
php artisan content:golive:check --only-active
```

YMYL-Portale bleiben trotz `--threshold=85` bei 90 — das ist gewollt und keine
Fehlbedienung.

**Probe:** `content:rollout` ohne Argumente zeigt genau drei Portale mit „ja",
und `content:golive:check --only-active` endet ohne Fehler.

## 4. Tag 1 beobachten

Der Tageslauf startet über den Scheduler. Hat der Scheduler einen Lauf verpasst:

```
php artisan content:daily --tenant=<portal> --date=<heute>
```

**Probe:** Der Tagesbericht um 20:00 erreicht Enes und Uwe und weist für die drei
Portale je zwei Artikel aus.

## 5. Abnahme Woche 1

Zählwerte aus dem Tagesbericht, Sichtung nach
`golive-sichtpruefung-leitfaden.md`.

- Mindestens **40 von 42** Zielartikeln (3 Portale × 2 Artikel × 7 Tage)
  automatisch veröffentlicht.
- Stichprobe von 10 Artikeln ohne unbelegte Zahl.
- Stichprobe von 10 Artikeln ohne Doorway-Muster.
- Kosten der Woche im Panel unter *Leistung* innerhalb des Budgets.

**Probe:** Das ausgefüllte Protokoll liegt als
`docs/messungen/golive-sichtpruefung-<datum>.md` im Repository.

## 6. Woche 2 — alle Portale

```
php artisan content:rollout --activate-all --threshold=85
php artisan content:golive:check
```

**Probe:** Ein vollständiger Kalendertag mit 2 Artikeln je Portal ist im
Tagesbericht belegt, und die Kosten dieses Tages liegen unter
`CONTENT_BUDGET_DAILY_USD`.

## 7. Notbremse

```
php artisan content:rollout --deactivate=<portal>     # ein Portal
php artisan content:rollout --deactivate-all          # alle
```

Bereits veröffentlichte Artikel bleiben stehen; einzelne Artikel zieht man im
Panel zurück.

---

## 8. Offene Codefehler, die die Abnahme verfälschen

Diese Tickets sind noch offen und betreffen genau die Zahlen, an denen die
Abnahme hängt. Sie gehören vor Abschnitt 5 dieser Anleitung erledigt:

| Ticket | Wirkung auf die Abnahme |
| --- | --- |
| #102 | Tagesbericht zählt alle 20 Portale statt der freigeschalteten — die Quote der Woche 1 ist damit nicht ablesbar |
| #103 | Kindfassung einer Aktualisierung zählt als zweiter veröffentlichter Artikel — „2 Artikel je Portal" wäre vorgetäuscht |
| #104 | Providerstatus meldet „aktuell" bei erschöpftem Modellguthaben — ein Ausfall des Tageslaufs bliebe unbemerkt |
| #101 | Places-Import liest den Schlüssel mit `env()` statt `config()` und bricht auf Produktion mit gecachter Config ab |
| #100 | Anzeigenplatz `auto_ads` verursacht CLS bis 1,0 auf der Ratgeber-Seite, die mit dem Go-Live öffentlich wird |
| #105 | Modellguthaben aufladen und einen Kalendertag ohne Eingriff laufen lassen — Voraussetzung für Abschnitt 6 |
| #106 | Produktionsumgebung: Server, Deploy-Ziel, echte Domains, Schlüssel — ohne sie ist Abschnitt 1 nicht durchführbar |
| #107 | `gsc_property` bei 19 von 20 Portalen leer — zwei der drei Fehler des Checks stammen daher |

---

## 9. Protokoll

Je Durchlauf ausfüllen und als `docs/messungen/golive-durchfuehrung-<datum>.md`
ablegen.

```
## Vorbedingungen
Datum:                       <tt.mm.jjjj>
content:golive:check:        <n> Prüfpunkte, <n> Fehler, <n> Warnungen, Exit <0/1>
Begründete Warnungen:        <Zeile — Begründung>
Sicherung NDJSON:            <Pfad>            Größe: <n>
Datenbank-Backup extern:     <Ablageort>       Datum: <tt.mm.jjjj>

## Woche 1
Freigeschaltet:              <ymyl>, <handwerk-1>, <handwerk-2>     Schwelle: 85
Start:                       <tt.mm.jjjj>      Ende: <tt.mm.jjjj>
Automatisch veröffentlicht:  <n> von 42        (Vorgabe: ≥ 40)
Stichprobe unbelegte Zahlen: <n> von 10 Artikeln auffällig
Stichprobe Doorway:          <n> von 10 Artikeln auffällig
Kosten der Woche:            <n> USD           Budget: <n> USD
Abnahme Enes:                bestanden / nicht bestanden

## Woche 2
Freigeschaltet:              alle <n> Portale  Schwelle: 85
Voller Tag:                  <tt.mm.jjjj>      Artikel: <n> von <n> (2 je Portal)
Kosten des Tages:            <n> USD           Tagesbudget: <n> USD
Tagesbericht 20:00:          erreicht Enes: ja/nein   erreicht Uwe: ja/nein
Abnahme Enes:                bestanden / nicht bestanden
```
