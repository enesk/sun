#!/usr/bin/env bash
# Liest den Stand der Go-Live-Checkliste des Bot-Schutzes (docs/turnstile-golive.md)
# und nennt die naechste offene Zeile (#36).
#
# Warum es dieses Skript gibt: an #36 haengt Handarbeit (Cloudflare-Konto, Freigabe,
# echte Geraete). Jeder Anlauf hat denselben Satz lesender Befehle gegen Repo und
# Produktion von Hand wiederholt, um herauszufinden, wo die Checkliste steht. Das
# ist Werkzeugarbeit — hier in einem Aufruf, mit Exit-Code und immer in der
# Reihenfolge des Dokuments (B2 -> §1.1 -> §1.2 -> §1.3 -> §1.5 -> §1.6 -> Stufen).
#
# Das Skript aendert NICHTS: keine Schreibbefehle, kein Deploy, keine .env, keine
# Datenbank. Es ruft nur lesende Befehle und die --pruefen-Pfade der
# Schwesterskripte auf.
#
# Aufruf:
#   scripts/turnstile-golive-stand.sh            # Stand im Klartext
#   scripts/turnstile-golive-stand.sh --kurz     # nur die naechste offene Zeile
#   scripts/turnstile-golive-stand.sh --ohne-cloudflare   # Token/Widgets nicht pruefen
#
# Einstellbar: SUN_SSH_HOST (Vorgabe sun), APP_DIR, PHP_BIN.
#
# Exit-Codes:
#   0   alle maschinell pruefbaren Zeilen bestanden — naechster Schritt ist eine Stufe
#   70  mindestens eine Zeile offen (die erste steht als "NAECHSTER HANDGRIFF" da)
#   65  Produktion nicht erreichbar

set -euo pipefail

SSH_HOST="${SUN_SSH_HOST:-sun}"
APP_DIR="${APP_DIR:-/home/sanitaerfinden/htdocs/sanitaerfinden.dev}"
APP_USER="${APP_USER:-sanitaerfinden}"
PHP_BIN="${PHP_BIN:-/usr/bin/php8.4}"
HIER="$(cd "$(dirname "$0")/.." && pwd)"

KURZ=0
OHNE_CF=0
while [ $# -gt 0 ]; do
  case "$1" in
    --kurz) KURZ=1; shift ;;
    --ohne-cloudflare) OHNE_CF=1; shift ;;
    -h|--help) sed -n '2,27p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done

remote() { ssh -o BatchMode=yes -o ConnectTimeout=20 "$SSH_HOST" "$@"; }
als_app() { remote "su -s /bin/bash ${APP_USER} -c 'HOME=/home/${APP_USER}; cd ${APP_DIR} && $1'"; }

OFFEN=()
zeile() { # zeile <ok|offen|unklar> <abschnitt> <text> [handgriff]
  local z="$1" a="$2" t="$3" h="${4:-}"
  case "$z" in
    ok)     printf '  [x] %-6s %s\n' "$a" "$t" ;;
    offen)  printf '  [ ] %-6s %s\n' "$a" "$t"; OFFEN+=("${a} — ${t}${h:+
        Handgriff: ${h}}") ;;
    unklar) printf '  [?] %-6s %s\n' "$a" "$t" ;;
  esac
}

echo "Stand der Go-Live-Checkliste Bot-Schutz — $(date '+%d.%m.%Y %H:%M')"
echo "Ablauf: docs/turnstile-golive.md   Belege: docs/messungen/turnstile-golive/"
echo

# ---------------------------------------------------------------- B2: Code
echo "B2 — Modul im Repository"
cd "$HIER"
if git cat-file -e "origin/main:config/turnstile.php" 2>/dev/null; then
  zeile ok "B2" "config/turnstile.php liegt auf origin/main ($(git rev-parse --short origin/main))"
else
  zeile offen "B2" "Bot-Schutz-Modul liegt nicht auf origin/main (nur in der Arbeitskopie)" \
    "Commit und Push durch Enes freigeben lassen (Freigabe-Gatter von #36)"
fi

