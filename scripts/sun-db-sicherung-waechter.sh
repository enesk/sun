#!/usr/bin/env bash
# Prueft die Abdeckung der Datenbanksicherung auf der Produktion (#39).
#
# Warum es diesen Waechter gibt: die Luecke von 09/2026 fiel erst am 08.10.2026
# bei einem Eingriff auf — zehn Portal-Datenbanken hatten monatelang keine
# Sicherung, und neun 521-Byte-Dumps geloeschter Tenants liessen die Ablage
# vollstaendig aussehen. Eine Sicherung, deren Abdeckung niemand prueft, ist
# keine Sicherung. Der Waechter geht wie der Sicherungslauf von den
# **lebenden** Datenbanken aus und sucht zu jeder eine frische, nicht leere
# Datei.
#
# Laeuft auf der Produktion als Benutzer 'sanitaerfinden', eingetragen in
# /etc/cron.d/sun-db-sicherung. Er liest nur, dumpt nicht und darf deshalb auch
# in der Geschaeftszeit laufen.
#
# Pruefkriterien je lebender Datenbank:
#   * irgendwo unter <ZIEL>/<tag>/ eine Datei <datenbank>.sql.gz
#   * juenger als MAX_STUNDEN (Vorgabe 26 — ein Tag plus Puffer)
#   * groesser als MIN_BYTES (Vorgabe 1024)
# Zusaetzlich gemeldet (ohne Fehler): Dumps, zu denen es keine lebende
# Datenbank mehr gibt — Reste geloeschter Tenants.
#
# Exit-Codes:
#   0   jede lebende Datenbank hat einen frischen Dump
#  64   falscher Aufruf
#  65   .env oder Datenbank nicht lesbar
#  70   Luecke: mindestens eine Datenbank ohne frischen Dump

set -euo pipefail

APP_DIR="${APP_DIR:-/home/sanitaerfinden/htdocs/sanitaerfinden.dev}"
ZIEL="${ZIEL:-/home/sanitaerfinden/backups/datenbanken}"
PROTOKOLL="${PROTOKOLL:-/var/log/sun-db-sicherung-waechter.log}"
MIN_BYTES="${MIN_BYTES:-1024}"
MAX_STUNDEN="${MAX_STUNDEN:-26}"
# Empfaenger der Luecken-Mail. Reihenfolge: Umgebung MELDEN_AN, dann
# DB_BACKUP_ALERT_MAIL aus der .env (Projektkonvention: Empfaenger stehen in
# der .env, nicht im Code). Bleibt beides leer, gibt es nur Protokoll und
# Exit-Code — das ist der Hauptmeldeweg, die Mail ist die Zugabe.
MELDEN_AN="${MELDEN_AN:-}"

if [ $# -gt 0 ]; then
  case "$1" in
    -h|--help) sed -n '2,35p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
fi

log() { printf '%s %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" | tee -a "$PROTOKOLL"; }

cd "$APP_DIR" || { echo "APP_DIR fehlt: $APP_DIR" >&2; exit 65; }

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
unset MYSQL_PWD

if [ -z "$MELDEN_AN" ]; then MELDEN_AN="$(wert DB_BACKUP_ALERT_MAIL)"; fi

# 'show databases' liefert eine Zeile je Name. Hier bewusst auf eine
# leerzeichengetrennte Liste normalisieren: die Waisen-Pruefung unten vergleicht
# mit dem Muster " $name " und wuerde an Zeilenumbruechen jede lebende
# Datenbank als Waise melden.
LISTE="$(printf '%s\n' $HAUPT $TENANTS | tr '\n' ' ')"
GESAMT=$(printf '%s\n' $LISTE | wc -w)

JETZT=$(date +%s)
GRENZE=$((JETZT - MAX_STUNDEN * 3600))

LUECKEN=""
OK=0
for db in $LISTE; do
  neuste=""
  neuste_zeit=0
  for datei in "$ZIEL"/*/"${db}.sql.gz"; do
    [ -f "$datei" ] || continue
    zeit=$(stat -c %Y "$datei")
    if [ "$zeit" -gt "$neuste_zeit" ]; then
      neuste_zeit=$zeit
      neuste="$datei"
    fi
  done

  if [ -z "$neuste" ]; then
    LUECKEN="${LUECKEN}  ${db}: keine Sicherungsdatei unter ${ZIEL}\n"
    continue
  fi
  groesse=$(stat -c %s "$neuste")
  if [ "$neuste_zeit" -lt "$GRENZE" ]; then
    LUECKEN="${LUECKEN}  ${db}: jüngster Dump vom $(date -d "@${neuste_zeit}" '+%d.%m.%Y %H:%M'), älter als ${MAX_STUNDEN} h\n"
    continue
  fi
  if [ "$groesse" -lt "$MIN_BYTES" ]; then
    LUECKEN="${LUECKEN}  ${db}: jüngster Dump nur ${groesse} Byte (Grenze ${MIN_BYTES})\n"
    continue
  fi
  OK=$((OK+1))
done

# Reste geloeschter Tenants: Dumps ohne lebende Datenbank. Kein Fehler, aber
# sie sollen nicht unbemerkt Platz belegen und Abdeckung vortaeuschen.
WAISEN=""
for datei in "$ZIEL"/*/*.sql.gz; do
  [ -f "$datei" ] || continue
  name="$(basename "$datei" .sql.gz)"
  case " $LISTE " in
    *" $name "*) ;;
    *) printf '%s\n' "$name" ;;
  esac
done | sort -u > /tmp/sun-db-waisen.$$ || true
if [ -s /tmp/sun-db-waisen.$$ ]; then
  WAISEN="$(tr '\n' ' ' < /tmp/sun-db-waisen.$$)"
fi
rm -f /tmp/sun-db-waisen.$$

if [ -n "$WAISEN" ]; then
  log "Hinweis: Dumps ohne lebende Datenbank: ${WAISEN}"
fi

if [ -z "$LUECKEN" ]; then
  log "Abdeckung vollständig: ${OK} von ${GESAMT} lebenden Datenbanken haben einen Dump jünger als ${MAX_STUNDEN} h und größer als ${MIN_BYTES} Byte."
  exit 0
fi

log "LÜCKE: ${OK} von ${GESAMT} lebenden Datenbanken gesichert. Fehlend:"
printf "%b" "$LUECKEN" | tee -a "$PROTOKOLL"

if [ -n "$MELDEN_AN" ] && command -v mail >/dev/null 2>&1; then
  {
    echo "Die Datenbanksicherung auf der Produktion deckt nicht alle lebenden Datenbanken ab."
    echo
    echo "${OK} von ${GESAMT} in Ordnung. Fehlend:"
    printf "%b" "$LUECKEN"
    echo
    echo "Protokoll: ${PROTOKOLL}"
    echo "Sicherungslauf von Hand: sudo -u sanitaerfinden /usr/local/sbin/sun-db-sicherung.sh"
  } | mail -s "SUN: Lücke in der Datenbanksicherung (${OK}/${GESAMT})" "$MELDEN_AN" || true
fi

exit 70
