#!/usr/bin/env bash
#
# Namen ohne Tenant aus der Laravel-Auslieferung nehmen (#128).
#
# Sechs Namen stehen im server_name des Portal-vhost, haben aber keinen
# Eintrag in tenants.domain. Jeder Aufruf landet deshalb in Laravel und
# endet mit TenantCouldNotBeIdentifiedOnDomainException und HTTP 500.
#
# tenants.domain ist einwertig (DomainTenantResolver sucht direkt darauf),
# ein Portal kann also keine zweite Domain fuehren. Deshalb werden die
# Wunschdomains vor Laravel per 301 auf das laufende Portal gelenkt:
#
#   apothekefinden.net  -> apotheke.firmenfreund.de
#   arztfinder.net      -> arztfinder.firmenfreund.de
#   unfallarzt.net      -> unfallarzt.firmenfreund.de
#   zahnarztportal.com  -> zahnarzt.firmenfreund.de
#   firmenfreund.com    -> firmenfreund.de
#   sanitaerfinden.dev  -> kein Portal (CloudPanel-Sitename), HTTP 404
#
# Alle sechs Namen bleiben im vhost stehen, damit die SAN-Liste des
# gemeinsamen Zertifikats unveraendert bleibt (#130) und die HTTP-01-Probe
# unter /.well-known/ weiter aus dem Webroot beantwortet wird.
#
# Laeuft als root auf 88.198.64.145 (SSH-Kuerzel `sun`), ist wiederholbar.
#
#   scripts/vhost-namen-ohne-tenant.sh --pruefen   # nur zeigen, nichts schreiben
#   scripts/vhost-namen-ohne-tenant.sh

set -euo pipefail

SITE="sanitaerfinden.dev"
VHOST="/etc/nginx/sites-enabled/${SITE}.conf"
WEBROOT="/home/sanitaerfinden/htdocs/${SITE}/public"
CERT="/etc/nginx/ssl-certificates/${SITE}.crt"
KEY="/etc/nginx/ssl-certificates/${SITE}.key"
ANFANG="# >>> #128 Namen ohne Tenant"
ENDE="# <<< #128 Namen ohne Tenant"

NUR_PRUEFEN=0
[ "${1:-}" = "--pruefen" ] && NUR_PRUEFEN=1

if [ "$(id -u)" -ne 0 ]; then
  echo "Muss als root laufen." >&2
  exit 2
fi
for pfad in "$VHOST" "$WEBROOT" "$CERT" "$KEY"; do
  [ -e "$pfad" ] || { echo "Fehlt: $pfad" >&2; exit 2; }
done

SICHERUNG="${VHOST}.vor-128-$(date +%Y%m%d-%H%M%S)"
if [ "$NUR_PRUEFEN" -eq 0 ]; then
  cp -a "$VHOST" "$SICHERUNG"
  echo "Sicherung: $SICHERUNG"
fi

NEU="$(mktemp)"
trap 'rm -f "$NEU"' EXIT

VHOST="$VHOST" WEBROOT="$WEBROOT" CERT="$CERT" KEY="$KEY" \
ANFANG="$ANFANG" ENDE="$ENDE" ZIEL="$NEU" python3 <<'PY'
import os, re

vhost   = os.environ['VHOST']
webroot = os.environ['WEBROOT']
cert    = os.environ['CERT']
key     = os.environ['KEY']
anfang  = os.environ['ANFANG']
ende    = os.environ['ENDE']
ziel    = os.environ['ZIEL']

# Name -> Ziel, None bedeutet: keine Weiterleitung, HTTP 404
UMLEITUNG = {
    'apothekefinden.net': 'apotheke.firmenfreund.de',
    'arztfinder.net':     'arztfinder.firmenfreund.de',
    'unfallarzt.net':     'unfallarzt.firmenfreund.de',
    'zahnarztportal.com': 'zahnarzt.firmenfreund.de',
    'firmenfreund.com':   'firmenfreund.de',
    'sanitaerfinden.dev': None,
}

