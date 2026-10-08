#!/usr/bin/env bash
# Prueft von aussen, ob die vier Cloudflare-Regeln aus docs/bot-traffic.md
# Abschnitt 2 wirken (#24). Ohne Kontozugang, ohne API-Token, ohne Last:
# je Zone fuenf einzelne GET-Anfragen.
#
# Gegenstueck zu scripts/cloudflare-waf-regeln-setzen.sh --pruefen, das das
# Soll gegen die API vergleicht und einen Token braucht. Dieses Skript misst
# die Wirkung dort, wo sie zaehlt: an der Kante.
#
# Geprueft wird je Zone:
#
#   1 Kante      HEAD /            -> Header cf-ray vorhanden?
#                Ohne Kante kann keine Regel greifen (#34).
#   2 Sperre     GET  /            mit UA "python-requests/2.31.0"
#                -> erwartet 403 (Regel 2, docs/bot-traffic.md 2.2)
#   3 Besucher   GET  /            mit Browser-UA
#                -> erwartet 200 (kein Kollateralschaden)
#   4 Ausnahme   GET  /robots.txt  mit UA "python-requests/2.31.0"
#                -> erwartet 200 (Regel 1 steht VOR Regel 2)
#   5 IndexNow   GET  /<32 Hex>.txt mit UA "Bingbot"
#                -> erwartet NICHT 403. Der Pfad trifft absichtlich keine
#                  echte Schluesseldatei: 404 vom Ursprung beweist, dass das
#                  Hex-Muster der Ausnahme greift, ohne den Schluessel der
#                  Produktion zu kennen (docs/guide-golive.md 1.4).
#
# Nebenbefund zur Bestandsaufnahme (Ticket #24, "vorher festhalten"): kommt
# Probe 2 mit 200 durch, ist auch Bot Fight Mode bzw. Super Bot Fight Mode
# fuer diesen Verkehr nicht aktiv — eine der beiden Fragen ist damit ohne
# Dashboard beantwortet.
#
# Aufruf:
#   scripts/waf-wirkung-pruefen.sh
#   scripts/waf-wirkung-pruefen.sh --zone=elektrikerportal.com
#
# Exit-Codes:
#   0  alle Zonen hinter der Kante verhalten sich wie vorgesehen
#  64  unbekannte Option
#  69  dig fehlt
#  72  mindestens eine Zone hinter der Kante sperrt nicht (Regeln fehlen)
#  73  mindestens eine Zone sperrt zu viel (echte Besucher, robots.txt oder
#      die IndexNow-Schluessel sind betroffen) — das ist der schlimmere Fall

set -uo pipefail

UA_BOT='python-requests/2.31.0'
UA_BROWSER='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36'
UA_BING='Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'
HEX_PFAD='/0123456789abcdef0123456789abcdef.txt'

# Dieselbe Portalliste wie scripts/cloudflare-waf-regeln-setzen.sh.
ZONEN_VORGABE='fahrschulefinder.de elektrikerportal.com sanitaerfinden.com
sanitaerfinder.com malerfinder.de fliesenleger.io kfzwerkstatt.io
findegutachter.de bodenlegerfinden.com tierarztportal.com
energieberaterportal.net firmenfreund.net firmenfreund.de geruestbauer.gmbh
metallbauer.io mjet.net schluesseldienstportal.com speditionportal.com'

ZONEN=''
while [ $# -gt 0 ]; do
  case "$1" in
    --zone=*) ZONEN="${ZONEN} ${1#--zone=}"; shift ;;
    -h|--help) sed -n '2,41p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done
[ -n "$ZONEN" ] || ZONEN="$ZONEN_VORGABE"

command -v dig >/dev/null || { echo 'dig fehlt (macOS: brew install bind).' >&2; exit 69; }

# Adresse am autoritativen Nameserver der Zone, nicht ueber den eigenen
# Resolver: ein zwischenspeichernder Router haelt nach einem Zonenumzug noch
# die alte Ursprungsadresse, jede Probe laeuft dann an der Kante vorbei und das
# Skript meldet faelschlich "keine Cloudflare-Kante" (so geschehen fuer
# elektrikerportal.com am 08.10.2026). Alle Proben unten gehen deshalb mit
# --resolve auf diese Adresse.
autoritative_adresse() {
  local name="$1" ns
  ns="$(dig +short NS "$name" 2>/dev/null | sed 's/\.$//' | sort | head -1)"
  if [ -n "$ns" ]; then
    dig +short +time=3 +tries=1 A "$name" "@${ns}" 2>/dev/null | grep -E '^[0-9.]+$' | head -1
    return 0
  fi
  dig +short A "$name" 2>/dev/null | grep -E '^[0-9.]+$' | head -1
}

