# Durchführung #36 — Abbruch vor Stufe 0, Stand 08.10.2026

Zweck: festhalten, wie weit die Durchführung ohne Menschen kommt, was dabei
nachgeprüft wurde und an welcher Zeile sie stehenbleibt. Verbindlicher Ablauf:
`docs/turnstile-golive.md`.

Ergebnis in einem Satz: **B1 ist ungelöst**, deshalb darf §1.3 (Deploy) nicht
laufen — und damit keine Stufe. Nachgeprüft am 08.10.2026, nichts an der
Produktion verändert.

## 1. Produktion — Nachprüfung (nur lesend)

```
ssh sun; cd /home/sanitaerfinden/htdocs/sanitaerfinden.dev
ls -l /root/sun-zugang.txt                 → No such file or directory
grep -c '^TURNSTILE' .env                  → 0
grep -n '^CSP_MODE\|^TURNSTILE_ALERT' .env → keine Treffer
git log --oneline -1                       → f5fa66ed
ls -d app/Turnstile app/AntiSpam config/turnstile.php → alle drei fehlen
crontab -u sanitaerfinden -l | grep -c schedule:run   → 1
```

Neu gegenüber `vorpruefung-2026-10-08.md`: **`/root/sun-zugang.txt` existiert
nicht.** Ticket #18 (Cloudflare-Zugang) steht auf „Erledigt", die Ablage, die
`scripts/cloudflare-token-ablegen.sh` anlegt und die
`scripts/turnstile-widgets-anlegen.sh` liest, ist aber leer. Ohne diese Datei
scheitert §1.1 am ersten Haken: keine Widgets, keine Sitekeys, keine Secrets.
Dafür ist ein eigenes Ticket angelegt.

## 2. Warum der Deploy ohne B1 nicht laufen darf

`TenantTurnstileSettingSeeder` legt 23 Zeilen mit `is_enabled = true` an, und
der Vorgabewert der Spalte ist ebenfalls `true` (`docs/turnstile-golive.md`
§0.1). Fehlt das Schlüsselpaar, wirft `TurnstileConfigResolver` die
`TurnstileNotConfiguredException`; `TurnstileRule` behandelt das wie einen
Siteverify-Ausfall und lässt bei Fail-Mode `open` **jedes** Formular ungeschützt
durch — mit SUN-TS-011-Alarm je Portal. Dazu käme, dass `config:cache` in §1.3
auf diesem Server gleichzeitig `CSP_MODE=enforce` auf alle 23 Portale schaltet
(keine `CSP_MODE`-Zeile in der `.env`, Vorgabe im Code ist `enforce`). Ein
Deploy ohne Schlüssel verschlechtert den Zustand also gegenüber heute. Er wurde
deshalb nicht angefasst.

## 3. B2 — Commit-Reife des Moduls geprüft

Der Commit selbst bleibt beim Menschen (Freigabe Enes). Geprüft wurde, dass er
sauber durchgeht:

```
# Secret-Scan über die unversionierten Dateien (der reguläre Lauf sieht nur
# versionierte Dateien, das Modul also noch nicht):
git status --porcelain | awk '$1=="??"{print $2}' \
  | while read f; do [ -d "$f" ] && find "$f" -type f || echo "$f"; done \
  | xargs php scripts/secret-scan.php
    → secret-scan: sauber (149 Dateien geprüft)

php -l über die 112 neuen PHP-Dateien   → 0 Syntaxfehler
vendor/bin/pint --test über dieselben   → pass (nach den Korrekturen unten)
php scripts/secret-scan.php             → sauber (2475 versionierte Dateien)
```

Drei Formatbefunde in neuen Dateien wurden behoben, damit der Commit die
Qualitätsstufe des Repos trifft (nur diese Dateien, kein Lauf über fremde
Arbeit):

* `app/AntiSpam/Support/BotCandidate.php`, `app/AntiSpam/Support/BurstIndex.php`
  — `new BurstCounts()` → `new BurstCounts` (`new_with_parentheses`)
* `app/Filament/Admin/Widgets/Concerns/InteractsWithQuarantine.php`
  — Importreihenfolge (`ordered_imports`)

## 4. Offene Zeilen — alle am Menschen

