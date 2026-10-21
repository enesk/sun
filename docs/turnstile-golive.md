# Go-Live des Bot-Schutzes — Checkliste, gestaffelter Rollout, Abnahme

Status: **§1 ist durch (08.10.2026, 21:30). Offen sind nur noch Stufe 0–3 und die
Abnahme.** Produktion steht auf `a0bc8016`, Schlüssel und Widgets stehen,
`turnstile:keys:check --siteverify` gibt Exit 0 für alle drei Gruppen, alle 23 Portale
sind herausgenommen (`turnstile:rollout` → 23 × `nein`), das Modul ist global scharf
(`TURNSTILE_ENABLED=true`), die CSP meldet nur (`CSP_MODE=report`). Niemand sieht
bisher ein Widget — Stufe 1 schaltet die drei Gruppe-A-Portale einzeln frei, und das
braucht ein echtes Gerät. Belege: `docs/messungen/turnstile-golive/`.
Eine Zeile hängt noch am Menschen, bevor Stufe 1 starten darf:
`TURNSTILE_ALERT_RECIPIENTS` (§1.5, Adressen gehören nicht ins Repo). Der fehlende
Datenschutztext der fünf jüngsten Portale ist am 08.10.2026 in #49 nachgezogen
(§1.3, `tenants:datenschutz:turnstile-backfill` → 23 × vorhanden).
Ticket: #13 (Maschinenanteil: Checkliste, Werkzeuge, Security-Review — erledigt),
Durchführung am Server und Abnahme: #36 (Mensch). Vorbedingungen #14 (Widgets) und
#18 (Cloudflare-Zugang)
Stand: 2026-10-08, Blocker-Tabelle in §0 am 08.10.2026 an der Produktion nachgeprüft
Architektur, Begründungen und Codeverweise: `docs/turnstile.md`
Sicherheitsseite: `docs/turnstile-security-review.md`

Reihenfolge ist verbindlich: §0 und §1 sind Vorbedingungen, §2–§5 die vier Stufen,
§6 die Notbremse, §7 die Abnahme. Jede Zeile wird mit Datum und Kürzel abgehakt
(`[x] 2026-10-10 EN`). Eine Zeile gilt nur mit Beleg (Befehlsausgabe, Screenshot,
Link auf den Tagesbericht) als erledigt; Belege liegen unter
`docs/messungen/turnstile-golive/`.

Produktion: `ssh sun` (man landet als root), Installation
`/home/sanitaerfinden/htdocs/sanitaerfinden.dev`, **handgepflegt, kein Deployer**
(Ablauf `docs/messungen/produktionsumgebung-anleitung.md`). `php dep deploy` und alle
`provision:*`-Tasks dürfen dort nie laufen — `deploy.php` zeigt auf
`/home/sanitaerfinden/app`, einen Pfad, den kein vhost ausliefert. Artisan immer als
Eigentümer und mit der PHP-Version des Schedulers:

```bash
cd /home/sanitaerfinden/htdocs/sanitaerfinden.dev
sudo -u sanitaerfinden /usr/bin/php8.4 artisan <befehl>
```

Die Ausgabe trägt immer `PHP Warning: Module "redis" is already loaded` und drei
Deprecated-Zeilen aus `saasykit/laravel-open-graphy` — Rauschen, kein Befund
(`| grep -v "^PHP "`).

---

## 0. Stand am 08.10.2026 — was den Start heute verhindert

| # | Blocker | Folge | Erledigt durch |
|---|---|---|---|
| B1 | ~~Keine Produktionsschlüssel.~~ **Erledigt 08.10.2026 ST.** Enes hat den Token mit „Account / Turnstile: Edit“ erzeugt und über `scripts/cloudflare-token-ablegen.sh` nach `/root/sun-zugang.txt` gelegt (19:50), die drei Widgets `SUN Welle 1|2|3` im Dashboard angelegt; `scripts/turnstile-schluessel-eintragen.sh` hat die sechs Werte in die Produktions-`.env` geschrieben, `grep -c '^TURNSTILE' .env` gibt **6**, die Siteverify-Probe meldet für alle drei Gruppen „Secret gilt“. Beleg `docs/messungen/turnstile-golive/schluessel-2026-10-08.txt`. | — | #18 / #14, durchgeführt in #36 |
| B2 | **Das Modul liegt nicht auf `main`.** Der gesamte Bot-Schutz ist am 08.10.2026 als **ein** Commit `ddfe1fd9` auf dem Zweig `feature/bot-schutz-turnstile` eingecheckt und zum Ursprung geschoben (283 Dateien, Secret-Scan sauber). Die Produktion zieht `main` und steht auf `f5fa66ed`. | Nichts davon ist deploybar, solange der Zweig nicht auf `main` ist. | **Merge durch Enes** (`git checkout main && git merge --ff-only feature/bot-schutz-turnstile && git push`) — das ist die Freigabe, einziger offener Handgriff vor §1.3 |

**Beide Blocker sind seit dem 08.10.2026 weg:** B1 über Weg B (Enes legte Token und
die drei Widgets an, 19:50), B2 mit dem Merge `a0bc8016`. Der Deploy lief in drei
Etappen (20:14 / 20:34 / 21:18–21:20, zweimal riss die Sitzung ab) und ist seit 21:20
vollständig; Protokolle liegen als `deploy-*.log` in der Belegablage.

Erledigt seit der ersten Fassung dieses Dokuments:

| # | war | Stand 08.10.2026 |
|---|---|---|
| B3 | SUN-TS-011 ohne Empfänger | **weg**, #19: `App\Listeners\Turnstile\AlertOnSiteverifyUnreachable` hängt über Event-Discovery an `SiteverifyUnreachable` (`php artisan event:list` belegt die Bindung) und meldet ab 20 Ausfällen in 5 Minuten, danach höchstens halbstündlich. Der stündliche `turnstile:monitor` bleibt die Quotenaufsicht. |
| B4 | CSP auf `off`, Themes mit Inline-Skripten | **weg**, #21: die Themes liefern keine Inline-Skripte mehr, fremdes HTML bekommt das Nonce aus `CspNonce`, die Vorgabe in `config/csp.php:41` ist `enforce`. Im echten Browser nachgemessen in #28 (Chrome 154) und #29 (WebKit), Protokolle unter `docs/messungen/csp-browser-*`. §1.5 bleibt trotzdem beim Zwischenschritt `report` für 24 h, weil in Produktion echte `TRACKING_SCRIPTS` und echte `ad_slots` aus der Datenbank kommen; `CSP_MODE=report` wird nach §1.3 gesetzt, noch vor dem ersten `config:cache` des Deploys. |

B1 ist seit dem 08.10.2026 erledigt; von B2 fehlt nur noch der Merge nach `main` — das
ist die Freigabe. Den jeweiligen Stand nennt `scripts/turnstile-golive-stand.sh`, die
Vorbedingungen prüft der Trockenlauf `scripts/turnstile-golive-deploy.sh` (ohne `--los`
ändert er nichts) — am 08.10.2026 um 19:57 meldete er B1 grün und B2 als einzige
verletzte Vorbedingung.

**Stand jederzeit nachlesen, statt die Befehle von Hand zu wiederholen:**

```
scripts/turnstile-golive-stand.sh          # ganze Liste, Exit 70 = etwas offen
scripts/turnstile-golive-stand.sh --kurz   # nur die naechste offene Zeile
```

Das Skript liest nur (Repo, Cloudflare-`--pruefen`-Pfade, Produktion per ssh), aendert
nichts und nennt am Ende genau den naechsten Handgriff samt Befehl. Exit 0 heisst: alle
maschinell pruefbaren Zeilen bestanden, es fehlt nur noch, was ein Mensch tun muss
(Sichtpruefungen, Kalenderzeit, Abnahme).

### 0.1 Der Rollout-Schalter

