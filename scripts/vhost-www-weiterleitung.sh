#!/usr/bin/env bash
#
# Weiterleitungsschleife der www.-Namen beheben (#129).
#
# Der 443-Serverblock der www.-Namen in /etc/nginx/sites-enabled/
# sanitaerfinden.dev.conf antwortet mit
#
#   return 301 https://$host$request_uri;
#
# $host ist dort noch der www.-Name. Die Antwort auf https://www.<domain>/
# ist damit eine 301 auf genau dieselbe Adresse -- eine Schleife, kein
# Wechsel auf die Hauptdomain. Betroffen ist jeder www.-Name dieses vhost,
# also jedes Portal.
#
# Der Block bekommt stattdessen ein vorangestelltes
#
#   if ($host ~* ^www\.(.+)$) { return 301 https://$1$request_uri; }
#
# das das fuehrende www. abschneidet. Der alte return bleibt als Auffang
# stehen, falls ein Name ohne www. je in diesem Block landet.
#
# Bewusst kein `map` im http-Kontext: das braeuchte eine zweite Datei unter
# /etc/nginx/conf.d/, die CloudPanel nicht kennt. So bleibt die Aenderung an
# der Stelle, an der sie gilt.
#
# Gepatcht wird beides:
#   1. die ausgelieferte Datei /etc/nginx/sites-enabled/sanitaerfinden.dev.conf
#   2. site.vhost_template in /home/clp/htdocs/app/data/db.sq3 -- das ist die
#      Vorlage hinter dem Vhost-Editor der Site, aus der CloudPanel die Datei
#      neu schreibt. Ohne (2) waere die Aenderung beim naechsten Durchgang weg.
#
# Achtung: www.www.kasernencheck.de gehoert zu einem fremden Projekt auf
# demselben Server und wird nicht geprueft.
#
# Laeuft als root auf 88.198.64.145 (SSH-Kuerzel `sun`), ist wiederholbar.
#
#   scripts/vhost-www-weiterleitung.sh --pruefen   # nur zeigen, nichts schreiben
#   scripts/vhost-www-weiterleitung.sh

set -euo pipefail

SITE="sanitaerfinden.dev"
VHOST="/etc/nginx/sites-enabled/${SITE}.conf"
CLPDB="/home/clp/htdocs/app/data/db.sq3"
ANFANG="  # >>> #129 www. abschneiden"
ENDE="  # <<< #129 www. abschneiden"

NUR_PRUEFEN=0
[ "${1:-}" = "--pruefen" ] && NUR_PRUEFEN=1

if [ "$(id -u)" -ne 0 ]; then
  echo "Muss als root laufen." >&2
  exit 2
fi
for pfad in "$VHOST" "$CLPDB"; do
  [ -e "$pfad" ] || { echo "Fehlt: $pfad" >&2; exit 2; }
done

ZEITSTEMPEL="$(date +%Y%m%d-%H%M%S)"
ALT_VHOST="$(mktemp)"; NEU_VHOST="$(mktemp)"
ALT_TPL="$(mktemp)";   NEU_TPL="$(mktemp)"
trap 'rm -f "$ALT_VHOST" "$NEU_VHOST" "$ALT_TPL" "$NEU_TPL"' EXIT

cp -a "$VHOST" "$ALT_VHOST"
sqlite3 "$CLPDB" "select vhost_template from site where domain_name = '${SITE}';" > "$ALT_TPL"
[ -s "$ALT_TPL" ] || { echo "site.vhost_template fuer ${SITE} ist leer." >&2; exit 2; }

