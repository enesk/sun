#!/usr/bin/env bash
#
# Echte Besucher-IP am Ursprung herstellen (#43).
#
# AUSGANGSLAGE (gemessen 16.10.2026 auf 88.198.64.145)
#
# CloudPanel baut jeden vhost zweistufig: ein aeusserer Serverblock auf
# 443 reicht per `proxy_pass http://127.0.0.1:8080` an einen inneren
# Block weiter und setzt dabei `X-Real-IP $remote_addr`. Der innere Block
# loest den Header ueber das realip-Modul wieder auf -- daher die
# `set_real_ip_from`-Zeilen in /etc/nginx/nginx.conf.
#
# Zwei Fehler steckten darin:
#
#   1. `real_ip_header` war auskommentiert (Zeile 28), nginx nahm also die
#      Vorgabe X-Real-IP -- und `set_real_ip_from 0.0.0.0/0` machte den
#      Header von JEDEM Absender vertrauenswuerdig. Ein
#      `curl -H 'X-Real-IP: 203.0.113.77'` gegen den Ursprung setzte
#      $remote_addr und damit REMOTE_ADDR frei waehlbar (belegt, Protokoll
#      in docs/echte-besucher-ip.md 3). Jedes throttle-Limit, jede
#      Turnstile-IP-Bindung und jede Netzpruefung war mit einem Header
#      aushebelbar.
#   2. Der aeussere Block sah hinter Cloudflare die Edge-Adresse und gab
#      sie als X-Real-IP weiter. `$request->ip()` war damit die Edge --
#      ein throttle-Eimer fuer viele Besucher (Befund S3 aus Review #39).
#
# AENDERUNG
#
#   nginx.conf (http-Kontext, gilt fuer alle Pools):
#     * `set_real_ip_from 0.0.0.0/0;` faellt weg
#     * `real_ip_header X-Real-IP;` steht explizit da
#     -> der innere Block vertraut dem Header nur noch vom Vorbau
#        (127.0.0.1 / ::1). Unveraendertes Verhalten fuer alle Sites,
#        nur nicht mehr faelschbar.
#
#   sanitaerfinden.dev-vhost, NUR der aeussere 443-Block:
#     * `include /etc/nginx/cloudflare-ips.conf;` (die CF-Netze)
#     * `real_ip_header CF-Connecting-IP;`
#     -> kommt die Verbindung aus einem Cloudflare-Netz, ist $remote_addr
#        dort die echte Besucheradresse. Das vorhandene
#        `proxy_set_header X-Real-IP $remote_addr;` traegt sie ohne weitere
#        Aenderung an den inneren Block und damit an PHP; das Zugriffslog
#        des Blocks zeigt sie ebenfalls. Portale ohne Cloudflare-Kante
#        (elektrikerportal.com, sanitaerfinden.com) liefern weiter die
#        echte Adresse, weil ihr Absender nicht in der Liste steht -- die
#        Bemessung ist danach netzweit einheitlich.
#
# Bewusst KEIN TrustProxies in bootstrap/app.php: die nginx-Loesung gilt
# fuer alle Pools, fuer das Zugriffslog, fuer `include blocked_ips` und
# fuer `limit_req_zone` gleich mit -- TrustProxies wuerde nur PHP erreichen
# und die beiden Quellen auseinanderlaufen lassen.
#
# Gepatcht wird -- wie bei scripts/vhost-www-weiterleitung.sh -- beides:
# die ausgelieferte Datei UND site.vhost_template in der CloudPanel-DB,
# sonst ist die Aenderung beim naechsten Durchgang des Vhost-Editors weg.
#
# Laeuft als root auf 88.198.64.145 (SSH-Kuerzel `sun`), ist wiederholbar.
#
#   scripts/nginx-echte-besucher-ip.sh --pruefen          # nur Stand zeigen
#   scripts/nginx-echte-besucher-ip.sh --netze-abgleichen # CF-Liste gegen cloudflare.com
#   scripts/nginx-echte-besucher-ip.sh                    # anwenden + reload
#   scripts/nginx-echte-besucher-ip.sh --abnahme          # nach dem Anwenden pruefen

set -euo pipefail