# --resolve-Argumente fuer die aktuelle Zone; leer, wenn keine Adresse
# ermittelbar war (dann entscheidet der eigene Resolver).
ziel=()

# Antwortcode einer einzelnen Anfrage. Weiterleitungen werden NICHT gefolgt:
# eine 301 ist hier ein eigener Befund (#37), kein Durchgang. -k, weil das eine
# Ursprungszertifikat nur die gepflegten SAN-Namen traegt und hier der
# Antwortcode zaehlt, nicht die Kette.
code() {
  local ua="$1" url="$2"
  curl -sSk -o /dev/null --max-time 20 -w '%{http_code}' "${ziel[@]}" -A "$ua" "$url" 2>/dev/null || echo '000'
}

# Steht die Zone hinter Cloudflare? cf-ray setzt nur die Kante.
hat_kante() {
  curl -sSk -I --max-time 20 "${ziel[@]}" -A "$UA_BROWSER" "https://$1/" 2>/dev/null \
    | tr 'A-Z' 'a-z' | grep -q '^cf-ray:'
}

OHNE_SPERRE=''
ZU_VIEL=''
OHNE_KANTE=''
WEITERLEITUNG=''

printf '%-28s %-6s %-8s %-8s %-8s %-8s %s\n' \
  'Zone' 'Kante' 'Bot/' 'Browser' 'robots' 'Hex.txt' 'Befund'

for name in $ZONEN; do
  adresse="$(autoritative_adresse "$name")"
  if [ -n "$adresse" ]; then ziel=(--resolve "${name}:443:${adresse}"); else ziel=(); fi

  if ! hat_kante "$name"; then
    printf '%-28s %-6s %-8s %-8s %-8s %-8s %s\n' "$name" 'nein' '-' '-' '-' '-' 'keine Cloudflare-Kante (#34)'
    OHNE_KANTE="${OHNE_KANTE} ${name}"
    continue
  fi

  c_bot="$(code "$UA_BOT" "https://${name}/")"
  c_mensch="$(code "$UA_BROWSER" "https://${name}/")"
  c_robots="$(code "$UA_BOT" "https://${name}/robots.txt")"
  c_hex="$(code "$UA_BING" "https://${name}${HEX_PFAD}")"

  befund=''
  case "$c_mensch" in
    30[12378])
      befund='Weiterleitung, nicht beurteilbar (#37)'
      WEITERLEITUNG="${WEITERLEITUNG} ${name}"
      ;;
    *)
      # Der schlimmere Fall zuerst: zu viel gesperrt.
      if [ "$c_mensch" = '403' ] || [ "$c_robots" = '403' ] || [ "$c_hex" = '403' ]; then
        befund='SPERRT ZU VIEL'
        ZU_VIEL="${ZU_VIEL} ${name}"
      elif [ "$c_bot" = '403' ]; then
        befund='ok'
      else
        befund='Bot kommt durch, Regel 2 wirkt nicht'
        OHNE_SPERRE="${OHNE_SPERRE} ${name}"
      fi
      ;;
  esac

  printf '%-28s %-6s %-8s %-8s %-8s %-8s %s\n' \
    "$name" 'ja' "$c_bot" "$c_mensch" "$c_robots" "$c_hex" "$befund"
done

echo
[ -z "$OHNE_KANTE" ]    || echo "Ohne Cloudflare-Kante (#34):${OHNE_KANTE}"
[ -z "$WEITERLEITUNG" ] || echo "Weiterleitung statt Portal (#37):${WEITERLEITUNG}"

if [ -n "$ZU_VIEL" ]; then
  echo "SPERRT ZU VIEL — sofort nachsehen:${ZU_VIEL}"
  echo 'Regel 1 (Ausnahme) muss VOR Regel 2 stehen, docs/bot-traffic.md 2.1.'
  exit 73
fi

if [ -n "$OHNE_SPERRE" ]; then
  echo "Regeln nicht wirksam:${OHNE_SPERRE}"
  echo 'Setzen mit scripts/cloudflare-waf-regeln-setzen.sh (braucht einen Token'
  echo 'mit Zone: Read + Firewall Services: Edit) oder von Hand im Dashboard'
  echo 'nach docs/bot-traffic.md Abschnitt 2.'
  exit 72
fi

echo 'Alle erreichbaren Zonen hinter der Kante sperren Bot-Verkehr und lassen'
echo 'Besucher, robots.txt und die IndexNow-Schluessel durch.'