# ------------------------------------------------------- §1.1: Token, Widgets
echo
echo "§1.1 — Schluessel je Widget"
if [ "$OHNE_CF" -eq 1 ]; then
  zeile unklar "§1.1" "Token- und Widget-Pruefung auf Wunsch uebersprungen (--ohne-cloudflare)"
else
  if "$HIER/scripts/cloudflare-token-ablegen.sh" --pruefen >/dev/null 2>&1; then
    zeile ok "§1.1" "Token mit 'Account / Turnstile: Edit' liegt in /root/sun-zugang.txt"
    if "$HIER/scripts/turnstile-widgets-anlegen.sh" --pruefen >/dev/null 2>&1; then
      zeile ok "§1.1" "drei Widgets stehen am Konto (3 + 10 + 5 Hostnames)"
    else
      zeile offen "§1.1" "Widgets fehlen oder weichen ab" \
        "scripts/turnstile-widgets-anlegen.sh  (danach im Dashboard 'Allow a domain to be added automatically' bei allen drei Widgets AUS)"
    fi
  else
    # Der API-Token ist KEIN Blocker: er automatisiert nur das Anlegen der Widgets
    # und diese Gegenprobe. Weg B in docs/turnstile-golive.md §1.1 legt die drei
    # Widgets im Dashboard an; die sechs .env-Werte und keys:check --siteverify
    # brauchen ihn nie. Deshalb hier nur ein Hinweis, keine offene Zeile.
    zeile unklar "§1.1" "kein Cloudflare-API-Token am Ursprung (#18/#38) — nur Weg A faellt weg"
    zeile unklar "§1.1" "Widgets nicht maschinell pruefbar; Weg B (Dashboard) ueber keys:check gegenpruefen (§1.2)"
  fi
fi

# --------------------------------------------------------- Produktion lesen
echo
echo "Produktion (${SSH_HOST}:${APP_DIR})"
if ! ROH="$(remote "cd ${APP_DIR} 2>/dev/null && {
    echo \"COMMIT=\$(git log --oneline -1 2>/dev/null | cut -d' ' -f1)\"
    echo \"MODUL=\$([ -d app/Turnstile ] && echo ja || echo nein)\"
    echo \"BUILD=\$([ -d public/build ] && echo ja || echo nein)\"
    echo \"TSKEYS=\$(grep -c '^TURNSTILE_SITE_KEY\|^TURNSTILE_SECRET' .env || true)\"
    echo \"TSENABLED=\$(grep -m1 '^TURNSTILE_ENABLED' .env | cut -d= -f2)\"
    echo \"CSP=\$(grep -m1 '^CSP_MODE' .env | cut -d= -f2)\"
    echo \"ALERT=\$(grep -c '^TURNSTILE_ALERT_RECIPIENTS=.' .env || true)\"
    echo \"SCHEDULE=\$(crontab -u ${APP_USER} -l 2>/dev/null | grep -c 'schedule:run' || true)\"
  }" 2>/dev/null)"; then
  echo "  Produktion nicht erreichbar (ssh ${SSH_HOST})." >&2
  exit 65
fi
eval "$(printf '%s\n' "$ROH" | grep -E '^[A-Z]+=')"

zeile unklar "" "Codestand ${COMMIT:-unbekannt}, Modul ${MODUL:-?}, public/build ${BUILD:-?}"

echo
echo "§1.2/§1.3 — Deploy und Schluessel in Produktion"
if [ "${MODUL:-nein}" = "ja" ]; then
  zeile ok "§1.3" "Bot-Schutz-Code ist ausgerollt (${COMMIT})"
else
  zeile offen "§1.3" "Deploy fehlt — app/Turnstile existiert in Produktion nicht" \
    "erst B2 und §1.1, dann docs/turnstile-golive.md §1.3 Zeile fuer Zeile (kein php dep deploy)"
fi
if [ "${TSKEYS:-0}" -ge 6 ]; then
  zeile ok "§1.1" "sechs TURNSTILE-Schluesselwerte stehen in der Produktions-.env"
