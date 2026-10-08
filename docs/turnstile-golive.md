# Go-Live des Bot-Schutzes — Checkliste, gestaffelter Rollout, Abnahme

Status: **bereit zur Durchführung, nicht begonnen** (Stufe 1 startet erst, wenn §0 leer ist).
Durchführungsversuch 08.10.2026 in #36 vor §1.3 abgebrochen, weil B1 offen ist —
Protokoll `docs/messungen/turnstile-golive/durchfuehrung-2026-10-08.md`.
Zweite Nachprüfung am 08.10.2026 (#36): Produktion unverändert `f5fa66ed`,
`/root/sun-zugang.txt` fehlt weiter, `grep -c '^TURNSTILE' .env` = 0,
`grep -c '^CSP_MODE' .env` = 0 — B1 unverändert offen, keine Änderung am Server.
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
| B1 | **Keine Produktionsschlüssel.** `grep -c '^TURNSTILE' .env` in der Produktion gibt **0** (erneut geprüft 08.10.2026). Die drei Cloudflare-Widgets sind nicht angelegt. Der Cloudflare-API-Token mit „Account / Turnstile: Edit“ fehlt ebenfalls (#38, `/root/sun-zugang.txt` existiert nicht), **ist aber nicht die Ursache**: er automatisiert nur das Anlegen der Widgets. Ohne ihn geht §1.1 über Weg B — drei Widgets im Dashboard von Hand, sechs Werte über `scripts/turnstile-schluessel-eintragen.sh`; weder dieses Skript noch `turnstile:keys:check --siteverify` reden mit der Cloudflare-API. | Ohne Schlüsselpaar wirft der Resolver in Produktion `TurnstileNotConfiguredException`; die Rule behandelt das wie einen Ausfall (Fail-Mode `open`): jedes Formular läuft ungeschützt weiter und meldet SUN-TS-011. | §1.1 Weg B (Dashboard, Mensch) oder Weg A nach #18/#14; Durchführung in #36 |
| B2 | **Das Modul ist nicht eingecheckt.** `app/Turnstile/`, `app/AntiSpam/`, `config/turnstile.php`, `config/antispam.php`, `config/csp.php`, die sieben `2026_10_09_*`-Migrationen, die Filament-Seiten und die Blade-Komponente stehen als `??` in `git status`; die Produktion steht auf `f5fa66ed` und kennt nichts davon (geprüft 08.10.2026). | Nichts davon ist deploybar. | eigener Commit, Freigabe Enes — Schritt 1 in #36 |

Erledigt seit der ersten Fassung dieses Dokuments:

| # | war | Stand 08.10.2026 |
|---|---|---|
| B3 | SUN-TS-011 ohne Empfänger | **weg**, #19: `App\Listeners\Turnstile\AlertOnSiteverifyUnreachable` hängt über Event-Discovery an `SiteverifyUnreachable` (`php artisan event:list` belegt die Bindung) und meldet ab 20 Ausfällen in 5 Minuten, danach höchstens halbstündlich. Der stündliche `turnstile:monitor` bleibt die Quotenaufsicht. |
| B4 | CSP auf `off`, Themes mit Inline-Skripten | **weg**, #21: die Themes liefern keine Inline-Skripte mehr, fremdes HTML bekommt das Nonce aus `CspNonce`, die Vorgabe in `config/csp.php:41` ist `enforce`. Im echten Browser nachgemessen in #28 (Chrome 154) und #29 (WebKit), Protokolle unter `docs/messungen/csp-browser-*`. §1.5 bleibt trotzdem beim Zwischenschritt `report` für 24 h, weil in Produktion echte `TRACKING_SCRIPTS` und echte `ad_slots` aus der Datenbank kommen; `CSP_MODE=report` wird nach §1.3 gesetzt, noch vor dem ersten `config:cache` des Deploys. |

Start von Stufe 1 erst, wenn B1 und B2 erledigt sind. Beides sind Handgriffe eines
Menschen: B1 braucht eine Dashboard-Anmeldung am Cloudflare-Konto (kein API-Token, siehe
§1.1 Weg B), B2 einen Commit mit Freigabe. Alles andere in diesem Dokument ist
vorbereitet und mit Werkzeug hinterlegt — der Deploy als Ganzes in
`scripts/turnstile-golive-deploy.sh`.

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
Siteverify — beide reden nie mit der Cloudflare-API. Stand 08.10.2026 fehlt der Token
(#38, nachgewiesen in `docs/messungen/turnstile-golive/durchfuehrung-2026-10-08.md` §5
und §7); deshalb läuft §1.1 über **Weg B**, und der Token wird nachgeholt, wann es passt.

*Weg A — mit Token (schneller, wenn er liegt):* Der Token existiert nirgends und lässt
sich nicht finden, nur im Dashboard erzeugen (abschließend gesucht am 08.10.2026,
Protokoll §10.1). **#38 ist geschlossen; dieser Handgriff lebt ab jetzt hier** — er ist
kein Blocker, Weg B führt ohne ihn zum Go-Live.

- [ ] Token mit „Account / Turnstile: Edit“ liegt über
  `scripts/cloudflare-token-ablegen.sh` in `/root/sun-zugang.txt` (chmod 600),
  Gegenprobe `scripts/cloudflare-token-ablegen.sh --pruefen` → Exit 0. In derselben
  Ablage steht der WAF-Token `CLOUDFLARE_API_TOKEN_SUN_WAF` aus #24 — beide in einem
  Durchgang ablegen
- [ ] `scripts/turnstile-widgets-anlegen.sh` legt die drei Widgets an,
  `--pruefen` meldet 3 + 10 + 5 Hostnames, `mode: managed`,
  `clearance_level: no_clearance`

*Weg B — ohne Token, drei Widgets von Hand (Dashboard → Turnstile → Add widget):*

Die Angaben zum Abtippen druckt das Skript selbst, ohne Token und ohne ssh — es nimmt
die Namen und Hostnames aus derselben Stelle, die später `--pruefen` vergleicht:

```bash
scripts/turnstile-widgets-anlegen.sh --weg-b
```

- [ ] Drei Widgets, Namen genau `SUN Welle 1`, `SUN Welle 2`, `SUN Welle 3` (unter
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
- [ ] Kein Hostname steht in zwei Widgets und keiner fehlt — die Gegenprobe macht
  `turnstile:keys:check` in §1.2 (Spalte `hostname_fehler`, Summe 18)
- [ ] Das am 08.10.2026 von Hand angelegte Widget mit den vier gemischten Hostnames
  (Sitekey `0x4AAAAAAFRd_ph0Sz_IuU2u`) ist gelöscht — es passt zu keiner Gruppe,
  Entscheidung Enes im Ticket #36

Für beide Wege gilt:

- [ ] Im Dashboard von Hand: „Allow a domain to be added automatically“ bei allen drei
  Widgets **aus** (die API kennt den Schalter nicht) — sonst wandern fremde Hostnames
  ins Widget und der Hostname-Check verliert seinen Sinn
- [ ] Passwort-Manager: je Gruppe ein Eintrag `SUN / Cloudflare Turnstile / Gruppe A|B|C`
  mit `sitekey`, `secret` und der Hostname-Liste als Notiz
- [ ] Sechs Werte in der Produktions-`.env`:
  `scripts/turnstile-schluessel-eintragen.sh` (sichert die `.env`, weist Testschlüssel
  ab, baut den Config-Cache neu, lädt `php8.5-fpm` nach, startet
  `sanitaerfinden-horizon` durch, fährt je Gruppe die Siteverify-Probe)
- [ ] Kein Schlüssel im Repo: `php scripts/secret-scan.php` sauber

### 1.2 Keine Testschlüssel in Produktion (Akzeptanzkriterium 1b)

`.env.example` setzt die Cloudflare-Testschlüssel als Vorgabe (`1x0000…`), damit eine
frische Installation läuft. In Produktion sind sie wertlos: sie bestehen auf jeder
Domain und liefern `hostname: example.com` ohne `action`, der Hostname- und
Action-Check wäre damit tot. Der Resolver wirft deshalb in Produktion, wenn ein
Testschlüssel hinterlegt ist (`TurnstileConfigResolver::TEST_SITE_KEYS`).

- [ ] `sudo -u sanitaerfinden /usr/bin/php8.4 artisan turnstile:keys:check --siteverify`
  meldet für **alle drei** Gruppen `ok`, zählt 18 Hostnames, nennt kein falsch
  zugeordnetes Portal und gibt Exit 0 — Ausgabe ablegen
- [ ] Kein Portal hat ein eigenes Widget in der Datenbank (Spalte „Schluessel“ in
  `turnstile:rollout` steht überall auf `Gruppe`)
- [ ] Gegenprobe im Browser: der `data-sitekey` im HTML von `/register` beginnt **nicht**
  mit `1x`, `2x` oder `3x`

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

- [ ] B2 erledigt: Commit mit dem Bot-Schutz liegt auf `origin/main`, Hash hier
  eintragen: `________`
- [ ] **Backup vor der Migration** — die sieben `2026_10_09_*`-Migrationen legen
  Tabellen und Spalten in der zentralen und in jeder Portal-Datenbank an. Die
  nächtliche Sicherung ist dafür **nicht** ausreichend: `clpctl db:backup` sichert nur,
  was in CloudPanels eigener `db.sq3` steht, und zehn der 23 Portal-Datenbanken stehen
  dort nicht (geprüft 08.10.2026, ~3 GB Nutzdaten ohne Sicherung, eigenes Ticket).
  Deshalb selbst dumpen — das macht Schritt 3 des Deploy-Skripts nach
  `storage/app/backups/golive-36/<stempel>/` und bricht ab, wenn eine Datei unter
  1 KB bleibt oder weniger als 24 Dateien entstehen.
- [ ] Notbremse vorziehen: `TURNSTILE_ENABLED=false` in die `.env`, dann `config:cache`.
  So ist der Schutz während Migration und Seeder global aus, und die Lücke zwischen
  „Seeder legt 23 Zeilen mit `is_enabled = true` an“ und „§1.6 nimmt sie heraus“ kann
  niemanden treffen.
- [ ] **Im selben Schritt `CSP_MODE=report` in die `.env`**, noch vor dem ersten
  `config:cache` dieses Deploys. Die Vorgabe im Code ist `enforce` und die
  Produktions-`.env` hat heute keine `CSP_MODE`-Zeile — jeder Neuaufbau des
  Config-Caches schaltet die CSP sonst sofort auf allen 23 Portalen durchsetzend,
  also schon hier und nicht erst in §1.5. Die 24-Stunden-Messung und der Umstieg auf
  `enforce` stehen in §1.5.
- [ ] `git pull --ff-only` **als root** (nur root hat den GitHub-Schlüssel), danach
  `chown -R sanitaerfinden:sanitaerfinden /home/sanitaerfinden/htdocs/sanitaerfinden.dev`
- [ ] `composer install --no-dev -o`
- [ ] `npm ci && npm run build` — `public/build/` ist nicht versioniert, und
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
- [ ] `php artisan migrate --force` (zentral) — Ausgabe ablegen
- [ ] `php artisan tenants:migrate --force` — Ausgabe ablegen, alle 23 Portale ohne Fehler
- [ ] `php artisan db:seed --class=TenantTurnstileSettingSeeder` — meldet 23 angelegt
- [ ] `php artisan tenants:datenschutz:turnstile-backfill` (erst ohne, dann mit `--write`, #11) — Turnstile-Abschnitt in
  der Datenschutzerklärung jedes Portals
- [ ] `php artisan config:cache`, `route:clear`, `view:clear`, `filament:optimize`.
  Achtung: der Config-Cache auf diesem Server ist alt — der Neuaufbau schaltet zugleich
  alle seit Mai aufgelaufenen `.env`-Änderungen scharf. Vorher `php artisan config:show`
  der betroffenen Bereiche sichten.
- [ ] **`systemctl reload php8.5-fpm`** nach jedem `config:cache`. Die Website läuft über
  den FPM-Pool von PHP 8.5, Artisan über CLI-PHP 8.4 — ohne den Reload bleibt im Web der
  alte Config-Cache stehen, und genau dann wirkt die Notbremse `TURNSTILE_ENABLED=false`
  nicht, obwohl sie in der `.env` steht und `config:show` sie anzeigt.
- [ ] `php artisan queue:restart` und `php artisan horizon:terminate`, danach
  `supervisorctl restart sanitaerfinden-horizon`

### 1.4 Backup vor `antispam:scan` (Akzeptanzkriterium 1d)

`antispam:scan` steht absichtlich **nicht** im Scheduler: markiert wird nur nach
Sichtung, von Hand. Vor dem ersten Produktivlauf wird gesichert — der Produktivlauf
selbst ist #23, nicht dieses Ticket.

- [ ] `php artisan antispam:backup` — sichert die zentrale `users` und die `companies`
  jedes Portals als NDJSON nach `storage/app/backups/antispam/<datum>/`
- [ ] Ordner gesichtet: 23 Portaldateien + zentrale Datei, Zeilenzahlen plausibel
- [ ] Eigener Datenbank-Dump vorhanden (zweite Ebene, unabhängig vom NDJSON) — der
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

- [ ] `php artisan schedule:list` zeigt alle vier Einträge mit den Zeiten oben
- [ ] Schneller Alarm steht (#19): `php artisan event:list | grep -A1 Siteverify` zeigt
  `AlertOnSiteverifyUnreachable@handle`. Ohne diese Zeile fällt ein Siteverify-Ausfall
  erst beim stündlichen Monitor auf
- [ ] `php artisan turnstile:monitor --no-mail` läuft ohne Fehler durch (zeigt Alarme nur
  auf der Konsole, keine Entdopplung)
- [ ] `php artisan turnstile:report --no-mail` baut den Bericht über gestern
- [ ] **Alarme an Enes und Uwe:** `TURNSTILE_ALERT_RECIPIENTS=<enes>,<uwe>` in der
  Produktions-`.env`, danach `config:cache`. Ohne diese Zeile gehen Alarme und
  Tagesbericht an alle nicht gesperrten Administratoren mit dem Recht
  `update settings` — das ist der Rückfall, nicht die Zielgruppe. Adressen gehören
  nicht ins Repo.
- [ ] Verteiler nachgewiesen:
  `php artisan tinker --execute="print_r(App\Turnstile\Support\BotProtectionRecipients::emails());"`
  listet genau die beiden Adressen
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
  3. 24 Stunden Meldungen sichten (dieselben Seiten wie in
     `docs/messungen/csp-browser-messung-2026-10-08.md`: Startseite, Suche, Profil mit
     Anfrage-Dialog, Ratgeber, `/register`, Eintragsformular — je Portal eines der drei
     Gruppe-A-Portale, mobil und Desktop)
  4. dann `CSP_MODE=enforce`, `config:cache`, Reload, und derselbe `curl` zeigt
     `Content-Security-Policy` ohne `-Report-Only`
  Begründung für den Zwischenschritt, obwohl die Messung sauber war: lokal gemessen wurde
  gegen `php artisan serve` mit Testwerten für Analytics und AdSense; in Produktion
  stehen echte `TRACKING_SCRIPTS` und echte `ad_slots` aus der Verwaltung in der
  Datenbank, und genau die sind fremdes HTML. `report` kostet einen Tag und kann nichts
  abschalten.

### 1.6 Alle Portale herausnehmen, dann global scharf

- [ ] `php artisan turnstile:rollout --deactivate-all --force` — Tabelle zeigt 23 × `nein`
- [ ] `TURNSTILE_ENABLED=true` (bzw. die Zeile aus §1.3 wieder entfernen), `config:cache`,
  `queue:restart`
- [ ] `php artisan turnstile:rollout` zur Gegenprobe: 23 × `nein`, Gruppen A/B/C wie in
  `docs/turnstile.md` §8 verteilt (3 / 15 / 5 Portale)

---

## 2. Stufe 0 — Trockenprobe ohne Besucher

Ziel: beweisen, dass Schlüssel, Hostname- und Action-Check stimmen, **bevor** ein
echtes Formular daran hängt.

- [ ] `turnstile:keys:check --siteverify` grün (§1.2)
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

- [ ] Vergleichswerte der Vorwoche notieren, je Portal und Tag:
  `php artisan turnstile:report --date=<tag> --json` gibt es erst ab dem Deploy, die
  Vorwoche kommt deshalb aus der Datenbank — neue `users` je Portal und neue
  `companies` je Portal, Tag für Tag, sieben Tage. Tabelle hier eintragen:

  | Portal | Registrierungen Vorwoche (Ø/Tag) | Einträge Vorwoche (Ø/Tag) |
  |---|---|---|
  | fahrschulefinder.de | ____ | ____ |
  | elektrikerportal.com | ____ | ____ |
  | sanitaerfinden.com | ____ | ____ |

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

---

## 8. Belege

Ablage `docs/messungen/turnstile-golive/`:

| Datei | Inhalt |
|---|---|
| `keys-check-<datum>.txt` | Ausgabe `turnstile:keys:check --siteverify` |
| `migrate-<datum>.txt` | Ausgabe `migrate` und `tenants:migrate` |
| `vorwoche-<datum>.json` | Registrierungen und Einträge je Portal, sieben Tage vor Stufe 1 |
| `stufe1/` | sechs Screenshots, `turnstile:report --json` nach 24 h |
| `stufe2/`, `stufe3/` | Screenshots und je drei Tagesberichte |
| `backup-<datum>.txt` | Ausgabe `antispam:backup`, Dateiliste |
| `csp-report-<datum>.txt` | 24 h `CSP_MODE=report`, gesichtete Meldungen |

Welche Belege noch fehlen, zaehlt `scripts/turnstile-golive-stand.sh` mit auf.
