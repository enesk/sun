#!/usr/bin/env bash
# Entfernt die Registrierungen geloeschter Tenant-Datenbanken aus CloudPanels
# db.sq3 auf der Produktion (#41, abgespalten aus #39).
#
# Hintergrund: 'clpctl db:backup' (/etc/cron.d/clp, 03:15) sichert genau die in
# db.sq3 registrierten Datenbanken. Neun Eintraege dort gehoeren zu Tenants,
# die es nicht mehr gibt — der Nachtlauf legt fuer sie jede Nacht einen Ordner
# mit einem 521-Byte-Dump an und die Ablage sieht nach Abdeckung aus, die es
# nicht gibt. Die echte Sicherung haengt daran nicht, sie laeuft seit #39 ueber
# /usr/local/sbin/sun-db-sicherung.sh (23:40, siehe docs/datenbanksicherung.md).
#
# WARUM MIT FREIGABE: 'clpctl db:delete' loescht die MariaDB-Datenbank mit,
# wenn der Name doch einer lebenden entspricht — unumkehrbar. Dieses Skript
# ermittelt die Kandidaten deshalb selbst und loescht nur Namen, die
#   1. in db.sq3 stehen,
#   2. in 'show databases' NICHT vorkommen,
#   3. als UUID NICHT in der Tabelle 'tenants' der zentralen Datenbank stehen,
#   4. dem Muster tenant_<uuid> entsprechen.
# Ist eine der Bedingungen fuer einen Namen verletzt, bricht der Lauf ab, statt
# ihn zu ueberspringen.
#
# Aufruf:
#   scripts/cloudpanel-tote-db-eintraege-entfernen.sh            # Trockenlauf, aendert NICHTS
#   scripts/cloudpanel-tote-db-eintraege-entfernen.sh --einzeln  # loescht NUR den ersten Kandidaten
#   scripts/cloudpanel-tote-db-eintraege-entfernen.sh --los      # loescht alle Kandidaten
#   scripts/cloudpanel-tote-db-eintraege-entfernen.sh --abnahme  # zaehlt nur, aendert nichts
#   scripts/cloudpanel-tote-db-eintraege-entfernen.sh --nur-sq3  # Rueckfall: nur die Zeile in db.sq3
#
# Empfohlene Reihenfolge: Trockenlauf, --einzeln, Ausgabe ansehen, --los,
# naechste Nacht abwarten (03:15), --abnahme.
#
# --nur-sq3 ist der Rueckfall, falls 'clpctl db:delete' den Eintrag nicht
# los wird, weil die MariaDB-Datenbank fehlt: dieselben vier Pruefungen, aber
# es wird ausschliesslich die Zeile in db.sq3 entfernt, nie eine Datenbank.
#
# Einstellbar: SUN_SSH_HOST (Vorgabe sun), APP_DIR, SQ3, APP_USER.
#
# Exit-Codes:
#   0   durchgelaufen
#  64   falscher Aufruf
#  65   Produktion oder Datenbank nicht erreichbar
#  66   Pruefung fehlgeschlagen — es wurde nichts geloescht
#  70   ein Loeschschritt ist gescheitert

set -euo pipefail

SSH_HOST="${SUN_SSH_HOST:-sun}"
APP_DIR="${APP_DIR:-/home/sanitaerfinden/htdocs/sanitaerfinden.dev}"
APP_USER="${APP_USER:-sanitaerfinden}"
SQ3="${SQ3:-/home/clp/htdocs/app/data/db.sq3}"

MODUS=trocken
while [ $# -gt 0 ]; do
  case "$1" in
    --einzeln) MODUS=einzeln; shift ;;
    --los) MODUS=los; shift ;;
    --abnahme) MODUS=abnahme; shift ;;
    --nur-sq3) MODUS=nur-sq3; shift ;;
    -h|--help) sed -n '2,45p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done

ferndienst() { ssh -o BatchMode=yes -o ConnectTimeout=20 "$SSH_HOST" bash -s -- "$@"; }

ssh -o BatchMode=yes -o ConnectTimeout=20 "$SSH_HOST" true >/dev/null 2>&1 \
  || { echo "ssh ${SSH_HOST} nicht erreichbar." >&2; exit 65; }

# Der Fernteil steht in einem *gequoteten* Heredoc: nichts davon wird lokal
# ersetzt, alle Werte kommen als Argumente an.
ferndienst "$MODUS" "$APP_DIR" "$SQ3" "$APP_USER" <<'FERNEENDE'
set -euo pipefail
MODUS="$1"; APP_DIR="$2"; SQ3="$3"; APP_USER="$4"
MIN_LEBEND="${MIN_LEBEND:-20}"
MAX_TOT="${MAX_TOT:-12}"

