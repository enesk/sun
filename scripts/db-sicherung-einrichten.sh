#!/usr/bin/env bash
# Installiert die eigene Datenbanksicherung samt Abdeckungs-Waechter auf der
# Produktion und nimmt sie ab (#39).
#
# Hintergrund: 'clpctl db:backup' sichert nur die in CloudPanels db.sq3
# registrierten Datenbanken; die Portal-Datenbanken aus TenantCreationService
# stehen dort nie. Am 08.10.2026 waren zehn von 24 lebenden Datenbanken ohne
# jede Sicherung. Die beiden Skripte dieser Einrichtung gehen von den lebenden
# Datenbanken aus — siehe scripts/sun-db-sicherung.sh.
#
# Aufruf:
#   scripts/db-sicherung-einrichten.sh            # Trockenlauf, aendert NICHTS
#   scripts/db-sicherung-einrichten.sh --los      # installiert und richtet Cron ein
#   scripts/db-sicherung-einrichten.sh --probe    # nur Sicherungslauf + Waechter
#   scripts/db-sicherung-einrichten.sh --stand    # zeigt Cron, Protokoll, Abdeckung
#
# Zeitplan (Benutzer sanitaerfinden, /etc/cron.d/sun-db-sicherung):
#   23:40  Sicherung  — ausserhalb 8-22 Uhr, sonst beendet
#          /usr/local/sbin/backup-tar-waechter.sh laufende Backups
#   05:30  Waechter   — nach Sicherung und nach clpctl db:backup (03:15)
#
# Einstellbar: SUN_SSH_HOST (Vorgabe sun), APP_DIR, APP_USER. Den Empfaenger
# der Luecken-Meldung setzt DB_BACKUP_ALERT_MAIL in der Produktions-.env.
#
# Exit-Codes:
#   0   durchgelaufen
#  64   falscher Aufruf
#  65   Produktion nicht erreichbar
#  70   ein Schritt ist gescheitert

set -euo pipefail

SSH_HOST="${SUN_SSH_HOST:-sun}"
APP_DIR="${APP_DIR:-/home/sanitaerfinden/htdocs/sanitaerfinden.dev}"
APP_USER="${APP_USER:-sanitaerfinden}"
HIER="$(cd "$(dirname "$0")/.." && pwd)"
SBIN=/usr/local/sbin
CRON=/etc/cron.d/sun-db-sicherung

MODUS=trocken
while [ $# -gt 0 ]; do
  case "$1" in
    --los) MODUS=los; shift ;;
    --probe) MODUS=probe; shift ;;
    --stand) MODUS=stand; shift ;;
    -h|--help) sed -n '2,30p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done

remote() { ssh -o BatchMode=yes -o ConnectTimeout=20 "$SSH_HOST" "$@"; }
als_app() { remote "su -s /bin/bash ${APP_USER} -c 'HOME=/home/${APP_USER}; $1'"; }

remote true >/dev/null 2>&1 || { echo "ssh ${SSH_HOST} nicht erreichbar." >&2; exit 65; }

cron_datei() {
  cat <<CRONEOF
# Eigene Sicherung aller lebenden SUN-Datenbanken (#39, Dimitri).
# 'clpctl db:backup' kennt nur die in db.sq3 registrierten Datenbanken und
# laesst die Portal-Datenbanken aus TenantCreationService aus.
# Nicht zwischen 8 und 22 Uhr laufen lassen: dann beendet
# /usr/local/sbin/backup-tar-waechter.sh laufende Backups.
# Protokolle: /var/log/sun-db-sicherung.log, /var/log/sun-db-sicherung-waechter.log
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
MAILTO=""
40 23 * * * ${APP_USER} ${SBIN}/sun-db-sicherung.sh >/dev/null 2>&1
30 5 * * * ${APP_USER} ${SBIN}/sun-db-sicherung-waechter.sh >/dev/null 2>&1
CRONEOF
}

if [ "$MODUS" = stand ]; then
  echo "=== Cron"
  remote "cat ${CRON} 2>/dev/null || echo '(nicht eingerichtet)'"
  echo "=== Skripte"
  remote "ls -la ${SBIN}/sun-db-sicherung.sh ${SBIN}/sun-db-sicherung-waechter.sh 2>&1"
  echo "=== Protokoll Sicherung (letzte 15 Zeilen)"
  remote "tail -15 /var/log/sun-db-sicherung.log 2>/dev/null || echo '(leer)'"
  echo "=== Protokoll Waechter (letzte 15 Zeilen)"
  remote "tail -15 /var/log/sun-db-sicherung-waechter.log 2>/dev/null || echo '(leer)'"
  echo "=== Ablage"
  remote "ls -la /home/${APP_USER}/backups/datenbanken/ 2>/dev/null | tail -12; for d in /home/${APP_USER}/backups/datenbanken/*/; do [ -d \"\$d\" ] && echo \"\$(basename \$d): \$(ls \$d | wc -l) Dateien, \$(du -sh \$d | cut -f1)\"; done"
  exit 0
