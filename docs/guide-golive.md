# Go-Live des Ratgebersystems — Checkliste, Rollout, Abnahme

Status: **bereit zur Durchführung, nicht begonnen**
Ticket: #21 (Vorbereitung), Durchführung #39
Stand: 2026-09-21
Löst für das themengetriebene System `docs/content-golive.md` (alte Pipeline, #26) ab.

Reihenfolge ist verbindlich: §0 und §1 sind Vorbedingungen, §2–§5 die drei Stufen,
§6 die Notbremse, §7 die Abnahme. Jede Zeile wird mit Datum und Kürzel abgehakt
(`[x] 2026-10-01 EN`). Eine Zeile gilt nur mit Beleg (Befehlsausgabe, Screenshot,
Link auf den Tagesbericht) als erledigt; Belege liegen unter `docs/messungen/guide-golive/`.

Sicherheitsseite: `docs/guide-security-review.md`. Architektur, Budgets, Kostenmodell:
`docs/guide-system.md` (SUN-RG-001).

---

## 0. Stand am 21.09.2026 — was den Start heute verhindert

| # | Blocker | Folge | Erledigt durch |
|---|---|---|---|
| B1 | Das gesamte Ratgebersystem ist **nicht eingecheckt**: `app/Guide/`, `config/guide.php`, `config/guide_lint.php`, alle `2026_09_22_*`-Migrationen, `deploy/supervisor/guide-*.conf`, Seeder. Produktion steht auf `653a080` ohne `app/Guide`. | Nichts davon ist deploybar. | #41 (Freigabe Enes): `scripts/guide-einchecken.sh` |
| B2 | Security-Review hat offene Punkte (Import-Pfad aus dem Client, offene Weiterleitung nach Login, Altpfad ohne Sanitizing). | Review nicht „ohne offene Punkte“. | #37 |
| B3 | Guide-Alarme verschicken keine Mail; Queue `guide-assets` hat keinen Worker; YMYL hebt die Schwelle nicht auf 90; Portal-Budget wirkt beim Modellaufruf nicht; Tagesbericht kennt „fällig“ nicht. | Checkliste §1 und Messung §3 nicht erfüllbar. | #38 |
| B4 | Anthropic-Guthaben war am 09.09.2026 erschöpft (HTTP 400 „credit balance is too low“). | Kein Lauf möglich. | Aufladen, Nachweis `guide:llm:ping` |

Start von Stufe 1 erst, wenn B1–B4 erledigt sind.

## 0.1 Rollout-Schalter

Maßgeblich ist `tenant_guide_settings.is_active` in der Datenbank des Portals, Vorgabe
`false`. Der `DailyOrchestrator` überspringt inaktive Portale für `dispatch` und
`watchdog` vollständig (`app/Guide/Orchestration/DailyOrchestrator.php:123-128`): keine
Probe, kein Schreiben, keine Kosten.

Bedienung heute: Content-Panel › *Einstellungen › Portal* (nur Rolle `owner`), Felder
„Portal ist freigeschaltet“, „YMYL“, „Auto-Freigabe ab Score“, „Tagesbudget“.
Konsole (#40, G8): `php artisan guide:rollout [--activate=<domain>] [--deactivate=<domain>]
[--threshold=] [--ymyl] [--activate-all|--deactivate-all] [--force]` — Tabelle mit der
wirksamen Schwelle; Sammelaktionen fragen nach (Vorgabe nein), `--force` ohne Rückfrage.
`content:rollout` wirkt nicht mehr und endet mit Exit 1.

Schwelle (#40, G3/G4): wirksam ist `max(eingetragen, 90)` bei YMYL; bei wirksam 100 gibt es
keine Auto-Freigabe. Vorbedingungen prüft `php artisan guide:golive:check` (Exit 1 bei FAIL,
kostet einen `guide:llm:ping`).

---

## 1. Vorbedingungen

### 1.1 Deploy

Server: `ssh sun` (root), Installation `/home/sanitaerfinden/htdocs/sanitaerfinden.dev`,
handgepflegt, kein Deployer (Ablauf `docs/messungen/produktionsumgebung-anleitung.md`).
Artisan immer als Benutzer `sanitaerfinden`.

- [ ] B1 erledigt: Commit mit dem Ratgebersystem liegt auf `origin/main`, Hash hier eintragen: `________`
  Ablauf (#41): `scripts/guide-einchecken.sh --pruefen`, dann
  `FREIGABE=$(git rev-parse --short HEAD) scripts/guide-einchecken.sh --committen` (vier Commits: Rückbau, Ratgebersystem, Themes/Panel, Doku) und
  `FREIGABE=$(git rev-parse --short HEAD) scripts/guide-einchecken.sh --pushen`. Die vier Commits nur gemeinsam pushen und deployen.
- [ ] **Backup vor der Migration** (§1.3) — die Migrationen `2026_09_22_000005` (tenant) und `2026_09_22_000014` (central) droppen Alt-Tabellen.
- [ ] `git pull --ff-only`, `composer install --no-dev -o`, `npm ci && npm run build`
- [ ] `php artisan migrate --force` (central) — Ausgabe ablegen
- [ ] `php artisan tenants:migrate --force` — Ausgabe ablegen, alle 23 Portale ohne Fehler
- [ ] Seeder: `GuidePromptTemplateSeeder`, `GuideSourceListSeeder`, `TenantGuideSettingSeeder` (legt Einstellungen mit `is_active = false` an)
- [ ] `config:cache`, `route:clear`, `view:clear`, `filament:optimize`
- [ ] Worker neu starten: `php artisan horizon:terminate` und `php artisan queue:restart` (entspricht `artisan:horizon:terminate`/`artisan:queue:restart` in `deploy.php:176-177`)

### 1.2 Zugänge und Schlüssel

- [ ] Treiber geklärt (`GUIDE_LLM_DRIVER`, Vorgabe `cli`, #42):
  - `cli`: `CLAUDE_CLI_BINARY` und `CLAUDE_CODE_OAUTH_TOKEN` gesetzt, die CLI ist für `sanitaerfinden` ausführbar. `ANTHROPIC_API_KEY` ist nicht nötig und wird der CLI ohnehin entzogen.
  - `api`: `ANTHROPIC_API_KEY` gesetzt, Guthaben aufgeladen.
- [ ] `sudo -u sanitaerfinden php artisan guide:llm:ping` nach `config:cache` meldet ok, zeigt den Treiber und mindestens zwei Quellen
- [ ] `FAL_API_KEY` gesetzt (Titelbilder #20; der Name ist `FAL_API_KEY`, nicht `FAL_KEY`)
- [ ] `APP_KEY` wird **nie** gewechselt — die IndexNow-Keys sind daraus abgeleitet (§1.4)
- [ ] Schlüssel stehen nur in `.env`, kein Wert in Git (`php scripts/secret-scan.php` sauber)

### 1.3 Backup der Artikel-Tabelle vor dem ersten Lauf

```
php artisan content:golive:backup            # alle Portale
```

Sichert `posts` und `guide_legacy_articles` (Altartikel-Blöcke, #34) je Portal als NDJSON nach
`storage/app/backups/content/<datum>/`. Es gibt keine Restore-Option; das Backup ist die
Rückfallebene für einen manuellen Import. Mit #38 (G10) kommen `guide_redirects` und die
Guide-Tabellen dazu.

- [ ] Backup vor der Migration (§1.1): Ordner, Dateianzahl = 23 Portale
- [ ] Backup am Morgen des ersten Laufs von Stufe 1 (vor 02:00): Ordner
- [ ] Stichprobe: Zeilenzahl `posts` eines Portals in NDJSON = `SELECT COUNT(*) FROM posts`
- [ ] Kopie außerhalb des Servers abgelegt (Ort: `________`)

### 1.4 IndexNow-Keys je Domain

Kein gespeicherter Wert: der Key ist `HMAC-SHA256('indexnow|<tenant-key>', APP_KEY)`,
32 Zeichen, ausgeliefert unter `https://<domain>/<key>.txt`
(`IndexNowClient::keyLocation($tenant)`, `IndexNowKeyController`).

- [ ] `GUIDE_INDEXNOW_ENABLED=true` in `.env`
- [ ] Je Domain Key-URL ermitteln (tinker: `app(\App\Guide\Publishing\IndexNowClient::class)->keyLocation($tenant)`) und per `curl -s -o /dev/null -w '%{http_code}'` prüfen — **200 und Inhalt = Key** für alle 23:

| Domain | 200 | Domain | 200 | Domain | 200 |
|---|---|---|---|---|---|
| firmenfreund.de | [ ] | bodenlegerfinden.com | [ ] | sanitaerfinden.com | [ ] |
| fahrschulefinder.de | [ ] | elektrikerportal.com | [ ] | malerfinder.de | [ ] |
| geruestbauer.gmbh | [ ] | metallbauer.io | [ ] | tierarztportal.com | [ ] |
| kfzwerkstatt.io | [ ] | findegutachter.de | [ ] | fliesenleger.io | [ ] |
| mjet.net | [ ] | sanitaerfinder.com | [ ] | apotheke.firmenfreund.de | [ ] |
| firmenfreund.net | [ ] | unfallarzt.firmenfreund.de | [ ] | zahnarzt.firmenfreund.de | [ ] |
| klempner.firmenfreund.de | [ ] | energieberaterportal.net | [ ] | arztfinder.firmenfreund.de | [ ] |
| speditionportal.com | [ ] | schluesseldienstportal.com | [ ] | | |

- [ ] Cloudflare-Regeln blockieren `/<key>.txt` nicht (Bot-Fight/WAF): Abruf mit User-Agent `Bingbot` liefert 200

### 1.5 Supervisor / Horizon / Scheduler

Produktion betreibt Horizon (`sanitaerfinden-horizon` unter Supervisor). **Nicht** zusätzlich
`deploy:supervisor-content` bzw. `deploy/supervisor/guide-*.conf` einspielen — sonst hängen
zwei Konsumenten an jeder Queue.

- [ ] `supervisorctl status` → `sanitaerfinden-horizon RUNNING` (Stand 21.09.2026: läuft)
- [ ] `php artisan horizon:supervisors` listet `supervisor-guide-research`, `-write`, `-publish`
- [ ] Queue `guide-assets` hat einen Konsumenten (#38 G6) — sonst entstehen keine Titelbilder
- [ ] Alle `guide.queues` (dispatch, research, write, publish, assets) im Horizon-Dashboard sichtbar
- [ ] Crontab `sanitaerfinden`: `schedule:run` im Minutentakt (Stand 21.09.2026: vorhanden)
- [ ] `php artisan schedule:list` zeigt `guide:daily --stage=dispatch` 02:00, `--stage=watchdog` alle 10 min, `--stage=report` 20:00 (Europe/Berlin)

### 1.6 Budgets

Werte in `.env`. **0 heißt „kein Limit“**, nie 0 setzen, um etwas zu stoppen — dafür ist
`is_active` da.

| Schlüssel (`.env`) | Stufe 1 | Stufe 2 | Stufe 3 |
|---|---:|---:|---:|
| `GUIDE_BUDGET_DAILY_USD_TOTAL` | 10,00 | 20,00 | aus §4, Obergrenze 60,00 ohne Uwes Freigabe |
| `GUIDE_BUDGET_DAILY_USD_PER_TENANT` | 5,00 | 5,00 | aus §4 |
| `GUIDE_BUDGET_MAX_USD_PER_RUN` | 1,50 | 1,50 | 1,50 |
| `GUIDE_MAX_CREATES_PER_TENANT_PER_DAY` | 5 | 5 | 5 |

- [ ] Werte der aktuellen Stufe gesetzt, `config:cache` ausgeführt
- [ ] *Einstellungen › Portal* zeigt dieselben Werte (Panel-Tagesbudget je Portal leer lassen, bis #38 G5 erledigt ist — es wirkt sonst nur bei der Auswahl)
- [ ] Budgetfreigabe Stufe 3 durch Uwe (Datum, Betrag): `________`

### 1.7 Alarme an Enes und Uwe

Empfänger sind alle nicht gesperrten Konten mit `guide_role = owner`.

- [ ] `php artisan guide:user:create <mail-enes> --role=owner`
- [ ] `php artisan guide:user:create <mail-uwe> --role=owner`
- [ ] #38 G1/G2 erledigt: kritische Alarme gehen sofort per Mail
- [ ] Probe Tagesbericht: `php artisan guide:daily --stage=report --mail-to=<mail-enes>` kommt an (Postfach, nicht Spam)
- [ ] Probe Alarm: ein ausgelöster `provider_down` (beim Treiber `cli` z. B. `CLAUDE_CODE_OAUTH_TOKEN` kurz ungültig, beim Treiber `api` `ANTHROPIC_API_KEY` kurz leer; `guide:run` auf einem Test-Thema) erreicht beide Postfächer; danach Wert zurück, `config:cache`
- [ ] Mail-Versand läuft über die Queue: Horizon zeigt die Mail-Jobs als verarbeitet

---

## 2. Stufe 1 — ein Portal, 3 Tage, alles in die Prüfung

**Portal:** ein Handwerksportal ohne YMYL mit mindestens 30 zugewiesenen Themen und
vollständiger Gliederung. Auswahl am Tag 0 im Panel unter *Themen*: das Portal mit den
meisten Themen im Status `queued`. Nicht das zentrale Portal der Installation (Ausfall dort
träfe alle).

Einstellungen: `is_active = an`, `is_ymyl = aus`, Auto-Freigabe **100** (bis #38 G4:
eine Fassung mit exakt 100 würde veröffentlicht — Tagessichtung prüft das mit).

Täglich (Enes, ~45–60 min bei 5 Neuanlagen):

1. 08:00 Tagesbericht der Nacht lesen (Mail) — Alarme zuerst.
2. *Prüfung* abarbeiten: jede Fassung nach `docs/messungen/golive-sichtpruefung-leitfaden.md`
   (Tagessichtung, Stufen ok / anmerkung / stopp). Entscheiden: *Freigeben*, *Mit Hinweis neu
   schreiben* oder *Verwerfen*. Keine Fassung bleibt über Nacht liegen.
3. Protokollzeile in `docs/messungen/guide-golive/stufe-1.md`:

| Tag | Läufe | create | unchanged | update | review | failed | Kosten USD | Stopps | Notiz |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---|

**Ausstieg Stufe 1** (alle erfüllt, sonst Stufe verlängern, nicht überspringen):

- [ ] 3 aufeinanderfolgende Tage ohne `failed`-Lauf, der nicht erklärt ist
- [ ] 0 Stopps in den letzten 2 Tagen
- [ ] keine Fassung mit Score 100 automatisch live gegangen
- [ ] Kosten je Lauf liegen je Lauftyp höchstens 30 % über dem Modell (§4)
- [ ] Titelbild für jedes neu angelegte Thema vorhanden

## 3. Stufe 2 — drei Portale inkl. YMYL, 7 Tage, reguläre Schwellen

**Portale:** das Portal aus Stufe 1, ein zweites Handwerksportal, ein YMYL-Portal
(Gesundheit/Recht, z. B. `arztfinder.firmenfreund.de` oder `zahnarzt.firmenfreund.de`;
Auswahl nach Themenzahl).

Einstellungen: Handwerk Schwelle **80**; YMYL `is_ymyl = an`, Schwelle **90 von Hand**
(bis #38 G3).

### 3.1 Tägliche Stichprobe — 10 Artikel

Nicht frei greifen. Zusammensetzung je Tag:

| Anzahl | Auswahl |
|---:|---|
| 3 | YMYL-Portal, davon mindestens 1 mit Score 90–93 |
| 4 | Handwerk, Score 80–85 (knapp über der Schwelle) |
| 2 | Läufe vom Typ `update` (abschnittsweise geändert) |
| 1 | beliebiger `unchanged`-Lauf (für den dateModified-Nachweis §3.3) |

Geprüft wird auf der **öffentlichen Portalseite**, nicht in der Vorschau. Je Artikel die
Belegprüfung aus dem Leitfaden: jede Zahl hat (1) einen zugeordneten Fakt, (2) eine
erreichbare Quelle, (3) einen Zeitbezug, (4) denselben Wert wie die Quelle.
Protokoll `docs/messungen/guide-golive/stufe-2-stichprobe.md`:

| Tag | Portal | Artikel-URL | Lauftyp | Score | Zahlen gesamt | davon unbelegt | Ergebnis | Notiz |
|---|---|---|---|---:|---:|---:|---|---|

Eine unbelegte Zahl ist immer **stopp**: Artikel zurückrollen (*Verlauf › Versionen*), Fall
als Ticket anlegen (Befund im `FactChecker` übersehen?).

### 3.2 Prüfquote ≥ 95 %

Quelle ist der Tagesbericht (`guide_daily_reports.report_json`, je Portal), nicht das Panel.
Quote je Portal und Tag = `checked / due`. Bis #38 G7 fehlt `due`; Näherung
`due ≈ checked + deferred + open`.

| Tag | Portal | due | checked | Quote | ≥ 95 %? |
|---|---|---:|---:|---:|---|

### 3.3 dateModified nur bei inhaltlicher Änderung

Regel im Code: JSON-LD `dateModified` = `content_changed_at`; das setzt nur ein
`create`/`update`. Ein `unchanged`-Lauf setzt nur `last_checked_at` („Geprüft am“).

Nachweis (einmal an Tag 3 und Tag 7):

1. Vor dem Nachtlauf für 5 Artikel `dateModified` aus dem JSON-LD der öffentlichen Seite und die Stand-Zeile notieren.
2. Nach dem Lauf erneut: bei `unchanged` bleibt `dateModified` gleich, nur „Geprüft am“ springt; bei `update` ändern sich beide und der Changelog hat einen neuen Eintrag.
3. Sitemap-`lastmod` verhält sich wie `dateModified`.

| Artikel | Lauftyp | dateModified vorher | nachher | Geprüft-Datum nachher | lastmod | ok |
|---|---|---|---|---|---|---|

**Ausstieg Stufe 2** — das sind die Akzeptanzkriterien von #21:

- [ ] an allen 7 Tagen Quote ≥ 95 % je Portal
- [ ] 0 unbelegte Zahlen in 70 Stichproben-Artikeln
- [ ] dateModified-Nachweis an Tag 3 und 7 ohne Abweichung
- [ ] Kostenabgleich §4 ausgefüllt

## 4. Kosten: Ist gegen Modell (SUN-RG-001)

Ist-Werte aus `llm_usage_logs` (`operation LIKE 'guide.%'`, `guide_topic_id`) bzw. Panel
*Verlauf › Kosten* (je Thema). Modell aus `docs/guide-system.md` §8 (inkl. 15 % Aufschlag).

Beim Treiber `cli` (#42) sind die Beträge **rechnerisch**: `total_cost_usd` der CLI zu Listenpreisen, abgerechnet wird über das Claude-Abo. `llm_usage_logs.driver` weist den Treiber je Zeile aus. Der Vergleich mit dem Modell bleibt aussagekräftig für Token- und Suchmengen; eine Rechnung dazu gibt es nicht. Die Grenze setzt hier das Nutzungslimit des Abos, das Budget greift trotzdem.

| Größe | Modell | Ist Stufe 1 | Ist Stufe 2 | Abweichung |
|---|---:|---:|---:|---:|
| `create` je Thema | 0,783 USD | | | |
| `unchanged` (Probe) | 0,091 USD | | | |
| `update` | 0,605 USD | | | |
| Änderungsquote je Prüfung | 10 % | | | |
| Erwartungswert je Prüfung | 0,142 USD | | | |
| Suchen je Probe / Tiefenrecherche | 2 / 6 | | | |
| Kosten je Portal und Tag (Mittel / Höchstwert) | 2,03 USD (laufend) | | | |
| Titelbild (fal.ai) je Thema | Cent-Bereich | | | |

Regeln:

* Abweichung > +30 % bei einem Lauftyp: **nicht** in Stufe 3, sondern Ursache klären (Suchen, Retries, Fix-Durchläufe).
* Budget Stufe 3: `DAILY_USD_PER_TENANT` = Höchstwert je Portal und Tag aus Stufe 2 × 1,25, aufgerundet auf 0,50 USD, mindestens 5,00. `DAILY_USD_TOTAL` = Mittelwert je Portal und Tag × 23 × 1,28, höchstens 60,00 ohne Uwes Freigabe.
* Die beschlossenen Werte hier und in §1.6 eintragen, mit Datum.

## 5. Stufe 3 — alle Portale

- [ ] Budgets aus §4 gesetzt, Freigabe Uwe (§1.6)
- [ ] Backup §1.3 am Vortag
- [ ] alle Portale `is_active = an`, YMYL-Portale mit Schwelle 90 (Liste: `________`)
- [ ] erster vollständiger Tageslauf: Tagesbericht vom `________` zeigt alle 23 Portale, Quote je Portal ≥ 95 %, keine unerklärten `failed` (Link/Beleg ablegen)
- [ ] Stichprobe weiter täglich 10 Artikel über alle Portale, bis 7 Tage ohne Stopp

## 6. Notbremse und Rückweg

| Lage | Schritt |
|---|---|
| Falsche Inhalte gehen live | Portal(e) `is_active = aus` (Panel); betroffene Artikel über *Verlauf › Versionen* zurückrollen |
| Kosten laufen weg | `GUIDE_BUDGET_DAILY_USD_TOTAL` klein positiv (z. B. 1,00) + `config:cache` — **nicht 0** |
| Queue hängt | `horizon:terminate`, Watchdog setzt Läufe nach 20 min neu an; nach 2 Neuansätzen `failed` |
| Datenverlust `posts` | NDJSON aus §1.3 manuell einspielen (kein Restore-Befehl) |

## 7. Abnahme

| Punkt | Beleg | Datum | Kürzel |
|---|---|---|---|
| §1 vollständig abgehakt | | | |
| Stufe 1 Ausstieg | `stufe-1.md` | | |
| Stufe 2 Ausstieg (95 %, 0 unbelegte Zahlen, dateModified) | `stufe-2-*.md` | | |
| Kostenabgleich §4 | | | |
| Security-Review ohne offene Punkte | `docs/guide-security-review.md` (S1–S4 behoben, #37) | 2026-09-21 | #37 |
| Stufe 3: alle Portale aktiv, vollständiger Tageslauf im Tagesbericht | | | |
| **Abnahme durch Enes** | | | |