SITE="sanitaerfinden.dev"
VHOST="/etc/nginx/sites-enabled/${SITE}.conf"
CLPDB="/home/clp/htdocs/app/data/db.sq3"
NGINXCONF="/etc/nginx/nginx.conf"
CFLISTE="/etc/nginx/cloudflare-ips.conf"
URSPRUNG="88.198.64.145"

# Stand 16.10.2026, Quelle https://www.cloudflare.com/ips-v4 und ips-v6.
# Mit --netze-abgleichen gegen die Quelle pruefen, nie von Hand raten.
CF_NETZE="173.245.48.0/20
103.21.244.0/22
103.22.200.0/22
103.31.4.0/22
141.101.64.0/18
108.162.192.0/18
190.93.240.0/20
188.114.96.0/20
197.234.240.0/22
198.41.128.0/17
162.158.0.0/15
104.16.0.0/13
104.24.0.0/14
172.64.0.0/13
131.0.72.0/22
2400:cb00::/32
2606:4700::/32
2803:f800::/32
2405:b500::/32
2405:8100::/32
2a06:98c0::/29
2c0f:f248::/32"

MODUS="${1:-anwenden}"

case "$MODUS" in
  --pruefen|--netze-abgleichen|--abnahme|anwenden) ;;
  *) echo "Unbekannt: $MODUS" >&2; exit 2 ;;
esac

stand() {
  echo "== nginx.conf =="
  grep -nE "set_real_ip_from|real_ip_header|real_ip_recursive" "$NGINXCONF" || true
  echo
  echo "== ${VHOST##*/} (aeusserer 443-Block) =="
  grep -nE "cloudflare-ips.conf|real_ip_header" "$VHOST" || echo "  (noch nichts)"
  echo
  echo "== CF-Liste =="
  if [ -f "$CFLISTE" ]; then
    echo "  $CFLISTE, $(grep -c set_real_ip_from "$CFLISTE") Netze"
  else
    echo "  fehlt"
  fi
}

netze_abgleichen() {
  local live
  live="$(mktemp)"
  { curl -fsS --max-time 20 https://www.cloudflare.com/ips-v4; echo;
    curl -fsS --max-time 20 https://www.cloudflare.com/ips-v6; echo; } \
    | tr -d '\r' | grep -E '^[0-9a-fA-F:.]+/[0-9]+$' | sort > "$live"

  local hier
  hier="$(mktemp)"
  echo "$CF_NETZE" | sort > "$hier"

  if diff -u "$hier" "$live" > /dev/null; then
    echo "CF-Netze unveraendert ($(wc -l < "$live" | tr -d ' ') Eintraege)."
    rm -f "$live" "$hier"
    return 0
  fi

  echo "ABWEICHUNG zur Quelle (links: Skript, rechts: cloudflare.com):" >&2
  diff -u "$hier" "$live" >&2 || true
  echo "CF_NETZE im Skript nachziehen, dann erneut anwenden." >&2
  rm -f "$live" "$hier"
  return 71
}

if [ "$MODUS" = "--netze-abgleichen" ]; then
  netze_abgleichen
  exit $?
fi

if [ "$MODUS" = "--pruefen" ]; then
  stand
  exit 0
fi

if [ "$(id -u)" -ne 0 ]; then
  echo "Muss als root laufen." >&2
  exit 2
fi

if [ "$MODUS" = "--abnahme" ]; then
  LOG="/home/sanitaerfinden/logs/nginx/access.log"
  KENNUNG="ip43-abnahme-$(date +%s)"
  FEHLER=0

  # 1. Portal ohne Cloudflare-Kante, X-Real-IP gefaelscht: die Adresse
  #    darf im Log NICHT auftauchen.
  curl -s -o /dev/null --max-time 20 \
    --resolve "elektrikerportal.com:443:${URSPRUNG}" \
    -H 'X-Real-IP: 203.0.113.77' \
    -H "User-Agent: ${KENNUNG}-direkt" \
    "https://elektrikerportal.com/robots.txt" || true
  sleep 2
  ZEILE="$(grep "${KENNUNG}-direkt" "$LOG" | tail -1 || true)"
  echo "direkt:     ${ZEILE:-(keine Logzeile)}"
  case "$ZEILE" in
    203.0.113.77*) echo "  FEHLER: gefaelschte X-Real-IP wird weiter uebernommen." >&2; FEHLER=1 ;;
    "")            echo "  FEHLER: keine Logzeile gefunden." >&2; FEHLER=1 ;;
    *)             echo "  ok: Absenderadresse statt Header." ;;
  esac

  # 2. Portal hinter der Kante: im Log muss die echte Besucheradresse
  #    stehen, keine Cloudflare-Edge. Gegenprobe ist das letzte Glied von
  #    X-Forwarded-For auf derselben Zeile -- das setzt Cloudflare auf die
  #    Besucheradresse, es muss also dasselbe sein.
  #    Der Cache-Brecher ist Pflicht: eine gecachte Antwort erreicht den
  #    Ursprung nie und es entsteht keine Logzeile.
  curl -s -o /dev/null --max-time 20 \
    -H "User-Agent: ${KENNUNG}-kante" \
    "https://firmenfreund.de/robots.txt?cb=${KENNUNG}" || true
  sleep 2
  ZEILE="$(grep "${KENNUNG}-kante" "$LOG" | tail -1 || true)"
  echo "ueber Kante: ${ZEILE:-(keine Logzeile)}"
  if [ -z "$ZEILE" ]; then
    echo "  FEHLER: keine Logzeile gefunden (ueber die Kante gelaufen?)." >&2
    FEHLER=1
  else
    GELOGGT="${ZEILE%% *}"
    WEITERGABE="$(printf '%s' "$ZEILE" | awk -F'"' '{print $(NF-1)}' | awk -F', ' '{print $NF}')"
    echo "  geloggt: ${GELOGGT} / X-Forwarded-For: ${WEITERGABE}"
    if [ "$GELOGGT" = "$WEITERGABE" ]; then
      echo "  ok: echte Besucheradresse im Log."
    else
      echo "  FEHLER: geloggt ist nicht die Besucheradresse (Edge?)." >&2
      FEHLER=1
    fi
  fi

  exit "$FEHLER"
