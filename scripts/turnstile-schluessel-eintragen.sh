#!/usr/bin/env bash
# Traegt die Turnstile-Produktionsschluessel der drei Widget-Gruppen auf sun
# ein und schaltet sie scharf (#14, docs/turnstile.md Abschnitt 8).
#
# Voraussetzung ist Kontoarbeit, die vorher passiert: die drei Widgets sind
# angelegt (scripts/turnstile-widgets-anlegen.sh oder im Dashboard) und
# Sitekey/Secret liegen im Passwort-Manager unter
# "SUN / Cloudflare Turnstile / Gruppe A|B|C".
#
# Alles danach macht dieses Skript: .env pflegen (mit Sicherung), Config-Cache
# neu bauen, PHP-FPM nachladen, Horizon durchstarten, je Gruppe die
# Siteverify-Probe fahren.
#
# Aufruf (Schluessel werden abgefragt, nichts landet in der Shell-Historie):
#   scripts/turnstile-schluessel-eintragen.sh
#
# Nur die Proben wiederholen, ohne etwas zu aendern:
#   scripts/turnstile-schluessel-eintragen.sh --pruefen
#
# Nicht interaktiv: die sechs Variablen vorher exportieren. Ein leerer Wert
# laesst die Zeile in der .env unangetastet — so laesst sich eine Gruppe
# nachtragen, ohne die anderen anzufassen.

set -euo pipefail

SSH_HOST="${SUN_SSH_HOST:-sun}"
APP_DIR="/home/sanitaerfinden/htdocs/sanitaerfinden.dev"
APP_USER="sanitaerfinden"
PHP="/usr/bin/php8.4"          # CLI
FPM_SERVICE="php8.5-fpm"       # Pool von sanitaerfinden.dev
HORIZON="sanitaerfinden-horizon"

VARIABLEN="TURNSTILE_SITE_KEY TURNSTILE_SECRET_KEY TURNSTILE_SITE_KEY_B TURNSTILE_SECRET_KEY_B TURNSTILE_SITE_KEY_C TURNSTILE_SECRET_KEY_C"

NUR_PRUEFEN=0
while [ $# -gt 0 ]; do
  case "$1" in
    --pruefen) NUR_PRUEFEN=1; shift ;;
    -h|--help) sed -n '2,22p' "$0"; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done

remote() { ssh -o BatchMode=yes "$SSH_HOST" "$@"; }

# Artisan laeuft nie als root: ein von root geschriebener Config-Cache sperrt
# PHP-FPM aus.
artisan_remote() {
  remote "su -s /bin/bash ${APP_USER} -c 'HOME=/home/${APP_USER}; cd ${APP_DIR} && $1'"
}

# Probe je Gruppe: Siteverify mit einem erfundenen Token. Ist das Secret falsch,
# antwortet Cloudflare 'invalid-input-secret'; ist es richtig, beanstandet es nur
# den Token ('invalid-input-response'). Damit ist das Secret geprueft, ohne ein
# Formular abzuschicken. Laeuft auf dem Server, damit die Werte aus der dortigen
# .env kommen und nicht aus der lokalen Shell.
#
# Der PHP-Teil geht base64-kodiert hinueber: er steckt in ssh -> su -c '...' und
# duerfte sonst kein einziges Anfuehrungszeichen enthalten.
probe_php() {
  cat <<'PHPCODE'
<?php

declare(strict_types=1);

$env = [];
foreach (file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $zeile) {
    if (preg_match('/^([A-Z0-9_]+)=(.*)$/', trim($zeile), $treffer)) {
        $env[$treffer[1]] = trim($treffer[2], "\"'");
    }
}

$fehler = 0;

foreach (['A' => '', 'B' => '_B', 'C' => '_C'] as $gruppe => $suffix) {
    $sitekey = $env['TURNSTILE_SITE_KEY'.$suffix] ?? '';
    $secret = $env['TURNSTILE_SECRET_KEY'.$suffix] ?? '';
    $sichtbar = $sitekey === '' ? '(leer)' : substr($sitekey, 0, 10).'...';

    if ($secret === '') {
        printf("Gruppe %s: keine Schluessel in der .env\n", $gruppe);
        $fehler++;

        continue;
    }

    // Testschluessel sind oeffentlich und gelten auf jeder Domain — auf
    // Produktion macht sie jedes Formular wirkungslos.
    if (preg_match('/^[123]x0000/', $secret) === 1) {
        printf("Gruppe %s: noch der Cloudflare-Testschluessel\n", $gruppe);
        $fehler++;

        continue;
    }

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => 'probe-ungueltig']),
    ]);
    $antwort = json_decode((string) curl_exec($ch), true) ?: [];
    curl_close($ch);

    $codes = $antwort['error-codes'] ?? [];

    if (in_array('invalid-input-response', $codes, true)) {
        printf("Gruppe %s: ok — Secret gilt, nur der Probe-Token nicht (Sitekey %s)\n", $gruppe, $sichtbar);

        continue;
    }

    if (in_array('invalid-input-secret', $codes, true)) {
        printf("Gruppe %s: Secret wird von Cloudflare abgelehnt (Sitekey %s)\n", $gruppe, $sichtbar);
    } else {
        printf("Gruppe %s: unklare Antwort: %s\n", $gruppe, json_encode($antwort));
    }

    $fehler++;
}

