#!/usr/bin/env bash
# Traegt die drei Produktionsschluessel auf sun ein und schaltet sie scharf (#120).
#
# Voraussetzung ist ausschliesslich Kontoarbeit, die vorher im Browser passiert
# (docs/messungen/produktionsschluessel-anleitung.md, Abschnitte 2 bis 4):
# Anthropic-Projekt mit Ausgabenlimit und Guthaben, Voyage-Konto, Google-
# Dienstkonto mit aktivierter Search Console API und client_email als Leser in
# mindestens einer Property.
#
# Alles danach macht dieses Skript: Datei ablegen, .env pflegen, Config-Cache
# neu bauen, Horizon durchstarten, die drei Proben fahren.
#
# Aufruf (Schluessel werden abgefragt, nichts landet in der Shell-Historie):
#   scripts/produktionsschluessel-eintragen.sh --dienstkonto ~/Downloads/search-console.json
#
# Nur die Proben wiederholen, ohne etwas zu aendern:
#   scripts/produktionsschluessel-eintragen.sh --pruefen
#
# Nicht interaktiv (z. B. aus einem anderen Skript): ANTHROPIC_API_KEY und
# VOYAGE_API_KEY vorher exportieren. Ein leerer Wert laesst die Zeile in der
# .env unangetastet.

set -euo pipefail

SSH_HOST="${SUN_SSH_HOST:-sun}"
APP_DIR="/home/sanitaerfinden/htdocs/sanitaerfinden.dev"
APP_USER="sanitaerfinden"
PHP="/usr/bin/php8.4"
KEY_PATH="storage/app/private/google/search-console.json"

DIENSTKONTO=""
NUR_PRUEFEN=0

while [ $# -gt 0 ]; do
  case "$1" in
    --dienstkonto) DIENSTKONTO="${2:-}"; shift 2 ;;
    --pruefen) NUR_PRUEFEN=1; shift ;;
    -h|--help) sed -n '2,26p' "$0"; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done

remote() { ssh -o BatchMode=yes "$SSH_HOST" "$@"; }

# Artisan laeuft nie als root: ein von root geschriebener Config-Cache sperrt
# PHP-FPM aus.
artisan_remote() {
  remote "su -s /bin/bash ${APP_USER} -c 'HOME=/home/${APP_USER}; cd ${APP_DIR} && $1'"
}

proben() {
  echo
  echo "== content:golive:check =="
  artisan_remote "${PHP} artisan content:golive:check" || true
  echo
  echo "== content:metrics:preflight =="
  artisan_remote "${PHP} artisan content:metrics:preflight" || true
  echo
  echo "== content:llm:ping =="
  artisan_remote "CONTENT_PIPELINE_ENABLED=true ${PHP} artisan content:llm:ping" || true
  echo
  echo "Fertig, wenn: golive:check zeigt bei 'Anthropic-Zugang' und"
  echo "'Search-Console-Dienstkonto' ein Haekchen und preflight bei"
  echo "'Sichtbare Properties'. Fehlerbilder: Anleitung Abschnitt 8."
}

if [ "$NUR_PRUEFEN" = "1" ]; then
  proben
  exit 0
fi

# --- Schluessel einsammeln, ohne sie ueber die Kommandozeile zu reichen ------
if [ -z "${ANTHROPIC_API_KEY:-}" ] && [ -t 0 ]; then
  printf 'ANTHROPIC_API_KEY (leer = unveraendert lassen): '
  read -rs ANTHROPIC_API_KEY; echo
fi
if [ -z "${VOYAGE_API_KEY:-}" ] && [ -t 0 ]; then
  printf 'VOYAGE_API_KEY (leer = unveraendert lassen): '
  read -rs VOYAGE_API_KEY; echo
fi
ANTHROPIC_API_KEY="${ANTHROPIC_API_KEY:-}"
VOYAGE_API_KEY="${VOYAGE_API_KEY:-}"

case "$ANTHROPIC_API_KEY" in
  ''|sk-ant-*) ;;
  *) echo "ANTHROPIC_API_KEY sieht nicht wie ein Anthropic-Schluessel aus (sk-ant-…)." >&2; exit 65 ;;
esac

# --- Dienstkonto-Datei pruefen und ablegen -----------------------------------
if [ -n "$DIENSTKONTO" ]; then
  [ -f "$DIENSTKONTO" ] || { echo "Datei nicht gefunden: $DIENSTKONTO" >&2; exit 66; }
  # Der Go-Live-Check verlangt seit #119 lesbares JSON mit client_email und
  # private_key — lieber hier scheitern als auf dem Server.
  php -r '
    $d = json_decode(file_get_contents($argv[1]), true);
    if (! is_array($d) || ! isset($d["client_email"], $d["private_key"])) {
      fwrite(STDERR, "Datei ist kein Dienstkonto-JSON mit client_email und private_key.\n");
      exit(1);
    }
    fwrite(STDOUT, "Dienstkonto: {$d["client_email"]}\n");
  ' "$DIENSTKONTO"

  echo "Lege die Schluesseldatei ab (0600, ${APP_USER}) …"
  remote "install -d -m 0700 -o ${APP_USER} -g ${APP_USER} ${APP_DIR}/storage/app/private/google"
  remote "cat > ${APP_DIR}/${KEY_PATH}.neu && install -m 0600 -o ${APP_USER} -g ${APP_USER} ${APP_DIR}/${KEY_PATH}.neu ${APP_DIR}/${KEY_PATH} && rm -f ${APP_DIR}/${KEY_PATH}.neu" < "$DIENSTKONTO"
  remote "ls -l ${APP_DIR}/${KEY_PATH}"
fi

# --- .env pflegen -------------------------------------------------------------
# Skript und Werte gehen als ein einziger stdin-Strom zum Server: ueber argv
# stuenden die Schluessel dort in der Prozessliste. Vorhandene Zeilen werden
# ersetzt, nicht verdoppelt; ein leerer Wert laesst die Zeile unangetastet.
{
  cat <<REMOTE
set -euo pipefail
cd "${APP_DIR}"
cp -p .env ".env.bak-120-\$(date +%Y%m%d-%H%M%S)"
while IFS=\$'\t' read -r key val; do
  [ -n "\$val" ] || { echo "\$key: unveraendert"; continue; }
  grep -v "^\${key}=" .env > .env.neu
  printf '%s=%s\n' "\$key" "\$val" >> .env.neu
  cat .env.neu > .env
  rm -f .env.neu
  echo "\$key: gesetzt"
done <<'DATEN'
REMOTE
  printf 'ANTHROPIC_API_KEY\t%s\n' "$ANTHROPIC_API_KEY"
  printf 'VOYAGE_API_KEY\t%s\n' "$VOYAGE_API_KEY"
  [ -n "$DIENSTKONTO" ] && printf 'GOOGLE_SERVICE_ACCOUNT_JSON\t%s\n' "$KEY_PATH"
  # Gehoert fachlich zu #132, steht hier mit drin, weil es sonst einen zweiten
  # config:cache erzwingt und im Preflight als zweiter Fehler stehen bleibt.
  printf 'CONTENT_SOURCES_GSC_ENABLED\ttrue\n'
  cat <<REMOTE
DATEN
chown ${APP_USER}:${APP_USER} .env
REMOTE
} | remote "bash -s"

# --- Scharfschalten -----------------------------------------------------------
echo "Baue den Config-Cache neu und starte Horizon durch …"
artisan_remote "${PHP} artisan config:clear && ${PHP} artisan config:cache && ${PHP} artisan horizon:terminate"

proben
