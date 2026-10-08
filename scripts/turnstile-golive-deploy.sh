#!/usr/bin/env bash
# Fuehrt Abschnitt 1.3 der Go-Live-Checkliste des Bot-Schutzes auf der
# Produktion aus (docs/turnstile-golive.md, #36). Kein 'php dep deploy' — die
# Installation ist handgepflegt.
#
# Warum es dieses Skript gibt: §1.3 sind vierzehn Befehle von Hand auf einer
# handgepflegten Kiste, und drei der Fallen darin kosten im Fehlerfall einen
# Produktionsausfall oder eine nicht zurueckdrehbare Migration:
#   * CSP_MODE fehlt in der Produktions-.env, die Codevorgabe ist 'enforce' —
#     der erste config:cache des Deploys schaltet die CSP sonst in einem Schlag
#     auf allen 23 Portalen durchsetzend (siehe §1.5).
#   * 'npm run build' als root zerlegt public/build (Eigentuemer root) und
#     scheitert beim naechsten Lauf an rmSync; gebaut wird als
#     sanitaerfinden, nach 'npm ci' und nach chown von public/build.
#   * eine .env-Aenderung wirkt im Web erst nach config:cache PLUS
#     'systemctl reload php8.5-fpm' — ohne den Reload laeuft die Notbremse
#     TURNSTILE_ENABLED=false ins Leere, obwohl sie in der .env steht.
# Dazu die sieben 2026_10_09_*-Migrationen: sie laufen in der zentralen und in
# jeder der 23 Portal-Datenbanken. Vorher wird gedumpt, und zwar selbst — nicht
# auf die naechtlichen Dateien vertraut (die waren 2026 schon einmal leer).
#
# Aufruf:
#   scripts/turnstile-golive-deploy.sh              # Trockenlauf, aendert NICHTS
#   scripts/turnstile-golive-deploy.sh --los        # fuehrt aus, fragt einmal nach
#   scripts/turnstile-golive-deploy.sh --los --ohne-dump   # nur wenn frisch gedumpt
#
# Einstellbar: SUN_SSH_HOST (Vorgabe sun), APP_DIR, APP_USER, PHP_BIN.
#
# Das Protokoll landet unter docs/messungen/turnstile-golive/deploy-<datum>.log
# und ist der Beleg fuer die §1.3-Zeilen ("Ausgabe ablegen").
#
# Exit-Codes:
#   0   durchgelaufen (oder Trockenlauf ohne Befund)
#  64   falscher Aufruf
#  65   Produktion nicht erreichbar
#  66   Vorbedingung verletzt (B1/B2, Platz, Arbeitsbaum, Sicherung)
#  70   ein Schritt ist gescheitert — Abbruch, Stand steht im Protokoll

set -euo pipefail

SSH_HOST="${SUN_SSH_HOST:-sun}"
APP_DIR="${APP_DIR:-/home/sanitaerfinden/htdocs/sanitaerfinden.dev}"
APP_USER="${APP_USER:-sanitaerfinden}"
PHP_BIN="${PHP_BIN:-/usr/bin/php8.4}"
FPM_SERVICE="${FPM_SERVICE:-php8.5-fpm}"
HORIZON="${HORIZON:-sanitaerfinden-horizon}"
HIER="$(cd "$(dirname "$0")/.." && pwd)"
STAMPEL="$(date +%Y%m%d-%H%M%S)"
PROTOKOLL="${HIER}/docs/messungen/turnstile-golive/deploy-${STAMPEL}.log"
MIN_FREI_GB=50

LOS=0
OHNE_DUMP=0
while [ $# -gt 0 ]; do
  case "$1" in
    --los) LOS=1; shift ;;
    --ohne-dump) OHNE_DUMP=1; shift ;;
    -h|--help) sed -n '2,40p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done

remote() { ssh -o BatchMode=yes -o ConnectTimeout=20 "$SSH_HOST" "$@"; }
als_app() { remote "su -s /bin/bash ${APP_USER} -c 'HOME=/home/${APP_USER}; cd ${APP_DIR} && $1'"; }