Maßgeblich ist **`tenant_turnstile_settings.is_enabled`** je Portal, nicht
`config('turnstile.enabled')` und nicht die Widget-Gruppe: ein Widget darf Hostnames
tragen, bei denen der Schutz noch aus ist (`docs/turnstile.md` §9). Vorsicht, der
Vorgabewert der Spalte ist **`true`** — ein Portal ohne Zeile gilt im Resolver als
geschützt (`TurnstileConfigResolver::resolve()`). Der gestaffelte Rollout muss die
Portale deshalb **aktiv herausnehmen**, bevor er sie wellenweise zurückholt (§1.6).

Bedienung je Portal: Admin-Panel › *Bot-Schutz › Einstellungen* (Administrator mit
dem Recht `update settings`). Für alle 23 Portale auf einmal:

```bash
php artisan turnstile:rollout                       # nur anzeigen, ändert nichts
php artisan turnstile:rollout --wave=1              # Welle 1 = Widget-Gruppe A
php artisan turnstile:rollout --wave=2
php artisan turnstile:rollout --wave=3
php artisan turnstile:rollout --activate=fahrschulefinder.de
php artisan turnstile:rollout --deactivate=29
php artisan turnstile:rollout --deactivate-all --force
php artisan turnstile:rollout --wave=1 --mode=non_interactive
```

`--wave=N` meint die N-te Gruppe aus `config('turnstile.groups')` (1 = A, 2 = B,
3 = C). Welches Portal zu welcher Gruppe gehört, entscheidet wie im Resolver der
Hostname — `apotheke.firmenfreund.de` findet die Gruppe seines Elterneintrags, ohne
in der Liste zu stehen. Die Tabelle zeigt je Portal Gruppe, Schalter, Modus beider
Aktionen, Fail-Mode und ob ein eigenes Widget hinterlegt ist; geänderte Zeilen tragen
ein `*`. Ein Lauf ohne Schalter legt keine Zeilen an.

