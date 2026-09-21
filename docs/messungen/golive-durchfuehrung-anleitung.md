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

## 0. Ausgangsstand am 09.09.2026 (auf Produktion erhoben)

Erhoben auf `88.198.64.145` (SSH-Kürzel `sun`), Anwendung unter
`/home/sanitaerfinden/htdocs/sanitaerfinden.dev`, alle Befehle als
`sudo -u sanitaerfinden /usr/bin/php8.4 artisan …`.

### 0.1 Vorher: keine Zeile in `tenant_content_settings`

Erster Lauf von `content:golive:check` auf Produktion: **143 Prüfpunkte, 1
Fehler, 73 Warnungen, Exit-Code 1.** Ursache der Masse an Warnungen war nicht
die Pflege, sondern eine fehlende Zeile: `tenant_content_settings` war in
**allen 23** Portal-Datenbanken leer. `TenantRollout::status()` liest daraus
`articles_per_day`, `auto_publish_threshold`, `is_ymyl` und `gsc_property` und
fällt ohne Zeile auf 0 beziehungsweise leer zurück. Jedes Portal meldete
deshalb „articles_per_day ist 0 — das Portal erzeugt nichts", „Schwelle 0" und
„gsc_property ist leer".

Nachgezogen mit dem vorhandenen, idempotenten Seeder:

```
php artisan db:seed --class=TenantContentSettingSeeder --force
```

Ergebnis: **23 angelegt, 0 bereits vorhanden.** Der Seeder schreibt
`articles_per_day = 2`, `auto_publish_threshold` 90 für YMYL und 80 sonst,
`is_ymyl` aus dem Slug von Name und Domain, das Veröffentlichungsfenster und
`gsc_property = sc-domain:<domain>`. `is_active` bleibt bewusst `false` — die
Freischaltung ist allein Sache von `content:rollout`.

> **Stand #35:** `content:rollout` ist entfernt. Freigeschaltet wird über
> `tenant_guide_settings.is_active` im Content-Panel unter *Einstellungen › Portal*
> („Portal ist freigeschaltet“); die `content:rollout`-Aufrufe unten sind Historie.

Als YMYL erkannt wurden sechs Portale: Tierarztportal.com, ApothekeFinden,
Unfallchirurgie in der Nähe, Zahnarzt in der Nähe, Energieberater, ArztFinder.

### 0.2 Nachher: ein Fehler, 21 Warnungen

`content:golive:check`: **143 Prüfpunkte, 1 Fehler, 21 Warnungen, Exit-Code 1.**

| Punkt | Zustand | Befund | Abschnitt |
| --- | --- | --- | --- |
| Anthropic-Zugang | Fehler | `ANTHROPIC_API_KEY` fehlt in der Produktions-`.env` | 1 |
| Voyage-Zugang | Warnung | `VOYAGE_API_KEY` fehlt | 1 |
| Search-Console-Dienstkonto | Warnung | `GOOGLE_SERVICE_ACCOUNT_JSON` nicht gesetzt | 1 |
| AdSense-Ertragsdaten | Warnung | abgeschaltet, `pageviews` und `adsense_revenue_usd` bleiben null | 1 |
| Freigeschaltete Portale | Warnung | kein Portal freigeschaltet — so gewollt, siehe 0.4 | 5 |
| Auto-Live-Schwelle | Warnung | 17 Nicht-YMYL-Portale stehen auf 80 statt 85 | 5 |

Die 17 Schwellen-Warnungen setzt `content:rollout --threshold=85` beim Rollout
selbst; der Befehl greift nur auf **freigeschaltete** Portale, vorher ändert er
nichts. Sie sind kein offener Punkt.

### 0.3 Was auf Produktion in Ordnung ist

Abschnitt 2 (Budget) und Abschnitt 3 (Betrieb) sind vollständig grün:

| Punkt | Befund |
| --- | --- |
| Pipeline eingeschaltet | `CONTENT_PIPELINE_ENABLED=true` |
| Zeitzone des Scheduler | `Europe/Berlin` |
| Sieben Quell-Connectoren | eingeschaltet, Zugang vollständig |
| Budget | 35,00 USD/Tag, 1.050,00 USD/Monat, 2,50 USD je Portal × 23 gegen 35,00 |
| Provider-Anteile | Summe 1,00 |
| Queue-Verbindung | `redis` |
| Alle sechs Pipeline-Queues | je in einem Horizon-Supervisor |
| `APP_KEY` | gesetzt |
| Deploy-Ziel (`deploy.php`) | Host, Domain und Repository eingetragen |
| Scheduler | Tagesbericht `content:report:daily` steht auf `0 20 * * *` |

Erreichbarkeit am Ursprung geprüft (an Cloudflare vorbei, `--resolve` auf
`88.198.64.145`) für die drei Portale der Woche 1: `/` und `/bot` antworten je
mit HTTP 200.

Sicherung nach dem Seeder neu geschrieben:
`php artisan content:golive:backup` → 46 Dateien in
`storage/app/backups/content/2026-09-09`, `posts` und `article_drafts` je
Portal, durchweg 0 Zeilen (es ist noch kein Ratgeber erschienen).

Tagesbericht geprobt: `content:report:daily --no-mail` meldet „0 von 0
Artikeln" — die Zählung nimmt korrekt nur freigeschaltete Portale (#102).
Danach einmal echt versandt, Bestätigung „Tagesbericht versandt an:
kul@widimedia.com".

### 0.4 Was den Go-Live weiterhin blockiert