def varianten(name):
    # CloudPanel fuehrt zu jedem Namen die www.- und www1.-Fassung.
    return [name, f'www.{name}', f'www1.{name}']

raus = set()
for name in UMLEITUNG:
    raus.update(varianten(name))
# Tippfehler aus dem Bestand: es gibt keine Domain bfirmenfreund.com.
raus.add('www1.bfirmenfreund.com')

text = open(vhost, encoding='utf-8').read()

# 1. frueheren Lauf dieses Skripts entfernen -- wiederholbar halten
text = re.sub(
    re.escape(anfang) + r'.*?' + re.escape(ende) + r'\n',
    '', text, flags=re.S,
)

# 2. Namen aus jedem server_name nehmen (Vergleich ohne Gross-/Kleinschreibung)
entfernt = 0
def kuerzen(treffer):
    global entfernt
    namen = treffer.group(1).split()
    behalten = [n for n in namen if n.lower() not in raus]
    entfernt += len(namen) - len(behalten)
    if not behalten:
        raise SystemExit('server_name waere leer -- Abbruch')
    return '  server_name ' + ' '.join(behalten) + ';'

text = re.sub(r'^  server_name ([^;]+);', kuerzen, text, flags=re.M)

# 3. eigene Bloecke fuer die Namen anhaengen
bloecke = [anfang, '#', '# Wunschdomains ohne Eintrag in tenants.domain: 301 auf das',
           '# laufende Portal, statt in Laravel zu laufen und 500 zu liefern.']
for name, ziel_name in UMLEITUNG.items():
    namen = ' '.join(varianten(name))
    if ziel_name:
        antwort = f'    return 301 https://{ziel_name}$request_uri;'
        note = f'# {name} -> {ziel_name}'
    else:
        # CloudPanel-Sitename, kein Portal: klar benannter 404 statt Stacktrace.
        antwort = ('    default_type text/plain;\n'
                   '    return 404 "Kein Portal unter diesem Namen.\\n";')
        note = f'# {name}: CloudPanel-Sitename, kein Portal'
    bloecke.append(f"""
{note}
server {{
  listen 80;
  listen [::]:80;
  listen 443 quic;
  listen 443 ssl;
  listen [::]:443 quic;
  listen [::]:443 ssl;
  http2 on;
  http3 off;
  ssl_certificate_key {key};
  ssl_certificate {cert};
  server_name {namen};
  root {webroot};
  access_log off;
  # HTTP-01-Probe muss aus dem Webroot kommen, sonst scheitert die
  # Ausstellung des gemeinsamen Zertifikats fuer diesen Namen.
  location ~ /.well-known {{
    auth_basic off;
    allow all;
  }}
  location / {{
{antwort}
  }}
}}""")
bloecke.append(f'\n{ende}\n')

open(ziel, 'w', encoding='utf-8').write(text.rstrip('\n') + '\n' + '\n'.join(bloecke))
print(f'Namen aus server_name entfernt: {entfernt}')
PY

if [ "$NUR_PRUEFEN" -eq 1 ]; then
  echo "--- Unterschied (nichts geschrieben) ---"
  diff -u "$VHOST" "$NEU" || true
  exit 0
fi

cat "$NEU" > "$VHOST"
if ! nginx -t; then
  echo "nginx -t fehlgeschlagen, Sicherung wird zurueckgespielt." >&2
  cat "$SICHERUNG" > "$VHOST"
  exit 1
fi
systemctl reload nginx
echo "nginx neu geladen."

echo "--- Probe am Ursprung ---"
for name in apothekefinden.net arztfinder.net unfallarzt.net zahnarztportal.com firmenfreund.com sanitaerfinden.dev; do
  printf '%-24s %s\n' "$name" \
    "$(curl -sk -o /dev/null -w '%{http_code} %{redirect_url}' --max-time 20 \
        --resolve "${name}:443:127.0.0.1" "https://${name}/")"
done
