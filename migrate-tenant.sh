#!/bin/bash
#======================================================================
# Tenant Migration Script (Full Pipeline)
# Migriert einen Tenant von widimedia.com → sanitaerfinden.dev
# inkl. DB-Import über den Laravel TenantImporter
#
# Usage: ./migrate-tenant.sh <domain> <tenant_uuid> [source_tenant_id] [photo_slug]
#
# Beispiel:
#   ./migrate-tenant.sh klempner-mueller.de abc123-def456 3 klempner-mueller
#
# photo_slug wird NUR für den Foto-Ordner-Pfad auf widimedia verwendet.
# Falls weggelassen, wird die Domain als Ordnername benutzt.
#
# Mit Queue (asynchron, Live-Watch):
#   USE_QUEUE=1 ./migrate-tenant.sh klempner-mueller.de abc123-def456 3 klempner-mueller
#
# Nicht-interaktiv (z.B. in Schleife):
#   INTERACTIVE=0 ./migrate-tenant.sh klempner-mueller.de abc123-def456 3 klempner-mueller
#======================================================================

set -euo pipefail

# ─── Konfiguration ───────────────────────────────────────────────────
REMOTE_HOST="widimedia.com"
REMOTE_USER="root"                          # SSH-User auf widimedia.com – anpassen!
REMOTE_WEBROOT="/var/www/vhosts/widimedia.com/httpdocs"

LOCAL_WEBROOT="/home/sanitaerfinden/htdocs/sanitaerfinden.dev"
LOCAL_OWNER="sanitaerfinden:sanitaerfinden"

ARTISAN="php artisan"
LOG_FILE="/tmp/migrate-tenant-$(date +%Y%m%d_%H%M%S).log"
DUMP_DIR="/tmp"

# ─── Farben ──────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; NC='\033[0m'

SCRIPT_START=$(date +%s)

log()   { echo -e "${BLUE}[$(date +%H:%M:%S)]${NC} $1" | tee -a "$LOG_FILE"; }
ok()    { echo -e "${GREEN}  [✓]${NC} $1" | tee -a "$LOG_FILE"; }
warn()  { echo -e "${YELLOW}  [!]${NC} $1" | tee -a "$LOG_FILE"; }
fail()  { echo -e "${RED}  [✗]${NC} $1" | tee -a "$LOG_FILE"; exit 1; }
step()  { echo -e "\n${CYAN}━━━ SCHRITT $1 ━━━${NC}" | tee -a "$LOG_FILE"; }

confirm() {
    if [ "${INTERACTIVE:-1}" = "0" ]; then return 0; fi
    echo -e "${YELLOW}$1${NC}"
    read -p "Fortfahren? (j/N): " answer
    [[ "$answer" =~ ^[jJyY]$ ]] || { warn "Abgebrochen."; exit 0; }
}