fi

if [ "$MODUS" = trocken ]; then
  echo "Trockenlauf — nichts wird geaendert. Was '--los' tun wuerde:"
  echo
  echo "1. ${SBIN}/sun-db-sicherung.sh und ${SBIN}/sun-db-sicherung-waechter.sh anlegen (root, 0755)"
  echo "2. /var/log/sun-db-sicherung{,-waechter}.log anlegen, Eigentuemer ${APP_USER}"
  echo "3. Logrotate /etc/logrotate.d/sun-db-sicherung"
  echo "4. ${CRON} schreiben:"
  cron_datei | sed 's/^/     /'
  echo "5. Probelauf der Sicherung und des Waechters (siehe --probe)"
  exit 0
fi

if [ "$MODUS" = los ]; then
  echo "== 1. Skripte hochschieben"
  for s in sun-db-sicherung.sh sun-db-sicherung-waechter.sh; do
    remote "cat > ${SBIN}/${s} && chmod 0755 ${SBIN}/${s} && chown root:root ${SBIN}/${s}" < "${HIER}/scripts/${s}"
    echo "   ${SBIN}/${s}"
  done

  echo "== 2. Protokolle"
  remote "for l in /var/log/sun-db-sicherung.log /var/log/sun-db-sicherung-waechter.log; do
    touch \$l; chown ${APP_USER}:${APP_USER} \$l; chmod 0644 \$l; done"

  echo "== 3. Logrotate"
  remote "cat > /etc/logrotate.d/sun-db-sicherung" <<'ROTEOF'
/var/log/sun-db-sicherung.log /var/log/sun-db-sicherung-waechter.log {
    weekly
    rotate 8
    missingok
    notifempty
    compress
    delaycompress
    create 0644 sanitaerfinden sanitaerfinden
}
ROTEOF

  echo "== 4. Cron"
  cron_datei | remote "cat > ${CRON} && chmod 0644 ${CRON} && chown root:root ${CRON}"
  remote "cat ${CRON}" | sed 's/^/   /'

  MODUS=probe
fi

if [ "$MODUS" = probe ]; then
  echo "== 5. Probelauf der Sicherung (dauert einige Minuten, ~3 GB Nutzdaten)"
  als_app "${SBIN}/sun-db-sicherung.sh" || { echo "Sicherungslauf gescheitert (Exit $?)." >&2; exit 70; }

  echo "== 6. Abdeckung zaehlen"
  remote "for d in /home/${APP_USER}/backups/datenbanken/*/; do [ -d \"\$d\" ] && echo \"\$(basename \$d): \$(ls \$d | wc -l) Dateien, kleinste \$(ls -l \$d | awk 'NR>1{print \$5}' | sort -n | head -1) Byte, \$(du -sh \$d | cut -f1)\"; done"

  echo "== 7. Waechter (erwartet Exit 0)"
  if als_app "${SBIN}/sun-db-sicherung-waechter.sh"; then
    echo "   Waechter: Exit 0"
  else
    echo "   Waechter meldet eine Luecke (Exit $?)." >&2
    exit 70
  fi

  echo "== 8. Gegenprobe: einen Dump verschieben, Waechter muss meckern"
  # Der Originalpfad wird gemerkt und wiederhergestellt: ein 'mv' in den
  # Tagesordner zurueck wuerde die Datei unter dem Namen der Zwischenablage
  # ablegen, und der Waechter sucht nach <datenbank>.sql.gz.
  OPFER="$(remote "set -e
    tag=\$(ls -d /home/${APP_USER}/backups/datenbanken/*/ | tail -1)
    opfer=\$(ls \$tag/tenant_*.sql.gz | head -1)
    mv \"\$opfer\" /tmp/gegenprobe.sql.gz
    printf '%s' \"\$opfer\"")"
  echo "   verschoben: $(basename "$OPFER")"
  if als_app "${SBIN}/sun-db-sicherung-waechter.sh"; then
    echo "   FEHLER: Waechter meldet trotz fehlendem Dump Exit 0." >&2
    remote "mv /tmp/gegenprobe.sql.gz '${OPFER}'; chown ${APP_USER}:${APP_USER} '${OPFER}'"
    exit 70
  fi
  echo "   Waechter: Luecke erkannt (Exit != 0) — richtig"
  remote "set -e
    mv /tmp/gegenprobe.sql.gz '${OPFER}'
    chown ${APP_USER}:${APP_USER} '${OPFER}'
    echo '   zurueckgelegt'"
  als_app "${SBIN}/sun-db-sicherung-waechter.sh" >/dev/null && echo "   Waechter wieder Exit 0"
fi

echo
echo "Fertig. Stand jederzeit mit: scripts/db-sicherung-einrichten.sh --stand"
