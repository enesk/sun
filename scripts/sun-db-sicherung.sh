#!/usr/bin/env bash
# Naechtliche Sicherung aller lebenden SUN-Datenbanken auf der Produktion (#39).
#
# Warum es dieses Skript gibt: 'clpctl db:backup' (/etc/cron.d/clp, 03:15)
# sichert ausschliesslich Datenbanken, die in CloudPanels eigener db.sq3
# registriert sind. Die Portal-Datenbanken, die TenantCreationService selbst
# anlegt, stehen dort nie — am 08.10.2026 hatten zehn von 24 lebenden
# Datenbanken (rund 3 GB) ueberhaupt keinen Sicherungsordner, waehrend neun
# Ordner zu Resten geloeschter Tenants gehoerten und mit 521-Byte-Dumps nach
# Abdeckung aussahen. Dieser Lauf geht deshalb von den **lebenden**
# Datenbanken aus (show databases) und nicht von CloudPanels Liste.
#
# Laeuft auf der Produktion als Benutzer 'sanitaerfinden', eingetragen in
# /etc/cron.d/sun-db-sicherung. Repo-Fassung ist die Quelle, installiert wird
# nach /usr/local/sbin/sun-db-sicherung.sh via
# scripts/db-sicherung-einrichten.sh.
#
# Zeitfenster: NICHT zwischen 8 und 22 Uhr aufrufen. In dem Fenster beendet
# /usr/local/sbin/backup-tar-waechter.sh laufende Backups, weil sie /home
# volllaufen lassen; ausserdem liegt die Last auf zwei rotierenden HDDs
# (md3, RAID1), ein Dumplauf zieht dort die Antwortzeiten der Portale hoch.
#
# Ablage: <ZIEL>/<YYYY-MM-DD>/<datenbank>.sql.gz, Aufbewahrung 7 Tagesordner.
# Eigener Pfad, nicht CloudPanels backups/databases — das verwaltet clpctl.
#
# Exit-Codes:
#   0   alle lebenden Datenbanken gesichert, jede Datei ueber 1 KB
#  64   falscher Aufruf
#  65   .env oder Datenbank nicht lesbar
#  66   zu wenige Datenbanken gefunden (Plausibilitaetsgrenze MIN_DB)
#  70   mindestens ein Dump fehlte oder blieb unter 1 KB

set -euo pipefail

APP_DIR="${APP_DIR:-/home/sanitaerfinden/htdocs/sanitaerfinden.dev}"
ZIEL="${ZIEL:-/home/sanitaerfinden/backups/datenbanken}"
PROTOKOLL="${PROTOKOLL:-/var/log/sun-db-sicherung.log}"
TAGE="${TAGE:-7}"
MIN_BYTES="${MIN_BYTES:-1024}"
MIN_DB="${MIN_DB:-20}"
DUMP_TIMEOUT="${DUMP_TIMEOUT:-3600}"
SPERRE="${SPERRE:-/tmp/sun-db-sicherung.lock}"

if [ $# -gt 0 ]; then
  case "$1" in
    -h|--help) sed -n '2,40p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
fi

# Nur ein Lauf gleichzeitig: ein zweiter wuerde auf derselben HDD mitlesen.
exec 9>"$SPERRE"
if ! flock -n 9; then
  echo "Es laeuft bereits eine Sicherung ($SPERRE) — Abbruch." >&2
  exit 0
fi