1. **`ANTHROPIC_API_KEY` fehlt** (#120, Handarbeit Enes). Ohne Modellzugang
   erzeugt kein Portal einen Artikel; jeder Tageslauf bräche in der
   Erzeugungsstufe ab. Das ist der eine Fehler des Checks.
2. **`VOYAGE_API_KEY` fehlt** — ohne Embeddings arbeitet die Duplikatsprüfung
   nur über SimHash.
3. **`GOOGLE_SERVICE_ACCOUNT_JSON` fehlt** — die Search-Console-Quelle liefert
   nichts; `gsc_property` ist zwar seit 0.1 überall gepflegt, aber ohne
   Dienstkonto ungeprüft (#107).
4. **Modellguthaben** (#105) — auch mit Schlüssel ist ein Kalendertag ohne
   Eingriff erst danach möglich.
5. **Zweiter Empfänger fehlt.** `ContentAlert::ownerRecipients()` liefert nur
   `kul@widimedia.com`; für Uwe existiert kein Redaktions-Account mit der Rolle
   `owner`. Abschnitt 4 der Checkliste verlangt beide.

Solange Punkt 1 offen ist, wird **nicht freigeschaltet**. Ein Portal
freizuschalten setzt `activated_at` auf den Tag der Freischaltung; die
Wochenzählung der Abnahme (`activeTenantsOn()`, #102) begänne dann mit Tagen
ohne jede Produktion, und die Vorgabe „40 von 42" wäre strukturell verfehlt.

### 0.5 Automatische Datenbanksicherung ist wirkungslos

Bei der Suche nach dem Datenbank-Backup außerhalb des Servers gefunden: unter
`/home/sanitaerfinden/backups/databases/<db>/<datum>/` liegt für jeden Tag und
jede der 23 Datenbanken genau eine `.sql.gz` — jede davon **20 Byte groß**, also
ein leeres gzip. Das betrifft auch die zentrale Datenbank `sun`. Der zugehörige
Cron-Eintrag in `/etc/cron.d/clp` ruft `clpctl db:backup` auf; dieser Befehl
existiert in der installierten CloudPanel-Version nicht mehr. Es gibt damit auf
Produktion **keine verwertbare Datenbanksicherung**. Eigenes Ticket, siehe #134.

Die NDJSON-Sicherung aus `content:golive:backup` ist davon unberührt und
geschrieben; sie deckt aber nur `posts` und `article_drafts`.

### 0.6 Zusammensetzung der Woche 1

Vorgesehen, sobald Punkt 0.4/1 erledigt ist:

| Rolle | Portal | ID | Domain | Schwelle |
| --- | --- | --- | --- | --- |
| YMYL | ApothekeFinden | 50 | apotheke.firmenfreund.de | 90 (YMYL-Boden) |
| Handwerk | Hoch- und Tiefbauunternehmen | 24 | firmenfreund.de | 85 |
| Handwerk | ElektrikerPortal | 30 | elektrikerportal.com | 85 |

```
php artisan content:rollout --activate=50 --activate=24 --activate=30 --threshold=85
```

Gewählt, weil alle drei am Ursprung mit HTTP 200 antworten, kein Portal davon
in den Cloudflare-Weiterleitungen aus #127 oder den vhost-Namen ohne Tenant aus
#128 steckt und `sanitaerfinden.com` als `CENTRAL_DOMAIN` bewusst außen vor
bleibt.

---

## 1. Vorbedingungen (Checkliste Abschnitte 1 bis 4)

Reihenfolge: Zugänge, Budget, Betrieb, Alarme. Jede Zeile der Abschnitte 1 bis 4
von `docs/content-golive.md` abhaken, dann:

```
php artisan content:golive:check
```

**Probe:** Der Befehl endet mit Exit-Code 0 und meldet 0 Fehler. Warnungen sind
zulässig, müssen aber im Protokoll unten je Zeile begründet stehen.

- **Auto Ads:** Meldet der Check bei einem Portal „Anzeigenplatz auto_ads: n
  aktiver Platz", darf dieses Portal erst öffentlich gehen, wenn im
  AdSense-Konto unter *Auto Ads → Anzeigenformate* die Anker-Anzeigen
  abgeschaltet sind. Nachweis mit Datum, Konto und ausführender Person ins
  Protokoll (Abschnitt 9) eintragen; ohne Nachweis den Platz vorher auf inaktiv
  setzen (Vorgabe #100, Abschnitt 6).

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

## 8. Was vor der Abnahme noch offen ist

Stand 09.09.2026. Die Codefehler, die die Abnahmezahlen verfälscht hätten, sind
inzwischen erledigt: #100 (CLS durch `auto_ads`), #101 (Places-Schlüssel über
`config()`), #102 (Bericht zählt nur freigeschaltete Portale), #103/#113
(Aktualisierung zählt nicht als zweiter Artikel), #104 (Providerstatus bei
leerem Guthaben), #106 (Produktionsumgebung).

Offen und vor Abschnitt 3 dieser Anleitung zu erledigen:

| Ticket | Wirkung auf den Go-Live |
| --- | --- |
| #120 | Anthropic-, Voyage- und Google-Schlüssel auf Produktion eintragen — ohne den Anthropic-Schlüssel erzeugt kein Portal einen Artikel. Der eine Fehler des Checks. |
| #105 | Modellguthaben aufladen und einen Kalendertag ohne Eingriff laufen lassen |
| #107 | `gsc_property` ist seit dem Seeder-Nachzug (siehe 0.1) überall gepflegt, der Zugriff des Dienstkontos je Property ist aber ungeprüft — `content:rollout` zeigt den Zustand je Portal (#116) |
| #134 | Automatische Datenbanksicherung schreibt leere Dumps — die Rückfahrkarte für Abschnitt 2 fehlt |
| — | Zweiter Redaktions-Account mit der Rolle `owner` für Uwe (#135), sonst erreicht der Tagesbericht nur Enes |

Vor der Woche 2 zusätzlich zu klären, weil sie Portale betreffen, die dann
öffentlich Ratgeber ausliefern: #130 und #131 (Cloudflare-Weiterleitungen und
fehlende Namen im Zertifikat). #129 (Weiterleitungsschleife der www.-Namen) ist
am 09.09.2026 behoben.

---

## 9. Protokoll

Je Durchlauf ausfüllen und als `docs/messungen/golive-durchfuehrung-<datum>.md`
ablegen.

```
## Vorbedingungen
Datum:                       <tt.mm.jjjj>
content:golive:check:        <n> Prüfpunkte, <n> Fehler, <n> Warnungen, Exit <0/1>
Begründete Warnungen:        <Zeile — Begründung>
Auto-Ads-Nachweis:           <Portal> — Anker-Anzeigen aus am <tt.mm.jjjj>, Konto <id>, durch <name> / kein aktiver Platz
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