# Dump-Skript fuer Schritt 3. Laeuft als Anwendungsbenutzer im Anwendungspfad,
# liest die Zugangsdaten aus der dortigen .env und gibt nur Groessen aus. Es geht
# als stdin-Strom hinueber: ueber argv stuenden die Zugangsdaten in der
# Prozessliste der Produktion.
dump_skript() {
  printf 'ziel=%s\n' "storage/app/backups/golive-36/${STAMPEL}"
  cat <<'DUMP'
set -euo pipefail
mkdir -p "$ziel"
wert() { grep -m1 "^$1=" .env | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
user="$(wert DB_USERNAME)"; haupt="$(wert DB_DATABASE)"
MYSQL_PWD="$(wert DB_PASSWORD)"; export MYSQL_PWD
listen="$haupt $(mariadb -u "$user" -N -B -e "show databases like 'tenant_%'")"
anz=0
for db in $listen; do
  mariadb-dump -u "$user" --single-transaction --quick "$db" | gzip -1 > "$ziel/$db.sql.gz"
  groesse=$(stat -c %s "$ziel/$db.sql.gz")
  if [ "$groesse" -lt 1024 ]; then
    echo "FEHLER: Dump von $db ist nur $groesse Byte" >&2
    exit 1
  fi
  anz=$((anz+1))
done
unset MYSQL_PWD
echo "$anz Datenbanken gedumpt, $(du -sh "$ziel" | cut -f1) unter $ziel"
if [ "$anz" -lt 24 ]; then
  echo "FEHLER: nur $anz Datenbanken gedumpt, erwartet 24 (zentral + 23 Portale)" >&2
  exit 1
fi
DUMP
}

log() { printf '%s\n' "$*" | tee -a "$PROTOKOLL"; }
kopf() { log ""; log "=== $* ==="; }

# schritt <beschreibung> <befehl-als-root|app> <wo: root|app>
schritt() {
  local text="$1" befehl="$2" wo="${3:-app}"
  if [ "$LOS" -eq 0 ]; then
    log "  [Trockenlauf] ${text}"
    log "                ${wo}: ${befehl}"
    return 0
  fi
  log "  -> ${text}"
  log "     ${wo}: ${befehl}"
  if [ "$wo" = "root" ]; then
    remote "set -euo pipefail; cd ${APP_DIR} && ${befehl}" 2>&1 | tee -a "$PROTOKOLL"
  else
    als_app "${befehl}" 2>&1 | tee -a "$PROTOKOLL"
  fi
}

mkdir -p "$(dirname "$PROTOKOLL")"
log "Deploy Bot-Schutz §1.3 — $(date '+%d.%m.%Y %H:%M')"
log "Modus: $([ "$LOS" -eq 1 ] && echo 'AUSFUEHREN' || echo 'Trockenlauf (nichts wird geaendert)')"
log "Ziel: ${SSH_HOST}:${APP_DIR}"

# --------------------------------------------------------------- Vorbedingungen
BEFUND=0
kopf "Vorbedingungen"

if ! remote true 2>/dev/null; then
  log "FEHLER: ssh ${SSH_HOST} nicht erreichbar."
  exit 65
fi

cd "$HIER"
git fetch --quiet origin 2>/dev/null || true
if git cat-file -e "origin/main:config/turnstile.php" 2>/dev/null; then
  ZIEL="$(git rev-parse --short origin/main)"
  log "  [x] B2: Bot-Schutz liegt auf origin/main (${ZIEL})"
else
  ZIEL="(fehlt)"
  log "  [ ] B2: config/turnstile.php liegt nicht auf origin/main."
  log "          Commit und Push brauchen die Freigabe von Enes — Gatter von #36."
  BEFUND=$((BEFUND+1))
fi

ROH="$(remote "cd ${APP_DIR} && {
  echo \"COMMIT=\$(git log --oneline -1 | cut -d' ' -f1)\"
  echo \"DRECK=\$(git status --porcelain | grep -cv '^??' || true)\"
  echo \"UNVERF=\$(git status --porcelain | grep -c '^??' || true)\"
  echo \"KEYS=\$(grep -c '^TURNSTILE_SITE_KEY\|^TURNSTILE_SECRET_KEY' .env || true)\"
  echo \"TESTKEY=\$(grep -c '^TURNSTILE_SITE_KEY.*=[123]x0000\|^TURNSTILE_SECRET_KEY.*=[123]x0000' .env || true)\"
  echo \"FREI=\$(df --output=avail -BG /home | tail -1 | tr -dc '0-9')\"
}")"
eval "$(printf '%s\n' "$ROH" | grep -E '^[A-Z]+=')"

log "  [-] Produktion steht auf ${COMMIT:-unbekannt}, Ziel ${ZIEL}"

if [ "${KEYS:-0}" -lt 6 ]; then
  log "  [ ] B1: nur ${KEYS:-0} von 6 TURNSTILE-Schluesselwerten in der Produktions-.env."
  log "          scripts/turnstile-schluessel-eintragen.sh — alle drei Gruppen VOR dem"
  log "          Deploy, sonst laeuft jedes Portal aus B/C in die TurnstileNotConfiguredException."
  BEFUND=$((BEFUND+1))
else
  log "  [x] B1: sechs Schluesselwerte stehen in der .env"
fi

if [ "${TESTKEY:-0}" -gt 0 ]; then
  log "  [ ] B1: ein Cloudflare-Testschluessel (1x/2x/3x0000…) steht in der Produktions-.env."
  log "          Testschluessel bestehen auf jeder Domain — Hostname- und Action-Check waeren tot."
  BEFUND=$((BEFUND+1))
else
  log "  [x] B1: kein Testschluessel in der .env"
fi

if [ "${DRECK:-1}" -ne 0 ]; then
  log "  [ ] ${DRECK} geaenderte verfolgte Datei(en) in Produktion — 'git pull --ff-only'"
  log "          wuerde scheitern. Erst sichten:"
  log "          ssh ${SSH_HOST} 'cd ${APP_DIR} && git status --porcelain | grep -v ^??'"
  BEFUND=$((BEFUND+1))
else
  # Unverfolgte Pfade sind dort der Normalfall (.cache/, public/vendor/livewire/,
  # shared/) und stoeren den Fast-Forward nicht — nur melden, nicht blockieren.
  log "  [x] Keine geaenderte verfolgte Datei in Produktion (${UNVERF:-0} unverfolgte Pfade, unkritisch)"
fi

if [ "${FREI:-0}" -lt "$MIN_FREI_GB" ]; then
  log "  [ ] Nur ${FREI:-?} GB frei unter /home — fuer Dump und node_modules zu wenig (< ${MIN_FREI_GB} GB)."
  BEFUND=$((BEFUND+1))
else
  log "  [x] ${FREI} GB frei unter /home"
fi

# Naechtliche Sicherung sichten, aber nicht darauf verlassen. Gezaehlt wird von
# den LEBENDEN Datenbanken her, nicht von den Sicherungsordnern: 'clpctl
# db:backup' sichert nur, was in CloudPanels eigener db.sq3 steht, und die zehn
# Portal-Datenbanken, die TenantCreationService selbst angelegt hat, stehen dort
# nicht (geprueft 08.10.2026, eigenes Ticket). Dazu liegen Ordner fuer laengst
# leere Datenbanken, deren Dump 521 Byte gross ist — ein Ordner ist also kein Beleg.
NACHT="$(remote "cd ${APP_DIR}
  U=\$(grep -m1 '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '\"')
  H=\$(grep -m1 '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '\"')
  MYSQL_PWD=\$(grep -m1 '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '\"'); export MYSQL_PWD
  d=/home/sanitaerfinden/backups/databases
  heute=\$(date +%F); gestern=\$(date -d yesterday +%F)
  lebend=0; gesichert=0
  for db in \$H \$(mariadb -u \$U -N -B -e \"show databases like 'tenant_%'\"); do
    lebend=\$((lebend+1))
    f=\$(ls -S \$d/\$db/\$heute/*.sql.gz \$d/\$db/\$gestern/*.sql.gz 2>/dev/null | head -1)
    [ -n \"\$f\" ] || continue
    [ \"\$(stat -c %s \"\$f\")\" -ge 1024 ] && gesichert=\$((gesichert+1))
  done
  unset MYSQL_PWD
  echo \"NLEBEND=\$lebend NGESICHERT=\$gesichert\"")"
eval "$(printf '%s\n' "$NACHT" | tr ' ' '\n' | grep -E '^N[A-Z]+=')"
if [ "${NGESICHERT:-0}" -lt "${NLEBEND:-24}" ]; then
  log "  [!] Naechtliche Sicherung deckt nur ${NGESICHERT:-0} von ${NLEBEND:-?} lebenden Datenbanken."
  log "      Das Skript dumpt deshalb in Schritt 3 selbst — ohne --ohne-dump kein Blocker."
  if [ "$OHNE_DUMP" -eq 1 ]; then
    log "  [ ] Mit --ohne-dump waere diese Luecke die einzige Sicherung vor den Migrationen."
    BEFUND=$((BEFUND+1))
  fi
else
  log "  [x] Naechtliche Sicherung deckt alle ${NLEBEND} lebenden Datenbanken"
fi

if [ "$BEFUND" -gt 0 ]; then
  log ""
  log "${BEFUND} Vorbedingung(en) verletzt — kein Deploy. Reihenfolge und Handgriffe:"
  log "  docs/turnstile-golive.md §1.1 bis §1.3, Stand: scripts/turnstile-golive-stand.sh"
  log "Protokoll: ${PROTOKOLL#"$HIER"/}"
  exit 66
fi
log "  Alle Vorbedingungen erfuellt."

if [ "$LOS" -eq 1 ]; then
  if [ -t 0 ]; then
    log ""
    printf 'Alles darueber gelesen? Dann "deploy" eintippen: '
    read -r ANTWORT
    [ "$ANTWORT" = "deploy" ] || { log "Abgebrochen."; exit 64; }
  else
    log "FEHLER: --los braucht ein Terminal (einmalige Rueckfrage). Abbruch."
    exit 64
  fi
fi

# ------------------------------------------------------------------- Schritte
kopf "1. Notbremse und CSP vorziehen (vor dem ersten config:cache)"
schritt "Sicherung der .env" \
  "cp -p .env .env.bak-36-${STAMPEL}" root
schritt "TURNSTILE_ENABLED=false und CSP_MODE=report setzen" \
  "for z in TURNSTILE_ENABLED=false CSP_MODE=report; do k=\${z%%=*}; grep -v \"^\${k}=\" .env > .env.neu; printf '%s\n' \"\$z\" >> .env.neu; cat .env.neu > .env; rm -f .env.neu; done; chown ${APP_USER}:${APP_USER} .env; grep -E '^TURNSTILE_ENABLED|^CSP_MODE' .env" root
schritt "Config-Cache neu bauen" "${PHP_BIN} artisan config:clear && ${PHP_BIN} artisan config:cache" app
schritt "PHP-FPM nachladen (ohne das wirkt die .env im Web nicht)" "systemctl reload ${FPM_SERVICE}" root

kopf "2. Codestand"
schritt "git pull --ff-only als root (nur root hat den GitHub-Schluessel)" \
  "git pull --ff-only && git log --oneline -1" root
schritt "Eigentuemer zuruecksetzen" \
  "chown -R ${APP_USER}:${APP_USER} ${APP_DIR}" root
schritt "composer install --no-dev -o" \
  "composer install --no-dev -o --no-interaction" app

kopf "3. Eigener Dump vor den Migrationen"
if [ "$OHNE_DUMP" -eq 1 ]; then
  log "  [-] uebersprungen (--ohne-dump)"
else
  DUMP_ZIEL="storage/app/backups/golive-36/${STAMPEL}"
  log "  -> zentrale und alle Portal-Datenbanken nach ${DUMP_ZIEL}/"
  if [ "$LOS" -eq 0 ]; then
    log "     [Trockenlauf] mariadb-dump je Datenbank (zentral + alle tenant_%), gzip -1"
  else
    # Das Dump-Skript geht als stdin-Strom hinueber: ueber argv stuenden die
    # Zugangsdaten in der Prozessliste der Produktion.
    dump_skript | remote "su -s /bin/bash ${APP_USER} -c 'HOME=/home/${APP_USER}; cd ${APP_DIR} && bash -s'" 2>&1 | tee -a "$PROTOKOLL"
  fi
fi

kopf "4. Assets"
schritt "Eigentuemer von public/build und node_modules" \
  "chown -R ${APP_USER}:${APP_USER} public/build node_modules 2>/dev/null || true" root
schritt "npm ci (neue Abhaengigkeiten, nie als root)" \
  "npm ci --no-audit --no-fund" app
schritt "npm run build — public/build ist nicht versioniert, resources/js/turnstile.js kommt aus dem Build" \
  "npm run build" app
log "  Hinweis: haengt der Build auf dem Server (2026 schon vorgekommen, > 10 min bei"
log "  'transforming'), lokal auf demselben Commit bauen und hochschieben:"
log "    npm run build && rsync -az public/build/ ${SSH_HOST}:${APP_DIR}/public/build/"
log "    ssh ${SSH_HOST} 'chown -R ${APP_USER}:${APP_USER} ${APP_DIR}/public/build'"
log "  Prozesse nie mit 'pkill -f \"vite build\"' beenden, das trifft die eigene"
log "  SSH-Sitzung — Muster '[v]ite build' verwenden."

kopf "5. Migrationen und Seeder"
schritt "migrate --force (zentral)" "${PHP_BIN} artisan migrate --force" app
schritt "tenants:migrate --force (23 Portale)" "${PHP_BIN} artisan tenants:migrate --force" app
schritt "TenantTurnstileSettingSeeder (erwartet 23 angelegt)" \
  "${PHP_BIN} artisan db:seed --class=TenantTurnstileSettingSeeder --force" app
schritt "Datenschutz-Backfill, erst trocken" \
  "${PHP_BIN} artisan tenants:datenschutz:turnstile-backfill" app
schritt "Datenschutz-Backfill schreibend" \
  "${PHP_BIN} artisan tenants:datenschutz:turnstile-backfill --write" app

kopf "6. Caches und Dienste"
schritt "config:cache, route:clear, view:clear, filament:optimize" \
  "${PHP_BIN} artisan config:clear && ${PHP_BIN} artisan config:cache && ${PHP_BIN} artisan route:clear && ${PHP_BIN} artisan view:clear && ${PHP_BIN} artisan filament:optimize" app
schritt "PHP-FPM nachladen" "systemctl reload ${FPM_SERVICE}" root
schritt "Queue und Horizon durchstarten" \
  "${PHP_BIN} artisan queue:restart && ${PHP_BIN} artisan horizon:terminate" app
schritt "Horizon-Dienst" "supervisorctl restart ${HORIZON}" root

kopf "Fertig"
if [ "$LOS" -eq 0 ]; then
  log "Trockenlauf beendet, nichts geaendert. Mit --los ausfuehren."
else
  log "§1.3 ist durch. Der Schutz ist global AUS (TURNSTILE_ENABLED=false) und die"
  log "CSP meldet nur (CSP_MODE=report) — beides absichtlich."
  log "Weiter im Dokument: §1.4 (antispam:backup), §1.5 (Scheduler, Verteiler,"
  log "24 h CSP-Messung), §1.6 (alle Portale herausnehmen, dann global scharf)."
fi
log "Protokoll: ${PROTOKOLL#"$HIER"/}"
log "Stand jederzeit: scripts/turnstile-golive-stand.sh"