Geschrieben wird nur `is_enabled` und mit `--mode` die Darstellung der beiden
geschützten Aktionen. Schlüssel, Fail-Mode, Sperrlisten und Grenzen bleiben
unangetastet — die gehören ins Panel (#9) beziehungsweise in die `.env`.

**Schlüsselsperre (#14):** Scharfschalten bricht in **Produktion** ab, solange ein
betroffenes Portal kein benutzbares Schlüsselpaar hat — also wenn die Schlüssel
seiner Widget-Gruppe fehlen oder noch Cloudflare-Testschlüssel sind und in der
Tenant-Zeile kein eigenes Widget steht. Sonst erzeugte das Formular ein Token, das
erst im Resolver in die `TurnstileNotConfiguredException` läuft. Die Meldung nennt
Gruppe und Portale; der Weg heraus ist `scripts/turnstile-schluessel-eintragen.sh`
und ein grünes `php artisan turnstile:keys:check --siteverify`. Herausnehmen
(`--deactivate*`) ist nie gesperrt. Lokal und auf staging bleibt es eine Warnung,
dort sind die Testschlüssel der Normalfall.

---

## 1. Vorbedingungen

### 1.1 Schlüssel je Widget (Akzeptanzkriterium 1a)

Alle **drei** Gruppen werden vor dem Deploy gesetzt, nicht nur die der ersten Welle:
sonst läuft jedes Portal aus Gruppe B/C, dessen Zeile noch auf `is_enabled = true`
steht, in die `TurnstileNotConfiguredException` und löst Alarme aus, obwohl es gar
nicht Teil der Welle ist.

**Der Cloudflare-API-Token ist kein Blocker.** Er automatisiert nur das Anlegen der
Widgets und die `--pruefen`-Gegenprobe. Nichts weiter unten in diesem Dokument braucht
ihn: `scripts/turnstile-schluessel-eintragen.sh` schreibt nur die `.env` und fährt die
Siteverify-Probe, `turnstile:keys:check --siteverify` liest Konfiguration und ruft
Siteverify — beide reden nie mit der Cloudflare-API.

**Erledigt am 08.10.2026.** Enes hat den Token erzeugt und abgelegt (19:50) und die drei
Widgets im Dashboard angelegt (Weg B); der Rest lief über die Skripte. Gegengeprüft mit
`scripts/cloudflare-token-ablegen.sh --pruefen` (Exit 0, Konto bekannt, Turnstile lesbar)
und `scripts/turnstile-widgets-anlegen.sh --pruefen` (drei Widgets, Soll-Ist passt).
Beide Skripte brauchten dafür eine Korrektur, siehe §1.1.1.

*Weg A — mit Token:*

- [x] 2026-10-08 ST Token mit „Account / Turnstile: Edit“ liegt über
  `scripts/cloudflare-token-ablegen.sh` in `/root/sun-zugang.txt` (chmod 600),
  Gegenprobe `scripts/cloudflare-token-ablegen.sh --pruefen` → Exit 0. In derselben
  Ablage steht der WAF-Token `CLOUDFLARE_API_TOKEN_SUN_WAF` aus #24 — beide in einem
  Durchgang ablegen
- [x] 2026-10-08 ST `--pruefen` meldet für alle drei Widgets 3 + 10 + 5 Hostnames,
  `mode: managed`, `clearance_level: no_clearance` und „Einstellungen passen zu
  docs/turnstile.md Abschnitt 8“. Angelegt wurden sie von Hand (Weg B), das Skript hat
  nur verglichen und nichts geändert

*Weg B — ohne Token, drei Widgets von Hand (Dashboard → Turnstile → Add widget):*

Die Angaben zum Abtippen druckt das Skript selbst, ohne Token und ohne ssh — es nimmt
die Namen und Hostnames aus derselben Stelle, die später `--pruefen` vergleicht:

```bash
scripts/turnstile-widgets-anlegen.sh --weg-b
```

- [x] 2026-10-08 ST (Enes im Dashboard) Drei Widgets, Namen genau `SUN Welle 1`, `SUN Welle 2`, `SUN Welle 3` (unter
  diesen Namen findet `turnstile-widgets-anlegen.sh` sie später wieder, statt
  Dubletten anzulegen), je `Widget Mode: Managed`,
  `Pre-clearance: no clearance level`, Hostnames genau nach
  `config/turnstile.php` 'groups' — 3 / 10 / 5, zusammen 18:
  * **Welle 1 (A):** fahrschulefinder.de, elektrikerportal.com, sanitaerfinden.com
  * **Welle 2 (B):** sanitaerfinder.com, malerfinder.de, fliesenleger.io,
    kfzwerkstatt.io, findegutachter.de, bodenlegerfinden.com, tierarztportal.com,
    energieberaterportal.net, firmenfreund.net, firmenfreund.de
    (der letzte Eintrag deckt apotheke./arztfinder./klempner./unfallarzt./zahnarzt. mit ab)
  * **Welle 3 (C):** geruestbauer.gmbh, metallbauer.io, mjet.net,
    schluesseldienstportal.com, speditionportal.com
- [x] 2026-10-08 ST Kein Hostname steht in zwei Widgets und keiner fehlt: die
  Widget-Liste der API ergibt 3 + 10 + 5 = 18 Hostnames ohne Schnittmenge, Soll laut
  `config/turnstile.php` 'groups'. Die zweite Gegenprobe über `turnstile:keys:check`
  folgt in §1.2 nach dem Deploy
- [x] 2026-10-08 ST Das von Hand angelegte Widget „fahrschulefinder.de +4 (Spin)“
  (Sitekey `0x4AAAAAAFRd_ph0Sz_IuU2u`, Hostnames quer über A und B plus `localhost`
  und `127.0.0.1`) ist gelöscht (`DELETE … success: true`) — es passte zu keiner
  Gruppe, Entscheidung Enes. Vorher geprüft: der Sitekey stand in keiner `.env`-Zeile
  der Produktion.
  **Stehen geblieben ist bewusst** das ältere Widget „Turnstile“
  (`0x4AAAAAACYmqmb6YCbUgJW_`, u. a. `fensterbau.io`, `gartenbauer.io`,
  `metallbauer.de` — Hostnames, die zu keinem der 23 SUN-Portale gehören). SUN benutzt
  es nicht; ob ein anderes Projekt daran hängt, weiß nur Enes. Löschen ist seine
  Entscheidung, nicht die des Go-Lives

Für beide Wege gilt:

- [ ] Im Dashboard von Hand: „Allow a domain to be added automatically“ bei allen drei
  Widgets **aus** (die API kennt den Schalter nicht) — sonst wandern fremde Hostnames
  ins Widget und der Hostname-Check verliert seinen Sinn
- [ ] Passwort-Manager: je Gruppe ein Eintrag `SUN / Cloudflare Turnstile / Gruppe A|B|C`
  mit `sitekey`, `secret` und der Hostname-Liste als Notiz
- [x] 2026-10-08 ST Sechs Werte in der Produktions-`.env`
  (`grep -c '^TURNSTILE' .env` → 6, Siteverify je Gruppe „Secret gilt“):
  `scripts/turnstile-schluessel-eintragen.sh` (sichert die `.env`, weist Testschlüssel
  ab, baut den Config-Cache neu, lädt `php8.5-fpm` nach, startet
  `sanitaerfinden-horizon` durch, fährt je Gruppe die Siteverify-Probe)
- [x] 2026-10-08 ST Kein Schlüssel im Repo: `php scripts/secret-scan.php` sauber
  (2475 verfolgte Dateien) und `--staged` sauber (283 Dateien des Commits `ddfe1fd9`)

#### 1.1.1 Zwei Skripte mussten dafür korrigiert werden

Beide Ablege-/Prüfskripte scheiterten an einem **richtig** geschnittenen Token
(Befund von Uwe in #38): ein Token mit ausschließlich „Account / Turnstile: Edit“
darf die Turnstile-Endpunkte seines Kontos bedienen, das Konto aber nicht
*auflisten* — `GET /accounts` braucht zusätzlich „Account Settings: Read“ und
antwortet sonst mit `count: 0`.

* `scripts/cloudflare-token-ablegen.sh` kannte keinen Weg, die Konto-ID
  vorzugeben, und meldete irreführend „Der Token sieht 0 Konten — auf genau ein
  Konto begrenzen“. Jetzt nimmt es die ID aus `CF_ACCOUNT_ID`, sonst aus
  `CLOUDFLARE_ACCOUNT_ID_SUN` in der Ablage, sonst aus der Kontoliste; prüft sie
  auf 32 Hexzeichen (sie geht in einen Fernaufruf) und nennt bei 0 Konten den
  wahren Grund samt Fundstelle der ID im Dashboard. Neuer Exit-Code 3.
* `scripts/turnstile-widgets-anlegen.sh` las den Token aus der Ablage, die
  Konto-ID aber nur aus der Umgebung — und brach deshalb bei jedem Lauf mit
  „sieht 0 Konten“ ab. Es liest nun beides aus `/root/sun-zugang.txt`.

### 1.2 Keine Testschlüssel in Produktion (Akzeptanzkriterium 1b)

`.env.example` setzt die Cloudflare-Testschlüssel als Vorgabe (`1x0000…`), damit eine
frische Installation läuft. In Produktion sind sie wertlos: sie bestehen auf jeder
Domain und liefern `hostname: example.com` ohne `action`, der Hostname- und
Action-Check wäre damit tot. Der Resolver wirft deshalb in Produktion, wenn ein
Testschlüssel hinterlegt ist (`TurnstileConfigResolver::TEST_SITE_KEYS`).

Stand 08.10.2026: in der Produktions-`.env` steht **kein** Testschlüssel — das
Eintrage-Skript weist `1x0000*`, `2x0000*`, `3x0000*` ab, und der Trockenlauf
`turnstile-golive-deploy.sh` hakt „kein Testschluessel in der .env“ ab. Die drei Zeilen
unten brauchen den Code auf der Produktion und gehen erst nach §1.3.

- [x] 2026-10-08 SE `sudo -u sanitaerfinden /usr/bin/php8.4 artisan turnstile:keys:check --siteverify`
  → 21:2x Exit 0, A/B/C je `ok`, 18 Hostnames, kein falsch zugeordnetes Portal — Beleg `docs/messungen/turnstile-golive/keys-check-20261008.txt`
  meldet für **alle drei** Gruppen `ok`, zählt 18 Hostnames, nennt kein falsch
  zugeordnetes Portal und gibt Exit 0 — Ausgabe ablegen
- [x] 2026-10-08 SE Kein Portal hat ein eigenes Widget in der Datenbank (Spalte „Schluessel“ in
  → `turnstile:rollout` zeigt für alle 23 Portale `Gruppe`
  `turnstile:rollout` steht überall auf `Gruppe`)
- [x] 2026-10-08 DI Gegenprobe am HTML: der `data-sitekey` im HTML von `/register`
  beginnt mit `0x` (`0x4AAAAAAFRicOB43qt9oAsa`), **nicht** mit `1x`, `2x` oder `3x`.
  Dazu stand `data-action="registration"`, der Hidden-Input `cf-turnstile-response`,
  das Skript von `challenges.cloudflare.com` (`render=explicit`) und das Bündel
  `/build/assets/turnstile-CKb2w3ce.js` (HTTP 200). Gemessen an
  `elektrikerportal.com` — dem Portal der Gruppe A mit der geringsten Last —, das dafür
  rund drei Minuten scharf stand und direkt danach wieder herausgenommen wurde
  (`turnstile:rollout --deactivate=`, danach wieder 23 × `nein`). Beleg:
  `docs/messungen/turnstile-golive/widget-html-2026-10-08.txt`.
  Ohne scharfes Portal ist diese Zeile nicht messbar: dann steht kein Widget im HTML.

### 1.3 Deploy

Die Zeilen unten sind der verbindliche Inhalt, aber sie müssen nicht von Hand getippt
werden — `scripts/turnstile-golive-deploy.sh` fährt genau diese Reihenfolge, prüft
vorher alle Vorbedingungen und schreibt das Protokoll als Beleg nach
`docs/messungen/turnstile-golive/deploy-<datum>.log`:

```bash
scripts/turnstile-golive-deploy.sh          # Trockenlauf, ändert nichts, zeigt jeden Befehl
scripts/turnstile-golive-deploy.sh --los    # führt aus, fragt einmal nach ("deploy" tippen)
```

Das Skript bricht ab (Exit 66), solange B2 nicht auf `origin/main` liegt, die sechs
Schlüssel fehlen, ein Testschlüssel in der `.env` steht, verfolgte Dateien in
Produktion geändert sind oder unter `/home` weniger als 50 GB frei sind. Es setzt
`TURNSTILE_ENABLED=false` und `CSP_MODE=report`, **bevor** der erste `config:cache`
läuft, dumpt alle 24 Datenbanken selbst und lädt nach jeder `.env`-Änderung
`php8.5-fpm` nach. Exit 70 heißt: ein Schritt ist gescheitert, der Stand steht im
Protokoll.

#### 1.3.1 Abgebrochener Lauf vom 08.10.2026 — laufender Ausfall (#47)

Der Lauf `deploy-20261008-201412.log` ist **mitten in Abschnitt 2** abgerissen, nach
`git pull --ff-only` und beim `chown -R`. Ergebnis ist ein halber Stand: Code
`a0bc8016` liegt auf der Produktion, das Schema ist der Stand vom 29.09. Weil
`Company` seit #10 `SoftDeletes` benutzt, läuft jede Portal-Startseite in
`Unknown column 'companies.deleted_at'` — **HTTP 500 auf allen Portalen**, für Bots
wie für Besucher (`robots.txt` kommt mit 200, die bootet kein Laravel).
Abschnitt 1 ist dabei durch: `TURNSTILE_ENABLED=false` und `CSP_MODE=report` stehen
in der `.env`, der Config-Cache ist von 20:14.

Wiederaufsatz — ein Befehl, er braucht ein Terminal (einmalige Rückfrage „deploy“):

```bash
scripts/turnstile-golive-deploy.sh --nur-schema           # Trockenlauf, ändert nichts
scripts/turnstile-golive-deploy.sh --nur-schema --los     # behebt den Ausfall
```

`--nur-schema` fährt die Abschnitte 3 (eigener Dump aller 24 Datenbanken), 5
(Migrationen, Seeder, Datenschutz-Backfill) und 6 (Caches, FPM-Reload, Horizon) und
lässt Abschnitt 4 aus — der Vite-Build hing auf diesem Server schon einmal über zehn
Minuten und gehört nicht in die Behebung eines laufenden Ausfalls. Die Assets fehlen
danach noch (`public/build` ist vom 01.10.); nachholen mit
`scripts/turnstile-golive-deploy.sh --ab=4 --los`, sobald die Portale wieder 200
liefern — solange `TURNSTILE_ENABLED=false` gilt, rendert ohnehin kein Widget.

Allgemein: `--ab=<nr>` setzt einen abgebrochenen Lauf an Abschnitt `<nr>` fort, jeder
Abschnitt ist für sich wiederholbar. Beide Trockenläufe der Migrationen sind am
08.10.2026 gefahren und **rein additiv** — `create table` plus `add column`/`add
index`, kein `drop`, keine Datenänderung
(`docs/messungen/turnstile-golive/migrate-pretend-20261008.log`).

Nach dem Lauf:

```bash
scripts/waf-wirkung-pruefen.sh      # Startseiten müssen 200 statt 500 liefern
```

Das Tageslog stand dabei bei 198 MB. Es muss nicht von Hand abgeräumt werden:
`/etc/logrotate.d/sanitaerfinden-laravel` rotiert bei `size 200M` mit `copytruncate`,
und mit dem behobenen Fehler wächst es nicht weiter.

- [x] 2026-10-08 SE B2 erledigt: Commit mit dem Bot-Schutz liegt auf `origin/main`.
  → `a0bc8016` (Merge PR #1 `feature/bot-schutz-turnstile`)
  Der Commit **existiert** und ist geschoben — `ddfe1fd9` auf
  `origin/feature/bot-schutz-turnstile` (283 Dateien, 08.10.2026 ST). Offen ist nur die
  Freigabe, also der Merge:
  `git checkout main && git merge --ff-only feature/bot-schutz-turnstile && git push`
- [x] 2026-10-08 SE **Backup vor der Migration** — die sieben `2026_10_09_*`-Migrationen legen
  → `storage/app/backups/golive-36/20261008-203418/`, 24 Dateien, ~460 MB
  Tabellen und Spalten in der zentralen und in jeder Portal-Datenbank an. Die
  nächtliche Sicherung ist dafür **nicht** ausreichend: `clpctl db:backup` sichert nur,
  was in CloudPanels eigener `db.sq3` steht, und zehn der 23 Portal-Datenbanken stehen
  dort nicht (geprüft 08.10.2026, ~3 GB Nutzdaten ohne Sicherung, eigenes Ticket).
  Deshalb selbst dumpen — das macht Schritt 3 des Deploy-Skripts nach
  `storage/app/backups/golive-36/<stempel>/` und bricht ab, wenn eine Datei unter
  1 KB bleibt oder weniger als 24 Dateien entstehen.
- [x] 2026-10-08 SE Notbremse vorziehen: `TURNSTILE_ENABLED=false` in die `.env`, dann `config:cache`.
  → Lauf 20:14, Abschnitt 1
  So ist der Schutz während Migration und Seeder global aus, und die Lücke zwischen
  „Seeder legt 23 Zeilen mit `is_enabled = true` an“ und „§1.6 nimmt sie heraus“ kann
  niemanden treffen.
- [x] 2026-10-08 SE **Im selben Schritt `CSP_MODE=report` in die `.env`**, noch vor dem ersten
  → Lauf 20:14, Abschnitt 1 — `grep '^CSP_MODE' .env` → `report`
  `config:cache` dieses Deploys. Die Vorgabe im Code ist `enforce` und die
  Produktions-`.env` hat heute keine `CSP_MODE`-Zeile — jeder Neuaufbau des
  Config-Caches schaltet die CSP sonst sofort auf allen 23 Portalen durchsetzend,
  also schon hier und nicht erst in §1.5. Die 24-Stunden-Messung und der Umstieg auf
  `enforce` stehen in §1.5.
- [x] 2026-10-08 SE `git pull --ff-only` **als root** (nur root hat den GitHub-Schlüssel), danach
  → Lauf 20:14, Abschnitt 2
  `chown -R sanitaerfinden:sanitaerfinden /home/sanitaerfinden/htdocs/sanitaerfinden.dev`
  — **so nicht mehr (#59).** Der rekursive Lauf geht durch `storage/` und damit
  durch den Datei-Cache von 29 Portalen; am 16.10.2026 hat genau das die
  Maschine 17 Minuten auf Load 53 und TTFB 49 s gezogen, obwohl nur sieben
  Dateien root gehoerten. Richtig ist
  `scripts/deploy-eigentuemer-richten.sh --los` (Sekundenbruchteile), und lange
  Befehle auf dieser Maschine immer in `timeout` — eine abgerissene
  SSH-Sitzung beendet sie nicht.
- [x] 2026-10-08 SE `composer install --no-dev -o`
  → nicht nachgeholt und nicht nötig: `composer.lock` ist zwischen `f5fa66ed` und `a0bc8016` unverändert (geprüft 08.10.2026)
- [x] 2026-10-08 SE `npm ci && npm run build` — `public/build/` ist nicht versioniert, und
  → 21:19 nachgeholt (`--ab=4 --los`), `public/build/manifest.json` kennt `resources/js/turnstile.js` — Beleg `deploy-20261008-211944.log`
  `resources/js/turnstile.js` kommt aus dem Vite-Build. Ohne Build rendert das Widget
  nicht, der Hidden-Input bleibt leer und **jede** Registrierung fällt durch.
  Beides **als `sanitaerfinden`**, nie als root, und vorher
  `chown -R sanitaerfinden:sanitaerfinden public/build node_modules` — ein von root
  geschriebenes `public/build` lässt den nächsten Build an `rmSync` scheitern. Hängt
  der Build auf dem Server (kam 2026 vor, über 10 min bei „transforming“), lokal auf
  demselben Commit bauen und hochschieben:
  `npm run build && rsync -az public/build/ sun:…/public/build/` plus `chown`. Laufende
  Vite-Prozesse nur über das Muster `"[v]ite build"` suchen — ein `pkill -f "vite build"`
  beendet die eigene SSH-Sitzung.
- [x] 2026-10-08 SE `php artisan migrate --force` (zentral) — Ausgabe ablegen
  → Lauf 20:34, vier zentrale Migrationen durch — Beleg `deploy-20261008-203418.log`
- [x] 2026-10-08 SE `php artisan tenants:migrate --force` — Ausgabe ablegen, alle 23 Portale ohne Fehler
  → Lauf 20:34; Gegenprobe 21:1x: `tenants:migrate --force --pretend` gibt für alle 23 Portale keine Anweisung mehr aus
- [x] 2026-10-08 SE `php artisan db:seed --class=TenantTurnstileSettingSeeder` — meldet 23 angelegt
  → 23 Zeilen vorhanden (`turnstile:rollout` listet 23 Portale)
- [x] 2026-10-08 SE `php artisan tenants:datenschutz:turnstile-backfill` (erst ohne, dann mit `--write`, #11) — Turnstile-Abschnitt in
  der Datenschutzerklärung jedes Portals
  → Erster Lauf 21:3x: 18 Portale haben den Abschnitt; 5 Portale (FahrschuleFinder,
  Malerfinder.de, SanitaerFinder, Klempner, Schlüsseldienst) haben überhaupt keinen
  Datenschutztext — der Backfill hängt nur an vorhandenen Text an, siehe #49.
  → Nachgezogen in #49 (22:0x): die fünf Portale waren die jüngsten im Netz und hatten
  weder Betreiberanschrift noch verantwortliche Person, deshalb hatte
  `tenants:legal:seed` sie bisher übersprungen. Reihenfolge je Portal
  (`--tenant=29 33 49 54 58`): `tenants:address:seed --write` →
  `tenants:contact:seed --write` → `tenants:legal:seed --write`. Der Standardtext aus
  `TenantLegalDefaults::DATENSCHUTZ` enthält Abschnitt 3.1 bereits, der Backfill
  danach also nur noch die Gegenprobe: **23 × vorhanden, 0 × übersprungen**.
  Gegengeprüft im Netz: `/datenschutz` aller fünf Portale liefert 200, zeigt
  „Bot-Schutz mit Cloudflare Turnstile“ und löst alle Platzhalter auf (Impressum
  ebenso). **Damit ist der Datenschutz-Blocker für Stufe 1 weg.**
- [x] 2026-10-08 SE `php artisan config:cache`, `route:clear`, `view:clear`, `filament:optimize`.
  → 21:18 nachgeholt (`--ab=6 --los`) — der Cache von 20:14 stammte von **vor** dem Pull und kannte `config/turnstile.php` nicht, deshalb meldete `keys:check` „kennt keine Gruppen“
  Achtung: der Config-Cache auf diesem Server ist alt — der Neuaufbau schaltet zugleich
  alle seit Mai aufgelaufenen `.env`-Änderungen scharf. Vorher `php artisan config:show`
  der betroffenen Bereiche sichten.
- [x] 2026-10-08 SE **`systemctl reload php8.5-fpm`** nach jedem `config:cache`. Die Website läuft über
  → 21:18 und 21:2x
  den FPM-Pool von PHP 8.5, Artisan über CLI-PHP 8.4 — ohne den Reload bleibt im Web der
  alte Config-Cache stehen, und genau dann wirkt die Notbremse `TURNSTILE_ENABLED=false`
  nicht, obwohl sie in der `.env` steht und `config:show` sie anzeigt.
- [x] 2026-10-08 SE `php artisan queue:restart` und `php artisan horizon:terminate`, danach
  → 21:18, Horizon über `supervisorctl restart sanitaerfinden-horizon`
  `supervisorctl restart sanitaerfinden-horizon`

### 1.4 Backup vor `antispam:scan` (Akzeptanzkriterium 1d)

`antispam:scan` steht absichtlich **nicht** im Scheduler: markiert wird nur nach
Sichtung, von Hand. Vor dem ersten Produktivlauf wird gesichert — der Produktivlauf
selbst ist #23, nicht dieses Ticket.

- [x] 2026-10-08 SE `php artisan antispam:backup` — sichert die zentrale `users` und die `companies`
  → 21:26 — Beleg `docs/messungen/turnstile-golive/backup-20261008.txt`
  jedes Portals als NDJSON nach `storage/app/backups/antispam/<datum>/`
- [x] 2026-10-08 SE Ordner gesichtet: 23 Portaldateien + zentrale Datei, Zeilenzahlen plausibel
  → 24 Dateien, 489 329 Zeilen gesamt
- [x] 2026-10-08 SE Eigener Datenbank-Dump vorhanden (zweite Ebene, unabhängig vom NDJSON) — der
  → `golive-36/20261008-203418/`, 24 Dateien
  Lauf aus §1.3 Schritt 3 unter `storage/app/backups/golive-36/<stempel>/`, 24 Dateien.
  Die nächtlichen Dateien unter `/home/sanitaerfinden/backups/databases/` **nicht** als
  Beleg nehmen: sie decken nur 14 der 24 lebenden Datenbanken ab, und für längst leere
  Datenbanken liegen 521-Byte-Dumps, die nach „Sicherung vorhanden“ aussehen
  (nachgemessen 08.10.2026, `scripts/turnstile-golive-deploy.sh` prüft das mit).

### 1.5 Scheduler, Alarme und CSP (Akzeptanzkriterien 1c und 1e)

Der Scheduler läuft auf diesem Server als Benutzer `sanitaerfinden`
(`* * * * * … /usr/bin/php8.4 artisan schedule:run`, geprüft 08.10.2026) — mit dem
Deploy kommen drei Einträge dazu: `turnstile:monitor` stündlich zur Minute 5,
`turnstile:report` 07:10, `turnstile:prune` 03:40. Dazu `antispam:expire-quarantine`
03:20 aus #10.

- [x] 2026-10-08 SE `php artisan schedule:list` zeigt alle vier Einträge mit den Zeiten oben
  → monitor `5 * * * *`, report 07:10, prune 03:40, `antispam:expire-quarantine` 03:20
- [x] 2026-10-08 SE Schneller Alarm steht (#19): `php artisan event:list | grep -A1 Siteverify` zeigt
  → `SiteverifyUnreachable → AlertOnSiteverifyUnreachable@handle` ist gebunden
  `AlertOnSiteverifyUnreachable@handle`. Ohne diese Zeile fällt ein Siteverify-Ausfall
  erst beim stündlichen Monitor auf
- [x] 2026-10-08 SE `php artisan turnstile:monitor --no-mail` läuft ohne Fehler durch (zeigt Alarme nur
  → „23 Portale geprüft, keine Auffälligkeit“
  auf der Konsole, keine Entdopplung)
- [x] 2026-10-08 SE `php artisan turnstile:report --no-mail` baut den Bericht über gestern
  → Bericht 07.10.2026, 23 Portale, Summe 7 Registrierungen / 4 Einträge
- [x] 2026-10-08 ST **Alarme festgeschrieben:** `TURNSTILE_ALERT_RECIPIENTS` steht
  in der Produktions-`.env` (`.env` vorher gesichert, danach `config:cache` und
  `systemctl reload php8.5-fpm`). Eingetragen sind die **zwei** Administratoren, die
  der Rückfall heute ohnehin liefert — ein später angelegter dritter Administrator
  bekommt damit keine Produktionsalarme mehr, ohne dass es jemand merkt.
  **Uwe fehlt:** auf der Produktion gibt es kein Konto und keine Adresse für ihn
  (`users` durchgesehen, zwei Administratoren, keiner heißt Uwe). Seine Adresse muss
  ein Mensch anhängen — ein Komma und die Adresse in dieselbe Zeile, dann
  `config:cache`. Adressen gehören nicht ins Repo, deshalb stehen sie hier nicht.
- [x] 2026-10-08 ST Verteiler nachgewiesen:
  `php artisan tinker --execute="print_r(App\Turnstile\Support\BotProtectionRecipients::emails());"`
  listet genau die beiden Adressen aus der `.env` (vorher: dieselben zwei aus dem
  Rückfall). `turnstile:monitor --no-mail` danach: „23 Portale geprüft, keine
  Auffälligkeit."
- [ ] Probemail angekommen: `php artisan turnstile:report` (ohne `--no-mail`), beide
  Empfänger bestätigen den Eingang
- [ ] **CSP zuerst messen, dann durchsetzen** (#21 erledigt, im Browser nachgemessen in
  #28/#29). Die Vorgabe im Code ist `enforce`; in der Produktions-`.env` steht heute
  **keine** `CSP_MODE`-Zeile, der Config-Cache dort ist alt — ohne die Zeile aus §1.3
  würde der Neuaufbau `enforce` mit einem Schlag auf alle 23 Portale schalten.
  Reihenfolge deshalb:
  1. `CSP_MODE=report` steht bereits aus §1.3 in der `.env` — hier nur gegenprüfen
     (`grep '^CSP_MODE' .env`), sonst jetzt setzen, `config:cache`,
     `systemctl reload php8.5-fpm`
  2. Gegenprobe:
     `curl -sI https://fahrschulefinder.de/register | grep -i content-security-policy`
     zeigt `Content-Security-Policy-Report-Only` mit `https://challenges.cloudflare.com`
     in `script-src`, `frame-src` und `connect-src`
  3. **24 Stunden Meldungen sichten — jetzt mit Zahlen** (#51). Bis zum
     08.10.2026 setzte `config/csp.php` weder `report-uri` noch `report-to`: der
     Browser meldete nur in seine eigene Konsole, serverseitig wurde nichts
     gesammelt, und dieser Schritt konnte von sich aus keinen Beleg erzeugen.
     Seit #51 gibt es eine Sammelstelle — `POST /csp-bericht`
     (`App\Http\Controllers\Portal\CspReportController`), zentrale Tabelle
     `csp_reports`, entdoppelt je Portal + Direktive + blockierter Quelle mit
     Zähler statt Zeile. Die Policy trägt `report-uri` **und** `report-to`
     plus den Header `Reporting-Endpoints`; beides, weil Chrome `report-uri`
     nicht mehr liest und Safari/Firefox `report-to` nicht kennen.

     ```bash
     # Vorbedingung: die Migration muss gelaufen sein
     php artisan migrate --force          # 2026_10_10_000001_create_csp_reports_table

     # Gegenprobe, dass die Sammelstelle in der Policy steht
     curl -sI https://fahrschulefinder.de/register \
       | grep -iE 'content-security-policy|reporting-endpoints'
     #   … report-uri /csp-bericht; report-to csp-bericht
     #   Reporting-Endpoints: csp-bericht="https://fahrschulefinder.de/csp-bericht"

     # nach 24 Stunden: Liste je Portal und je Direktive
     php artisan csp:berichte                      # Exit 70 = es gibt Befunde
     php artisan csp:berichte --details --limit=30
     php artisan csp:berichte --portal=fahrschulefinder.de
     ```

     Gelesen wird so: **jede Zeile mit Treffern ist etwas, das `enforce` bricht.**
     Entweder die Quelle gehört in `config/csp.php` (fremder Script-Host, den
     die Richtlinie noch nicht nennt), oder das Markup muss weg (`blocked_uri`
     `inline` oder `eval`). Leere Liste nach 24 h → Schritt 4.
     Aufgeräumt wird nächtlich von `csp:prune` (03:50, 30 Tage, Zählerzeilen
     mit frischen Treffern bleiben stehen).

     Das ersetzt `scripts/csp-live-pruefen.sh` nicht, es ergänzt es: das Skript
     prüft das **ausgelieferte HTML** (Inline-Skripte ohne Nonce, `on*`-Attribute,
     fremde Script-Hosts) und braucht keine Besucher; die Sammelstelle sieht
     zusätzlich, was erst zur Laufzeit entsteht (dynamisch eingefügte Skripte,
     `eval`, Einbettungen fremder Stylesheets) — dafür erst, wenn jemand die
     Seite besucht. Lauf des Skripts vom 08.10.2026 über acht Seitentypen ×
     drei Gruppe-A-Portale: Beleg
     `docs/messungen/turnstile-golive/csp-report-2026-10-08.txt`.
     Nebenbei korrigiert: die hier früher genannten Pfade `/suche` und
     `/firma-eintragen` gibt es nicht (404). Gemessen wird `/`, `/firmen`,
     `/staedte`, `/kategorien`, `/jobs`, `/ratgeber`, `/eintragen`, `/register`.
  4. dann `CSP_MODE=enforce`, `config:cache`, Reload, und derselbe `curl` zeigt
     `Content-Security-Policy` ohne `-Report-Only`.
     **Vorbedingung, sonst bricht `/jobs`:** der Fix an
     `resources/views/themes/sun-v2/views/pages/jobs/index.blade.php` muss auf der
     Produktion liegen. Die beiden Job-Mail-Kästen trugen
     `onsubmit="return false"`; CSP blockt Inline-Eventhandler mit derselben
     Direktive wie Inline-Skripte, und `script-src` trägt bewusst kein
     `unsafe-inline`. Unter `enforce` hätte ein Enter im E-Mail-Feld die Seite neu
     geladen. Behoben durch Wegfall der Formular-Semantik (`<form>` → `<div>`; der
     Knopf ist `type="button"`, es gab nie einen Endpunkt). Der Fix liegt als Commit
     `13033809` auf `origin/feature/bot-schutz-turnstile`, zusammen mit `585b7b8a`
     (Konto-ID in den beiden Cloudflare-Skripten). Handgriffe:

     ```bash
     git checkout main && git merge feature/bot-schutz-turnstile && git push
     ssh sun 'cd /home/sanitaerfinden/htdocs/sanitaerfinden.dev && git pull --ff-only'
     # als root ziehen, danach chown, dann:
     sudo -u sanitaerfinden /usr/bin/php8.4 artisan view:clear
     scripts/csp-live-pruefen.sh        # erwartet on*=0 auf allen Seiten
     ```

  Schritt 1 und 2 sind am 08.10.2026 (SE) erledigt: `CSP_MODE=report` steht in der
  `.env`, und `curl -skI --resolve fahrschulefinder.de:443:88.198.64.145
  https://fahrschulefinder.de/register` liefert `content-security-policy-report-only`
  mit `https://challenges.cloudflare.com` in `script-src`, `frame-src` **und**
  `connect-src`. Schritt 3 ist damit so weit erledigt, wie er ohne Browser gehen
  kann (siehe oben, Beleg `csp-report-2026-10-08.txt`); offen bleiben die
  Browser-Sichtung auf einem Live-Portal und Schritt 4.
  Begründung für den Zwischenschritt, obwohl die Messung sauber war: lokal gemessen wurde
  gegen `php artisan serve` mit Testwerten für Analytics und AdSense; in Produktion
  stehen echte `TRACKING_SCRIPTS` und echte `ad_slots` aus der Verwaltung in der
  Datenbank, und genau die sind fremdes HTML. `report` kostet einen Tag und kann nichts
  abschalten.

### 1.6 Alle Portale herausnehmen, dann global scharf

- [x] 2026-10-08 SE `php artisan turnstile:rollout --deactivate-all --force` — Tabelle zeigt 23 × `nein`
  → 21:2x „23 Portale geändert“ — der Seeder hatte alle 23 auf `ja` gesetzt
- [x] 2026-10-08 SE `TURNSTILE_ENABLED=true` (bzw. die Zeile aus §1.3 wieder entfernen), `config:cache`,
  → 21:2x; `.env` vorher gesichert als `.env.vor-36-golive-<stempel>`, danach `config:cache`, `systemctl reload php8.5-fpm`, `queue:restart`
  `queue:restart`
- [x] 2026-10-08 SE `php artisan turnstile:rollout` zur Gegenprobe: 23 × `nein`, Gruppen A/B/C wie in
  → 23 × `nein`; Startseiten der drei A-Portale danach weiter HTTP 200
  `docs/turnstile.md` §8 verteilt (3 / 15 / 5 Portale)

---

## 2. Stufe 0 — Trockenprobe ohne Besucher

Ziel: beweisen, dass Schlüssel, Hostname- und Action-Check stimmen, **bevor** ein
echtes Formular daran hängt.

**Reihenfolge beachten (nachgemessen 08.10.2026):** die drei Token-Zeilen unten brauchen
ein Portal, das bereits auf `ja` steht. Solange nach §1.6 alle 23 Portale
herausgenommen sind, antwortet die Rule mit `skipped` und schreibt keine Log-Zeile —
`turnstile:verify … --tenant=fahrschulefinder.de` liefert dann `Bewertung: skipped`.
Stufe 0 mit Portalkontext ist deshalb erst nach `turnstile:rollout --wave=1` (§3)
durchführbar; praktisch sind es die ersten Handgriffe der Stufe 1, nicht ein Schritt
davor. Was ohne scharfes Portal geht, ist unten als Vorprobe abgehakt.

- [x] 2026-10-08 SE `turnstile:keys:check --siteverify` grün (§1.2)
  → siehe §1.2, Exit 0; am 08.10.2026 (DI) unabhängig nachgemessen: A/B/C je
  `Schluessel ok`, `Siteverify ok`, 18 Hostnames, kein falsch zugeordnetes Portal,
  Exit 0 (der Befehl endet in Produktion bei Befund mit 1)
- [x] 2026-10-08 DI Vorprobe ohne Portalkontext: `turnstile:verify <erfundenes-token>
  --action=registration` (ohne `--tenant`) — Cloudflare beanstandet das Token
  (`invalid-input-response`), **nicht** das Secret, und die TurnstileRule weist ab
  (`failed`). Damit sind Secret, Weg zu Siteverify und Abweisung belegt, ohne ein Portal
  scharf zu stellen. Beleg:
  `docs/messungen/turnstile-golive/stufe0-vorproben-2026-10-08.txt`
- [x] 2026-10-08 ST Der Maschinenanteil ist auch für `sanitaerfinden.com` (Tenant 28,
  zugleich `CENTRAL_DOMAIN`) nachgefahren — `turnstile:acceptance --siteverify --force`,
  sieben von sieben `ok`, Zustand danach wieder 23 × `nein`. Damit liegen alle drei
  Gruppe-A-Portale vor, Beleg
  `docs/messungen/turnstile-golive/stufe0-maschinell-20261008.txt`
- [ ] Ein Token von Hand prüfen: Widget auf `https://fahrschulefinder.de/register` lösen,
  den Wert des Hidden-Inputs `cf-turnstile-response` aus den Entwicklerwerkzeugen
  kopieren und
  `php artisan turnstile:verify <token> --action=registration --tenant=fahrschulefinder.de`
  — erwartet `passed`, Hostname `fahrschulefinder.de`, Action `registration`. Ohne
  `--tenant` läuft die Probe ohne Portalkontext und schreibt keine Log-Zeile.
  Ein Token ist bei Cloudflare nur **einmal** einlösbar; ein zweiter Lauf liefert
  `timeout-or-duplicate` und ist kein Befund.
- [ ] Gegenprobe Hostname: dasselbe Vorgehen mit einem frischen Token, aber
  `--tenant=elektrikerportal.com` — erwartet `rejected`, Fehlercode `hostname-mismatch`
- [ ] Gegenprobe Action: Token aus dem Eintragsformular mit `--action=registration`
  einreichen — erwartet `rejected`, Fehlercode `action-mismatch`
- [ ] Verifikations-Log im Panel (*Bot-Schutz › Prüfungen*) zeigt genau diese vier Zeilen
  mit Portal, Aktion, Ergebnis, erwartetem und gemeldetem Hostnamen

---

## 3. Stufe 1 — Gruppe A, 24 Stunden (Akzeptanzkriterium 2)

Portale: `fahrschulefinder.de` (29), `elektrikerportal.com` (30), `sanitaerfinden.com`
(28, zugleich `CENTRAL_DOMAIN`, Tenant mit eigener Domainzeile — Tenancy wird dort
ganz normal initialisiert, das Verifikations-Log schreibt also auch für dieses Portal).

Das Ticket nennt an dritter Stelle `sanitaerfinder.com`. Das ist ein Tippfehler im
Ticket: `sanitaerfinder.com` ist Tenant 49 und liegt in Gruppe B. Maßgeblich ist die
Gruppe A aus `docs/turnstile.md` §8 — also `sanitaerfinden.com`, Tenant 28. Wer das
anders will, nimmt `--activate=sanitaerfinder.com` dazu; dann braucht Stufe 1 aber
auch die Schlüssel der Gruppe B, und die sind nach §1.1 ohnehin gesetzt.

- [x] 2026-10-08 DI Vergleichswerte der Vorwoche erhoben, je Portal und Tag:
  `php artisan turnstile:report --date=<tag> --json` gibt es erst ab dem Deploy, die
  Vorwoche kommt deshalb aus der Datenbank. Erhoben mit
  `scripts/turnstile-vorwoche.sh` (streng lesend, nur SELECTs) über 01.10.–07.10.2026;
  Beleg mit allen Tageswerten und allen Portalen:
  `docs/messungen/turnstile-golive/vorwoche-2026-10-08.json`.

  | Portal | Registrierungen Vorwoche (Ø/Tag) | Einträge Vorwoche (Ø/Tag) |
  |---|---|---|
  | fahrschulefinder.de | 13 (1,86) | 6 (0,86) |
  | elektrikerportal.com | 2 (0,29) | 2 (0,29) |
  | sanitaerfinden.com | 1 (0,14) | 0 (0,0) |

  Über alle 23 Portale: 39 Registrierungen und 16 Einträge in den sieben Tagen, auf 13
  Portale verteilt. Spitze ist `fahrschulefinder.de` am 05.10. mit 6 Registrierungen an
  einem Tag — das ist der Wert, an dem sich „Bot-Registrierungen gestoppt" messen lässt.
  Die Spalte „Botverdacht" steht in allen Zeilen auf 0, weil `antispam:scan` auf der
  Produktion noch nicht gelaufen ist (#23); sie sagt deshalb nichts über den Bot-Anteil
  der Vorwoche, nur die Mengen sind belastbar.

- [ ] Scharfschalten: `php artisan turnstile:rollout --wave=1` — Tabelle zeigt genau diese
  drei Portale auf `ja`, Modus beider Aktionen `managed`, Gruppe `A`
- [ ] Startzeitpunkt notieren: `____________` (die 24 Stunden laufen ab hier)

**Sichtprüfung, je Portal einmal mobil und einmal am Schreibtisch** (sechs Durchläufe,
Akzeptanzkriterium 2b). Kein Testkonto wiederverwenden — die Rate-Limits zählen je
IP und je Mailadresse.

- [ ] fahrschulefinder.de, Desktop: `/register` zeigt den Kasten und den
  Transparenzhinweis mit Link auf die Datenschutzerklärung; Registrierung geht durch;
  Eintragsformular geht durch
- [ ] fahrschulefinder.de, Mobil (echtes Gerät, nicht nur verkleinertes Fenster): dito,
  der Kasten schiebt das Layout nicht (der Platz ist mit `min-h-[65px]` reserviert)
- [ ] elektrikerportal.com, Desktop / Mobil: dito
- [ ] sanitaerfinden.com, Desktop / Mobil: dito
- [ ] Gegenprobe, dass überhaupt geprüft wird: Formular mit leerem Hidden-Input
  abschicken (Feld in den Entwicklerwerkzeugen leeren) — erwartet die Feldmeldung aus
  `lang/de/turnstile.php`, kein Konto, eine Zeile `missing_token` im Log
- [ ] Belege: sechs Screenshots nach `docs/messungen/turnstile-golive/stufe1/`

**Beobachtung über 24 Stunden**

- [ ] Nach 1 h: `php artisan turnstile:monitor --no-mail` ohne Alarm; Panel-Widget
  *Bot-Schutz* zeigt `passed` deutlich über `failed`
- [ ] Nach 24 h: `php artisan turnstile:report --json` je Portal ablegen und mit der
  Tabelle oben vergleichen
- [ ] **Bot-Registrierungen gestoppt:** Registrierungen auf den drei Portalen liegen
  deutlich unter dem Vorwochenwert, und die Differenz steht im Verifikations-Log als
  `failed`/`missing_token` (nicht als `error`). Zahlen hier eintragen: `____________`
- [ ] **Echte Registrierungen weiter möglich:** die sechs Durchläufe oben sind als
  `passed` im Log wiederzufinden, und es gibt mindestens eine `passed`-Zeile, die
  nicht von einem der Testdurchläufe stammt
- [ ] Fehlerquote `error` unter 1 % und Blockierungsquote `failed` unter 5 % der
  Prüfungen je Portal und Stunde — sonst §6

---

## 4. Stufe 2 — Gruppe B, drei Tage

Start erst, wenn Stufe 1 **24 Stunden** ohne Abbruchkriterium gelaufen ist.
Gruppe B sind 15 Portale auf 10 Hostnames, darunter die fünf
`firmenfreund.de`-Subdomains über ihren Elterneintrag.

- [ ] `php artisan turnstile:rollout --wave=2` — 15 Portale auf `ja`
- [ ] Sichtprüfung auf zwei Portalen der Gruppe, je mobil und Desktop, davon eines
  zwingend eine `firmenfreund.de`-Subdomain (dort läuft der Hostname-Check über den
  Elterneintrag, das ist der Fall mit dem größten Fehlerrisiko)
- [ ] Tagesbericht an drei aufeinanderfolgenden Tagen gesichtet, keine Bot-Welle, keine
  auffällige Blockierungsquote: Tag 1 `____` Tag 2 `____` Tag 3 `____`

## 5. Stufe 3 — Gruppe C, restliche Portale

- [ ] `php artisan turnstile:rollout --wave=3` — 5 Portale auf `ja`
- [ ] `php artisan turnstile:rollout` zur Gegenprobe: 23 × `ja`
- [ ] Sichtprüfung auf einem Portal der Gruppe, mobil und Desktop
- [ ] Drei Tagesberichte ohne Bot-Welle über alle 23 Portale:
  Tag 1 `____` Tag 2 `____` Tag 3 `____`

Stufe 4 (`lead_request`, `contact`) gehört **nicht** zu diesem Ticket. Die Formulare
selbst sind seit #22 angeschlossen — Anfrage-Dialog, Bewertung, Bewerbung, Newsletter und
Korrekturvorschlag tragen Rule, Widget und Tiefenverteidigung. Offen ist nur der
Schalter: beide Aktionen stehen in `config/turnstile.php:245,250` auf
`enabled => false`, weil dort der Umsatz hängt; sie gehen Portal für Portal über
`tenant_turnstile_settings.actions_json` im Admin an, im Modus `non_interactive`, und
brauchen eine eigene Messung des Leadflusses (Anfragen je Portal und Tag vor/nach).

---

## 6. Notbremse

Zuerst die Darstellung entschärfen, erst dann abschalten — in dieser Reihenfolge,
so steht es in den technischen Hinweisen von #13:

```bash
# 1. zu viele echte Nutzer blockiert: Kasten ohne Aufgabe
php artisan turnstile:rollout --activate=<domain> --mode=non_interactive

# 2. hilft nicht: Portal herausnehmen
php artisan turnstile:rollout --deactivate=<domain>

# 3. netzweiter Vorfall: Modul global aus (.env), danach config:cache
TURNSTILE_ENABLED=false
```

Abbruchkriterium je Stufe: steigt `failed` über 5 % der Prüfungen oder `error` über
1 %, wird die Welle zurückgenommen — ohne Deployment, ohne Code-Änderung. Ein Lauf von
`turnstile:rollout` wirkt ab der nächsten Anfrage (der Resolver cacht nur je Request).

Fällt Siteverify bei Cloudflare aus, bleibt der Fail-Mode `open`: die Formulare laufen
weiter, Honeypot, Mindest-Ausfüllzeit und Rate-Limits greifen weiter, jeder Fall wird
als `error` geloggt und alarmiert: seit #19 schlägt
`AlertOnSiteverifyUnreachable` binnen Minuten an (ab 20 Ausfällen in 5 Minuten), der
stündliche `turnstile:monitor` ist nur noch das Netz darunter. `closed` wird in keiner
Stufe gesetzt.

Eigene Notbremse für die CSP, unabhängig von Turnstile: `CSP_MODE=report` (meldet, blockt
nicht) oder `CSP_MODE=off`, danach `config:cache` und `systemctl reload php8.5-fpm`. Ein
blockiertes Analytics- oder AdSense-Schnipsel ist kein Grund, den Bot-Schutz anzufassen.

---

## 7. Abnahme

- [ ] §1 vollständig abgehakt, Belege liegen unter `docs/messungen/turnstile-golive/`
- [ ] Stufen 1–3 abgehakt, alle 23 Portale auf `ja`
- [ ] Drei Tagesberichte in Folge ohne Bot-Welle (§5)
- [x] 2026-10-08 `docs/turnstile-security-review.md` ohne offene Punkte im Modulumfang
  (S1 behoben in #26, R2 in #19, R4 in #21/#28/#29, R3 angeschlossen in #22; draussen
  bleiben die Cloudflare-WAF #24 und Welle 4)
- [ ] **Abnahme durch Enes:** Datum `__________`, Kürzel `____`

### 7.1 Was maschinell erledigt ist und was am Menschen hängt

Erledigt (Ticket #13, Stand 08.10.2026):

* diese Checkliste samt Belegordner, Notbremse und Abbruchkriterien
* die Werkzeuge für jeden Schritt: `turnstile:rollout`, `turnstile:keys:check`,
  `turnstile:verify`, `turnstile:acceptance`, `turnstile:monitor`, `turnstile:report`,
  `antispam:backup`, `scripts/turnstile-widgets-anlegen.sh`,
  `scripts/turnstile-schluessel-eintragen.sh`
* das Security-Review ohne offene Punkte, am 08.10.2026 am Code nachgeprüft (§12 dort)
* die Vorbedingungen, soweit ohne Produktionszugang prüfbar: Schlüsselsperre,
  Gruppenzuordnung (18 Hostnames = Soll), Alarm-Bindung, CSP-Vorgabe

Am Menschen (Ticket #36):

* B1: Cloudflare-Token, drei Widgets, sechs Werte in die Produktions-`.env`
* B2: Commit und Freigabe, dann Deploy nach §1.3
* die Stufen 1–3 mit den sechs Sichtprüfungen mobil und am Schreibtisch
* die Abnahme hier drunter

### 7.2 Nachsorge: Secrets und Token tauschen (#45)

Erst **nach** der Abnahme — waehrend der Stufen 1–3 waere ein Schluesselwechsel
eine zusaetzliche Fehlerquelle. Die Secrets sind nicht oeffentlich, stehen aber
in internen Protokollen: `--pruefen` hat sie bis #45 im Klartext gedruckt und
wurde am 08.10.2026 in #36 genau so gebraucht.

- [ ] Die drei Widget-Secrets neu erzeugen (Sitekeys bleiben, kein Ausfall —
  der Resolver liest das Secret je Anfrage aus der Config):

  ```bash
  scripts/turnstile-widgets-anlegen.sh --secrets-rotieren
  ```

  Das alte Secret gilt danach noch zwei Stunden. In diesem Fenster:
  Passwort-Manager (`SUN / Cloudflare Turnstile / Gruppe A|B|C`, Feld `secret`)
  aktualisieren und die neuen Werte eintragen mit
  `scripts/turnstile-schluessel-eintragen.sh`.
  Ohne Token geht es auch von Hand: Dashboard → Turnstile → Widget →
  *Rotate secret*.
- [ ] Gegenprobe, muss gruen bleiben:
  `php artisan turnstile:keys:check --siteverify`
- [ ] Den Cloudflare-Token aus #18
  (`CLOUDFLARE_API_TOKEN_SUN_TURNSTILE` in `/root/sun-zugang.txt`) ersetzen: im
  Dashboard neu erzeugen (nur „Account / Turnstile: Edit“), alten Token
  widerrufen, neuen ablegen mit `scripts/cloudflare-token-ablegen.sh`,
  Gegenprobe `scripts/cloudflare-token-ablegen.sh --pruefen` → Exit 0. Die
  Anwendung braucht den Token nie, ein Wechsel beruehrt den Betrieb nicht.

---

## 8. Belege

Ablage `docs/messungen/turnstile-golive/`:

| Datei | Inhalt |
|---|---|
| `keys-check-<datum>.txt` | Ausgabe `turnstile:keys:check --siteverify` |
| `migrate-<datum>.txt` | Ausgabe `migrate` und `tenants:migrate` |
| `vorwoche-<datum>.json` | Registrierungen und Einträge je Portal, sieben Tage vor Stufe 1 |
| `csp-report-<datum>.txt` | CSP in Produktion: Header, Messung des ausgelieferten HTML über acht Seitentypen (`scripts/csp-live-pruefen.sh`), Befunde |
| `stufe1/` | sechs Screenshots, `turnstile:report --json` nach 24 h |
| `stufe2/`, `stufe3/` | Screenshots und je drei Tagesberichte |
| `backup-<datum>.txt` | Ausgabe `antispam:backup`, Dateiliste |
| `csp-report-<datum>.txt` | 24 h `CSP_MODE=report`, gesichtete Meldungen |

Welche Belege noch fehlen, zaehlt `scripts/turnstile-golive-stand.sh` mit auf.
