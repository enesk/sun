# Datenbanksicherung der Produktion

Stand 08.10.2026 (#39). Betrifft den Produktionsserver `ssh sun`
(88.198.64.145), Anwendung `/home/sanitaerfinden/htdocs/sanitaerfinden.dev`.

## Warum eine eigene Sicherung

`clpctl db:backup` (CloudPanel, `/etc/cron.d/clp`, 03:15) sichert
**ausschliesslich** Datenbanken, die in CloudPanels eigener `db.sq3`
registriert sind. Die Portal-Datenbanken, die `TenantCreationService` selbst
anlegt, stehen dort nie.

Befund vom 08.10.2026:

* 24 lebende Datenbanken (`sun` plus 23 `tenant_<uuid>`), zusammen rund 9,3 GB
  Nutzdaten.
* 14 davon waren gesichert. **Zehn Portal-Datenbanken hatten keinen
  Sicherungsordner**, u.a. unfallarzt.firmenfreund.de (788 MB),
  arztfinder.firmenfreund.de (589 MB), zahnarzt.firmenfreund.de (511 MB),
  energieberaterportal.net (420 MB), sanitaerfinder.com (54 MB).
* Neun vorhandene Ordner gehoerten zu Datenbanken, die es nicht mehr gibt
  (Reste geloeschter Tenants); ihre Dumps waren 521 Byte reiner
  `mariadb-dump`-Kopf und sahen nach Abdeckung aus.

Die Luecke war monatelang unbemerkt, weil niemand die Abdeckung geprueft hat.
Deshalb gehoert zur Sicherung ein Waechter.

## Was jetzt laeuft

`/etc/cron.d/sun-db-sicherung`, Benutzer `sanitaerfinden`:

| Uhrzeit | Lauf | Skript |
|---|---|---|
| 23:40 | Sicherung aller lebenden Datenbanken | `/usr/local/sbin/sun-db-sicherung.sh` |
| 05:30 | Abdeckungs-Waechter | `/usr/local/sbin/sun-db-sicherung-waechter.sh` |

Quelle im Repo: `scripts/sun-db-sicherung.sh`,
`scripts/sun-db-sicherung-waechter.sh`. Installiert und abgenommen wird mit
`scripts/db-sicherung-einrichten.sh` (`--los`, `--probe`, `--stand`).

`clpctl db:backup` laeuft unveraendert weiter — die 14 Dumps dort sind in
Ordnung, doppelte Sicherung schadet nicht. Verlassen darf man sich nur auf die
eigene.

### Zeitfenster

Der Lauf darf **nicht zwischen 8 und 22 Uhr** stattfinden: in dem Fenster
beendet `/usr/local/sbin/backup-tar-waechter.sh` laufende Backups, weil sie
`/home` volllaufen lassen. Ausserdem liegt `/home` auf zwei rotierenden HDDs
(md3, RAID1) — der Dumplauf braucht `nice`/`ionice`, sonst steigen die
Antwortzeiten der Portale.

### Ablage

`/home/sanitaerfinden/backups/datenbanken/<YYYY-MM-DD>/<datenbank>.sql.gz`,
Aufbewahrung 7 Tagesordner. Eigener Pfad, **nicht** CloudPanels
`backups/databases` — das verwaltet `clpctl`. Ein Tagesstand sind 24 Dateien
und rund 774 MB.

Das Aufraeumen der Aufbewahrung laeuft erst nach einem fehlerfreien Lauf: ein
kaputter Lauf darf nicht den letzten guten Stand wegwerfen.

### Abbruchbedingungen des Sicherungslaufs

* Dump unter 1 KB oder `mariadb-dump` gescheitert → Exit 70, Aufbewahrung
  bleibt unangetastet.
* weniger als `MIN_DB` (20) lebende Datenbanken gefunden → Exit 66. Die
  Erwartung kommt sonst aus dem Ist-Stand, nicht aus einer festen 24: ein neues
  Portal soll die Sicherung nicht rot machen, ein kaputtes
  `show databases` aber nicht als Erfolg durchgehen.
* `flock` gegen Ueberlappung — ein zweiter Lauf wuerde auf derselben HDD
  mitlesen.

### Waechter

Prueft je lebender Datenbank eine Datei `<datenbank>.sql.gz`, juenger als 26 h
und groesser als 1 KB. Luecke → Exit 70, Protokoll
`/var/log/sun-db-sicherung-waechter.log`, zusaetzlich eine Mail an
`DB_BACKUP_ALERT_MAIL` aus der `.env`, falls gesetzt. Dumps ohne lebende
Datenbank meldet er als Hinweis, nicht als Fehler.

Er liest nur und darf deshalb auch in der Geschaeftszeit laufen.

## Abnahme vom 08.10.2026

* Sicherungslauf: 24 von 24 Datenbanken, 774 MB, 47 s, kleinste Datei 352 KB,
  `gzip -t` auf allen 24 ohne Befund, `sun.sql.gz` 5746 Zeilen mit 88
  `CREATE TABLE`, groesster Tenant 3,5 Mio. Zeilen mit 50 `CREATE TABLE`.
* Waechter: Exit 0.
* Gegenprobe: ein Dump verschoben → Waechter Exit 70 mit Nennung der
  Datenbank; zurueckgelegt → wieder Exit 0.
* Aufbewahrung: ein Tagesordner mit Datum vor 7 Tagen wurde entfernt, der vom
  Vortag blieb stehen.

## Tote Sicherungsordner

Die neun Ordner geloeschter Tenants sind entfernt; vorher in
`/home/sanitaerfinden/backups/tote-sicherungsordner-39-20261008.tar.gz`
(29 KB) gesichert. Ihre Eintraege in CloudPanels `db.sq3` bestehen noch,
`clpctl db:backup` legt die Ordner also neu an — Entfernen ueber
`clpctl db:delete` braucht eine eigene Freigabe — #41.

## Von Hand

```bash
# Stand ansehen (Cron, Protokolle, Ablage)
scripts/db-sicherung-einrichten.sh --stand

# Sicherung sofort (nur ausserhalb 8-22 Uhr)
ssh sun "su -s /bin/bash sanitaerfinden -c 'HOME=/home/sanitaerfinden; /usr/local/sbin/sun-db-sicherung.sh'"

# Abdeckung pruefen (jederzeit)
ssh sun "su -s /bin/bash sanitaerfinden -c 'HOME=/home/sanitaerfinden; /usr/local/sbin/sun-db-sicherung-waechter.sh'"

# Eine Datenbank zuruecksichern
ssh sun "zcat /home/sanitaerfinden/backups/datenbanken/<tag>/<db>.sql.gz | mariadb -u sunny -p <db>"
```