ARB="$(mktemp -d)"; trap 'rm -rf "$ARB"' EXIT
cd "$APP_DIR" || { echo "APP_DIR fehlt: $APP_DIR" >&2; exit 65; }

# Zugangsdaten nur ueber die Umgebung, nie ueber argv — sonst stehen sie in der
# Prozessliste der Produktion.
wert() { { grep -m1 "^$1=" .env || true; } | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
DB_USER="$(wert DB_USERNAME)"
HAUPT="$(wert DB_DATABASE)"
MYSQL_PWD="$(wert DB_PASSWORD)"; export MYSQL_PWD
if [ -z "$DB_USER" ] || [ -z "$HAUPT" ] || [ -z "$MYSQL_PWD" ]; then
  echo "DB_USERNAME, DB_DATABASE oder DB_PASSWORD fehlt in ${APP_DIR}/.env" >&2
  exit 65
fi

if ! mariadb -u "$DB_USER" -N -B -e "show databases like 'tenant_%'" | sort > "$ARB/lebend"; then
  echo "Datenbank nicht erreichbar." >&2; exit 65
fi
sqlite3 "$SQ3" "select name from database where name like 'tenant_%'" | sort > "$ARB/sq3"
mariadb -u "$DB_USER" -N -B "$HAUPT" -e "select concat('tenant_', uuid) from tenants" | sort > "$ARB/registriert"

LEBEND=$(wc -l < "$ARB/lebend")
EINTRAEGE=$(wc -l < "$ARB/sq3")
TENANTS=$(wc -l < "$ARB/registriert")

# Plausibilitaetsgrenze: ein kaputtes 'show databases' darf nicht dazu fuehren,
# dass lebende Datenbanken als tot gelten.
if [ "$LEBEND" -lt "$MIN_LEBEND" ]; then
  echo "ABBRUCH: nur ${LEBEND} lebende tenant_-Datenbanken gefunden, Untergrenze ${MIN_LEBEND}." >&2
  exit 66
fi

# Kandidat = in db.sq3, aber weder lebend noch in der Tabelle 'tenants'.
comm -13 "$ARB/lebend" "$ARB/sq3" \
  | comm -13 "$ARB/registriert" - \
  | grep -E '^tenant_[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$' \
  > "$ARB/tot" || true
TOT=$(wc -l < "$ARB/tot")

echo "Lebende tenant_-Datenbanken:      ${LEBEND}"
echo "Tenants in der zentralen Tabelle: ${TENANTS}"
echo "Eintraege in db.sq3:              ${EINTRAEGE}"
echo "davon tot (Kandidaten):           ${TOT}"
echo
if [ "$TOT" -gt 0 ]; then
  echo "Kandidaten:"
  sed 's/^/  /' "$ARB/tot"
  echo
fi

if [ "$TOT" -gt "$MAX_TOT" ]; then
  echo "ABBRUCH: ${TOT} Kandidaten sind mehr als die Obergrenze ${MAX_TOT} — bitte erst ansehen." >&2
  exit 66
fi

ordner_zaehlen() { ls /home/"${APP_USER}"/backups/databases/ 2>/dev/null | grep -c tenant_ || true; }

if [ "$MODUS" = abnahme ]; then
  echo "=== Abnahme (liest nur)"
  echo "Sicherungsordner clpctl mit tenant_: $(ordner_zaehlen)   (Ziel 13)"
  echo "Lebende tenant_-Datenbanken:        ${LEBEND}   (Ziel 23)"
  echo "Tote Eintraege in db.sq3:           ${TOT}   (Ziel 0)"
  echo "--- Waechter der eigenen Sicherung (erwartet Exit 0)"
  if su -s /bin/bash "$APP_USER" -c "HOME=/home/${APP_USER}; /usr/local/sbin/sun-db-sicherung-waechter.sh" >/dev/null 2>&1; then
    echo "    Exit 0"
  else
    echo "    FEHLER: Waechter meldet eine Luecke." >&2; exit 70
  fi
  exit 0
fi

if [ "$MODUS" = trocken ]; then
  echo "Trockenlauf — es wurde nichts geaendert."
  if [ "$TOT" -eq 0 ]; then
    echo "Keine toten Eintraege, nichts zu tun."
    exit 0
  fi
  echo "'--einzeln' wuerde ausfuehren:"
  echo "  clpctl db:delete --databaseName='$(head -1 "$ARB/tot")'"
  echo "'--los' dasselbe fuer alle ${TOT} Kandidaten."
  echo "Vorher wird ${SQ3} nach /root/ gesichert."
  exit 0
fi

if [ "$TOT" -eq 0 ]; then
  echo "Keine toten Eintraege, nichts zu tun."
  exit 0
fi

STEMPEL="$(date +%Y%m%d-%H%M%S)"
SICHERUNG="/root/db.sq3.vor-41-${STEMPEL}"
cp -p "$SQ3" "$SICHERUNG"
chmod 600 "$SICHERUNG"
echo "db.sq3 gesichert: ${SICHERUNG}"
sqlite3 -header -csv "$SQ3" "select * from database where name like 'tenant_%'" \
  > "/root/db-eintraege-vor-41-${STEMPEL}.csv"
echo "Eintraege gesichert: /root/db-eintraege-vor-41-${STEMPEL}.csv"
echo

if [ "$MODUS" = einzeln ]; then
  head -1 "$ARB/tot" > "$ARB/auftrag"
else
  cp "$ARB/tot" "$ARB/auftrag"
fi

FEHLER=0
while read -r name; do
  [ -n "$name" ] || continue
  echo "== ${name}"

  # Vier Pruefungen direkt vor dem Eingriff, nicht nur bei der Ermittlung.
  if mariadb -u "$DB_USER" -N -B -e "show databases" | grep -qx "$name"; then
    echo "   ABBRUCH: ${name} IST eine lebende Datenbank." >&2; exit 66
  fi
  if grep -qx "$name" "$ARB/registriert"; then
    echo "   ABBRUCH: ${name} steht in der Tabelle 'tenants'." >&2; exit 66
  fi
  if ! grep -qx "$name" "$ARB/sq3"; then
    echo "   ABBRUCH: ${name} steht nicht in db.sq3." >&2; exit 66
  fi
  if ! printf '%s' "$name" | grep -qE '^tenant_[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'; then
    echo "   ABBRUCH: ${name} passt nicht auf tenant_<uuid>." >&2; exit 66
  fi
  echo "   geprueft: nicht lebend, kein Tenant, in db.sq3, Namensmuster passt"

  if [ "$MODUS" = nur-sq3 ]; then
    sqlite3 "$SQ3" "delete from database where name = '${name}'"
  else
    # clpctl fragt "Confirm to delete the database ... (yes/no)" auf stdin ab.
    # Die Freigabe ist hier die vierfache Pruefung direkt darueber, deshalb
    # wird die Antwort eingespeist statt den Lauf haengen zu lassen.
    if AUSGABE="$(printf 'yes\n' | clpctl db:delete --databaseName="$name" 2>&1)"; then
      printf '%s\n' "$AUSGABE" | sed 's/^/   /'
    else
      RC=$?
      printf '%s\n' "$AUSGABE" | sed 's/^/   /'
      echo "   clpctl endete mit Exit ${RC} — siehe Pruefung der db.sq3-Zeile."
    fi
  fi

  if sqlite3 "$SQ3" "select 1 from database where name = '${name}'" | grep -q 1; then
    echo "   FEHLER: Eintrag steht weiterhin in db.sq3." >&2
    FEHLER=$((FEHLER+1))
  else
    echo "   Eintrag aus db.sq3 entfernt."
  fi
done < "$ARB/auftrag"

echo
VERBLEIBEND=$(sqlite3 "$SQ3" "select count(*) from database where name like 'tenant_%'")
NACH_LEBEND=$(mariadb -u "$DB_USER" -N -B -e "show databases like 'tenant_%'" | wc -l)
echo "Eintraege in db.sq3 jetzt: ${VERBLEIBEND}"
echo "Lebende tenant_-Datenbanken jetzt: ${NACH_LEBEND} (vorher ${LEBEND})"
if [ "$NACH_LEBEND" -ne "$LEBEND" ]; then
  echo "ALARM: Die Zahl der lebenden Datenbanken hat sich geaendert — sofort pruefen." >&2
  exit 70
fi
if [ "$FEHLER" -gt 0 ]; then
  echo "${FEHLER} Eintrag/Eintraege blieben stehen (Rueckfall: --nur-sq3)." >&2
  exit 70
fi

echo
echo "Fertig. Naechster Schritt: 03:15 abwarten (clpctl db:backup), dann --abnahme."
FERNEENDE
