#!/usr/bin/env bash
# Prueft ausgeliefertes Produktions-HTML gegen die CSP (#36, §1.5 Schritt 3).
#
# Warum es dieses Skript gibt: config/csp.php setzt **keine** `report-uri` und
# keine `report-to`. Im Modus `report` meldet der Browser Verstoesse also nur in
# seine eigene Konsole — serverseitig wird nichts gesammelt, die 24-Stunden-
# Sichtung aus §1.5 kann nichts einsammeln. Dieses Skript prueft stattdessen
# genau das, was `enforce` brechen wuerde, am echten Auslieferungsstand:
#
#   * <script> ohne src und ohne nonce   -> script-src hat kein 'unsafe-inline'
#     (JSON-LD zaehlt NICHT: <script type="application/ld+json"> wird nicht
#     ausgefuehrt und von CSP nicht geblockt)
#   * on*-Attribute (onclick, onsubmit, …) -> von CSP ebenfalls geblockt
#   * fremde Script-Hosts, die script-src nicht nennt
#
# Was es NICHT ersetzt: ein Mensch mit offener Browser-Konsole sieht zusaetzlich
# Verstoesse, die erst zur Laufzeit entstehen (dynamisch eingefuegte Skripte,
# eval, Stylesheets fremder Einbettungen). Die Browsermessung liegt in
# docs/messungen/csp-browser-messung-2026-10-08.md (#28) und
# csp-webkit-nonce-2026-10-08.json (#29).
#
# Aufruf:
#   scripts/csp-live-pruefen.sh                      # Gruppe-A-Portale
#   scripts/csp-live-pruefen.sh fliesenleger.io      # bestimmte Portale
#
# Einstellbar: SUN_SSH_HOST (Vorgabe sun). Gemessen wird vom Server aus gegen
# 127.0.0.1, damit die Cloudflare-Kante nicht dazwischenliegt.
#
# Exit-Codes:
#   0  kein Befund
#   1  mindestens ein ausfuehrbares Inline-Konstrukt gefunden
#   65 Produktion nicht erreichbar

set -uo pipefail

SSH_HOST="${SUN_SSH_HOST:-sun}"
PORTALE="${*:-fahrschulefinder.de elektrikerportal.com sanitaerfinden.com}"
PFADE="/ /firmen /staedte /kategorien /jobs /ratgeber /eintragen /register"

FERN=$(cat <<'REMOTE'
set -u
BEFUND=0
for d in $PORTALE; do
  echo "=== $d"
  HEADER=$(curl -skI --resolve "$d:443:127.0.0.1" "https://$d/" | tr -d '\r' \
    | grep -i '^content-security-policy' | head -1 | cut -d: -f1)
  echo "  Header: ${HEADER:-(keiner)}"
  for p in $PFADE; do
    H=$(curl -sk -w '\n%{http_code}' --resolve "$d:443:127.0.0.1" "https://$d$p")
    CODE=$(printf '%s' "$H" | tail -1)
    BODY=$(printf '%s' "$H" | sed '$d')
    # Inline-Skripte ohne nonce, JSON-LD herausgerechnet
    INLINE=$(printf '%s' "$BODY" | grep -o '<script[^>]*>' \
      | grep -v 'src=' | grep -v 'nonce=' | grep -vc 'application/ld+json')
    HANDLER=$(printf '%s' "$BODY" | grep -coE ' on[a-z]+="')
    HOSTS=$(printf '%s' "$BODY" | grep -oE '<script[^>]*src="https?://[^/"]+' \
      | grep -oE 'https?://[^/"]+' | grep -v "$d" | sort -u | tr '\n' ' ')
    MARKE=''
    if [ "$INLINE" -gt 0 ] || [ "$HANDLER" -gt 0 ]; then MARKE=' <== BEFUND'; BEFUND=1; fi
    printf '  %-12s %s  inline=%s  on*=%s  fremd=%s%s\n' \
      "$p" "$CODE" "$INLINE" "$HANDLER" "${HOSTS:-keine}" "$MARKE"
  done
done
exit $BEFUND
REMOTE
)

ssh -o BatchMode=yes -o ConnectTimeout=15 "$SSH_HOST" \
  "PORTALE='${PORTALE}' PFADE='${PFADE}' bash -s" <<<"$FERN"
ERG=$?
[ "$ERG" -eq 255 ] && { echo "Produktion nicht erreichbar." >&2; exit 65; }
exit "$ERG"