log() { printf '%s %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" | tee -a "$PROTOKOLL"; }

cd "$APP_DIR" || { echo "APP_DIR fehlt: $APP_DIR" >&2; exit 65; }

# Zugangsdaten nur ueber die Umgebung, nie ueber argv — sonst stehen sie in der
# Prozessliste der Produktion.
# '|| true': fehlt die Variable, liefert grep Exit 1 und pipefail wuerde das
# Skript vor der eigenen, verstaendlichen Fehlermeldung abbrechen.
wert() { { grep -m1 "^$1=" .env || true; } | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
DB_USER="$(wert DB_USERNAME)"
HAUPT="$(wert DB_DATABASE)"
MYSQL_PWD="$(wert DB_PASSWORD)"; export MYSQL_PWD
if [ -z "$DB_USER" ] || [ -z "$HAUPT" ] || [ -z "$MYSQL_PWD" ]; then
  echo "DB_USERNAME, DB_DATABASE oder DB_PASSWORD fehlt in ${APP_DIR}/.env" >&2
  exit 65
fi

if ! TENANTS="$(mariadb -u "$DB_USER" -N -B -e "show databases like 'tenant_%'")"; then
  echo "Datenbank nicht erreichbar." >&2
  exit 65
fi

LISTE="$HAUPT"
[ -n "$TENANTS" ] && LISTE="$HAUPT $TENANTS"
ERWARTET=$(printf '%s\n' $LISTE | wc -l)

log "=== Sicherung startet, ${ERWARTET} lebende Datenbanken"

# Plausibilitaetsgrenze: ein kaputtes 'show databases' darf nicht als Erfolg
# durchgehen. Die Erwartung kommt sonst bewusst aus dem Ist-Stand und nicht aus
# einer festen 24 — ein neues Portal soll die Sicherung nicht rot machen.
if [ "$ERWARTET" -lt "$MIN_DB" ]; then
  log "FEHLER: nur ${ERWARTET} Datenbanken gefunden, Untergrenze ${MIN_DB}."
  exit 66
fi

TAG="$(date +%Y-%m-%d)"
ORDNER="${ZIEL}/${TAG}"
mkdir -p "$ORDNER"
chmod 750 "$ZIEL" "$ORDNER"

FEHLER=0
ANZAHL=0
for db in $LISTE; do
  datei="${ORDNER}/${db}.sql.gz"
  # nice/ionice: /home liegt auf rotierenden Platten, der Dump darf die
  # Portale nicht ausbremsen.
  if ! nice -n 10 ionice -c2 -n7 timeout "$DUMP_TIMEOUT" \
      mariadb-dump -u "$DB_USER" --single-transaction --quick \
      --default-character-set=utf8mb4 --routines --events "$db" \
      | gzip -1 > "$datei"; then
    log "FEHLER: Dump von ${db} ist gescheitert."
    FEHLER=$((FEHLER+1))
    continue
  fi
  groesse=$(stat -c %s "$datei")
  if [ "$groesse" -lt "$MIN_BYTES" ]; then
    log "FEHLER: Dump von ${db} ist nur ${groesse} Byte (Grenze ${MIN_BYTES})."
    FEHLER=$((FEHLER+1))
    continue
  fi
  ANZAHL=$((ANZAHL+1))
done
unset MYSQL_PWD

chmod 640 "$ORDNER"/*.sql.gz 2>/dev/null || true
log "${ANZAHL} von ${ERWARTET} Datenbanken gesichert, $(du -sh "$ORDNER" | cut -f1) unter ${ORDNER}"

if [ "$FEHLER" -gt 0 ] || [ "$ANZAHL" -lt "$ERWARTET" ]; then
  log "FEHLER: ${FEHLER} Fehlschlaege — Aufbewahrung bleibt unangetastet."
  exit 70
fi

# Aufbewahrung erst nach einem fehlerfreien Lauf aufraeumen: ein kaputter Lauf
# darf nicht den letzten guten Stand wegwerfen.
for alt in "$ZIEL"/*/; do
  name="$(basename "$alt")"
  case "$name" in
    20[0-9][0-9]-[0-9][0-9]-[0-9][0-9]) ;;
    *) continue ;;
  esac
  if [ "$(date -d "$name" +%s 2>/dev/null || echo 0)" -lt "$(date -d "-${TAGE} days" +%s)" ]; then
    rm -rf -- "$alt"
    log "Aufbewahrung: ${name} entfernt (aelter als ${TAGE} Tage)."
  fi
done

log "=== fertig, Exit 0"