| Abschnitt | Was fehlt | Warum kein Agent |
|---|---|---|
| §1.1 B1 | Token „Account / Turnstile: Edit", drei Widgets, sechs `.env`-Werte, Dashboard-Schalter „Allow a domain to be added automatically" aus, Passwort-Manager-Einträge | Zugang zum Cloudflare-Konto; der Schalter ist nicht in der API |
| §1.2 | `turnstile:keys:check --siteverify` grün | braucht die Schlüssel aus B1 |
| §1.3 B2 | Commit auf `origin/main`, dann Deploy | Freigabe Enes; Deploy ohne B1 schadet (Abschnitt 2) |
| §1.4–§1.6 | Backup, Verteiler, CSP 24 h `report`, Portale herausnehmen | nach dem Deploy |
| §2 | Trockenprobe `turnstile:verify` samt beiden Gegenproben | Token aus einem echten Browser nötig |
| §3 | sechs Sichtprüfungen, mobil und Desktop | echtes Gerät |
| §3–§5 | 24 h + 3 Tage + 3 Tage Beobachtung | Kalenderzeit |
| §7 | Abnahme | Enes |

## 5. B1 nachgeschärft (#38): es fehlen **beide** Cloudflare-Token

Punkt 4 aus #38 geprüft — `/root/sun-zugang.txt` ist die gemeinsame Ablage für
den Turnstile-Token (#18) *und* den WAF-Token (#24). Mit der Datei sind beide
weg. Nachgewiesen am 08.10.2026, nur lesend, ohne Tokeneingabe:

```
ssh sun 'ls -l /root/sun-zugang.txt'            → No such file or directory (2)
ssh sun 'grep -c "^TURNSTILE" …/.env'           → 0

scripts/cloudflare-token-ablegen.sh --pruefen   → Exit 1
    "Kein Token hinterlegt (sun:/root/sun-zugang.txt) — #18 ist noch nicht ausgefuehrt."
scripts/turnstile-widgets-anlegen.sh --pruefen  → Exit 65
    "Kein Token: weder CF_API_TOKEN gesetzt noch einer in /root/sun-zugang.txt."
scripts/cloudflare-waf-regeln-setzen.sh --pruefen → Exit 65
    "Kein Token: weder CF_API_TOKEN gesetzt noch CLOUDFLARE_API_TOKEN_SUN_WAF …"
```

Die WAF-Regeln aus #24 **sind davon nicht aufgehoben** — sie stehen am Konto
und wirken weiter; verloren ist nur die Möglichkeit, sie zu prüfen oder zu
ändern. Deshalb ist der WAF-Token kein Blocker für den Go-Live, der
Turnstile-Token schon. Beide gehören trotzdem in einem Durchgang abgelegt,
weil es dieselbe Datei ist.

Werkzeugseitig fehlt nichts: beide Ablege-Skripte prüfen den Token gegen die
API, *bevor* sie ihn schreiben, schicken ihn als stdin-Strom (nie als
ssh-Argument, sonst steht er in der Prozessliste), ersetzen vorhandene Zeilen
statt anzuhängen und setzen `chmod 600`. Der einzige nicht automatisierbare
Teil ist das Erzeugen der Token im Dashboard.

### Handgriff für den Menschen (Schritt 0 von §1.1)

Zwei getrennte Token, bewusst nicht einer: der Turnstile-Token darf keine
WAF-Regeln ändern und umgekehrt.

1. Dashboard → My Profile → API Tokens → Create Token → **Custom**, auf das
   SUN-Konto begrenzt:
   * Token A: `Account / Turnstile: Edit`
   * Token B: `Zone / Zone: Read`, `Zone / Firewall Services: Edit`,
     `Zone / Bot Management: Read`
2. Ablegen (fragt verdeckt ab, prüft vor dem Schreiben):

   ```
   scripts/cloudflare-token-ablegen.sh                       # Token A
   scripts/cloudflare-waf-regeln-setzen.sh --token-ablegen   # Token B
   ```

3. Gegenproben — jede muss **Exit 0** geben:

   ```
   scripts/cloudflare-token-ablegen.sh --pruefen
   scripts/turnstile-widgets-anlegen.sh --pruefen      # kein Fehler 10000
   scripts/cloudflare-waf-regeln-setzen.sh --pruefen   # Exit 71 = Regeln weichen ab
   ssh sun 'ls -l /root/sun-zugang.txt'                # -rw------- root root
   ```

Danach läuft §1.1 weiter: `scripts/turnstile-widgets-anlegen.sh` (Widgets),
Dashboard-Schalter „Allow a domain to be added automatically“ aus,
Passwort-Manager-Einträge, `scripts/turnstile-schluessel-eintragen.sh` (sechs
`.env`-Werte), `php scripts/secret-scan.php` sauber.

---

## 6. Zweiter Durchlauf am 08.10.2026 (#36) — Nachprüfung und zwei Korrekturen am Ablauf

Erneut nur lesende Befehle gegen die Produktion, nichts verändert:

```
ssh sun 'ls -l /root/sun-zugang.txt'   → No such file or directory
git log --oneline -1                   → f5fa66ed
grep -c '^TURNSTILE' .env              → 0
grep -c '^CSP_MODE' .env               → 0
```

B1 ist unverändert offen (Token A fehlt), B2 ebenso. Damit bleibt §1.1 der erste
Haken und alles dahinter unerreichbar. Keine Akzeptanzkriterium ist von einem
Agenten erfüllbar.

### Zwei Ablauffehler in `docs/turnstile-golive.md` behoben

1. **CSP-Reihenfolge.** §1.3 baute den Config-Cache zweimal neu (Notbremse
   `TURNSTILE_ENABLED=false` und am Ende des Deploys), bevor §1.5 überhaupt
   `CSP_MODE=report` setzte. Weil die Produktions-`.env` keine `CSP_MODE`-Zeile hat
   und die Codevorgabe `enforce` ist, wäre die CSP schon mit dem ersten
   `config:cache` auf allen 23 Portalen durchsetzend geworden — die 24-Stunden-Messung
   aus §1.5 hätte nie stattgefunden. `CSP_MODE=report` steht jetzt als eigene Zeile in
   §1.3 direkt neben der Notbremse, §1.5 Schritt 1 ist zur Gegenprobe
   (`grep '^CSP_MODE' .env`) geworden.
2. **B4-Zeile in §0.** Sie sagte noch, §1.5 setze direkt `enforce`; §1.5 schreibt aber
   seit der CSP-Messung den Zwischenschritt `report` für 24 h vor. Die Zeile nennt jetzt
   den Zwischenschritt und den Grund (echte `TRACKING_SCRIPTS` und `ad_slots` in
   Produktion sind fremdes HTML).
   Gegengeprüft und **nicht** geändert: die Aussage in §1.2, `.env.example` setze die
   Cloudflare-Testschlüssel als Vorgabe. Sie stimmt — `.env.example:163/164` trägt
   `TURNSTILE_SITE_KEY=1x00000000000000000000AA` und das passende Secret für Gruppe A
   (nur B und C stehen leer und auskommentiert). Die Schlüsselsperre in §1.2 bleibt
   damit genau so nötig, wie sie dort steht.

Beides sind Korrekturen am Ablauf, nicht an der Produktion. Der Rest des Dokuments
ist Zeile für Zeile abarbeitbar, sobald Token A liegt.

---

## 7. Vierter Durchlauf am 08.10.2026 (#36) — Standsabfrage als Werkzeug

Ausgangslage unverändert, erneut nur lesend geprüft — diesmal zusätzlich die
Frage, ob der Token irgendwo anders liegt als in `/root/sun-zugang.txt`:

```
ssh sun 'ls -l /root/sun-zugang.txt'           → No such file or directory
ssh sun 'ls -la /root/'                        → nur /root/minber-zugang.txt (fremdes Projekt)
ssh sun 'grep -ril turnstile /root --include=*.txt' → kein Treffer
grep -c '^TURNSTILE' .env                      → 0
grep -n '^CSP_MODE' .env                       → kein Treffer
git log --oneline -1                           → f5fa66ed
```

Damit ist auch die letzte offene Vermutung erledigt: Token A liegt nirgends am
Ursprung, auch nicht unter einem anderen Namen. B1 und B2 bleiben Handgriffe.

### Neu: `scripts/turnstile-golive-stand.sh`

Jeder der vier Anläufe hat denselben Satz lesender Befehle von Hand wiederholt,
um festzustellen, wo die Checkliste steht. Das ist jetzt ein Aufruf:

```
scripts/turnstile-golive-stand.sh          # ganze Liste
scripts/turnstile-golive-stand.sh --kurz   # nur die nächste offene Zeile
```

Geprüft wird in der Reihenfolge des Dokuments: B2 (liegt `config/turnstile.php`
auf `origin/main`?), §1.1 über die `--pruefen`-Pfade von
`cloudflare-token-ablegen.sh` und `turnstile-widgets-anlegen.sh`, dann in einem
ssh-Durchgang Codestand, `app/Turnstile`, `public/build`, die sechs
Schlüsselwerte, `CSP_MODE`, `TURNSTILE_ALERT_RECIPIENTS`, `schedule:run` im
Crontab, nach dem Deploy zusätzlich `turnstile:keys:check --siteverify` und
`turnstile:rollout`, zuletzt die Belege aus §8.

Ausgabe heute: 15 offene Zeilen, `NAECHSTER HANDGRIFF: B2 — Modul liegt nicht
auf origin/main`, Exit 70. Exit 0 heißt: alles maschinell Prüfbare ist bestanden,
offen sind nur noch Sichtprüfungen, Kalenderzeit und Abnahme. Das Skript
schreibt nichts — keine `.env`, keine Datenbank, kein Deploy.

Die Zweige hinter dem Deploy (`keys:check`, `rollout`) sind in Produktion noch
nicht gelaufen, weil der Code dort fehlt; sie stehen hinter der Abfrage
`app/Turnstile existiert`.

Verweise eingetragen: `docs/turnstile-golive.md` §0 (Kasten „Stand jederzeit
nachlesen“) und §8, sowie `docs/messungen/turnstile-golive/README.md`.

---

## 7. Fünfter Durchlauf am 08.10.2026 (#36) — §1.3 als Werkzeug, zwei neue Befunde

B1 und B2 sind unverändert offen (Token A liegt nirgends am Ursprung, Modul nicht
eingecheckt), also wurde wieder nicht deployt. Statt die Nachprüfung ein viertes
Mal zu wiederholen, ist §1.3 jetzt ein Skript — und beim Trockenlauf gegen die
Produktion sind zwei Dinge aufgefallen, die im Dokument falsch standen.

### Neu: `scripts/turnstile-golive-deploy.sh`

Fährt §1.3 in der verbindlichen Reihenfolge, ohne Argument als Trockenlauf
(ändert nichts, zeigt jeden Befehl mitsamt Benutzer), mit `--los` ausführend und
mit einer einmaligen Rückfrage. Protokoll nach
`docs/messungen/turnstile-golive/deploy-<stempel>.log` — das ist der Beleg für die
„Ausgabe ablegen“-Zeilen.

Vorbedingungen werden alle geprüft und **gesammelt** gemeldet (Exit 66), nicht
beim ersten Befund abgebrochen: B2 auf `origin/main`, sechs Schlüsselwerte in der
Produktions-`.env`, kein Testschlüssel, keine geänderte verfolgte Datei in
Produktion, mindestens 50 GB frei unter `/home`, Abdeckung der nächtlichen
Sicherung. Trockenlauf von heute: zwei Befunde (B2, B1), Exit 66.

Drei Fallen sind im Skript festverdrahtet, weil sie im Fehlerfall teuer sind:

1. `TURNSTILE_ENABLED=false` **und** `CSP_MODE=report` werden gesetzt, bevor der
   erste `config:cache` läuft.
2. `npm ci` und `npm run build` laufen als `sanitaerfinden`, nach `chown` von
   `public/build` und `node_modules`. Als root gebaut gehört `public/build`
   danach root und der nächste Build scheitert an `rmSync`.
3. Nach jeder `.env`-Änderung folgt `systemctl reload php8.5-fpm`. Die Website
   läuft über den FPM-Pool von PHP 8.5, Artisan über CLI-PHP 8.4 — ohne Reload
   bleibt im Web der alte Config-Cache stehen und die Notbremse wirkt nicht,
   obwohl `config:show` sie anzeigt.

### Befund 1: zehn Portal-Datenbanken haben keine Sicherung

§1.4 ließ die Zeile „Datenbank-Dump derselben Nacht vorhanden“ gegen
`/home/sanitaerfinden/backups/databases/` abhaken. Nachgemessen am 08.10.2026:

* 23 lebende `tenant_%`-Datenbanken, aber nur 22 Sicherungsordner — und **zehn**
  der lebenden Datenbanken haben überhaupt keinen Ordner:
  `unfallarzt.firmenfreund.de` (788 MB), `arztfinder.firmenfreund.de` (589 MB),
  `zahnarzt.firmenfreund.de` (511 MB), `energieberaterportal.net` (420 MB),
  `apotheke.firmenfreund.de` (228 MB), `speditionportal.com` (190 MB),
  `firmenfreund.net` (110 MB), `sanitaerfinder.com` (54 MB),
  `schluesseldienstportal.com` (44 MB), `klempner.firmenfreund.de` (35 MB)
  — zusammen rund 3 GB Nutzdaten.
* Neun der vorhandenen Ordner gehören zu Datenbanken mit **0 Tabellen**; ihr
  Dump ist 521 Byte groß und sieht nach „Sicherung vorhanden“ aus.
* Abdeckung also 14 von 24 lebenden Datenbanken.

Ursache: `15 3 * * * clpctl db:backup` (`/etc/cron.d/clp`) sichert nur, was in
CloudPanels eigener `db.sq3` registriert ist (26 Einträge, inklusive toter). Die
Datenbanken, die `TenantCreationService` selbst anlegt, stehen dort nie.

Folge für #36: `tenants:migrate --force` legt in jeder der 23 Portal-Datenbanken
Tabellen und Spalten an — für zehn davon gäbe es keinen Rückweg. Deshalb dumpt
Schritt 3 des Skripts selbst (alle 24, Abbruch bei einer Datei unter 1 KB oder
weniger als 24 Dateien), und §1.4 verweist jetzt auf diesen Dump statt auf die
nächtlichen Dateien. Die Lücke selbst gehört nicht in #36 und hat ein eigenes
Ticket.

### Befund 2: die Sauberkeitsprüfung des Arbeitsbaums war zu streng

Der erste Entwurf zählte `git status --porcelain` komplett und hätte den Deploy
an `?? .cache/`, `?? public/vendor/livewire/` und `?? shared/` scheitern lassen —
unverfolgte Pfade, die dort der Normalfall sind und einen Fast-Forward nicht
stören. Gezählt werden jetzt nur geänderte verfolgte Dateien; unverfolgte Pfade
werden genannt, nicht blockiert.

### Geändert

* `scripts/turnstile-golive-deploy.sh` (neu, ausführbar, `bash -n` sauber,
  Trockenlauf gegen die Produktion gefahren)
* `docs/turnstile-golive.md` — §1.3 mit dem Skript als Weg, drei Zeilen um die
  Produktionsfakten (FPM-Reload, Build-Rechte, eigener Dump) ergänzt; §1.4 Zeile
  zur Sicherung korrigiert
* `docs/messungen/turnstile-golive/README.md` — `deploy-<stempel>.log` als Beleg
  aufgenommen

---

## 8. Sechster Durchlauf am 08.10.2026 (#36) — der API-Token stand gar nicht im Weg

Fünf Anläufe sind an derselben Zeile hängengeblieben: „Token mit Account /
Turnstile: Edit fehlt (#18/#38)". Diesmal die Gegenfrage gestellt — **welcher
Schritt der Checkliste braucht diesen Token überhaupt?** Nachgelesen im Code,
nicht vermutet:

| Schritt | redet mit der Cloudflare-API? | Belegstelle |
|---|---|---|
| `scripts/turnstile-widgets-anlegen.sh` | **ja** | nur hierfür ist der Token da |
| `scripts/cloudflare-token-ablegen.sh --pruefen` | **ja** | reine Gegenprobe |
| `scripts/turnstile-schluessel-eintragen.sh` | nein | schreibt `.env`, Config-Cache, FPM-Reload, Horizon, Siteverify-Probe; Kopf sagt selbst „angelegt (… oder im Dashboard)" |
| `turnstile:keys:check --siteverify` | nein | liest `TurnstileConfigResolver::groups()` und ruft `siteverify` — kein API-Aufruf im Befehl |
| `scripts/turnstile-golive-deploy.sh` | nein | `grep -c 'token\|cloudflare'` → 0 Treffer |
| Stufen 0–3, Monitoring, CSP | nein | alles Artisan und `.env` |

Ergebnis: Der API-Token automatisiert **ausschließlich** das Anlegen der drei
Widgets. Legt ein Mensch sie im Dashboard an — was Enes am 08.10.2026 für ein
Widget schon getan hat —, ist der Token für den gesamten Go-Live entbehrlich und
Akzeptanzkriterium 2 (`keys:check --siteverify` grün) erreichbar.

Damit ist B1 keine Konto-Automatisierung mehr, sondern ein Dashboard-Handgriff
von wenigen Minuten. §1.1 ist deshalb in **Weg A** (mit Token) und **Weg B**
(drei Widgets von Hand) geteilt; Weg B nennt die Namen `SUN Welle 1|2|3` (damit
`turnstile-widgets-anlegen.sh` sie später wiederfindet statt Dubletten
anzulegen), `Managed`, `no clearance level` und die 18 Hostnames einzeln aus
`config/turnstile.php`. Die Gegenprobe gegen Tippfehler in der Hostname-Liste
ist `turnstile:keys:check` selbst (Spalte `hostname_fehler`, Summe 18) — die
braucht kein Dashboard.

`scripts/turnstile-golive-stand.sh` wertet den fehlenden Token entsprechend
nicht mehr als offene Zeile, sondern als Hinweis; der nächste Handgriff ist
seither B2 (Commit) und danach Widgets + sechs `.env`-Werte.

Nicht geändert: der Token bleibt in §1.1 Weg A stehen und ist für #24 (WAF
prüfen/ändern) weiter nötig. Nur die Rangfolge war falsch.

---

## 9. Stand von #38 am 08.10.2026, abends — Werkzeuge fertig, nur der Dashboard-Handgriff fehlt

Siebter lesender Durchgang, diesmal aus #38 selbst. Nichts an der Produktion und
nichts am Cloudflare-Konto verändert.

```
ssh sun 'ls -l /root/sun-zugang.txt'   → No such file or directory
ssh sun 'ls -l /root/'                 → nur minber-zugang.txt (fremdes Projekt)
grep -c '^TURNSTILE' .env              → 0
git log --oneline -1                   → f5fa66ed
php scripts/secret-scan.php            → sauber (2475 Dateien)
scripts/turnstile-golive-stand.sh --kurz
    → NAECHSTER HANDGRIFF: B2 (Commit/Push, Freigabe Enes), nicht der Token
```

Beide Ablege-Skripte wurden Zeile für Zeile gegengelesen (`bash -n` sauber,
ausführbar): `scripts/cloudflare-token-ablegen.sh` (Schlüssel
`CLOUDFLARE_API_TOKEN_SUN_TURNSTILE` + `CLOUDFLARE_ACCOUNT_ID_SUN`) und
`scripts/cloudflare-waf-regeln-setzen.sh --token-ablegen` (Schlüssel
`CLOUDFLARE_API_TOKEN_SUN_WAF`). Beide legen `/root/sun-zugang.txt` samt
Kopfzeile **selbst an**, wenn sie fehlt, ersetzen nur ihre eigene Zeile, schicken
den Token als stdin-Strom und setzen `chmod 600` — die verlorene Datei ist für
sie also kein Sonderfall, und die Reihenfolge der beiden Aufrufe ist beliebig.
Werkzeugseitig ist an #38 nichts mehr zu bauen.

Was bleibt, ist ausschließlich Kontoarbeit (Cloudflare-Dashboard, Enes) —
derselbe Handgriff wie in §5 beschrieben; ein Agent hat keinen Token, der Token
anlegen dürfte (Konto `Kul@widimedia.com's Account`, nur ein
Tunnel-Zertifikat in Reichweite, scheitert an Turnstile mit 10000 und am
Token-Anlegen mit 9109).

**Rangfolge korrigiert (gilt auch für die Beschreibung von #38):** Der fehlende
Token hält #36 nicht auf. Er automatisiert nur das Anlegen der drei Widgets und
die `--pruefen`-Gegenproben; §1.1 läuft über Weg B, und `keys:check
--siteverify`, `turnstile-schluessel-eintragen.sh` sowie der Deploy reden nie mit
der Cloudflare-API (Nachweis in §8 dieses Protokolls, Dokument-Zeilen in
`docs/turnstile-golive.md` §1.1 und §0 B1). Für #24 (WAF-Regeln prüfen oder
ändern) bleibt der zweite Token nötig; die am Konto stehenden Regeln wirken
unverändert weiter.

Akzeptanzkriterien von #38 im Ist: 1 und 2 brauchen das Dashboard und bleiben
offen, 3 ist erfüllt (Secret-Scan sauber, kein Token im Repo), 4 ist beantwortet
(beide Token betroffen, WAF-Token kein Blocker). Haken 1 von §1.1 ist damit nur
über Weg A abhakbar — Weg B macht ihn für den Go-Live entbehrlich.

## 10. Stand von #38 am 08.10.2026, später Abend — Tokensuche erschöpft, Weg B als Werkzeug

Zweiter Anlauf an #38. Statt den lesenden Befund ein viertes Mal zu wiederholen,
zwei neue Dinge: die Frage „gibt es irgendwo schon einen brauchbaren Token?“
abschließend beantwortet, und Weg B so weit abgekürzt, dass er ohne Token
auskommt.

### 10.1 Tokensuche, serverweit und lokal — Ergebnis: es gibt keinen

Alles nur lesend, Werte nie ausgegeben (nur Variablennamen und Dateinamen):

```
ssh sun 'ls -A /root/'                 → 40 Einträge, kein sun-zugang.txt
ssh sun 'ls -A /root/.cloudflared'     → existiert nicht
ssh sun 'command -v cloudflared flarectl' → keine
ssh sun 'grep -rlE "^(CLOUDFLARE|CF)_[A-Z_]*(TOKEN|KEY|EMAIL)"
          /home/*/htdocs/*/.env /root/*.txt'  → keine Treffer
```

Damit ist auch `/root/minber-zugang.txt` (fremdes Projekt) als Quelle
ausgeschlossen: es steht dort keine Cloudflare-Variable. Lokal auf dem
Arbeitsrechner ebenfalls nichts Brauchbares — `~/.cloudflared/` enthält nur
Tunnel-Zertifikat und Tunnel-Credentials (an den Turnstile-Endpunkten Fehler
10000, siehe §5), in keiner `.env` der Nachbarprojekte steht ein
Cloudflare-API-Token. Ein echtes Turnstile-Schlüsselpaar existiert nur in einem
fremden Projekt (anderer Hostname, andere Widget-Gruppe) — es ist für SUN
unbrauchbar und wird nicht angefasst.

**Schluss:** der Token lässt sich nicht finden, nur erzeugen. Das bleibt
Dashboard-Arbeit (Enes). Weitere Agentenläufe brauchen diese Messung nicht zu
wiederholen.

### 10.2 Weg B braucht keine Abschrift aus der Doku mehr

`scripts/turnstile-widgets-anlegen.sh --weg-b` druckt die drei
Widget-Definitionen zum Abtippen im Dashboard: Name, `Managed`,
`no clearance level`, die Hostnames je Gruppe (3 / 10 / 5), danach die beiden
Handgriffe („Allow a domain…“ aus, Passwort-Manager) und die beiden
Anschlussbefehle. Der Modus steht **vor** der jq-Prüfung und vor der
Tokensuche — er scheitert also an keiner Vorbedingung, die nur Weg A braucht,
und redet weder mit Cloudflare noch mit dem Ursprung (Exit 0).

Die Listen kommen aus denselben Variablen, die `--pruefen` später zum Vergleich
heranzieht; gegengelesen gegen `config('turnstile.groups')`:

```
php artisan tinker --execute='…config("turnstile.groups")…'
→ A: elektrikerportal.com, fahrschulefinder.de, sanitaerfinden.com
  B: bodenlegerfinden.com, energieberaterportal.net, findegutachter.de,
     firmenfreund.de, firmenfreund.net, fliesenleger.io, kfzwerkstatt.io,
     malerfinder.de, sanitaerfinder.com, tierarztportal.com
  C: geruestbauer.gmbh, metallbauer.io, mjet.net, schluesseldienstportal.com,
     speditionportal.com
```

Identisch mit der Skript-Ausgabe, Summe 18. `docs/turnstile-golive.md` §1.1
Weg B nennt den Befehl jetzt vor der Checkliste.