fi

# ---------------------------------------------------------------- anwenden

for pfad in "$VHOST" "$CLPDB" "$NGINXCONF"; do
  [ -e "$pfad" ] || { echo "Fehlt: $pfad" >&2; exit 2; }
done

ZEITSTEMPEL="$(date +%Y%m%d-%H%M%S)"

# 1. Liste der Cloudflare-Netze
{
  echo "# Netze, aus denen \`real_ip_header CF-Connecting-IP\` vertraut wird."
  echo "# Erzeugt von scripts/nginx-echte-besucher-ip.sh (#43)."
  echo "# Quelle: https://www.cloudflare.com/ips-v4 und ips-v6."
  echo "# Abgleich: scripts/nginx-echte-besucher-ip.sh --netze-abgleichen"
  echo "$CF_NETZE" | sed 's/^/set_real_ip_from /; s/$/;/'
} > "${CFLISTE}.neu"

if [ -f "$CFLISTE" ] && cmp -s "$CFLISTE" "${CFLISTE}.neu"; then
  rm -f "${CFLISTE}.neu"
  echo "CF-Liste unveraendert."
else
  [ -f "$CFLISTE" ] && cp -a "$CFLISTE" "${CFLISTE}.bak.${ZEITSTEMPEL}"
  mv "${CFLISTE}.neu" "$CFLISTE"
  chmod 644 "$CFLISTE"
  echo "CF-Liste geschrieben: $CFLISTE"
fi

# 2. nginx.conf: 0.0.0.0/0 raus, real_ip_header explizit
if grep -q "# >>> #43 echte Besucher-IP" "$NGINXCONF"; then
  echo "nginx.conf schon gepatcht."
else
  cp -a "$NGINXCONF" "${NGINXCONF}.bak.${ZEITSTEMPEL}"
  python3 - "$NGINXCONF" <<'PY'
import sys