else
  zeile offen "§1.1" "nur ${TSKEYS:-0} von 6 TURNSTILE-Schluesselwerten in der .env" \
    "drei Widgets anlegen (Dashboard = Weg B, §1.1) und scripts/turnstile-schluessel-eintragen.sh fuer alle drei Gruppen — sonst laeuft B/C in die TurnstileNotConfiguredException"
fi
case "${CSP:-}" in
  report)  zeile ok    "§1.5" "CSP_MODE=report — 24-Stunden-Messung laeuft oder steht bevor" ;;
  enforce) zeile ok    "§1.5" "CSP_MODE=enforce" ;;
  off)     zeile unklar "§1.5" "CSP_MODE=off (Notbremse gezogen)" ;;
  *)       zeile offen "§1.5" "keine CSP_MODE-Zeile in der .env — die Codevorgabe ist enforce" \
             "CSP_MODE=report in die .env, BEVOR der erste config:cache des Deploys laeuft (§1.3)" ;;
esac
if [ "${ALERT:-0}" -ge 1 ]; then
  zeile ok "§1.5" "TURNSTILE_ALERT_RECIPIENTS gesetzt"
else
  zeile offen "§1.5" "TURNSTILE_ALERT_RECIPIENTS fehlt — Alarme gehen an alle Administratoren" \
    "TURNSTILE_ALERT_RECIPIENTS=<enes>,<uwe> in die Produktions-.env, danach config:cache"
fi
if [ "${SCHEDULE:-0}" -ge 1 ]; then
  zeile ok "§1.5" "schedule:run laeuft im Crontab von ${APP_USER}"
else
  zeile offen "§1.5" "kein schedule:run im Crontab — Monitor, Bericht und Pruning laufen nicht"
fi

# ------------------------------------------- nur sinnvoll, wenn Code ausgerollt
if [ "${MODUL:-nein}" = "ja" ]; then
  echo
  echo "§1.2/§1.6 — Pruefungen in Produktion"
  if als_app "${PHP_BIN} artisan turnstile:keys:check --siteverify" >/dev/null 2>&1; then
    zeile ok "§1.2" "turnstile:keys:check --siteverify gruen fuer alle drei Gruppen"
  else
    zeile offen "§1.2" "turnstile:keys:check --siteverify nicht gruen" \
      "Ausgabe ansehen: su -s /bin/bash ${APP_USER} -c 'cd ${APP_DIR} && ${PHP_BIN} artisan turnstile:keys:check --siteverify'"
  fi
  ROLLOUT="$(als_app "${PHP_BIN} artisan turnstile:rollout" 2>/dev/null || true)"
  if printf '%s' "$ROLLOUT" | grep -q .; then
    AKTIV="$(printf '%s\n' "$ROLLOUT" | grep -c ' ja ' || true)"
    zeile unklar "§1.6" "turnstile:rollout: ${AKTIV} Portale aktiv"
  fi
else
  echo
  echo "  (§1.2, §1.4, §1.6 und die Stufen 0-3 sind erst nach dem Deploy pruefbar.)"
fi

# ------------------------------------------------------------------- Belege
echo
echo "§8 — Belege in docs/messungen/turnstile-golive/"
for muster in 'keys-check-*' 'migrate-*' 'vorwoche-*' 'backup-*' 'csp-report-*' 'stufe1' 'stufe2' 'stufe3'; do
  # shellcheck disable=SC2086
  if compgen -G "docs/messungen/turnstile-golive/${muster}" >/dev/null 2>&1; then
    zeile ok "§8" "Beleg ${muster} liegt"
  else
    zeile offen "§8" "Beleg ${muster} fehlt"
  fi
done

echo
if [ "${#OFFEN[@]}" -eq 0 ]; then
  echo "Alle maschinell pruefbaren Zeilen bestanden."
  exit 0
fi
echo "NAECHSTER HANDGRIFF:"
echo "  ${OFFEN[0]}"
if [ "$KURZ" -eq 0 ] && [ "${#OFFEN[@]}" -gt 1 ]; then
  echo
  echo "Danach offen (${#OFFEN[@]} Zeilen insgesamt):"
  for i in "${!OFFEN[@]}"; do
    [ "$i" -eq 0 ] && continue
    echo "  - ${OFFEN[$i]}"
  done
fi
exit 70