# ─── Parameter prüfen ───────────────────────────────────────────────
if [ $# -lt 2 ]; then
    echo ""
    echo "Usage: $0 <domain> <tenant_uuid> [source_tenant_id] [photo_slug]"
    echo ""
    echo "  domain            – Die Domain des Tenants (z.B. klempner-mueller.de)"
    echo "  tenant_uuid       – UUID des Tenants auf widimedia.com"
    echo "  source_tenant_id  – (Optional) Source-Tenant-ID im Dump, default: 0 (alle)"
    echo "  photo_slug        – (Optional) Ordnername für Fotos auf widimedia, default: Domain"
    echo ""
    echo "Umgebungsvariablen:"
    echo "  INTERACTIVE=0     – Nicht-interaktiver Modus"
    echo "  USE_QUEUE=1       – Import als Queue-Job mit Live-Watch"
    echo ""
    echo "Beispiel:"
    echo "  $0 klempner-mueller.de abc12345-def6-7890-ghij-klmnop 3 klempner-mueller"
    echo ""
    exit 1
fi

DOMAIN="$1"
TENANT_UUID="$2"
SOURCE_TENANT_ID="${3:-0}"
PHOTO_SLUG="${4:-${DOMAIN}}"

# ─── Input-Validierung ─────────────────────────────────────────────
[[ "$TENANT_UUID" =~ ^[a-zA-Z0-9_-]+$ ]] || fail "Ungültige Tenant UUID: '${TENANT_UUID}' — nur alphanumerische Zeichen, Bindestriche und Unterstriche erlaubt."
[[ "$DOMAIN" =~ ^[a-zA-Z0-9._-]+$ ]] || fail "Ungültige Domain: '${DOMAIN}'"

# Abgeleitete Werte
REMOTE_DB_NAME="tenants_${TENANT_UUID}"
LOCAL_TENANT_STORAGE="tenant${TENANT_UUID}"
REMOTE_PHOTO_PATH="${REMOTE_WEBROOT}/storage/app/public/${PHOTO_SLUG}/photos"
LOCAL_PHOTO_PATH="${LOCAL_WEBROOT}/storage/${LOCAL_TENANT_STORAGE}/app/public"
DUMP_FILE="${DUMP_DIR}/${REMOTE_DB_NAME}_$(date +%Y%m%d_%H%M%S).sql.gz"

# ─── Übersicht ───────────────────────────────────────────────────────
echo ""
echo "═══════════════════════════════════════════════════════════════"
echo "  Tenant Migration: ${DOMAIN}"
echo "═══════════════════════════════════════════════════════════════"
echo ""
echo "  Domain:                ${DOMAIN}"
echo "  Tenant UUID:           ${TENANT_UUID}"
echo "  Source Tenant ID:      ${SOURCE_TENANT_ID}"
echo "  Foto-Ordner:           ${PHOTO_SLUG}"
echo ""
echo "  Remote DB:             ${REMOTE_DB_NAME}"
echo "  Local Storage:         ${LOCAL_TENANT_STORAGE}"
echo ""
echo "  Quell-Fotos:           ${REMOTE_PHOTO_PATH}"
echo "  Ziel-Fotos:            ${LOCAL_PHOTO_PATH}"
echo ""
echo "  Log:                   ${LOG_FILE}"
echo ""

confirm "Alle Angaben korrekt?"

# ─── Cleanup-Trap ──────────────────────────────────────────────────
cleanup_on_exit() {
    local exit_code=$?
    if [ $exit_code -ne 0 ] && [ -f "${DUMP_FILE:-}" ]; then
        warn "Script mit Fehler beendet (Exit-Code: ${exit_code})"
        warn "Dump nicht aufgeräumt: ${DUMP_FILE}"
        warn "Manuell löschen: rm -f ${DUMP_FILE}"
    fi
}
trap cleanup_on_exit EXIT

# ─── Vorprüfungen ────────────────────────────────────────────────────
log "Vorprüfungen starten..."

# SSH
log "  Teste SSH-Verbindung zu ${REMOTE_HOST}..."
if ssh -o ConnectTimeout=5 -o BatchMode=yes "${REMOTE_USER}@${REMOTE_HOST}" "echo ok" &>/dev/null; then
    ok "SSH-Verbindung zu ${REMOTE_HOST}"
else
    fail "SSH-Verbindung fehlgeschlagen! Bitte setup-ssh-key.sh ausführen."
fi

# Laravel
log "  Prüfe Laravel-Installation in ${LOCAL_WEBROOT}..."
[ -f "${LOCAL_WEBROOT}/artisan" ] || fail "Laravel nicht gefunden: ${LOCAL_WEBROOT}"
ok "Laravel-Projekt gefunden"

# Remote-DB
log "  Prüfe Remote-DB '${REMOTE_DB_NAME}'..."
if ssh "${REMOTE_USER}@${REMOTE_HOST}" "mysql -e 'USE ${REMOTE_DB_NAME}'" 2>/dev/null; then
    ok "Remote-DB '${REMOTE_DB_NAME}' existiert"
else
    fail "Remote-DB '${REMOTE_DB_NAME}' nicht gefunden!"
fi

log "Vorprüfungen abgeschlossen"

# ═════════════════════════════════════════════════════════════════════
step "1/7: Tenant registrieren"
# ═════════════════════════════════════════════════════════════════════
cd "${LOCAL_WEBROOT}"
log "  Wechsle in: ${LOCAL_WEBROOT}"
log "  Führe aus: php artisan register-tenant ${DOMAIN}"
${ARTISAN} register-tenant "${DOMAIN}" 2>&1 | tee -a "$LOG_FILE"
ok "Tenant registriert: ${DOMAIN}"

# ═════════════════════════════════════════════════════════════════════
step "2/7: Tenant Storage anlegen"
# ═════════════════════════════════════════════════════════════════════
log "  Führe aus: php artisan tenants:storage"
${ARTISAN} tenants:storage 2>&1 | tee -a "$LOG_FILE"
ok "Tenant Storage erstellt"

# ═════════════════════════════════════════════════════════════════════
step "3/7: Domain/VHost hinzufügen"
# ═════════════════════════════════════════════════════════════════════
log "  Führe aus: php artisan add-domain ${DOMAIN}"
${ARTISAN} add-domain "${DOMAIN}" 2>&1 | tee -a "$LOG_FILE"
ok "Domain hinzugefügt: ${DOMAIN}"

# ═════════════════════════════════════════════════════════════════════
step "4/7: DB-Dump von widimedia.com ziehen"
# ═════════════════════════════════════════════════════════════════════
log "  Quelle: ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DB_NAME}"
log "  Ziel: ${DUMP_FILE}"
log "  Starte mysqldump mit --single-transaction --routines --triggers --quick..."
log "  Komprimiere mit gzip und übertrage via SSH..."

DUMP_START=$(date +%s)

ssh "${REMOTE_USER}@${REMOTE_HOST}" \
    "mysqldump --single-transaction --routines --triggers --quick '${REMOTE_DB_NAME}' | gzip" \
    > "${DUMP_FILE}" 2>> "$LOG_FILE"

DUMP_END=$(date +%s)
DUMP_DURATION=$((DUMP_END - DUMP_START))

[ -s "${DUMP_FILE}" ] || fail "DB-Dump ist leer!"

DUMP_SIZE=$(du -h "${DUMP_FILE}" | cut -f1)
log "  Dump-Dauer: ${DUMP_DURATION}s"
ok "DB-Dump erstellt: ${DUMP_FILE} (${DUMP_SIZE})"

# ═════════════════════════════════════════════════════════════════════
step "5/7: Fotos synchronisieren"
# ═════════════════════════════════════════════════════════════════════
log "  Erstelle lokalen Foto-Ordner: ${LOCAL_PHOTO_PATH}"
mkdir -p "${LOCAL_PHOTO_PATH}"

log "  Prüfe ob Remote-Foto-Ordner existiert: ${REMOTE_PHOTO_PATH}"
if ssh "${REMOTE_USER}@${REMOTE_HOST}" "test -d '${REMOTE_PHOTO_PATH}'"; then
    ok "Remote-Foto-Ordner gefunden"
    PHOTO_COUNT=$(ssh "${REMOTE_USER}@${REMOTE_HOST}" "find '${REMOTE_PHOTO_PATH}' -type f | wc -l")
    log "  Gefundene Fotos: ${PHOTO_COUNT}"
    log "  Starte rsync (komprimiert, mit Fortschritt)..."

    RSYNC_START=$(date +%s)

    rsync -avz --progress \
        "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PHOTO_PATH}/" \
        "${LOCAL_PHOTO_PATH}/" \
        2>&1 | tee -a "$LOG_FILE"

    RSYNC_END=$(date +%s)
    RSYNC_DURATION=$((RSYNC_END - RSYNC_START))
    log "  Rsync-Dauer: ${RSYNC_DURATION}s"
    ok "Fotos synchronisiert (${PHOTO_COUNT} Dateien in ${RSYNC_DURATION}s)"
else
    warn "Remote-Ordner existiert nicht: ${REMOTE_PHOTO_PATH}"
    warn "Überspringe Foto-Sync"
fi

# ═════════════════════════════════════════════════════════════════════
step "6/7: TenantImporter ausführen"
# ═════════════════════════════════════════════════════════════════════
log "  Starte Import: ${DUMP_FILE} → Tenant ${DOMAIN}"

IMPORT_ARGS=(--tenant="${DOMAIN}" --source-tenant-id="${SOURCE_TENANT_ID}")

# Queue-Modus: Import als Queue-Job mit Live-Watch
if [ "${USE_QUEUE:-0}" = "1" ]; then
    IMPORT_ARGS+=(--queue)
    log "  Queue-Modus: --queue hinzugefügt (Import läuft asynchron)"
fi

# Im nicht-interaktiven Modus: ohne Vorschau, mit Force
if [ "${INTERACTIVE:-1}" = "0" ]; then
    IMPORT_ARGS+=(--no-preview --force)
    log "  Nicht-interaktiver Modus: --no-preview --force hinzugefügt"
fi

log "  Führe aus: php artisan tenant:import-dump ${DUMP_FILE} ${IMPORT_ARGS[*]}"

cd "${LOCAL_WEBROOT}"

IMPORT_START=$(date +%s)

# set -e deaktivieren, damit wir den Exit-Code manuell prüfen können
set +e
${ARTISAN} tenant:import-dump "${DUMP_FILE}" "${IMPORT_ARGS[@]}" 2>&1 | tee -a "$LOG_FILE"
IMPORT_EXIT=${PIPESTATUS[0]}
set -e

IMPORT_END=$(date +%s)
IMPORT_DURATION=$((IMPORT_END - IMPORT_START))

if [ $IMPORT_EXIT -ne 0 ]; then
    fail "TenantImporter fehlgeschlagen! (Exit-Code: ${IMPORT_EXIT}, Dauer: ${IMPORT_DURATION}s)"
fi

log "  Import-Dauer: ${IMPORT_DURATION}s"
ok "TenantImporter erfolgreich (${IMPORT_DURATION}s)"

# ═════════════════════════════════════════════════════════════════════
step "7/7: Berechtigungen setzen"
# ═════════════════════════════════════════════════════════════════════
# Tenant-Storage
if [ -d "${LOCAL_WEBROOT}/storage/${LOCAL_TENANT_STORAGE}" ]; then
    log "  chown -R ${LOCAL_OWNER} storage/${LOCAL_TENANT_STORAGE}"
    chown -R ${LOCAL_OWNER} "${LOCAL_WEBROOT}/storage/${LOCAL_TENANT_STORAGE}"
    log "  chmod -R 775 storage/${LOCAL_TENANT_STORAGE}"
    chmod -R 775 "${LOCAL_WEBROOT}/storage/${LOCAL_TENANT_STORAGE}"
    ok "Tenant-Storage Berechtigungen gesetzt"
fi

# Storage allgemein
log "  chown -R ${LOCAL_OWNER} storage/"
chown -R ${LOCAL_OWNER} "${LOCAL_WEBROOT}/storage"
log "  chmod -R 775 storage/framework"
chmod -R 775 "${LOCAL_WEBROOT}/storage/framework"
log "  chmod -R 775 storage/logs"
chmod -R 775 "${LOCAL_WEBROOT}/storage/logs"
ok "Storage-Berechtigungen gesetzt"

# Bootstrap/Cache
log "  chown -R ${LOCAL_OWNER} bootstrap/cache"
chown -R ${LOCAL_OWNER} "${LOCAL_WEBROOT}/bootstrap/cache"
log "  chmod -R 775 bootstrap/cache"
chmod -R 775 "${LOCAL_WEBROOT}/bootstrap/cache"
ok "Bootstrap/Cache-Berechtigungen gesetzt"

ok "Alle Berechtigungen gesetzt auf ${LOCAL_OWNER}"

# ═════════════════════════════════════════════════════════════════════
# Abschluss
# ═════════════════════════════════════════════════════════════════════
SCRIPT_END=$(date +%s)
TOTAL_DURATION=$((SCRIPT_END - SCRIPT_START))
TOTAL_MIN=$((TOTAL_DURATION / 60))
TOTAL_SEC=$((TOTAL_DURATION % 60))

echo ""
echo "═══════════════════════════════════════════════════════════════"
echo -e "  ${GREEN}✅ Migration abgeschlossen: ${DOMAIN}${NC}"
echo -e "  Gesamtdauer: ${TOTAL_MIN}m ${TOTAL_SEC}s"
echo "═══════════════════════════════════════════════════════════════"
echo ""
echo "  Nächste Schritte:"
echo "    1. DNS A-Record: ${DOMAIN} → IP von sanitaerfinden.dev"
echo "    2. SSL: certbot --nginx -d ${DOMAIN}"
echo "    3. Testen: https://${DOMAIN}"
echo ""
echo "  Log: ${LOG_FILE}"
echo ""

# Dump aufräumen
if [ "${INTERACTIVE:-1}" != "0" ]; then
    read -p "DB-Dump löschen? (${DUMP_FILE}) (j/N): " cleanup
    if [[ "$cleanup" =~ ^[jJyY]$ ]]; then
        rm -f "${DUMP_FILE}"
        ok "Dump gelöscht"
    fi
else
    rm -f "${DUMP_FILE}"
    ok "Dump gelöscht (nicht-interaktiver Modus)"
fi