exit($fehler > 0 ? 1 : 0);
PHPCODE
}

proben() {
  echo
  echo "== Siteverify-Probe je Gruppe =="
  artisan_remote "echo $(probe_php | base64 | tr -d '\n') | base64 -d | ${PHP}" || true

  echo
  echo "== Sitekey im Config-Cache =="
  # Nur der Sitekey, der ist oeffentlich. Der Secret bleibt aus Terminal und
  # Protokoll heraus.
  artisan_remote "${PHP} artisan config:show turnstile.site_key" || true

  cat <<'HINWEIS'

Fertig, wenn alle drei Gruppen "ok — Secret gilt" melden und der Sitekey im
Config-Cache nicht mehr mit 1x00000 anfaengt. Dasselbe inklusive
Hostname-Zuordnung prueft:
  sudo -u sanitaerfinden /usr/bin/php8.4 artisan turnstile:keys:check --siteverify

Der Testaufruf aus dem Ticket ("auf elektrikerportal.com rendert ein Widget")
geht erst, wenn #5 (Blade-Komponente) und #6/#7 (Formulare) drin sind — vorher
gibt es im Frontend keine Stelle, die das Widget einbaut.
HINWEIS
}

if [ "$NUR_PRUEFEN" = 1 ]; then
  proben
  exit 0
fi

# --- Schluessel einsammeln, ohne sie ueber die Kommandozeile zu reichen -------
for name in $VARIABLEN; do
  eval "wert=\${$name:-}"
  if [ -z "$wert" ] && [ -t 0 ]; then
    printf '%s (leer = unveraendert lassen): ' "$name"
    read -rs wert; echo
    eval "$name=\$wert"
  fi
  eval "export $name=\"\${$name:-}\""
done

# Ein Testschluessel auf Produktion macht jedes Formular wirkungslos — lieber
# hier scheitern als es spaeter im Log suchen.
for name in $VARIABLEN; do
  eval "wert=\${$name}"
  case "$wert" in
    1x0000*|2x0000*|3x0000*) echo "${name} ist ein Cloudflare-Testschluessel — nicht auf Produktion." >&2; exit 65 ;;
  esac
done

# --- .env pflegen -------------------------------------------------------------
# Skript und Werte gehen als ein einziger stdin-Strom zum Server: ueber argv
# stuenden die Schluessel dort in der Prozessliste. Vorhandene Zeilen werden
# ersetzt, nicht verdoppelt; ein leerer Wert laesst die Zeile unangetastet.
{
  cat <<REMOTE
set -euo pipefail
cd "${APP_DIR}"
cp -p .env ".env.bak-14-\$(date +%Y%m%d-%H%M%S)"
while IFS=\$'\t' read -r key val; do
  [ -n "\$val" ] || { echo "\$key: unveraendert"; continue; }
  grep -v "^\${key}=" .env > .env.neu
  printf '%s=%s\n' "\$key" "\$val" >> .env.neu
  cat .env.neu > .env
  rm -f .env.neu
  echo "\$key: gesetzt"
done <<'DATEN'
REMOTE
  for name in $VARIABLEN; do
    eval "printf '%s\t%s\n' \"$name\" \"\${$name}\""
  done
  cat <<REMOTE
DATEN
chown ${APP_USER}:${APP_USER} .env
REMOTE
} | remote "bash -s"

# --- Scharfschalten -----------------------------------------------------------
echo "Baue den Config-Cache neu, lade PHP-FPM nach und starte Horizon durch …"
artisan_remote "${PHP} artisan config:clear && ${PHP} artisan config:cache"
remote "systemctl reload ${FPM_SERVICE} && supervisorctl restart ${HORIZON}"

proben