ziel = sys.argv[1]
anker = (
    '    set_real_ip_from 0.0.0.0/0;\n'
    '    #real_ip_header X-Forwarded-For;\n'
)
neu = '''    # >>> #43 echte Besucher-IP
    #
    # Ohne `real_ip_header` nimmt nginx die Vorgabe X-Real-IP. Mit
    # `set_real_ip_from 0.0.0.0/0` war der Header damit von JEDEM Absender
    # vertrauenswuerdig -- ein `X-Real-IP:`-Header gegen den Ursprung setzte
    # $remote_addr und REMOTE_ADDR frei waehlbar (belegt 16.10.2026,
    # docs/echte-besucher-ip.md). Vertraut wird jetzt nur dem
    # CloudPanel-Vorbau auf 127.0.0.1, der den Header selbst setzt.
    #
    # Die echte Adresse hinter Cloudflare stellt der aeussere 443-Block des
    # Portal-vhosts her (CF-Connecting-IP, Netzliste in
    # /etc/nginx/cloudflare-ips.conf).
    set_real_ip_from ::1;
    real_ip_header X-Real-IP;
    # <<< #43 echte Besucher-IP
'''

inhalt = open(ziel).read()

if inhalt.count(anker) != 1:
    sys.exit(f'Ankerzeilen nicht genau einmal in {ziel}')

open(ziel, 'w').write(inhalt.replace(anker, neu))
PY
  echo "nginx.conf gepatcht (Sicherung: ${NGINXCONF}.bak.${ZEITSTEMPEL})."
fi

# 3. vhost und CloudPanel-Vorlage
BLOCK_DATEI="$(mktemp)"
cat > "$BLOCK_DATEI" <<'EOF'
  # >>> #43 echte Besucher-IP hinter Cloudflare
  #
  # Kommt die Verbindung aus einem Cloudflare-Netz, ist $remote_addr ab hier
  # die echte Besucheradresse -- das Zugriffslog dieses Blocks und das
  # `proxy_set_header X-Real-IP $remote_addr;` unten tragen sie weiter an
  # den inneren Block und damit an PHP. Portale ohne Kante liefern weiter
  # die Absenderadresse, weil ihr Absender nicht in der Liste steht.
  #
  # Die Netzliste MUSS eng bleiben: ohne sie waere CF-Connecting-IP von
  # jedem Absender faelschbar.
  include /etc/nginx/cloudflare-ips.conf;
  real_ip_header CF-Connecting-IP;
  # <<< #43 echte Besucher-IP hinter Cloudflare
EOF

if grep -q "# >>> #43 echte Besucher-IP hinter Cloudflare" "$VHOST"; then
  echo "vhost schon gepatcht."
else
  cp -a "$VHOST" "${VHOST}.bak.${ZEITSTEMPEL}"
  python3 - "$VHOST" "$BLOCK_DATEI" <<'PY'
import sys

ziel, blockdatei = sys.argv[1], sys.argv[2]
anker = '  if ($scheme != "https") {\n'
block = open(blockdatei).read()
inhalt = open(ziel).read()

if inhalt.count(anker) != 1:
    sys.exit(f'Anker {anker!r} nicht genau einmal in {ziel}')

open(ziel, 'w').write(inhalt.replace(anker, block + anker))
PY
  echo "vhost gepatcht (Sicherung: ${VHOST}.bak.${ZEITSTEMPEL})."
fi

python3 - "$CLPDB" "$BLOCK_DATEI" "$SITE" <<'PY'
import sqlite3, sys

db, blockdatei, site = sys.argv[1], sys.argv[2], sys.argv[3]
block = open(blockdatei).read()
anker = '  if ($scheme != "https") {\n'
marke = '# >>> #43 echte Besucher-IP hinter Cloudflare'

con = sqlite3.connect(db)
zeile = con.execute(
    'select id, vhost_template from site where domain_name = ?', (site,)
).fetchone()

if zeile is None:
    sys.exit(f'Keine Site {site} in {db}')

id_, vorlage = zeile

if marke in vorlage:
    print('CloudPanel-Vorlage schon gepatcht.')
    sys.exit(0)

if vorlage.count(anker) != 1:
    sys.exit(f'Anker nicht genau einmal in der Vorlage von {site}')

con.execute(
    'update site set vhost_template = ? where id = ?',
    (vorlage.replace(anker, block + anker), id_),
)
con.commit()
print('CloudPanel-Vorlage gepatcht.')
PY
rm -f "$BLOCK_DATEI"

# 4. pruefen und uebernehmen
nginx -t
systemctl reload nginx
echo
stand
echo
echo "Jetzt: scripts/nginx-echte-besucher-ip.sh --abnahme"