patchen() {
  QUELLE="$1" ZIEL="$2" ANFANG="$ANFANG" ENDE="$ENDE" python3 <<'PY'
import os, re, sys

quelle = os.environ['QUELLE']
ziel   = os.environ['ZIEL']
anfang = os.environ['ANFANG'].encode()
ende   = os.environ['ENDE'].encode()

# Byteweise arbeiten: die Vorlage in db.sq3 haelt CRLF, die ausgelieferte
# Datei LF. Ein Umschreiben der Zeilenenden waere ein Unterschied ueber die
# ganze Datei und im Vhost-Editor nicht mehr lesbar.
ZEILE = b'  return 301 https://$host$request_uri;'

roh = open(quelle, 'rb').read()
zeilenende = b'\r\n' if b'\r\n' in roh else b'\n'

# 1. frueheren Lauf zurueckbauen -- wiederholbar halten
roh = re.sub(
    re.escape(anfang) + b'.*?' + re.escape(ende) + re.escape(zeilenende),
    ZEILE + zeilenende, roh, flags=re.S,
)

# Genau die Zeile auf Serverebene treffen (zwei Leerzeichen Einzug). Im
# 80er-Block darueber steht derselbe return in `location /` mit vier
# Leerzeichen und bleibt unberuehrt -- dort ist $host richtig.
muster = re.compile(b'(?m)^' + re.escape(ZEILE) + b'\r?$')
treffer = muster.findall(roh)
if len(treffer) != 1:
    sys.exit(f'Erwartet genau eine Zeile "{ZEILE.decode()}" auf Serverebene, gefunden: {len(treffer)}')

ersatz = zeilenende.join([
    anfang,
    b'  # $host ist hier noch der www.-Name; ein return auf $host zeigt auf',
    b'  # sich selbst und ist eine Weiterleitungsschleife (#129).',
    b'  if ($host ~* ^www\\.(.+)$) {',
    b'    return 301 https://$1$request_uri;',
    b'  }',
    ZEILE,
    ende,
])
if zeilenende == b'\r\n':
    # Das \r der getroffenen Zeile hat das Muster mitgenommen.
    ersatz += b'\r'
open(ziel, 'wb').write(muster.sub(ersatz.replace(b'\\', b'\\\\'), roh, count=1))
PY
}

patchen "$ALT_VHOST" "$NEU_VHOST"
patchen "$ALT_TPL"   "$NEU_TPL"

if [ "$NUR_PRUEFEN" -eq 1 ]; then
  echo "--- Unterschied ${VHOST} (nichts geschrieben) ---"
  diff -u "$ALT_VHOST" "$NEU_VHOST" || true
  echo "--- Unterschied site.vhost_template (nichts geschrieben) ---"
  diff -u "$ALT_TPL" "$NEU_TPL" || true
  exit 0
fi

SICHERUNG_VHOST="${VHOST}.vor-129-${ZEITSTEMPEL}"
SICHERUNG_DB="/root/db.sq3.vor-129-${ZEITSTEMPEL}"
cp -a "$VHOST" "$SICHERUNG_VHOST"
sqlite3 "$CLPDB" ".backup '${SICHERUNG_DB}'"
echo "Sicherung: ${SICHERUNG_VHOST}"
echo "Sicherung: ${SICHERUNG_DB}"

cat "$NEU_VHOST" > "$VHOST"
if ! nginx -t; then
  echo "nginx -t fehlgeschlagen, Sicherung wird zurueckgespielt." >&2
  cat "$SICHERUNG_VHOST" > "$VHOST"
  exit 1
fi
systemctl reload nginx
echo "nginx neu geladen."

# Vorlage erst nach erfolgreichem nginx -t nachziehen.
NEU_TPL="$NEU_TPL" SITE="$SITE" CLPDB="$CLPDB" python3 <<'PY'
import os, sqlite3
neu = open(os.environ['NEU_TPL'], encoding='utf-8').read()
# sqlite3-CLI haengt beim Auslesen ein \n an; wieder abschneiden.
if neu.endswith('\n'):
    neu = neu[:-1]
db = sqlite3.connect(os.environ['CLPDB'])
db.execute('update site set vhost_template = ? where domain_name = ?',
           (neu, os.environ['SITE']))
db.commit()
db.close()
print('site.vhost_template nachgezogen.')
PY

echo "--- Probe am Ursprung (www.www.kasernencheck.de bleibt aussen vor) ---"
sed -n 's/^  server_name \(www\..*\);$/\1/p' "$VHOST" | head -1 | tr ' ' '\n' \
  | grep -v '^www\.www\.' | while read -r name; do
  printf '%-40s %s\n' "$name" \
    "$(curl -sk -o /dev/null -w '%{http_code} %{redirect_url}' --max-time 20 \
        --resolve "${name}:443:127.0.0.1" "https://${name}/")"
done
