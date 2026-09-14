# Go-Live der Ratgeber-Pipeline — Checkliste und Rollout

Status: **verbindlich**
Ticket: #26
Letzte Änderung: 2026-09-09

Diese Checkliste führt die Ratgeber-Pipeline in den Regelbetrieb. Sie ist in der
Reihenfolge abzuarbeiten, in der sie hier steht: die Abschnitte 1–4 sind
Vorbedingungen, 5 ist der Rollout der Woche 1, 6 der Rollout der Woche 2.

Die Ausführung auf Produktion — Reihenfolge, Befehle, Proben und Protokoll —
steht in `docs/messungen/golive-durchfuehrung-anleitung.md` (Ticket #89).

Der Zustand der Produktionsumgebung selbst — Server, Deploy-Ziel, Domains,
Schlüssel, Queues — steht in `docs/messungen/produktionsumgebung-anleitung.md`
(Ticket #106). Sie ist der Vorlauf zu allem hier.

Was maschinell prüfbar ist, prüft `php artisan content:golive:check`. Der Befehl
endet mit Exit-Code 0, wenn kein Punkt als Fehler markiert ist. Er ersetzt nicht
die Sichtprüfung — die Zeilen unten mit **(manuell)** kann nur ein Mensch abhaken.

```
php artisan content:golive:check          # alle Portale
php artisan content:golive:check --only-active
```

---

## 0. Rollout-Schalter — wie ein Portal an- und ausgeht

Maßgeblich ist `tenant_content_settings.is_active` in der Datenbank des Portals,
Vorgabe `false`. Solange der Schalter aus ist, überspringt der Tages-Orchestrator
das Portal vollständig: keine Themenfindung, keine Erzeugung, keine
Veröffentlichung, kein Slot-Wachhund, keine Kosten.

```
php artisan content:rollout                                   # Zustand aller Portale
php artisan content:rollout --activate=sanitaer.de --threshold=85
php artisan content:rollout --deactivate=sanitaer.de          # Portal herausnehmen
php artisan content:rollout --activate-all --threshold=85     # Woche 2
php artisan content:rollout --deactivate-all                  # Notbremse
```

`--threshold` setzt die Auto-Live-Schwelle aller freigeschalteten Portale.
YMYL-Portale fallen dabei nie unter `content.quality.ymyl_auto_approve_score`
(90) — auch wenn die Welle 85 vorgibt.

Derselbe Schalter steht im Content-Panel unter *Einstellungen → Produktion*
(„Portal ist freigeschaltet"), sichtbar nur für die Rolle `owner`.

**Notbremse:** `content:rollout --deactivate-all` stoppt die Erzeugung sofort.
Bereits veröffentlichte Artikel bleiben stehen; zum Zurückziehen einzelner
Artikel dient der Weg über `Publisher::unpublish()` im Panel.

---

## 1. Zugänge und Schlüssel

Stand 09.09.2026: die drei Schlüssel dieses Abschnitts sind auf der Produktion
**noch nicht eingetragen** — sie müssen erst in den Konten von Anthropic,
Voyage und Google ausgestellt werden. Alles Maschinelle dazu ist fertig: der
Ablauf steht in `docs/messungen/produktionsschluessel-anleitung.md`, das
Eintragen samt Proben erledigt

```bash
scripts/produktionsschluessel-eintragen.sh --dienstkonto ~/Downloads/search-console.json
```

Das ist der einzige Punkt dieser Checkliste, der ohne Kontozugang gar nicht
vorbereitbar ist. Solange er offen ist, meldet `content:golive:check` genau
einen Fehler („Anthropic-Zugang") und Woche 1 kann nicht starten.

- [ ] `CONTENT_PIPELINE_ENABLED=true` in der Produktions-`.env`.
- [ ] `ANTHROPIC_API_KEY` gesetzt, Schlüssel auf ein eigenes Projekt mit
      eigenem Ausgabenlimit ausgestellt.
- [ ] `VOYAGE_API_KEY` gesetzt (Embeddings für die Duplikatsprüfung).
- [ ] `GOOGLE_SERVICE_ACCOUNT_JSON` zeigt auf die Schlüsseldatei des
      Dienstkontos. Die Datei liegt **außerhalb** von `public/`, Rechte 0600,
      Eigentümer der Webserver-Benutzer.
- [ ] **(manuell)** Das Dienstkonto ist in der Search Console **jeder**
      Property als Nutzer mit Leserecht eingetragen. Ohne diesen Eintrag
      liefert die API leere Antworten statt eines Fehlers.
- [ ] Je Portal `tenant_content_settings.gsc_property` gepflegt, Form
      `sc-domain:example.de`. Pflege in einem Durchgang:
      `php artisan content:gsc:property --fill` (mit `--dry-run` erst zeigen,
      mit `--force` auch abweichende Werte nachziehen). Prüfung:
      `content:golive:check` meldet die Zeile „Search-Console-Property" je
      Portal.
- [ ] Ein gepflegter Wert heißt noch nicht, dass Daten fließen. Den
      Zugriffsstand je Portal zeigen `php artisan content:gsc:property --check`
      und die Spalte *Search Console* in `content:rollout`; „Kein Zugriff"
      bedeutet, dass das Dienstkonto in der Property fehlt.
- [ ] **(manuell)** Dienstkonto, Property und AdSense Schritt für Schritt:
      `docs/messungen/metrik-abnahme-zugaenge.md` (Ticket #90).
- [ ] AdSense-Ertragsdaten: entweder `CONTENT_ADSENSE_ENABLED=false` (dann
      bleiben `pageviews` und `adsense_revenue_usd` bewusst leer) oder Schalter
      an **und** `ADSENSE_ACCOUNT_ID` gesetzt **und** die Schlüsseldatei lesbar
      (`ADSENSE_SERVICE_ACCOUNT_JSON`, ersatzweise `GOOGLE_SERVICE_ACCOUNT_JSON`).
      `content:golive:check` meldet die Zeile „AdSense-Ertragsdaten"; der
      abgeschaltete Zustand ist eine Warnung, kein Fehler.
- [ ] **(manuell)** Für jede Domain ist die IndexNow-Schlüsseldatei abrufbar.
      Der Schlüssel wird aus `APP_KEY` abgeleitet und unter
      `https://<domain>/<key>.txt` berechnet ausgeliefert — nichts zu
      hinterlegen, aber einmal je Domain im Browser prüfen. Die Adresse zeigt
      `content:golive:check` je Portal an.
- [ ] `https://<domain>/bot` ist erreichbar (Auskunftsseite des Recherche-Bots,
      steht im User-Agent der Connectoren).
- [ ] `APP_KEY` ist gesetzt und wird beim Deploy **nicht** neu erzeugt. Ein
      Wechsel entwertet alle IndexNow-Schlüssel und alle signierten
      Vorschau-Adressen.

## 2. Budget

- [ ] `CONTENT_BUDGET_DAILY_USD` freigegeben (Vorgabe 35).
- [ ] `CONTENT_BUDGET_MONTHLY_USD` freigegeben (Vorgabe 1050).
- [ ] `CONTENT_BUDGET_DAILY_USD_PER_TENANT` gesetzt (Vorgabe 2,50).
- [ ] `CONTENT_BUDGET_MAX_USD_PER_ARTICLE` gesetzt (Vorgabe 1,50).
- [ ] Summe der Provider-Anteile `content.budget.provider_share` ≤ 1,0.
- [ ] **(manuell)** Im Anthropic-Konto ist ein Ausgabenlimit als zweite
      Sicherung eingetragen. Der `BudgetGuard` schützt vor Fehlläufen der
      eigenen Anwendung, nicht vor einem entwendeten Schlüssel.

Erschöpftes Budget setzt den Provider in `provider_states` auf `paused` mit
`circuit_open_until` = morgen 00:00. Der Zustand steht im Panel unter
*Übersicht*; er ist kein Fehler, sondern die eingebaute Bremse.

## 3. Betrieb

- [ ] `QUEUE_CONNECTION` steht auf `redis`. Nicht `sync`, und auch nicht
      `database`: die Queues der Pipeline werden ausschließlich über Redis
      gefahren (Entscheidung #110).
- [ ] `php artisan horizon:status` meldet `running`, und `supervisorctl status`
      zeigt genau **ein** Programm `horizon` (angelegt von `dep
      provision:supervisor`). Horizon bedient alle sechs Queues aus
      `content.pipeline.queues` über die Supervisoren
      `supervisor-content-sources`, `supervisor-content-generate` und
      `supervisor-content-publish` in `config/horizon.php`.
- [ ] Die Programme aus `deploy/supervisor/` laufen **nicht** und
      `dep deploy:supervisor-content` wurde **nicht** ausgeführt. Sie sind der
      Ausweichweg für Server ohne Horizon; parallel zu Horizon zieht jede Queue
      zwei Konsumenten. `content:golive:check` kennt nur den Horizon-Weg und
      meldet für diesen Ausweichweg drei Fehler bei den Queue-Zeilen.
- [ ] Es läuft kein weiterer `queue:work`-Supervisor auf der Queue `default`
      (alte Programme wie `<projekt>-worker` gehören entfernt, sobald Horizon
      steht — `supervisor-1` in `config/horizon.php` bedient `default` bereits).
- [ ] Cron ruft `schedule:run` jede Minute auf. Die Einträge der Pipeline
      stehen in `routes/console.php`, Zeitzone `Europe/Berlin`.
- [ ] Deploy setzt `php artisan queue:restart` **nach** dem Wechsel des
      Release-Symlinks. Ohne den Neustart arbeiten die Worker weiter mit dem
      alten Code.
- [ ] `deploy.php` trägt ein echtes Ziel: `$host`, `$domain` und
      `$repository` sind nicht mehr die Starterkit-Platzhalter
      (`1.2.3.4`, `yourdomain.com`, `git@github.com:username/saasykit.git`).
      `content:golive:check` meldet die Zeile „Deploy-Ziel (deploy.php)" als
      Warnung, solange einer der drei Werte unverändert ist.
- [ ] `php artisan tenants:migrate` ist auf allen Portalen durch, insbesondere
      `2026_09_10_000015_add_rollout_switch_to_tenant_content_settings`.

## 4. Alarme, Bericht und Sicherung

- [ ] Für Enes und Uwe existiert je ein aktives Administratorkonto
      (`users.is_admin`, notfalls `php artisan app:create-admin-user`).
      An genau diese Adressen gehen Tagesbericht (20:00) und Alarme, und nur
      diese Konten kommen in `/content` hinein.
- [ ] Testversand: `php artisan content:report:daily --no-mail` zeigt den
      Bericht, danach einmal ohne `--no-mail` gegen die echten Empfänger.
- [ ] **Sicherung der Artikeltabellen vor dem ersten Lauf:**
      `php artisan content:golive:backup`
      schreibt `posts` und `article_drafts` jedes Portals als NDJSON nach
      `storage/app/backups/content/<datum>/`. `content:golive:check` prüft, ob
      für den Stichtag eine Sicherung liegt.
- [ ] **(manuell)** Zusätzlich ein Datenbank-Backup des Tages außerhalb des
      Servers. Die NDJSON-Sicherung ist die schnelle Rückfahrkarte, nicht das
      Backup.

## 5. Woche 1 — drei Portale

Zusammensetzung: ein YMYL-Portal, zwei Handwerksportale. Auto-Live-Schwelle 85
(YMYL bleibt bei 90).

Die mit **(manuell)** markierten Zeilen dieses Abschnitts und des Abschnitts 6
sind in `docs/messungen/golive-sichtpruefung-leitfaden.md` ausformuliert:
Auswahl der Stichprobe, Prüfpunkte je Artikel, Schwellen für „bestanden" und
die Protokollvorlage.

- [ ] `php artisan content:rollout --activate=<ymyl> --activate=<handwerk-1> --activate=<handwerk-2> --threshold=85`
- [ ] `php artisan content:golive:check --only-active` ohne Fehler.
- [ ] Tag 1: erster Tageslauf beobachtet. `php artisan content:daily --tenant=<portal> --date=<heute>` ist der
      Weg zum Nachholen, falls der Scheduler einen Lauf verpasst hat.
- [ ] **(manuell)** Enes sichtet **alle** Artikel der Woche 1 in der Prüf-Queue
      des Panels — auch die automatisch veröffentlichten.
- [ ] Abnahme Woche 1: mindestens **40 von 42** Zielartikeln (3 Portale × 2
      Artikel × 7 Tage) automatisch veröffentlicht. Zahl aus dem Tagesbericht.
- [ ] **(manuell)** Stichprobe von 10 Artikeln: keine Zahl ohne Beleg
      (jede Zahl hat einen `fact_snippet` mit Quelle und Zeitraum).
- [ ] **(manuell)** Stichprobe von 10 Artikeln: kein Doorway-Muster — keine
      zwei Artikel, die sich nur im Ortsnamen unterscheiden.
- [ ] Kosten der Woche im Panel unter *Leistung* gegen das Budget geprüft.

## 6. Woche 2 — alle Portale

- [ ] `php artisan content:rollout --activate-all --threshold=85`
- [ ] `php artisan content:golive:check` ohne Fehler (jetzt für alle Portale,
      insbesondere `gsc_property` je Domain).
- [ ] Erster vollständiger Tag mit 2 Artikeln je Portal im Tagesbericht belegt.
- [ ] Tagesbericht läuft täglich um 20:00 und erreicht beide Empfänger.
- [ ] Kosten des ersten vollen Tages innerhalb des Tagesbudgets.
- [ ] **(manuell)** Abnahme durch Enes.

---

## Wenn etwas schiefgeht

| Befund | Griff |
| --- | --- |
| Ein Portal produziert Unsinn | `content:rollout --deactivate=<portal>` |
| Alle Portale stoppen | `content:rollout --deactivate-all` |
| Kosten laufen weg | `CONTENT_BUDGET_DAILY_USD` senken, Worker neu starten |
| Ein Slot bleibt leer | Der Slot-Wachhund zieht bis zu drei Anläufe nach; danach Alarm im Panel |
| Artikel muss weg | Im Panel zurückziehen — setzt `published_at` zurück, löscht den Fingerprint und schreibt Sitemap und Cache neu |
| Artikeltabelle beschädigt | NDJSON aus `storage/app/backups/content/<datum>/` einlesen |

Der Security-Review zum Go-Live steht in `docs/content-security-review.md`.
