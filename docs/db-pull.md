# sun:db:pull — Live-Datenbanken lokal spiegeln

Stand 14.10.2026 (#1, Server-Befund und Zugang aus #2). Dieses Dokument ist der verbindliche Rahmen fuer das
Vorhaben: Was der Befehl tut, was er garantiert und welche Teile in welchem
Ticket entstehen (#2 Server-Vorbereitung, #3 Command, #4 Tabellenfilter,
#5 Anonymisierung, #6 Storage-Sync). Abweichungen gehoeren hier eingetragen,
nicht nur in den Code.

## Ziel

Ein Aufruf holt die zentrale Datenbank und alle Tenant-Datenbanken vom
Produktionsserver auf den Laptop, anonymisiert die Personendaten und
hinterlaesst ein Protokoll:

```bash
php artisan sun:db:pull
```

Erwartete Laufzeit: wenige Minuten (24 Datenbanken, rund 9,3 GB Nutzdaten,
komprimiert etwa 800 MB — Groessenordnung aus `docs/datenbanksicherung.md`).

## Nicht-Ziel

* Kein Schreibzugriff auf Live. Der Befehl kann die Produktion nicht
  veraendern — das ist die harte Anforderung, nicht eine Vorsichtsmassnahme.
* Kein Ersatz fuer die naechtliche Sicherung
  (`scripts/sun-db-sicherung.sh`, siehe `docs/datenbanksicherung.md`). Der
  Pull liest live, er legt auf Live nichts ab.
* Keine fremden Datenbanken. Auf dem Server liegen neben `sun` und
  `tenant_<uuid>` auch `analytics`, `funnels`, `leads`, `perigee`,
  `kasernencheck`, `minbercentral`, `minberprod`, `minberstaging`. Die
  gehoeren **nicht** zur Anwendung und bleiben in jedem Zuschnitt aussen vor.

## Sicherheitsmodell

Drei voneinander unabhaengige Sperren, damit ein Tippfehler lokal nichts auf
Live anrichten kann:

1. **Read-only-MySQL-Benutzer** (#2, eingerichtet): Benutzer
   `sun_sync`@`localhost`, Rechte **nur** `SELECT, SHOW VIEW` auf `sun`.* und
   `` `tenant\_%`.* ``. Kein `LOCK TABLES` (liesse sich zum Blockieren der
   Portale missbrauchen), kein `TRIGGER` und kein `EVENT` (beide erlauben
   `CREATE`/`DROP` und waeren damit Schreibrechte), kein `PROCESS` (fuer
   `--single-transaction` nicht noetig, solange `--no-tablespaces` gesetzt
   ist). Deshalb dumpt der Lauf mit `--single-transaction --skip-lock-tables`.
   Der Anwendungsbenutzer `sunny` aus der Live-`.env` wird fuer den Pull
   **nicht** verwendet. Belegte Abnahme siehe Abschnitt „Server-Befund".
2. **Richtungssperre im Command** (#3): der Lauf darf nur lokal starten.
   Abbruch mit Exit 64, wenn `app()->environment('production')` oder wenn das
   lokale Ziel (`DB_HOST`) nicht auf `127.0.0.1`/`localhost` zeigt. Es gibt
   keine Option, die diese Pruefung aufhebt.
3. **Nur lesende Kommandos ueber SSH**: ausgefuehrt wird auf Live
   ausschliesslich `mariadb -N -B -e "show databases like ..."` und
   `mariadb-dump`. Kein `scp` nach Live, kein `artisan` auf Live, keine
   Zwischendatei im Live-Dateisystem.

Zugangsdaten gehen nie ueber `argv` (sonst stehen sie in der Prozessliste der
Produktion), sondern ueber `MYSQL_PWD` in der Umgebung des Remote-Aufrufs —
gleiche Vorgehensweise wie in `scripts/sun-db-sicherung.sh`.

## Server-Befund (#2, erhoben 14.10.2026)

Alles unten ist auf `88.198.64.145` gemessen, nicht angenommen. Wer die Werte
nachziehen will: die Befehle stehen jeweils dabei.

### Datenbank-Namensschema

| | Wert | Quelle |
|---|---|---|
| Zentrale Datenbank | `sun` | `DB_DATABASE` der Live-`.env`, Verbindung `central` |
| Tenant-Praefix | `tenant_` | `config/tenancy.php` → `database.prefix` |
| Tenant-Suffix | *(leer)* | `config/tenancy.php` → `database.suffix` |
| Tenant-Datenbankname | `tenant_<uuid>`, z.B. `tenant_7504ca94-24de-47ac-b062-10ca282a51a1` | Praefix + Tenant-ID |
| Bestand am 14.10.2026 | 1 zentrale + 23 Tenant-Datenbanken = 24 | `show databases like 'tenant\_%'` |
| `sun`.`tenants` | 23 Zeilen — deckt sich mit den 23 Datenbanken | `select count(*) from sun.tenants` |

Das Muster im Ticket (`sun\_%`) trifft **nichts**: die zentrale Datenbank
heisst `sun` ohne Unterstrich, die Tenants beginnen mit `tenant_`. Die Rechte
brauchen deshalb zwei `GRANT`-Zeilen.

Fremde Datenbanken auf derselben Instanz: `analytics`, `funnels`, `leads`,
`perigee`, `kasernencheck`, `minbercentral`, `minberprod`, `minberstaging`.
Sie gehoeren nicht zur Anwendung. Der Benutzer `sun_sync` sieht sie in
`show databases` gar nicht, und ein `select` darauf scheitert mit
`ERROR 1142` — der Zuschnitt haengt also nicht an einem korrekten Filter im
Command, sondern an den Rechten.

### Storage-Engine

Alle 24 Datenbanken, 1 189 Basistabellen, **ausschliesslich InnoDB** — keine
einzige MyISAM- oder Aria-Tabelle. Damit ist `--single-transaction` wirklich
konsistent und braucht kein `LOCK TABLES`.

```sql
select table_schema, engine, count(*) from information_schema.tables
 where (table_schema = 'sun' or table_schema like 'tenant\_%')
   and table_type = 'BASE TABLE' group by 1,2;
```

Ebenfalls geprueft, jeweils **0** in allen 24 Datenbanken: Views, Trigger,
Events, Stored Routines. `--routines --triggers` kostet deshalb nur 86 Byte
Kommentar je Dump und funktioniert trotz fehlender `TRIGGER`-/`EVENT`-Rechte
(MariaDB zeigt bei `SHOW TRIGGERS` nur, was man sehen darf, und bricht nicht
ab). Das ist nachgemessen — beide Varianten liefen fehlerfrei.

### Versionen und Werkzeuge auf dem Server

| | Wert |
|---|---|
| Betriebssystem | Ubuntu 24.04.4 LTS |
| Server | MariaDB 11.4.10 |
| `mariadb-dump` | 11.4.10-MariaDB, Client 10.19 (`mysqldump` ist nur ein veralteter Aliasname und warnt auf stderr) |
| `gzip` | 1.12, `/usr/bin/gzip` — `pigz` ist **nicht** installiert |
| `rsync` | `/usr/bin/rsync` (vorhanden, fuer #6) |

Flag-Folgen aus MariaDB 11.4:

* `--no-tablespaces` existiert (geprueft in `mariadb-dump --help`) → `PROCESS`
  ist nicht noetig.
* `--set-gtid-purged` gibt es **nicht**. Niemals setzen.
* `--column-statistics=0` gibt es **nicht** (das ist ein MySQL-8-Client-Flag).
* In Befehlen und Protokollen `mariadb` / `mariadb-dump` verwenden. Die
  `mysql*`-Namen funktionieren noch, schreiben aber bei jedem Aufruf eine
  Deprecation-Warnung nach stderr — das sieht in #3 wie ein Fehler aus.

### Durchsatz (gemessen)

Groesste Tenant-Datenbank, 1 673 MB Nutzdaten, als Strom ueber SSH auf den
Laptop, ohne Zwischendatei:

```bash
ssh sun-sync "MYSQL_PWD=… nice -n 10 ionice -c2 -n7 mariadb-dump -u sun_sync \
  --single-transaction --quick --no-tablespaces --routines --triggers \
  tenant_e917ddeb-… | gzip -1" > probe.gz
```

**7 Sekunden**, 147 MB komprimiert (Faktor ~11). Hochgerechnet auf 9,6 GB
Gesamtbestand: rund 850 MB Uebertragung, gut eine Minute reine Dump-Zeit. Der
lokale Import ist also der langsamere Teil, nicht die Leitung — `--parallel`
in #3 lohnt sich an der Importseite, nicht am Uplink.

Grobe Groessen: `sun` 13 MB, Tenants zusammen 9 582 MB, groesste vier
Tenants 1 673 / 799 / 772 / 764 MB.

### Lokale Gegenseite: MariaDB 11.4 → MySQL 9.3

Das ist der Punkt, an dem #3 haette scheitern koennen, darum ist er
nachgemessen und nicht angenommen. Live laeuft **MariaDB 11.4.10**, lokal
laeuft **MySQL 9.3.0** (Homebrew, Client und Server unter
`/opt/homebrew/opt/mysql/bin/`). Das sind verschiedene Produkte.

| | Live | Lokal |
|---|---|---|
| Server | MariaDB 11.4.10 | MySQL 9.3.0 |
| `collation_server` | — | `utf8mb4_0900_ai_ci` |
| Kollation der Tabellen | `utf8mb4_unicode_ci` (einheitlich, alle 24 DBs) | muss beim `create database` mitgegeben werden |

`utf8mb4_unicode_ci` kennt MySQL 9.3 ebenfalls, deshalb geht es glatt. MariaDB
11.4 haette auch `utf8mb4_uca1400_*` liefern koennen — das kennt MySQL
**nicht**. Tut es heute nicht, aber falls irgendwann ein Import an einer
unbekannten Kollation scheitert, liegt es hier.

Lokal darum immer explizit anlegen, nicht auf die Servervorgabe verlassen:

```sql
create database if not exists `<name>`
  character set utf8mb4 collate utf8mb4_unicode_ci;
```

Zwei echte Durchlaeufe am 14.10.2026, jeweils als reiner Strom ohne
Zwischendatei (`mariadb-dump … | gzip -1` → `gunzip | mysql <lokal>`):

| Datenbank | Dauer | Tabellen | Gegenprobe |
|---|---|---|---|
| `sun` (zentral) | 2 s | 89 von 89 | `users` 644 = 644, `tenants` 23 = 23 |
| `tenant_0693bded-…` | 3 s | 50 von 50 | `companies` 1 905 = 1 905, `reviews` 7 304 = 7 304, `cities` 14 064 = 14 064 |

Keine Warnung, kein Fehler, keine verlorene Tabelle. Der MariaDB-Dump
enthaelt Zeilen wie `/*M!100616 SET NOTE_VERBOSITY=… */` — das ist ein
MariaDB-eigener bedingter Kommentar, den MySQL als gewoehnlichen Kommentar
ueberliest. Damit ist die Gegenprobe aus den Akzeptanzkriterien von #3
(`SELECT COUNT(*)` beidseitig) bereits an zwei Datenbanken belegt; #3 muss sie
nur noch fuer den vollen Lauf wiederholen.

### Serverpfade

| | Pfad |
|---|---|
| Anwendung | `/home/sanitaerfinden/htdocs/sanitaerfinden.dev` |
| `storage/` (Basis, `SYNC_REMOTE_STORAGE_PATH`) | `/home/sanitaerfinden/htdocs/sanitaerfinden.dev/storage` |
| zentrales `storage/app/public` | `…/storage/app/public` — **nahezu leer (8 KB)** |
| Tenant-Storage | `…/storage/tenant<uuid>/app/public` — **ohne Unterstrich** nach `tenant` |

Das ist die Falle fuer #6: `suffix_base` in `config/tenancy.php` ist `tenant`
(nicht `tenant_`), die Verzeichnisse heissen also `tenant7504ca94-…`, die
Datenbanken dagegen `tenant_7504ca94-…`. Beide Namen aus der Tenancy-Config
ableiten, nie voneinander. Die Nutzdaten liegen praktisch komplett im
Tenant-Storage; das zentrale `storage/app/public` zu spiegeln bringt nichts.

**Nicht gemessen: das Byte-Volumen des Tenant-Storage.** Ein rekursives
`du -sh` ueber die 28 Verzeichnisse lief auf dem RAID1 aus rotierenden Platten
in die Zeitgrenze und wurde abgebrochen — genau die Last, vor der
`docs/datenbanksicherung.md` warnt. Fuer #2 ist die Zahl nicht noetig; #6
sollte sie nicht als bekannt voraussetzen, sondern einmal gezielt mit
`nice -n 10 ionice -c2 -n7 du -sh …` je Tenant erheben (oder sich den ersten
`rsync --dry-run --stats` als Schaetzung nehmen, das kostet weniger IO).

Im `storage/`-Verzeichnis liegen ausserdem Ordner von Tenants, die es nicht
mehr gibt (z.B. `tenant14d85d01-…`, `tenant3f27df88-…`, und ein
`tenant8bdf0712-…x`). #6 darf also nicht ueber die Verzeichnisse iterieren,
sondern nur ueber die lebenden Tenants.

### SSH-Zugang

| | Wert |
|---|---|
| Schluessel | `~/.ssh/sun_sync`, ed25519, `-a 100`, **mit Passphrase** |
| Fingerprint | `SHA256:KQVdbUCbE5T/+06GFFn3MPt8Us+dfMFaTBu3cdwRIlM` |
| Hinterlegt in | `/home/sanitaerfinden/.ssh/authorized_keys` (0600) |
| SSH-Benutzer | `sanitaerfinden` — der Benutzer, als dem die Anwendung laeuft; **nicht** `root` |
| Port | 22, in `ufw` offen (`22/tcp ALLOW Anywhere`); keine `AllowUsers`-Einschraenkung in `sshd_config` |
| Laptop-Eintrag | `Host sun-sync` in `~/.ssh/config`, mit `IdentitiesOnly yes` |

Die Passphrase liegt im macOS-Schluesselbund (`ssh-add --apple-use-keychain
~/.ssh/sun_sync`, plus `UseKeychain yes`/`AddKeysToAgent yes` im
`config`-Eintrag). Darum laeuft `ssh sun-sync` passwortfrei, obwohl der
Schluessel eine Passphrase hat — der Agent haelt ihn, die Datei allein ist
ohne Passphrase nutzlos. Faellt der Agent aus, fragt der Lauf einmal nach;
das ist gewollt und besser als ein Schluessel ohne Passphrase.

Der bestehende Eintrag `Host sun` (Benutzer `root`) bleibt unberuehrt. `sun`
ist fuer Serverarbeit, `sun-sync` ausschliesslich fuer den Pull.

**Keine `command=`-Restriktion in `authorized_keys`.** Das Ticket laesst sie
offen; sie wuerde hier aber mehr kaputt machen als sichern, denn der Zugang
braucht drei verschiedene Kommandos (`mariadb` fuer die Tenant-Liste,
`mariadb-dump` fuer die Stroeme, `rsync` fuer #6). Ein `command=` auf genau
eines davon bricht die anderen zwei; ein Wrapper, der alle drei durchlaesst,
beschraenkt nichts mehr. Die eigentliche Schranke sind die MySQL-Rechte: der
Schluessel allein kommt an keine Datenbank. Wer spaeter trotzdem haerten will,
legt ein Wrapper-Script auf dem Server ab, das nur diese drei Kommandos mit
festen Flags annimmt, und traegt es hier ein.

### Abnahme der Rechte (nachgefuehrt)

```
GRANT USAGE ON *.* TO `sun_sync`@`localhost` IDENTIFIED BY PASSWORD '…'
GRANT SELECT, SHOW VIEW ON `sun`.* TO `sun_sync`@`localhost`
GRANT SELECT, SHOW VIEW ON `tenant\_%`.* TO `sun_sync`@`localhost`
```

`SHOW GRANTS` stellt den Backslash als `\\_` dar — das ist nur die Anzeige.
Gespeichert ist ein einfacher Backslash; nachgewiesen ueber
`select db, hex(db), length(db) from mysql.db where user='sun_sync'` →
`74656E616E745C5F25`, 9 Zeichen, also `tenant\_%`. Mit zwei Backslashes wuerde
das Muster keine einzige Tenant-Datenbank treffen, und die Rechte waeren
wirkungslos — deshalb steht der Hex-Nachweis hier.

Proben als `sun_sync` (alle am 14.10.2026 gelaufen):

| Probe | Erwartung | Ergebnis |
|---|---|---|
| `show databases` | nur `information_schema`, `sun`, 23 × `tenant_…` | wie erwartet, keine fremde Datenbank |
| `select count(*) from sun.tenants` | Zahl | `23` |
| `insert into sun.tenants …` | scheitert | `ERROR 1142 … INSERT command denied` |
| `update sun.users set name=name` | scheitert | `ERROR 1142 … UPDATE command denied` |
| `drop table sun.sessions` | scheitert | `ERROR 1142 … DROP command denied` |
| `select count(*) from leads.migrations` | scheitert | `ERROR 1142 … SELECT command denied` |
| `mariadb-dump --single-transaction --quick --no-tablespaces --routines --triggers <tenant>` | laeuft | 21 115 792 Byte, stderr leer, `-- Dump completed` |
| dito auf `sun` | laeuft | 435 865 Byte gzip-komprimiert, stderr leer |

Nachprobe ohne Ortskenntnis — ein Einzeiler, der 0 zurueckgeben muss:

```bash
ssh sun-sync 'MYSQL_PWD="$SYNC_DB_PASSWORD" mariadb -u sun_sync -N -B \
  -e "show databases like '"'"'tenant\_%'"'"'"' | wc -l   # erwartet: 23
```

### Personendaten

Wichtig fuer #5 und dafuer, wie der lokale Datenbestand behandelt werden muss:
**ein Pull holt echte Personendaten auf den Laptop.** Erhoben ueber
`information_schema.columns`, Zeilenzahlen als InnoDB-Schaetzung aus
`information_schema.tables`.

Zentral (`sun`):

| Tabelle | Personendaten | Zeilen |
|---|---|---|
| `users` | Name, E-Mail, Passwort-Hash, `remember_token`, `public_name`, Telefonnummer, `registration_user_agent` | 644 |
| `addresses` | Strasse, PLZ, Ort, Telefon, Steuernummer | 9 |
| `oauth_login_providers` | Name, Anbieter-Verknuepfung | 7 |
| `tenant_turnstile_settings` | `secret_key` (kein Personendatum, aber ein Geheimnis) | 23 |
| `invitations` | E-Mail, Token | 0 |
| `one_time_passwords`, `password_reset_tokens`, `two_factor_authentications`, `personal_access_tokens` | Einmalpasswoerter, Tokens, 2FA-`shared_secret` | je 0 |
| `sessions` | `ip_address`, `user_agent` | 0 |
| `media` | Dateinamen, ggf. mit Klarnamen | — |

Je Tenant (Summe ueber alle 23):

| Tabelle | Personendaten | Zeilen |
|---|---|---|
| `companies` | Firmenname, Strasse, PLZ, E-Mail, `created_user_agent` | ~483 000 |
| `reviews` | `author_name`, `moderated_by_name` | ~1 700 000 |
| `tracking_events` | `ip_address`, `user_agent` | ~9 356 000 |
| `company_inquiries` | `contact_name`, `contact_email`, `contact_phone` | 831 |
| `claim_requests` | Kontaktdaten des Antragstellers | 116 |
| `newsletter_subscribers` | E-Mail, `ip_address` | 127 |
| `turnstile_verifications` | `user_agent`, `hostname_reported`, `email_hash` | 94 |
| `company_edit_suggestions` | `reporter_name`, `reporter_email`, `ip_address` | 40 |
| `job_applications` | `applicant_name`, `applicant_email`, `applicant_phone`, `ip_address` | 23 |
| `company_leads` | `contact_name`, `contact_email`, `contact_phone` | 1 |
| `password_reset_tokens`, `personal_access_tokens` | Tokens | 0 |
| `media` | Dateinamen | — |

Zwei Folgerungen fuer die naechsten Tickets:

* `tracking_events` (~9,4 Mio.) und `reviews` (~1,7 Mio.) sind zusammen der
  Grossteil des Volumens. `tracking_events` enthaelt nur IP und User-Agent,
  nichts, was lokal gebraucht wird → Daten ueberspringen (#4). `reviews`
  braucht man lokal inhaltlich, also mitnehmen und anonymisieren (#5).
* `tenant_turnstile_settings.secret_key` sind echte Live-Geheimnisse. Sie
  gehoeren in #5 mit ueberschrieben, auch wenn es keine Personendaten sind.

## Last auf der Produktion

`/home` liegt auf zwei rotierenden HDDs (md3, RAID1); ein unachtsamer Lauf
zieht die Antwortzeiten der Portale hoch (siehe Erfahrungsbericht in
`docs/datenbanksicherung.md`).

* Dump-Strom auf dem Server mit `nice -n 10 ionice -c2 -n7` starten.
* Datenbanken **seriell** holen, nicht parallel.
* `gzip -1` auf dem Server, entpacken lokal — spart Uebertragung, kostet kaum
  CPU.
* Keine Zwischendatei auf Live: `ssh sun 'mariadb-dump ... | gzip -1' |
  gunzip | mysql <lokal>`. Damit laeuft nichts in `/home` voll und der
  `backup-tar-waechter` auf dem Server bleibt unberuehrt.
* Ein Lauf gleichzeitig: Lock-Datei
  `storage/app/db-pull.lock` (`flock`, zweiter Lauf bricht mit Hinweis ab;
  ein Lock aelter als 2 h gilt als verwaist und wird mit Hinweis
  ueberschrieben, #3).

## Datenbankmenge

Die Liste kommt von den **lebenden** Datenbanken, nicht aus einer gepflegten
Aufzaehlung:

```sql
show databases like 'tenant\_%'
```

plus die zentrale Datenbank. Deren Namen nimmt der Lauf aus der **lokalen**
`DB_DATABASE` (`sun`) und nicht aus der Live-`.env`: beide Seiten heissen
gleich (Befund #2), und die Live-`.env` enthaelt Live-Geheimnisse, die auf dem
Laptop nichts zu suchen haben. Tenant-Praefix
ist `tenant_` (`config/tenancy.php`), die zentrale Verbindung heisst
`central`. Lokal wird jede Datenbank unter demselben Namen angelegt
(`create database if not exists … character set utf8mb4 collate
utf8mb4_unicode_ci`, siehe „Lokale Gegenseite"), damit
`tenancy()` lokal ohne Umschreiben funktioniert. Plausibilitaetsgrenze wie bei
der Sicherung: unter 20 gefundenen Tenant-Datenbanken bricht der Lauf ab
(Exit 66) — eher kein Pull als ein halber.

## Befehlsvertrag

```
php artisan sun:db:pull
    [--central-only]        nur die zentrale Datenbank
    [--tenant=DOMAIN|ID]*   nur diese Tenants (mehrfach erlaubt)
    [--tables=a,b,c]        nur diese Tabellen, ohne Neuaufbau (#4)
    [--only-tables=a,b]     Daten nur dieser Tabellen, Struktur vollstaendig (#4)
    [--skip-data=x,y]       diese Tabellen nur als Struktur, zusaetzlich zur Config (#4)
    [--parallel=N]          gleichzeitige Dumps (Default 2, Obergrenze 4)
    [--no-migrate]          kein migrate/tenants:migrate/cache:clear danach
    [--no-anonymize]        Anonymisierung ueberspringen (Default: an, #5)
    [--force]               Rueckfrage zu --no-anonymize unterdruecken
    [--storage]             zusaetzlich storage/tenant<id>/app/public (#6)
    [--storage-only]        NUR die Dateien, keine Datenbank anfassen (#6)
    [--storage-path=P]      nur den Unterbaum P von app/public (#9)
    [--max-size=N]          Dateien ueber N auslassen, rsync-Schreibweise (#9)
    [--dry-run]             nur zeigen, was geholt wuerde
```

Exit-Codes (an `scripts/sun-db-sicherung.sh` angelehnt, damit die Zahlen im
Projekt eine Bedeutung behalten):

| Code | Bedeutung |
|---|---|
| 0 | alle gewaehlten Datenbanken geholt und importiert |
| 64 | falscher Aufruf oder Richtungssperre gegriffen |
| 65 | Live nicht erreichbar (SSH oder Datenbank) |
| 66 | zu wenige Datenbanken gefunden |
| 70 | mindestens ein Dump oder Import fehlgeschlagen |

Am Ende steht eine Zusammenfassung als Tabelle: Datenbank, Stand, Dauer,
uebertragene (also komprimierte) Bytes, lokale Groesse nach dem Import,
Tabellenanzahl, Zahl der Tabellen ohne Daten (#4), ob anonymisiert — danach
die Namen dieser Tabellen nach Liste gruppiert, darunter eine Zeile mit Gesamtdauer,
Gesamtvolumen und Fehlerzahl, danach je Fehler eine eigene Zeile. Dieselben
Zeilen landen im Protokoll `storage/logs/db-pull-<YYYYMMDD-HHMMSS>.log`
(Zeiten in UTC, wie `APP_TIMEZONE`).

Waehrend des Laufs laeuft eine Fortschrittsleiste mit; fertige Datenbanken
stehen als eigene Zeile darueber (`✓ <db> — 12,9 s, 29 MB uebertragen`).

Drei Eigenschaften, die man beim Aufruf kennen muss:

* **`--tenant=` zieht die Zentrale NICHT mit.** „Ein Portal nachziehen" darf
  den lokalen Stand von `sun` nicht ueberschreiben. Ohne `--tenant` kommen
  Zentrale und alle Portale. `--central-only` und `--tenant` schliessen sich
  aus (Exit 64).
* **Voller Pull baut die lokale Datenbank neu auf** (`drop database` lokal,
  danach `create`), damit keine Tabellen aus einem alten Stand
  stehenbleiben. Ein Teil-Pull mit `--tables` tut das bewusst **nicht** — er
  ueberschreibt nur die genannten Tabellen.
* **`--storage --dry-run`** zeigt Dateizahl und Volumen je Portal, ohne etwas
  zu uebertragen (ein Tenant-Storage ist schnell zwei Gigabyte gross).

## Ablauf eines Laufs (#3)

Die Reihenfolge ist nicht beliebig, jeder Schritt haengt am vorherigen:

1. **Richtungssperre, vor jeder Verbindung nach Live.** `APP_ENV` muss
   `local` sein, und die Verbindung `central` muss auf diesen Rechner zeigen
   (`127.0.0.1`, `localhost`, `::1` oder leer). Sonst Exit 64, ohne dass eine
   Verbindung aufgebaut wird. Eine Option, die das aufhebt, gibt es nicht.
2. **Zugangsdaten.** Fehlt `SYNC_DB_USER` oder `SYNC_DB_PASSWORD`: Exit 64.
3. **Lock** `storage/app/db-pull.lock` (`flock`, exklusiv). Laeuft schon ein
   Sync: `Ein Sync laeuft bereits (seit hh:mm).`, Exit 64. Ein Lock, dessen
   Zeitstempel aelter als `lock_stale_after_minutes` (120) ist, gilt als
   verwaist und wird mit Hinweis uebernommen.
4. **Zentrale Datenbank**, lokal `drop database` + `create` (utf8mb4,
   `utf8mb4_unicode_ci`), dann der Stream. Scheitert sie, bricht der Lauf ab
   (Exit 70) — ohne Zentrale ist die Portal-Liste unbekannt.
5. **Portal-Liste aus der frisch importierten Zentrale**: `Tenant`-Model,
   Datenbankname = `tenant_database_prefix` + Tenant-ID. Gegengeprueft gegen
   `show databases` auf Live. Eine Tenant-Zeile ohne Live-Datenbank wird mit
   Warnung uebersprungen, eine Live-Datenbank ohne Tenant-Zeile ebenfalls
   (Reste geloeschter Portale, #39). Ist die Tabelle `tenants` leer oder
   unlesbar, gilt die Liste von Live.
6. **Portale**, standardmaessig zwei gleichzeitig (`--parallel`, harte
   Obergrenze 4 aus `parallel_max`). Jeder Dump ist ein eigener Prozess;
   bricht einer ab, laufen die uebrigen weiter, der Exit-Code ist 70 und die
   Zusammenfassung nennt die gescheiterten Datenbanken. Mehr als drei bringt
   am Uplink nichts und belastet nur Live-MySQL.
7. **`migrate`, `tenants:migrate`, `cache:clear`** (abschaltbar mit
   `--no-migrate`), damit neue lokale Migrationen greifen und keine Werte des
   alten Standes im Cache liegen. `tenants:migrate` bekommt nur die IDs, zu
   denen lokal eine Tenant-Zeile existiert — es loest jede ID auf und bricht
   sonst beim ersten Unbekannten ganz ab. Ein Fehlschlag hier ist eine
   Warnung, kein Abbruch: lokal stehen oft fremde Tenant-Migrationen aus,
   das macht den geholten Datenstand nicht unbrauchbar.
8. **Zusammenfassung und Protokoll.**

### Reiner Stream, kein Passwort in `ps`

Es entsteht zu keinem Zeitpunkt eine Dump-Datei, weder auf Live noch lokal:

```
ssh … bash -s <<'…' | tee <fifo> | gunzip | sed -E 's/DEFINER=…//' | mysql … <db>
```

Das lokale Skript geht ueber STDIN an `bash -s`, das Remote-Skript ueber ein
Here-Document an `ssh` — in beiden Prozesslisten steht nur `bash -s`, das
Passwort nur in `MYSQL_PWD` der jeweiligen Umgebung. Der `tee`-Zweig geht in
eine FIFO, an der `wc -c` haengt: daher die uebertragenen Bytes in der
Zusammenfassung. Die einzige temporaere Datei ist diese Byte-Zahl
(`$TMPDIR/sun-db-pull-*`), sie wird nach jedem Lauf geloescht.

`sed` entfernt DEFINER-Klauseln — sie verweisen auf den Live-Benutzer, den es
lokal nicht gibt, und liessen den Import sonst scheitern.

### Zeilenzahlen gegenpruefen

Nach einem Lauf stichprobenartig vergleichen (Live lesend, lokal lesend):

```bash
DB=tenant_<uuid>
PW=$(grep '^SYNC_DB_PASSWORD=' .env | cut -d= -f2-)
US=$(grep '^SYNC_DB_USER=' .env | cut -d= -f2-)

for T in companies reviews jobs; do
  LIVE=$(ssh -o BatchMode=yes sun-sync \
    "MYSQL_PWD='$PW' mariadb -u $US -N -B -e \"select count(*) from \\\`$DB\\\`.$T\"")
  LOCAL=$(mysql -h localhost -u root -N -B -e "select count(*) from \`$DB\`.$T")
  echo "$T: live=$LIVE lokal=$LOCAL"
done
```

Fuer die Zentrale dieselbe Abfrage auf `sun` mit `users` und `tenants`.
Tabellen aus `skip_data` muessen abweichen — dort kommt absichtlich nur die
Struktur (lokal 0 Zeilen).

## Konfiguration

Eigene Datei `config/db-pull.php`, gelesen in #3. Alle Werte aus der lokalen
`.env`, Geheimnisse ohne Vorgabewert im Code (Projektregel, siehe
`CLAUDE.md`), jede Variable ohne Wert in `.env.example`.

Verbindlich sind die `SYNC_*`-Namen aus #2/#3. Der Entwurf in #1 nannte sie
`SUN_PULL_*` und den Benutzer `sun_ro` — das ist ueberholt, es gibt nur einen
Satz Schluessel:

| Schluessel | .env | Zweck | lokal gesetzt auf |
|---|---|---|---|
| `ssh_host` | `SYNC_SSH_HOST` | SSH-Ziel | `sun-sync` (eigener Eintrag in `~/.ssh/config`) |
| `ssh_port` | `SYNC_SSH_PORT` | SSH-Port | `22` |
| `ssh_user` | `SYNC_SSH_USER` | SSH-Benutzer auf Live | `sanitaerfinden` |
| `ssh_key_path` | `SYNC_SSH_KEY_PATH` | privater Sync-Schluessel | `~/.ssh/sun_sync` |
| `db_user` | `SYNC_DB_USER` | Read-only-Benutzer | `sun_sync` |
| `db_password` | `SYNC_DB_PASSWORD` | dessen Passwort | Passwort-Manager |
| `remote_storage_path` | `SYNC_REMOTE_STORAGE_PATH` | Live-`storage/` (Basis fuer #6) | `/home/sanitaerfinden/htdocs/sanitaerfinden.dev/storage` |
| `local_mysql_cmd` | `SYNC_LOCAL_MYSQL_CMD` | lokaler MySQL-Client | `/opt/homebrew/opt/mysql/bin/mysql` |

`SYNC_LOCAL_MYSQL_CMD` steht in `.env.example` leer. Die Vorgabe `mysql`
gehoert deshalb in `config/db-pull.php` mit `env('SYNC_LOCAL_MYSQL_CMD') ?:
'mysql'` — mit `env(..., 'mysql')` wuerde der leere Wert aus einer kopierten
`.env` die Vorgabe auf `''` setzen.

Dazu `SYNC_BWLIMIT` (Drossel fuer `--storage`, leer = `20m`) und die
Dummy-Werte der Anonymisierung: `SYNC_LOCAL_PASSWORD` (leer = `passwort`),
`SYNC_LOCAL_ADMIN_NAME`/`_EMAIL`/`_PASSWORD` (leer = `Enes` /
`enes@sun.test` / dasselbe Einheitspasswort). Das sind keine Geheimnisse,
sondern lokale Vorgaben; sie stehen trotzdem in `.env.example`, damit man
sie findet.

Dazu `SYNC_STORAGE_PATH` und `SYNC_STORAGE_MAX_SIZE` als Vorgabe fuer die
Begrenzungen aus #9 (leer = alles, keine Groessengrenze).

Nicht in der `.env`, weil Projektwissen und kein Zugangsdatum: `storage_exclude` (Ausschlussliste fuer `--storage`, siehe unten) und `storage_warn_gigabytes` (Schwellwert der Volumen-Vorwarnung, Vorgabe 5).

Nicht in der `.env`, weil nicht konfigurierbar: `anonymize` (Default `true`,
Schalter ist `--no-anonymize`, #5), `anonymize_rules`, `anonymize_json`,
`anonymize_truncate` und `anonymize_batch_size` (#5, Spaltenlisten gehoeren
in die Versionsverwaltung, nicht in eine `.env`), `min_tenant_databases` (Default 20,
Plausibilitaetsgrenze), `timeout` (3600 s je Datenbank),
`lock_stale_after_minutes` (120), `parallel` (2) und `parallel_max` (4,
harte Obergrenze fuer `--parallel`), `remote_dump_bin`/`remote_client_bin`
(`mariadb-dump`/`mariadb`), `tenant_database_prefix` (`tenant_`) und
`tenant_storage_prefix` (`tenant`, ohne Unterstrich).

## Tabellenfilter (#4)

Strukturen werden **immer** geholt, damit lokal keine Tabelle fehlt und kein
Code und keine Migration auf eine fehlende Tabelle laeuft. Uebersprungen
werden nur die **Daten** von Protokoll-, Cache-, Warteschlangen- und
Token-Tabellen.

Umgesetzt in `app/Services/DbPull/TableFilter.php`, Listen in
`config/db-pull.php` unter `skip_data` (getrennt nach `central` und
`tenant`), damit sie ohne Codeaenderung wachsen koennen.

### Zwei Dumplaeufe in derselben Pipe

Je Datenbank laufen zwei `mariadb-dump`-Aufrufe hintereinander in **einen**
lokalen `mysql`-Prozess:

| Reihenfolge | Aufruf | Ergebnis |
|---|---|---|
| 1 | `mariadb-dump --no-data <db> <skip…>` | Struktur der uebersprungenen Tabellen |
| 2 | `mariadb-dump --ignore-table=<db>.<skip> … <db>` | Struktur **und** Daten aller uebrigen |

Die beiden Mengen sind disjunkt; die Struktur der leeren Tabellen steht vorn,
damit sie im Protokoll zuerst zu sehen ist. Ist die Skip-Liste fuer eine
Datenbank leer, laeuft nur der zweite Aufruf.

### Platzhalter

`skip_data` erlaubt `*` und `?` (`telescope_*`). Aufgeloest wird **lokal
gegen die Tabellenliste der jeweiligen Live-Datenbank**, nie an
`mariadb-dump` weitergegeben — zwei Gruende:

* `--ignore-table` will den vollen Namen `db.tabelle` und kennt keine
  Platzhalter.
* Eine Tabelle, die es auf Live nicht gibt, laesst den Dump abbrechen.

Die Tabellenliste kommt aus einer einzigen `information_schema`-Abfrage fuer
alle Datenbanken des Laufs (`RemoteMariaDb::tablesByDatabase()`), nicht aus
einem `SHOW TABLES` je Datenbank; der `TableFilter` cacht die Aufloesung
zusaetzlich je Tabellenmenge. Namen, die auf Live fehlen, sind damit
unschaedlich — die Liste darf vorsorglich wachsen (`activity_log`,
`notifications` stehen drin, obwohl es sie heute nicht gibt).

Exakte Namen sind exakt: `cache` trifft **nicht** `cache_locks`. Wer beides
will, nennt beides oder schreibt `cache*`.

### Laufzeit-Optionen

```bash
# Skip-Liste fuer diesen Lauf ergaenzen (Config bleibt, kommt hinzu)
php artisan sun:db:pull --skip-data=reviews,company_stats_daily

# Daten nur dieser Tabellen — die Struktur kommt weiterhin VOLLSTAENDIG
php artisan sun:db:pull --only-tables=companies,cities
```

| Option | Struktur | Daten | Lokale Datenbank |
|---|---|---|---|
| (keine) | alle | alle ausser `skip_data` | wird neu aufgebaut |
| `--skip-data=x,y` | alle | alle ausser `skip_data` + `x,y` | wird neu aufgebaut |
| `--only-tables=a,b` | alle | nur `a,b` | wird neu aufgebaut |
| `--tables=a,b` | nur `a,b` | nur `a,b` | bleibt stehen, nur `a,b` ersetzt |

Drei Punkte, die man dazu wissen muss:

* **`--only-tables` setzt `skip_data` ausser Kraft.** Wer die Datenmenge
  selbst benennt, hat damit schon entschieden —
  `--only-tables=tracking_events` holt die Daten von `tracking_events`.
  Technisch dumpt der erste Lauf dann die Struktur der ganzen Datenbank
  (`--no-data` ohne Tabellenliste) und der zweite die Daten der genannten;
  jede uebrige Tabelle einzeln mit `--no-data` aufzufuehren waere dieselbe
  Wirkung bei dreistelliger Argumentzahl.
* **`--only-tables` und `--tables` schliessen sich aus** (Exit 64). Sie sehen
  aehnlich aus, tun aber Verschiedenes: `--tables` ist der Teil-Nachzug ohne
  Neuaufbau, `--only-tables` ein vollstaendiger Pull mit begrenzter
  Datenmenge.
* Platzhalter gelten in beiden Laufzeit-Optionen (`--only-tables=company_*`).
  Ein Name, den es auf Live nicht gibt, wird im Probelauf als Warnung
  genannt.

### Was in der Zusammenfassung steht

Die Ergebnistabelle hat je Datenbank die Spalte **„Ohne Daten"** mit der
Anzahl, darunter stehen die Namen — gruppiert nach identischer Liste, weil
sie bei 23 Portalen sonst 23-mal dasselbe waere:

```
Ohne Daten uebertragen (Struktur vorhanden, 0 Zeilen):
  sun: cache, cache_locks, failed_jobs, jobs, llm_usage_logs, …
  tenant_e917ddeb-…, tenant_d4d1db51-…, tenant_74a732dc-… und 20 weitere:
    article_metrics_raw, company_description_backups, jobs, tracking_events, …
```

Im Protokoll steht je Datenbank eine eigene Zeile mit allen Namen
ungekuerzt. Der Probelauf zeigt dasselbe vorab als Spalten „mit Daten" und
„nur Struktur".

### Wirkung, gemessen am 14.10.2026

Anteil der Skip-Tabellen am Live-Volumen, aus `information_schema` ueber
alle 24 Datenbanken (1 290 Tabellen):

| | Volumen | Zeilen | Tabellen |
|---|---|---|---|
| Gesamt | 9 595,9 MB | 24 971 138 | 1 290 |
| davon nur Struktur | **4 214,3 MB (43,9 %)** | 10 054 078 | 133 |
| bleibt zu holen | 5 381,5 MB | 14 917 060 | 1 157 |

Vorher/Nachher an der groessten Portal-Datenbank
(`tenant_e917ddeb-…`, 1 673 MB auf Live), voller Weg wie im Befehl
(`mariadb-dump … | gzip -1` ueber SSH → `gunzip` → lokaler `mysql`-Import):

| | Dauer | uebertragen (gz) | lokal nach dem Import | Tabellen |
|---|---|---|---|---|
| ohne Filter (Stand #2/#3) | 73,4 s | 147,0 MB | 1 457 MB | 51 von 51 |
| mit Filter (#4) | **46,0 s** | **69,4 MB** | 993 MB | 51 von 51 |
| Ersparnis | **−37 %** | −52,8 % | −32 % | — |

Die Zentrale `sun` ist zu klein fuer eine Zeitmessung (0,6 s in beiden
Faellen), beim Volumen sind es 435 KB → 233 KB (−46 %).

Gegenprobe in derselben Wegwerf-Datenbank: alle 51 Tabellen vorhanden,
`article_metrics_raw`, `company_description_backups`, `jobs`,
`tracking_events`, `turnstile_verifications` je **0 Zeilen**, die Nutzdaten
vollstaendig (`companies` 29 443, `reviews` 99 839). Danach weggeworfen.

Die Zeitersparnis traegt in dieser Groessenordnung auf den ganzen Lauf
durch: der langsamere Teil ist der lokale Import, und der ist dem Volumen
proportional. Ein voller Lauf ueber alle 24 Datenbanken wurde **nicht**
gefahren — er wuerde den lokalen Arbeitsstand ersetzen (siehe „Abnahme des
Befehls"), und `#8` (lokale Zentrale vor dem `drop` sichern) ist noch offen.
Gemessen ist der Mechanismus an der groessten Datenbank des Bestandes, also
am unguenstigsten Fall.

## Anonymisierung (#5)

Laeuft **lokal nach dem Import** als SQL-Update, nie auf Live — ein
Anonymisierer, der auf der Produktion schreiben koennte, waere genau das, was
das Sicherheitsmodell ausschliesst. Default an; `--no-anonymize` ist die
Ausnahme, verlangt eine Bestaetigung (ausser mit `--force`) und wird im
Protokoll vermerkt.

Umgesetzt in `app/Services/DbPull/Anonymizer.php`, Regeln in
`config/db-pull.php`. Welche Tabellen Personendaten tragen, steht oben im
Abschnitt „Server-Befund → Personendaten" — dort mit Spaltennamen und
Zeilenzahlen aus der Live-Instanz.

Vier Eigenschaften, die den Lauf ausmachen:

* **Deterministisch ueber die Zeilen-ID** (`nutzer<id>@example.test`,
  `Verfasser <id>`), nicht mit Faker: ein zweiter Pull liefert dieselben
  Werte, lokale Lesezeichen und Testlogins passen weiter.
* **Nur SQL, in ID-Fenstern.** Ein `UPDATE` je Fenster von
  `anonymize_batch_size` Zeilen (Default 20 000), damit `reviews` (~1,7 Mio.)
  keine Transaktion ueber die ganze Tabelle aufbaut. Kein Eloquent, also
  keine Model-Events, keine Observer, keine Mails und keine Jobs.
* **Fehlende Tabellen und Spalten werden uebersprungen** (`information_schema`
  vor jedem `UPDATE`), weil nicht jedes Portal jede Tabelle hat. Die Listen
  duerfen also grosszuegig sein.
* **Ein Teil-Pull anonymisiert nur die geholten Tabellen** (#7). `--tables`
  sagt zu, nur die genannten Tabellen anzufassen; der Lauf reicht die Liste
  der tatsaechlich geholten Tabellen an den Anonymisierer durch und schneidet
  Regeln, JSON-Schluessel und `TRUNCATE`-Liste darauf. Leere Liste (voller
  Pull) heisst weiterhin „alles". Aus demselben Grund laeuft der
  Administratorzugang unten beim Teil-Pull nur, wenn `users` dabei war.
* **Oeffentliche Daten bleiben stehen.** Firmenname, Adresse,
  Oeffnungszeiten, Beschreibung, Bewertungstexte und Zeitstempel/Status der
  Anfragen werden gebraucht, um lokal mit echten Mengen zu entwickeln;
  anonymisiert werden Kontaktwege und Namen von Personen.

Die Listen in `config/db-pull.php` sind ohne Codeaenderung erweiterbar:

| Schluessel | Inhalt |
|---|---|
| `anonymize_rules.central` / `.tenant` | je Tabelle Spalte → SQL-Ausdruck; `:password_hash` ist eine Bindung, die der Lauf setzt |
| `anonymize_json.central` / `.tenant` | Schluessel, die mit `JSON_REMOVE` aus einer JSON-Spalte fliegen (`tenants.data`: `lead_funnel_token`, `lead_webhook_secret`) |
| `anonymize_truncate.central` / `.tenant` | Tabellen, die lokal leer sein sollen: `personal_access_tokens`, `password_reset_tokens`, `one_time_passwords`, `two_factor_authentications`, `sessions`, je Portal zusaetzlich `turnstile_verifications`, `tracking_events` |
| `anonymize_password` | lokales Einheitspasswort aller Konten (`SYNC_LOCAL_PASSWORD`, Default `passwort`) |
| `anonymize_admin` | fester Administrator nach dem Lauf (`SYNC_LOCAL_ADMIN_*`, Default `Enes` / `enes@sun.test`) |

Reine Zugangsmittel werden nicht anonymisiert, sondern geleert — ein
zurueckgesetzter Token ist ein wertloser Token. Die Tabellen kommen ohnehin
ohne Daten (`skip_data`, #4); das `TRUNCATE` greift zusaetzlich, wenn eine
dieser Tabellen bei einem Teil-Pull mit `--tables` ausdruecklich genannt
wurde.

Die Portale haben **keine eigene `users`-Tabelle**: alle Konten (Firmen,
Redaktion, Administration) liegen zentral in `sun.users` bzw.
`sun.content_users`, deshalb stehen die Nutzer-Regeln nur unter
`anonymize_rules.central`.

### Zugang nach dem Lauf

Alle Passwoerter lauten lokal `passwort` (`anonymize_password`), jedes Konto
kann sich damit anmelden. Weil nach der Anonymisierung kein echtes
Administratorkonto mehr existiert, legt der Lauf eines an bzw. setzt es
zurueck (`app/Services/DbPull/AdminAccount.php`): `Enes`,
`enes@sun.test`, `is_admin = 1`, Spatie-Rolle `admin` — damit ist
`/admin` sofort nutzbar. Geschrieben wird per SQL, nicht ueber das Model
(sonst feuern Observer und die Tenant-Anlage). Der Schritt laeuft nur, wenn
anonymisiert wurde und die zentrale Datenbank Teil des Laufs war; bei einem
Teil-Pull zusaetzlich nur, wenn `--tables` die Tabelle `users` enthaelt (#7).
Sein Ergebnis steht am Ende auf dem Bildschirm und im Protokoll.

### Pruef-Query nach dem Lauf

```bash
php artisan tinker --execute='require "scripts/db-pull-anonymisierung-pruefen.php";'
```

Das Skript geht ueber `sun` und alle `tenant\_%`-Datenbanken, nimmt sich jede
Text-Spalte, deren Name nach E-Mail, Telefon, Name oder Nachricht aussieht,
und zaehlt Werte, die eine fremde Mail-Domain (alles ausser `*.test` und
`example.*`) oder eine echte `+49`-Nummer enthalten. Erwartet wird
`OK — keine echten E-Mail-Adressen oder Telefonnummern gefunden`; jeder
Befund kommt mit Datenbank, Tabelle, Spalte und Zeilenzahl und gehoert als
neue Regel in `config/db-pull.php`.

Zwei Dinge, die beim Bauen der Query aufgefallen sind:

* `like 'tenant_%'` trifft auch die `tenants_*`-Datenbanken anderer Projekte
  auf demselben Rechner — der Unterstrich ist in `LIKE` ein Platzhalter und
  muss escaped werden (`tenant\_%`), genau wie in `RemoteMariaDb`.
* `reviews.author_name` enthaelt auf Live echte Mail-Adressen (aus
  Google importierte Bewertungsnamen wie `Andreas Koch 1.andreas.koch@gmail.com`).
  Die Spalte ist also kein kosmetisches Namensfeld, sondern ein
  Personendatum.

## Storage-Sync (#6)

Optional, weil es die grosse Last ist: `rsync` von
`<remote_app_dir>/storage/tenant<id>/app/public` nach lokal
(`storage/tenant<id>/app/public`, `suffix_storage_path` ist in
`config/tenancy.php` aktiv). Nur lesend (`rsync` von Live nach lokal),
gedrosselt (`--bwlimit`), seriell, ein Portal nach dem anderen.

```bash
php artisan sun:db:pull --storage                       # Datenbanken + Dateien
php artisan sun:db:pull --storage-only                  # nur Dateien
php artisan sun:db:pull --storage-only --tenant=…       # nur ein Portal
php artisan sun:db:pull --storage-only --dry-run        # nur zaehlen

# begrenzt (#9): nur der media-Unterbaum, und nichts ueber 200 KB
php artisan sun:db:pull --storage-only --tenant=… --storage-path=media
php artisan sun:db:pull --storage-only --tenant=… --max-size=200K
php artisan sun:db:pull --storage-only --tenant=… --storage-path=media --max-size=200K
```

Eigenschaften, die man kennen muss:

* **`--delete` wirkt lokal, und nur auf `storage/tenant<id>/app/public`**
  (mit `--storage-path` nur auf dem gewaehlten Unterbaum davon).
  Lokal ist das ein Spiegel von Live, keine Ablage. Der Zielpfad wird vor
  jedem Lauf gegen den aus Praefix, Portal-ID und Teilpfad **erwarteten**
  Pfad geprueft und muss ihm genau entsprechen; eine Portal-ID oder ein
  Teilpfad mit `/`, `..` oder Leerzeichen bricht ab (Exit 64). Auf der
  Gegenseite wird nie geloescht.
* **Ausschluesse** stehen in `config/db-pull.php` unter `storage_exclude`
  und gehen als `--exclude-from` an rsync: Sicherungen, Exporte,
  Protokolle, Cache, `livewire-tmp/` und angefangene Uploads. Ausgeschlossene
  Dateien werden lokal auch **nicht** geloescht (rsync-Verhalten ohne
  `--delete-excluded`) — eigene Notizen unter einem dieser Muster bleiben
  also liegen.
* **Die Zahlen** („3 367 Datei(en) / 417,4 MB uebertragen, 12 lokal
  geloescht") kommen aus `--out-format`, nicht aus `--stats`: die
  `--stats`-Bloecke von openrsync (macOS) und rsync 3 (Live) sind
  unterschiedlich beschriftet. Beim zweiten Lauf steht dort das Delta.
* **`--storage-only`** fasst keine Datenbank an und braucht deshalb auch
  `SYNC_DB_USER`/`SYNC_DB_PASSWORD` nicht, nur den SSH-Zugang. Die
  Portal-Liste kommt dann aus der **lokalen** Zentrale (es gibt keine
  frischere), nie aus den Live-Verzeichnissen — dort liegen Ordner
  geloeschter Portale. `--storage-only` und `--central-only` schliessen sich
  aus (Exit 64), das zentrale `storage/app/public` ist auf Live praktisch
  leer.
* **Symlinks**: nach einem erfolgreichen Lauf wird `tenants:storage-link`
  aufgerufen, falls `public/storage-<id>` oder `public/storage` fehlt — ohne
  den Link zeigen die Firmenprofile lokal keine Bilder.
* **Fehlt `rsync` lokal**, bricht der Befehl vor dem ersten Byte mit Exit 64
  und dem Hinweis `brew install rsync` ab.

**Das Volumen ist kein Nebenschauplatz.** Gemessen am 14.10.2026: ein
einzelnes Portal haelt 127 GB (`photos` 59 GB, `media` 68 GB), ein zweites
33 GB, ein drittes 1,9 GB — alles echte Bilder, nichts, was die
Ausschlussliste abfangen koennte. `--storage` ueber alle Portale passt auf
keinen Laptop; im Alltag ist `--storage-only --tenant=…` fuer das gerade
bearbeitete Portal der richtige Aufruf. Vorher `--dry-run`, der zaehlt in
unter einer Sekunde.

### Begrenzen: `--storage-path` und `--max-size` (#9)

Damit der Sync ueberhaupt benutzbar bleibt, gibt es zwei Begrenzungen. Beide
wirken nur zusammen mit `--storage`/`--storage-only` (sonst Exit 64) und sind
in `config/db-pull.php` auch als Vorgabe setzbar (`storage_path`,
`storage_max_size`, dazu `SYNC_STORAGE_PATH` / `SYNC_STORAGE_MAX_SIZE`).

| Option | Wirkung |
| --- | --- |
| `--storage-path=media` | spiegelt nur diesen Unterbaum von `app/public`. Quelle **und** Ziel wandern hinein, `--delete` wirkt also nur dort. Auf Live liegen unter `app/public` die Unterbaeume `media` (Spatie-Medien, Logos und Firmenbilder), `photos` (der dicke Teil: 59 GB auf dem groessten Portal) und die Sitemaps. |
| `--max-size=2M` | geht als `--max-size` an rsync; groessere Originale bleiben draussen. Schreibweise wie bei rsync (`1024`, `500K`, `2M`, `1.5G`). |

Was man dabei wissen muss:

* **`--max-size` und `--delete` vertragen sich nicht so, wie man denkt.**
  Eine zu grosse Datei ist fuer rsync ein Ausschluss, und Ausschluesse
  werden lokal **nicht** geloescht (kein `--delete-excluded`). Ein spaeterer
  Lauf mit `--max-size` raeumt einen frueheren Vollspiegel also nicht ab —
  gewolltes Verhalten (sonst wuerde eine Begrenzung lokale Bilder
  wegwerfen), aber es heisst: lokal liegt dann ein Mischstand. Wer wirklich
  nur die kleinen Dateien haben will, loescht
  `storage/tenant<id>/app/public` vorher selbst. Der Befehl warnt bei jedem
  Lauf mit `--max-size` einmal in dieser Sache.
* **Ein Teilpfad, den es auf Live nicht gibt** (nicht jedes Portal hat
  `media/logos`), ist kein Fehler: das Portal wird mit „gibt es auf Live
  nicht — nichts zu holen" gemeldet und der Lauf geht weiter.
* **Die Guard-Pruefung waechst mit**: mit `--storage-path` endet das Ziel
  nicht mehr auf `/app/public`, geprueft wird daher gegen den erwarteten
  Pfad als Ganzes (siehe oben).
* **Pfadmuster der Ausschlussliste** beziehen sich weiter auf das
  rsync-Wurzelverzeichnis — mit `--storage-path=media` ist das
  `app/public/media`, ein Muster wie `/backups/` greift dort also nicht
  mehr. Fuer die Logos ist das unerheblich.

### Vorwarnung statt stumpf loszulaufen (#9)

Vor dem echten Lauf macht der Befehl je Portal einen Probelauf (`--dry-run`,
kostet nur die Dateiliste). Wuerde das Portal mehr als
`storage_warn_gigabytes` (Vorgabe 5 GB) uebertragen, kommt die Zahl als
Warnung samt Rueckfrage:

```
WARN  Portal 0fdc… wuerde 127,3 GB in 412 109 Datei(en) holen (Schwellwert 5,0 GB).
      Das dauert und belastet die Live-HDDs. Enger fassen mit
      --storage-path=media und/oder --max-size=200K.
 Portal 0fdc… trotzdem holen? (yes/no) [no]
```

Wer ablehnt, ueberspringt nur dieses Portal (Tabellenspalte
`uebersprungen`), der Lauf geht weiter und endet mit Exit 0. `--force`
ueberspringt die Rueckfrage, `storage_warn_gigabytes => 0` schaltet die
Vorwarnung ganz ab. **Ohne TTY** (Skript, CI) gilt die Vorgabe `no`, dort
also `--force` setzen oder enger fassen.

## Bedienung im Alltag

```bash
# alles, anonymisiert
php artisan sun:db:pull

# nur ein Portal nachziehen
php artisan sun:db:pull --tenant=sanitaerfinder.com

# nur Zentrale, ohne Bilder
php artisan sun:db:pull --central-only

# vorher sehen, was passiert (zeigt Datenbanken UND Befehle)
php artisan sun:db:pull --dry-run

# schneller, auf eigene Gefahr der Live-Last (Obergrenze 4)
php artisan sun:db:pull --parallel=3

# Datenstand holen, lokale Migrationen selbst fahren
php artisan sun:db:pull --no-migrate

# nur die Firmen- und Stadtdaten mit Inhalt, alles andere als leere Struktur
php artisan sun:db:pull --only-tables=companies,cities

# zusaetzlich die Bewertungen leer lassen (grosse Tabelle, lokal selten gebraucht)
php artisan sun:db:pull --skip-data=reviews

# lokalen Stand wirklich wegwerfen, ohne Sicherung
php artisan sun:db:pull --no-local-backup

# ohne Rueckfrage (Skript, Cron)
php artisan sun:db:pull --no-interaction
```

`migrate`, `tenants:migrate` und `cache:clear` laufen am Ende automatisch —
im Cache liegen Werte des alten Datenstandes, und die Schluessel sind nicht
mandantengetrennt. Mit `--no-migrate` entfaellt das und muss von Hand
nachgeholt werden; der Befehl sagt das am Ende auch.

**Ein voller Lauf ersetzt den lokalen Arbeitsstand.** Lokal haengen in `sun`
die Test-Tenants mit `.test`-Domains (ganzzahlige IDs), Live die echten
Portale (UUIDs) — nach einem Pull der Zentrale sind die lokalen Test-Tenants
weg, ihre Datenbanken `tenant_<n>` bleiben als Waisen liegen. Wer nur Live
anschauen will, nimmt `--tenant=<db>`: das legt die Portal-Datenbank unter
ihrem Live-Namen neu an und laesst Zentrale und Test-Tenants in Ruhe.

### Sicherung des lokalen Standes (#8)

Vor dem `drop database` der Zentrale schreibt der Befehl den lokalen Stand
nach `storage/app/db-pull/vor-pull-<YYYYMMDD-HHMMSS>.sql.gz` — lokal nach
lokal, Live ist daran nicht beteiligt. Gemessen am 14.10.2026: `sun` ist
7,4 GB gross, der Dump davon **1,07 GB in 72 s**. Die Groesse steht vor dem
Dump auf dem Bildschirm, und es wird abgebrochen, wenn weniger als die
Haelfte der Datenbankgroesse frei ist (`local_backup.free_factor`); gzip
kommt real auf 14 %, der Wert ist also Sicherheitsabstand, keine Erwartung.
Behalten werden die letzten zwei Staende (`local_backup.keep`), aeltere
loescht derselbe Lauf.

Zurueckspielen — der Dump enthaelt `DROP DATABASE` und `CREATE DATABASE`,
es braucht also keine Vorarbeit:

```bash
gunzip -c storage/app/db-pull/vor-pull-20261014-165648.sql.gz | mysql
```

Die Tenant-Datenbanken `tenant_<n>` der Test-Tenants sind **nicht** Teil der
Sicherung: der Pull fasst sie nicht an, sie bleiben liegen und passen nach
dem Zuruecksichern der Zentrale wieder zu ihren Tenant-Zeilen.

Vor einem vollen Lauf (ohne `--tenant`, ohne `--central-only`) fragt der
Befehl zusaetzlich nach und nennt die Zahl der betroffenen lokalen Portale.
`--no-interaction` ueberspringt die Frage, `--force` ebenfalls,
`--no-local-backup` laesst die Sicherung weg. Beide Schalter sind fuer den
Fall gedacht, dass der lokale Stand wirklich weg soll — ohne sie kostet ein
voller Pull gut eine Minute mehr und ein Gigabyte Platte, und der
Arbeitsstand ist zurueckholbar.

## Abnahme des Befehls (#1, 14.10.2026)

Gegen die echte Produktion gelaufen, mit dem Read-only-Benutzer `sun_sync`:

| Probe | Ergebnis |
|---|---|
| `sun:db:pull --dry-run` | 1 zentrale + 23 Tenant-Datenbanken, Tabellen gezaehlt (`sun` 89, Portale 50–80), zentraler Name aus der Live-`.env` gelesen |
| `sun:db:pull --tenant=tenant_0693bded-…` | **ok in 3,4 s**, 50 Tabellen, 18 MB lokal, 8 Tabellen anonymisiert (9 209 Zeilen) |
| Inhalt danach | 1 905 Firmen, **alle** mit `@example.test`; 7 304 Bewertungen, **alle** mit `Verfasser <id>` |
| `tracking_events` | Tabelle vorhanden, **0 Zeilen** — Struktur ohne Daten, wie in #4 vorgesehen |
| Zweiter Lauf `--tables=companies,cities` | identischer Hash ueber alle E-Mail-Adressen → Anonymisierung ist wirklich deterministisch; Tabellenzahl bleibt 50 (kein Neuaufbau) |
| `--tenant=… --storage --dry-run` | 24 411 Dateien, 1 929 537 644 B — Pfad `storage/tenant<uuid>/app/public` (ohne Unterstrich) stimmt |
| `--storage-path=media --max-size=200K --dry-run` (#9, 14.10.2026, dasselbe Portal) | ohne Grenze 14 643 Dateien / 1 839,5 MB, mit `--max-size=200K` 11 101 Dateien / **382,9 MB** — die Begrenzung zieht also 80 % des Volumens ab und laesst die Logos drin |
| `--storage-path=media/logos` auf einem Portal | `nicht vorhanden` statt Fehler (rsync-Exit 23) — die Unterbaeume heissen auf Live `media` und `photos`, nicht `media/logos` |
| Storage voll (#6, Portal `8867ddac-…`) | 3 367 Dateien / 417,4 MB in **56,9 s** (gedrosselt auf 20m), lokal 424 MB |
| Storage inkrementell (#6) | **0 Dateien / 0 B**, nur die zwischendurch angelegte Fremddatei als „1 lokal geloescht" — 0,9 s |
| Ausschlussliste (#6) | lokal angelegte `test.log` blieb liegen (von `*.log` ausgeschlossen, also auch vom `--delete` ausgenommen), `zu-loeschen.txt` wurde geloescht |
| Quellordner fehlt auf Live (#6) | rsync Exit ungleich 0, Fehlermeldung in Tabelle und Protokoll, **lokal nichts geloescht** |
| Portal-ID mit `../` (#6) | `RuntimeException: Unerwartete Portal-ID fuer rsync` vor dem rsync-Aufruf |
| Zweiter Lauf parallel | „Ein Sync laeuft bereits (seit 16:30)." (Exit 64) |
| Zentrale Regeln (#5) | gegen eine lokale Kopie der zentralen Tabellen geprueft: 16 Spalten in 6 Tabellen, `users` vollstaendig ersetzt, `Hash::check('passwort', …)` → `true`, `tenant_turnstile_settings.secret_key` geleert, `lead_funnel_token` per `JSON_REMOVE` aus `tenants.data` entfernt |

### Nachtrag Kern-Befehl (#3, 14.10.2026)

| Probe | Ergebnis |
|---|---|
| `APP_ENV=production` / `APP_ENV=staging` | sofortiger Abbruch mit Exit 64, ohne SSH- oder Datenbankverbindung |
| Lock gehalten, Zeitstempel frisch | `Ein Sync laeuft bereits (seit 16:30).`, Exit 64 |
| Lock gehalten, Zeitstempel 3 h alt | `Verwaistes Lock von 13:30 (180 Minuten alt, Grenze 120) — wird uebernommen.`, Lauf startet |
| `--dry-run` | 24 Datenbanken als Tabelle, dazu die vollstaendigen Befehle beider Dumpstufen — Passwort als `***` |
| Zwei Portale, `--parallel=2` | beide ok, **16,7 s gesamt** (3,4 s / 13,1 s Dump, 2 MB / 29 MB uebertragen), Anonymisierung 9 209 bzw. 143 020 Zeilen |
| Import eines Portals kuenstlich zum Scheitern gebracht | das andere Portal lief durch, Exit **70**, Zusammenfassung nennt die gescheiterte Datenbank mit der echten MySQL-Meldung (nicht „Broken pipe") |
| Zeilenvergleich nach dem Lauf | `companies` 1 905 = 1 905, `reviews` 7 304 = 7 304, `jobs` 0 = 0, `company_inquiries` 0 = 0; `tracking_events` live 43 348, lokal 0 (nur Struktur) |
| Dateien | auf Live keine neue Datei (`/tmp`, App-`storage/`), lokal keine `sun-db-pull-*`-Reste |
| `tenants:migrate` ohne Tenant-Zeile | Warnung mit den betroffenen IDs, Lauf bleibt bei Exit 0 |

Nicht gelaufen und bewusst offen gelassen:

* **Der volle Pull ueber alle 24 Datenbanken.** Er wuerde die lokale `sun`
  und damit den Arbeitsstand auf diesem Laptop (lokale Test-Tenants
  `handwerker.test`, `sanitaer.test`, `elektriker.test`) ersetzen. Der
  Mechanismus ist an einer echten Portal-Datenbank nachgewiesen; den
  Gesamtlauf startet, wer den lokalen Stand wirklich wegwerfen will.
* **Der echte 1,9-GB-Storage-Sync.** Pfad und Zugang sind im Probelauf
  belegt; die Uebertragung selbst ist reine Live-Last ohne Erkenntnisgewinn.

Aufgeraeumt: die zum Test gezogene Portal-Datenbank und die Probekopie der
zentralen Tabellen sind lokal wieder entfernt.

### Nachtrag Tabellenfilter (#4, 14.10.2026)

Gegen die echte Produktion gelaufen, Messdatenbank
`tenant_8867ddac-…` (kleinste Portal-Datenbank, 35 MB auf Live):

| Probe | Ergebnis |
|---|---|
| `--tenant=… --no-migrate` | ok in 4,1 s, 5 MB uebertragen, 51 Tabellen, Spalte „Ohne Daten" = 5 |
| Tabellenzahl danach | lokal 51 = live 51 — keine Tabelle fehlt |
| Zeilenvergleich | `companies` 5 609 = 5 609, `reviews` 22 255 = 22 255, `cities` 14 192 = 14 192; `tracking_events` live 300, **lokal 0** |
| `--only-tables=companies,cities` | 51 Tabellen (Struktur vollstaendig), **49 ohne Daten**, nur `companies` 5 609 und `cities` 14 192 gefuellt, `reviews`/`posts`/`media` 0 |
| `--only-tables=users,tenants,gibtsnicht --dry-run` | Warnung „`gibtsnicht` — in der Zentrale nicht vorhanden", beide Dumpstufen im Klartext, Passwort als `***` |
| `--skip-data=reviews,cit*` | Probelauf nennt die Ergaenzung; in der Zentrale gibt es beide Namen nicht, die Zahl bleibt daher bei 15 |
| `--tables` + `--only-tables` zusammen | Exit **64** mit Hinweis auf den Unterschied |
| Wildcard `telescope_*` | loest auf der Zentrale zu `telescope_entries`, `telescope_entries_tags`, `telescope_monitoring` auf; `cache` trifft `cache_locks` **nicht** |
| Ausgabe bei langer Liste | Terminal kuerzt nach 12 Namen („… und 38 weitere"), das Protokoll nennt alle 50 |

Aufgeraeumt: die zum Messen gezogene Portal-Datenbank und die
Wegwerf-Datenbank des Vorher/Nachher-Vergleichs sind lokal wieder entfernt.

### Zwei Fallen, die beim Bauen aufgefallen sind

* **`information_schema` braucht Aliase in Kleinschreibung.** Live ist
  MariaDB, lokal MySQL 8 — letzteres liefert `TABLE_NAME`/`COLUMN_NAME` in
  Grossbuchstaben zurueck. Ohne `as tabelle`/`as spalte` haengt der
  Spaltenzugriff an der Serverversion.
* **Das `rsync` von macOS ist 2.6.9** und nennt die `--stats`-Zeilen anders
  als rsync 3 (`Number of files:` statt `Number of regular files
  transferred:`); im Probelauf meldet es ausserdem 0 uebertragene Dateien.
  Aussagekraeftig ist dort die Gesamtgroesse.

### Nachtrag Sicherung des lokalen Standes (#8, 14.10.2026)

Lokal gegen die echte lokale Zentrale gelaufen (kein Live-Zugriff noetig):

| Probe | Ergebnis |
|---|---|
| Sicherung von `sun` (7,4 GB) | **1,07 GB in 72 s** nach `storage/app/db-pull/vor-pull-20261014-165648.sql.gz`, `gzip -t` ok |
| Inhalt | `handwerker.test` 494-mal im Dump — die Test-Tenants sind drin |
| Rund um die Schleife (Probedatenbank) | `drop database` + `gunzip -c … \| mysql` → Datenbank mit Kollation `utf8mb4_unicode_ci` und allen 400 Zeilen wieder da, ohne Vorarbeit |
| Aufraeumen | vier Staende angelegt, nach `prune()` bleiben die **zwei** jungsten, die aelteren sind geloescht |
| Platzpruefung | gefordert 3,7 GB (halbe Datenbankgroesse), frei 48 GB → laeuft; bei zu wenig Platz Exit **64**, bevor etwas geholt wird |
| Rueckfrage voller Lauf | „20 lokale Portal-Zeile(n), davon 20 mit .test-Domain" — „no" bricht ab (Exit 64), `--no-interaction` fragt nicht |
| `--dry-run` | nennt Zielpfad, Datenbankgroesse, geforderten und freien Platz sowie die Zahl vorhandener Staende; schreibt nichts |
| Abgebrochener Dump (Zeitgrenze 60 s auf 7,4 GB) | Ergebnis `ok=false` mit der Zeitgrenze als Meldung, `*.sql.gz.part` geloescht, der vorige vollstaendige Stand unberuehrt — eine halbe Sicherung bleibt nie als vollstaendige liegen. Im Befehl heisst das Exit **70** und: es wird nichts geholt |

Der Dump laeuft mit dem lokalen `mysqldump` (Homebrew MySQL 9), nicht mit
`mariadb-dump`: `--set-gtid-purged=OFF` und `--column-statistics=0` gibt es
nur dort und muessen aus. `--events` bleibt weg — es gibt keine, und ohne
EVENT-Recht bricht `mysqldump` daran ab.
