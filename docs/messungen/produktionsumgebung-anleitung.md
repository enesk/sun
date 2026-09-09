# Anleitung: Produktionsumgebung für den Go-Live bereitstellen (#106)

Stand der Erhebung: **09.09.2026**, erhoben von einem Agentenlauf mit Lesezugriff
auf den Server (`ssh sun`). Die Schritte 3 bis 7 kann nur ein Mensch mit Zugang zu
den Schlüsselkonten und der Freigabe für Änderungen am Live-System ausführen.

## 0. Der wichtigste Befund vorweg

**Es gibt bereits eine Produktionsumgebung.** Die frühere Annahme („SUN hat keine
Produktion") ist falsch. Belege:

| Sache | Wert |
| --- | --- |
| Server | `88.198.64.145` (SSH-Kürzel `sun`, User `root`), Hetzner, Ubuntu 24.04 |
| Verwaltung | CloudPanel (`/home/clp`), nginx 1.28, PHP-FPM, PHP-CLI 8.4.21, Redis aktiv |
| Installation | `/home/sanitaerfinden/htdocs/sanitaerfinden.dev`, Git-Remote `git@github.com:enesk/sun.git` |
| Stand dort | Commit `fc06dd3` (lokaler HEAD ist `e7e4664`) |
| Umgebung | `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://sanitaerfinden.com`, `CENTRAL_DOMAIN=sanitaerfinden.com` |
| Portale | 23 Tenants mit **echten** Domains (Tabelle in Abschnitt 4) |

Was dort **fehlt**: das gesamte Content-Vorhaben. `app/Content` existiert auf dem
Server nicht, `php artisan content:golive:check` antwortet dort mit
„There are no commands defined in the `content:golive` namespace".

Grund: der Code ist nie eingecheckt worden. Im Arbeitsverzeichnis liegen
**363 unversionierte Dateien** und 59 geänderte, darunter `app/Content/`,
`config/content*.php`, `deploy/supervisor/`, `docs/`, `design/`. Ein Deploy zieht
aus GitHub — dort liegt nichts davon. Das ist die erste harte Vorbedingung und
wird als eigenes Ticket geführt (siehe Abschnitt 1).

Der Server hostet außerdem **fremde Live-Projekte** (widimedia.com,
kasernencheck.de, pickyourpic.de, eneskul.com, esygraphy.de, theone-sportsbar.de,
perigee/analytics.widimedia.com). Deshalb gilt ausnahmslos:

> `dep provision` und alle `provision:*`-Tasks dürfen auf diesem Server **nicht**
> laufen. Sie installieren nginx, PHP und Supervisor neu und nehmen die fremden
> Seiten mit. `deploy.php` trägt diesen Hinweis seit #106 im Kopf.

## 1. Vorbedingung: Code nach GitHub bringen

Ohne diesen Schritt ist jeder weitere sinnlos.

1. Arbeitsstand sichten und in nachvollziehbaren Commits ablegen (Secret-Scan
   läuft im Pre-Commit-Hook mit: `composer run secrets:scan`).
2. Nach `origin/main` pushen.

**Probe:** `git status --porcelain | wc -l` ist 0 und
`git ls-remote origin main` zeigt denselben Commit wie `git rev-parse HEAD`.

## 2. Deploy-Ziel

`deploy.php` trug bis #106 die Starterkit-Platzhalter. Jetzt eingetragen:

```php
$remoteUser = 'sanitaerfinden';
$host       = '88.198.64.145';
$domain     = 'sanitaerfinden.com';
$repository = 'git@github.com:enesk/sun.git';
$phpVersion = '8.4';
```

Die laufende Installation ist **von Hand** eingerichtet und liegt nicht in einer
Deployer-Release-Struktur. `dep deploy` legt eine neue Struktur unter
`~/app` (also `/home/sanitaerfinden/app`) an und ändert an der ausgelieferten
Seite nichts, solange der vhost `sanitaerfinden.dev.conf` weiter auf
`htdocs/sanitaerfinden.dev` zeigt. Der Wechsel des vhost-Roots auf
`{{deploy_path}}/current/public` ist ein bewusster, einmaliger Handgriff mit
Wartungsfenster — oder man bleibt beim heutigen Weg (`git pull` im
Installationsverzeichnis) und benutzt Deployer gar nicht.

**Probe:** `php artisan content:golive:check` zeigt bei „Deploy-Ziel (deploy.php)"
ein ✓ (lokal am 09.09.2026 bestätigt).

## 3. Produktions-`.env` ergänzen

In `/home/sanitaerfinden/htdocs/sanitaerfinden.dev/.env` fehlen alle Schlüssel des
Vorhabens (`ANTHROPIC_API_KEY` und `VOYAGE_API_KEY` sind dort leer, die
`CONTENT_*`-Zeilen fehlen ganz). Zu ergänzen, Werte nach Abschnitt 2 der
Checkliste `docs/content-golive.md`:

```
CONTENT_PIPELINE_ENABLED=true
ANTHROPIC_API_KEY=…
VOYAGE_API_KEY=…
GOOGLE_SERVICE_ACCOUNT_JSON=storage/app/private/google-service-account.json
CONTENT_BUDGET_DAILY_USD=…
CONTENT_BUDGET_DAILY_USD_PER_TENANT=…
CONTENT_BUDGET_MONTHLY_USD=…
CONTENT_ALERT_MAIL=…
CONTENT_AUTHOR_USER_ID=…
```

Die Dienstkonto-Datei gehört nach `storage/app/private/`, **nie** unter `public/`;
`content:golive:check` weist das sonst als Fehler aus.

**Probe:** `php artisan content:metrics:preflight` läuft ohne Fehler, und
`content:golive:check` zeigt bei „Anthropic-Zugang", „Voyage-Zugang" und
„Search-Console-Dienstkonto" ✓.

## 4. Domains je Portal

Die echten Domains stehen bereits im vhost und in der Produktionsdatenbank. Die
lokale Entwicklungsdatenbank führt dieselben Portale auf `.test`; drei Werte waren
dort kaputt und sind mit #106 begradigt:

| Portal | lokal vorher | lokal jetzt | Produktion |
| --- | --- | --- | --- |
| Gerüstbauer | `geruestbuaer.gmbh` (Dreher, echte Domain) | `geruestbauer.test` | `geruestbauer.gmbh` |
| Zahnarzt | `zahnarzt.test#` (Doppelkreuz) | `zahnarzt.test` | `zahnarzt.firmenfreund.de` |
| Schlüsseldienst | `schlusseldienst` (ohne Endung) | `schluesseldienst.test` | `schluesseldienstportal.com` |

Annahme dazu: lokal bleibt es bei `.test`, damit kein Entwicklungslauf gegen die
echte Domain `geruestbauer.gmbh` läuft. Die Produktionsdatenbank führt die echten
Werte ohnehin selbst.

**Achtung:** derselbe Doppelkreuz-Fehler steckt auch in der Produktion, im vhost
und in CloudPanel: `tierarztportal.com#` steht als eigener `server_name` neben dem
richtigen `tierarztportal.com`. Der Tenant-Eintrag ist sauber, der vhost nicht —
beim nächsten CloudPanel-Durchgang mit entfernen.

Damit so etwas nicht wieder unbemerkt bleibt, prüft `content:golive:check` seit
#106 nicht mehr nur, ob eine Domain gesetzt ist, sondern auch ihre Schreibweise
(Protokollpräfix, fehlende Endung, unerlaubte Zeichen).

**Probe:** In `content:golive:check` steht in keiner Zeile „ist kein gültiger
Hostname"; alle IndexNow-Adressen sehen aufrufbar aus.

## 5. Betrieb: Queues, Horizon, Cron

Heutiger Stand auf dem Server:

| Punkt | Stand 09.09.2026 |
| --- | --- |
| `QUEUE_CONNECTION` | `database` — nicht Redis, obwohl Redis läuft |
| Horizon | läuft nicht; kein Horizon-Programm im Supervisor |
| Supervisor | `sanitaerfinden-worker` (beide Prozesse **FATAL**, „Exited too quickly"), `kasernencheck-moderation` |
| Cron | eingetragen am 09.09.2026, `schedule:run` läuft im Minutentakt |

### 5.1 Wegentscheidung (#110): Horizon auf Redis

Es gibt zwei Wege, die Queues der Pipeline zu bedienen. Gewählt ist **Horizon
auf Redis**. Die drei Programme aus `deploy/supervisor/` bleiben ungenutzt und
`dep deploy:supervisor-content` wird auf diesem Server **nie** ausgeführt.

Gründe:

- Horizon ist der Standardweg des Projekts. `dep provision:supervisor` legt das
  Programm `horizon` an, `deploy.php` ruft nach jedem Release
  `artisan:horizon:terminate`. Der Ausweichweg ist eine Sonderlocke, die bei
  jedem Deploy mitgedacht werden muss.
- `config/horizon.php` führt die drei Content-Supervisoren bereits vollständig
  samt Produktions- und Staging-Skalierung; sie decken alle sechs Queues.
- Beide Wege setzen Redis voraus: die Programme in `deploy/supervisor/` starten
  `queue:work redis`. `QUEUE_CONNECTION=database` ist also in jedem Fall falsch.
- `content:golive:check` prüft die Queues gegen `config('horizon.defaults')`.
  Der Horizon-Weg erreicht Exit 0 ohne Eingriff in den Prüfcode. Den
  Ausweichweg anzuerkennen hieße, im Check einen Serverzustand zu raten, den
  die Konfiguration nicht kennt.
- Horizon liefert zusätzlich die Warteschlangen-Sicht unter `/horizon`, die der
  Betrieb aus #26 für Wartezeiten und fehlgeschlagene Jobs braucht.

### 5.2 Schritte auf dem Server

Reihenfolge einhalten — erst Redis, dann Horizon, dann den alten Worker
abräumen.

1. **Ursache des FATAL klären, bevor etwas geändert wird.** Das Log des
   Programms steht in seiner Conf:
   ```
   grep -h "stdout_logfile\|command" /etc/supervisor/conf.d/sanitaerfinden-worker.conf
   tail -n 50 <stdout_logfile>
   ```
   „Exited too quickly" heißt: der Prozess stirbt in unter einer Sekunde.
   Die üblichen drei Ursachen, in dieser Reihenfolge prüfen:
   `command` zeigt auf einen Pfad, den es nicht (mehr) gibt (falscher
   `deploy_path`, alter Release-Symlink); `queue:work redis` bei fehlender
   PHP-Erweiterung `redis` (`php8.4 -m | grep redis`); oder ein Fehler beim
   Hochfahren der Anwendung, der auch `php artisan about` auslöst. Der Befund
   gehört als Zeile in dieses Dokument, auch wenn das Programm danach entfällt.
2. **`.env` auf Redis stellen** und den Config-Cache neu bauen:
   ```
   QUEUE_CONNECTION=redis
   REDIS_CLIENT=phpredis
   ```
   danach `php artisan config:cache`. Der bestehende Cache stammt vom
   12.05.2026, siehe Abschnitt „Achtung Config-Cache" — der Neuaufbau ist Teil
   des Deploys aus Abschnitt 1 und darf nicht vorgezogen werden.
   Wartende Jobs in der Tabelle `jobs` gehen mit dem Wechsel der Verbindung
   verloren: vorher `select count(*) from jobs` prüfen und den Rest abarbeiten
   lassen.
3. **Horizon-Programm anlegen.** Entweder `dep provision:supervisor` (legt
   `/etc/supervisor/conf.d/horizon.conf` an; Abschnitt 0 dieses Dokuments
   beachten, der Task fasst auch nginx und PHP an) oder die Conf von Hand mit
   demselben Inhalt und dem echten Pfad
   `/home/sanitaerfinden/htdocs/sanitaerfinden.dev`. `APP_ENV` muss `production`
   sein, sonst startet Horizon keinen einzigen Supervisor: fehlt der Eintrag
   für die laufende Umgebung unter `horizon.environments`, lässt
   `ProvisioningPlan::deploy()` die Programme kommentarlos aus.
4. **Alten Worker entfernen.** `supervisor-1` in `config/horizon.php` bedient
   die Queue `default` mit zehn Prozessen; `sanitaerfinden-worker` wird damit
   überflüssig und würde die Portal-Jobs doppelt ziehen:
   ```
   supervisorctl stop sanitaerfinden-worker:*
   rm /etc/supervisor/conf.d/sanitaerfinden-worker.conf
   supervisorctl reread && supervisorctl update
   ```
   `kasernencheck-moderation` gehört zu einer fremden Anwendung und bleibt.
5. **Starten und prüfen:** `supervisorctl start horizon`, dann
   `php artisan horizon:status`.
6. `php artisan tenants:migrate` über alle Portale.

Bei jedem Release gilt weiter: `artisan:horizon:terminate` nach dem Wechsel des
Symlinks (steht bereits in `deploy.php`). Ein zusätzliches `queue:restart`
schadet nicht, ist mit Horizon aber überflüssig.

**Probe:** `php artisan horizon:status` sagt `running`, `supervisorctl status`
zeigt alle Programme `RUNNING` und kein Programm mehr aus `deploy/supervisor/`,
`crontab -u sanitaerfinden -l` enthält `schedule:run`, und
`php artisan content:golive:check` zeigt bei „Queue-Verbindung" und den sechs
Zeilen „Queue content-…" kein ✗.

## 6. Rollout und Abnahme

Erst danach greift #89: Sicherung (`content:golive:backup`), Freischaltung von
drei Portalen (`content:rollout --activate=…`), Woche 1, dann alle. Ablauf:
`docs/messungen/golive-durchfuehrung-anleitung.md`, Sichtprüfung:
`docs/messungen/golive-sichtpruefung-leitfaden.md`.

## 7. Abschlussprobe des Tickets

```
cd /home/sanitaerfinden/htdocs/sanitaerfinden.dev && php artisan content:golive:check
```

Fertig ist #106, wenn dieser Aufruf **auf dem Server** mit Exit-Code 0 endet.
Lokal endet er am 09.09.2026 mit Exit 1 und drei Fehlern: Pipeline abgeschaltet
(so gewollt, lokal) und zwei leere `gsc_property` (#107).

## Protokoll

| Abschnitt | Datum | Wer | Ergebnis / Befund |
| --- | --- | --- | --- |
| 1 Code gepusht |  |  | offen — #109, 156 unversionierte Dateien unter `app/Content` |
| 2 Deploy-Ziel | 2026-09-09 | steffen | erledigt, `deploy.php` auf 88.198.64.145 / sanitaerfinden.com / enesk/sun |
| 3 Produktions-`.env` | 2026-09-09 | steffen | Schalter und Budget gesetzt; die drei Schlüssel fehlen, siehe unten |
| 4 Domains | 2026-09-09 | steffen | erledigt, 23 Portale gültig, vhost begradigt, 18 Stichproben HTTP 200 |
| 5 Betrieb | 2026-09-09 | steffen | Cron läuft; Weg entschieden (Horizon/Redis, #110), Umsetzung auf dem Server offen |
| 6 Rollout |  |  | offen — #89 |
| 7 `content:golive:check` Exit 0 |  |  | offen, setzt Abschnitt 1 voraus |

## 8. Was am 09.09.2026 tatsächlich auf dem Server geändert wurde

Alle Eingriffe mit Sicherung, alle Proben nachgestellt.

**Domains (Abschnitt 4).** Im ausgelieferten vhost
`/etc/nginx/sites-enabled/sanitaerfinden.dev.conf` standen die Namen
`tierarztportal.com#` und `www1.tierarztportal.com#` in vier `server_name`-Zeilen.
Das Doppelkreuz beginnt in nginx einen Kommentar: alles dahinter bis zum
Zeilenende war wirkungslos, samt abschließendem Semikolon und den rund zwanzig
danach aufgeführten Portaldomains. Der `root`-Eintrag der folgenden Zeile wurde
in die `server_name`-Liste hineingezogen. Sichtbar war das nicht, weil dieser
Serverblock zugleich der Standardblock ist und unpassende Hostnamen ohnehin
auffängt. `nginx -t` meldet dabei keinen Fehler.

Die kaputten Namen sind ersatzlos entfernt; `tierarztPortal.com` steht bereits
in derselben Liste und `server_name` vergleicht ohne Rücksicht auf Groß- und
Kleinschreibung. Derselbe Text steckt in der CloudPanel-Datenbank
(`/home/clp/htdocs/app/data/db.sq3`, `site.vhost_template`, `id=1`) und ist dort
mitkorrigiert — sonst schriebe CloudPanel den Fehler beim nächsten Erzeugen des
vhost zurück. Sicherungen unter `/root/sanitaerfinden.dev.conf.pre106.*` und
`/root/clp-db.sq3.pre106.*`.

Probe: `nginx -t` sauber, `systemctl reload nginx`, danach 18 Portale einzeln
über `curl --resolve <domain>:443:127.0.0.1` mit HTTP 200.

**Produktions-`.env` (Abschnitt 3).** Ergänzt um `CONTENT_PIPELINE_ENABLED=true`,
die vier Budgetgrenzen aus Abschnitt 2 der Checkliste (35 / 1050 / 2,50 / 1,50)
und `CONTENT_ADSENSE_ENABLED=false`. Der abgeschaltete AdSense-Zustand ist in
`content:golive:check` bewusst nur eine Warnung. Sicherung unter
`/root/sanitaerfinden.env.pre106.*`.

Nicht gesetzt sind `ANTHROPIC_API_KEY`, `VOYAGE_API_KEY` und
`GOOGLE_SERVICE_ACCOUNT_JSON`. Der Anthropic-Schlüssel aus der Entwicklung
wurde absichtlich **nicht** übernommen: Abschnitt 1 der Checkliste verlangt
einen eigenen Produktionsschlüssel mit eigenem Ausgabenlimit, und ein geteilter
Schlüssel vermischt die Abrechnung und trifft bei einem Rückruf beide
Umgebungen. Die beiden anderen existieren nirgends im Projekt.

**Achtung Config-Cache.** `bootstrap/cache/config.php` stammt vom 12.05.2026.
Die neuen `.env`-Werte wirken deshalb erst, wenn `php artisan config:cache`
erneut läuft. Das gehört an das Ende des Deploys aus Abschnitt 1 und ist hier
bewusst nicht vorgezogen worden: ein Neuaufbau des Caches würde die seit Mai
aufgelaufenen Änderungen an `.env` und `config/` unbeaufsichtigt scharfschalten.

**Cron (Abschnitt 5).** Es gab auf dem Server überhaupt keine crontab — der
Laravel-Scheduler lief nie. Für den Benutzer `sanitaerfinden` eingetragen:

```
* * * * * cd /home/sanitaerfinden/htdocs/sanitaerfinden.dev && /usr/bin/php8.4 artisan schedule:run >> /home/sanitaerfinden/logs/schedule.log 2>&1
```

Probe: `journalctl -u cron` zeigt den Aufruf zur nächsten vollen Minute,
`/home/sanitaerfinden/logs/schedule.log` antwortet mit „No scheduled commands
are ready to run". Vertretbar war das, weil der Umfang klein ist: eine einzige
Subscription, keine wartenden Jobs. Die neun Wartungsbefehle aus
`schedule:list` laufen damit erstmals planmäßig.

**Migrationen (Abschnitt 5).** `migrate:status` meldet zentral nichts
Ausstehendes. `tenants:migrate` bringt vor dem Deploy nichts, weil die
Tenant-Migrationen der Pipeline noch nicht auf dem Server liegen.

**Nicht angefasst.** Die beiden Supervisor-Programme
`sanitaerfinden-worker_00/_01` stehen weiter auf FATAL und
`QUEUE_CONNECTION` bleibt `database`. Das ist eine Wegentscheidung samt
Eingriff ins laufende System und gehört zu **#110**. Die Provisionierungs-Tasks
aus `deploy.php` blieben ebenfalls aus, siehe Abschnitt 0.

Randbefund ohne Handlungsbedarf: in `/etc/nginx/sites-enabled/` liegen
zahlreiche Sicherungen `sanitaerfinden.dev.conf.bak.<zeitstempel>`. Geladen
werden sie nicht, `nginx.conf` bindet nur `*.conf` ein.
