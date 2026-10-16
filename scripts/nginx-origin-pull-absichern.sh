#!/usr/bin/env bash
#
# Ursprung gegen gefaelschte CF-Connecting-IP absichern (#45, Rest aus #43).
#
# AUSGANGSLAGE
#
# Seit #43 loest der aeussere 443-Block des Portal-vhosts
# `real_ip_header CF-Connecting-IP` auf und vertraut dafuer jedem Absender
# aus einem Cloudflare-Netz (/etc/nginx/cloudflare-ips.conf, 22 Netze,
# docs/echte-besucher-ip.md 5). Wer selbst aus einem Cloudflare-Netz heraus
# den Ursprung direkt anspricht -- ein fremder Worker, ein fremder Tunnel --
# setzt den Header frei und hebelt damit jedes throttle-Limit, jede
# Turnstile-IP-Bindung und jede Netzpruefung aus.
#
# WARUM NICHT DIE FIREWALL (Weg 1 des Tickets)
#
# 443 am Ursprung auf die Cloudflare-Netze zu beschraenken trifft den ganzen
# Server: 88.198.64.145 traegt neben den Portalen die fremden Projekte
# (minber.app, kasernencheck.de, widimedia.com, eneskul.com ...) und die
# beiden Portale ohne Kante (elektrikerportal.com, sanitaerfinden.com).
# `--zonen-pruefen` zeigt, wie viele Namen eine pauschale Sperre mitnimmt.
#
# WAS DIESES SKRIPT TUT (Weg 2, Authenticated Origin Pulls)
#
# Der Portal-Block bekommt das Client-Zertifikat der Cloudflare-Kante
# (`ssl_client_certificate` + `ssl_verify_client optional`) und weist
# Anfragen ab, die ohne beglaubigtes Kantenzertifikat kommen -- aber NUR
# fuer Hosts, die ausdruecklich scharf geschaltet sind. Der Block bedient
# alle 23 Portale UND fremde Namen (analytics.widimedia.com,
# www.kasernencheck.de, perigee.widimedia.com) im selben server_name; ein
# `ssl_verify_client on` fuer den ganzen Block wuerde die alle erschlagen.
# Daher die Host-Liste in /etc/nginx/aop-scharfe-hosts.
#
#   --einrichten   aendert fuer sich NICHTS am Verhalten: die Liste ist
#                  leer, jeder Host bleibt wie vor dem Aufruf. Erst
#                  `--scharf <host>` wirkt.
#   --scharf       setzt voraus, dass Authenticated Origin Pulls in der
#                  Cloudflare-Zone dieses Hosts EINGESCHALTET ist (Handgriff
#                  von Enes, `sun-kein-cloudflare-zugang`). Das Skript prueft
#                  nach dem Reload selbst ueber die Kante nach und nimmt die
#                  Aenderung bei Fehlschlag automatisch zurueck.
#
# Ausnahmen, bewusst eingebaut:
#   * /.well-known/... bleibt immer offen, sonst stirbt die
#     http-01-Erneuerung des gemeinsamen Zertifikats
#     (scripts/tls-zertifikat-nachziehen.sh).
#   * Das Zertifikat der Kante ist das globale Cloudflare-Zertifikat. Es
#     beweist "irgendeine Cloudflare-Zone", nicht "unsere". Siehe
#     docs/echte-besucher-ip.md 6.3.
#
# Gepatcht wird -- wie bei scripts/nginx-echte-besucher-ip.sh -- beides: die
# ausgelieferte vhost-Datei UND site.vhost_template in der CloudPanel-DB.
#
# Laeuft als root auf 88.198.64.145 (SSH-Kuerzel `sun`), ist wiederholbar.
#
#   scripts/nginx-origin-pull-absichern.sh --pruefen
#   scripts/nginx-origin-pull-absichern.sh --zonen-pruefen
#   scripts/nginx-origin-pull-absichern.sh --einrichten
#   scripts/nginx-origin-pull-absichern.sh --scharf firmenfreund.de
#   scripts/nginx-origin-pull-absichern.sh --abnahme
#   scripts/nginx-origin-pull-absichern.sh --entschaerfen firmenfreund.de
#   scripts/nginx-origin-pull-absichern.sh --zurueck

set -euo pipefail

SITE="sanitaerfinden.dev"
VHOST="/etc/nginx/sites-enabled/${SITE}.conf"
CLPDB="/home/clp/htdocs/app/data/db.sq3"
CA="/etc/nginx/cloudflare-origin-pull-ca.pem"
HOSTLISTE="/etc/nginx/aop-scharfe-hosts"
MAPDATEI="/etc/nginx/conf.d/aop-scharfe-hosts.conf"
URSPRUNG="88.198.64.145"
MARKE="# >>> #45 Authenticated Origin Pulls"
MARKE_ENDE="# <<< #45 Authenticated Origin Pulls"

# Quelle: https://developers.cloudflare.com/ssl/origin-configuration/authenticated-origin-pull/
CA_URL="https://developers.cloudflare.com/ssl/static/authenticated_origin_pull_ca.pem"
# Geprueft am 16.10.2026. Abweichung = nicht blind uebernehmen.
CA_FINGERPRINT="9A:1A:C2:B4:BE:15:F9:F2:7E:EE:20:A7:34:CB:A4:E9:89:8F:61:00:1B:3B:D7:C8:4B:69:B5:6A:3E:25:A2:B9"
CA_CN="origin-pull.cloudflare.net"

MODUS="${1:---pruefen}"
shift || true

case "$MODUS" in
  --pruefen|--zonen-pruefen|--einrichten|--scharf|--entschaerfen|--abnahme|--zurueck) ;;
  *) echo "Unbekannt: $MODUS" >&2; exit 2 ;;
esac

# ------------------------------------------------------------------ Helfer

scharfe_hosts() {
  [ -f "$HOSTLISTE" ] || return 0
  grep -vE '^\s*(#|$)' "$HOSTLISTE" || true
}

# Adresse eines Hosts beim autoritativen DNS erfragen, nie beim eigenen
# Resolver -- der Entwicklungsrouter haelt veraltete Antworten
# (`kante-messen-autoritatives-dns`).
autoritativ() {
  local host="$1" ns adresse
  ns="$(dig +short NS "${host#www1.}" | head -1)"
  [ -n "$ns" ] || ns="$(dig +short NS "$(echo "${host#www1.}" | awk -F. '{print $(NF-1)"."$NF}')" | head -1)"
  [ -n "$ns" ] || return 1
  adresse="$(dig +short A "$host" "@${ns}" | grep -E '^[0-9.]+$' | head -1)"
  [ -n "$adresse" ] || return 1
  echo "$adresse"
}

hinter_kante() {
  local host="$1" adresse
  adresse="$(autoritativ "$host")" || return 1
  [ "$adresse" = "$URSPRUNG" ] && return 1
  curl -sSk -o /dev/null -D - --max-time 20 \
    --resolve "${host}:443:${adresse}" "https://${host}/robots.txt?cb=$$" 2>/dev/null \
    | grep -qi '^cf-ray:'
}

# Antwortcode ueber die Kante, mit Cache-Brecher.
code_ueber_kante() {
  local host="$1" adresse
  adresse="$(autoritativ "$host")" || { echo "dns"; return 0; }
  curl -sSk -o /dev/null -w '%{http_code}' --max-time 20 \
    --resolve "${host}:443:${adresse}" \
    "https://${host}/robots.txt?cb=aop-$(date +%s)-$RANDOM" 2>/dev/null || echo "000"
}

# Antwortcode direkt am Ursprung, CF-Connecting-IP gefaelscht.
code_direkt_gefaelscht() {
  local host="$1"
  curl -sSk -o /dev/null -w '%{http_code}' --max-time 20 \
    --resolve "${host}:443:${URSPRUNG}" \
    -H 'CF-Connecting-IP: 203.0.113.77' \
    "https://${host}/robots.txt?cb=aop-$(date +%s)-$RANDOM" 2>/dev/null || echo "000"
}

map_schreiben() {
  local tmp
  tmp="$(mktemp)"
  {
    echo "# Erzeugt von scripts/nginx-origin-pull-absichern.sh (#45)."
    echo "# Scharfe Hosts stehen in ${HOSTLISTE}, nicht hier."
    echo "#"
    echo "# aop_pflicht     1 = dieser Host darf nur ueber die beglaubigte Kante kommen"
    echo "# aop_beglaubigt  1 = die Verbindung hat das Cloudflare-Client-Zertifikat vorgelegt"
    echo "# aop_ausnahme    1 = /.well-known (ACME) -- bleibt immer offen"
    echo "# aop_abweisen    1 = Pflicht, aber nicht beglaubigt und keine Ausnahme"
    echo
    echo 'map $host $aop_pflicht {'
    echo '    default 0;'
    local host
    while read -r host; do
      [ -n "$host" ] || continue
      echo "    ${host} 1;"
      case "$host" in
        www1.*|www.*) ;;
        *) echo "    www1.${host} 1;" ;;
      esac
    done < <(scharfe_hosts)
    echo '}'
    echo
    echo 'map $ssl_client_verify $aop_beglaubigt {'
    echo '    default  0;'
    echo '    SUCCESS  1;'
    echo '}'
    echo
    echo 'map $request_uri $aop_ausnahme {'
    echo '    default            0;'
    echo '    ~^/\.well-known/   1;'
    echo '}'
    echo
    echo 'map "$aop_pflicht$aop_beglaubigt$aop_ausnahme" $aop_abweisen {'
    echo '    default  0;'
    echo '    "100"    1;'
    echo '}'
  } > "$tmp"

  if [ -f "$MAPDATEI" ] && cmp -s "$MAPDATEI" "$tmp"; then
    rm -f "$tmp"
    return 0
  fi
  mv "$tmp" "$MAPDATEI"
  chmod 644 "$MAPDATEI"
}

root_pflicht() {
  [ "$(id -u)" -eq 0 ] || { echo "Muss als root laufen." >&2; exit 2; }
}

# ------------------------------------------------------------------ pruefen

if [ "$MODUS" = "--pruefen" ]; then
  echo "== Client-Zertifikat der Kante =="
  if [ -f "$CA" ]; then
    echo "  $CA"
    openssl x509 -in "$CA" -noout -subject -enddate | sed 's/^/  /'
    openssl x509 -in "$CA" -noout -fingerprint -sha256 | sed 's/^/  /'
  else
    echo "  fehlt -- --einrichten noch nicht gelaufen"
  fi
  echo
  echo "== Portal-vhost =="
  grep -nE "ssl_client_certificate|ssl_verify_client|aop_abweisen" "$VHOST" | sed 's/^/  /' \
    || echo "  (nicht gepatcht)"
  echo
  echo "== map =="
  [ -f "$MAPDATEI" ] && echo "  $MAPDATEI" || echo "  fehlt"
  echo
  echo "== scharfe Hosts =="
  if [ -n "$(scharfe_hosts)" ]; then
    scharfe_hosts | sed 's/^/  /'
  else
    echo "  keine -- CF-Connecting-IP ist auf allen Hosts weiter faelschbar,"
    echo "  wenn der Absender in einem Cloudflare-Netz sitzt (#45 offen)."
  fi
  exit 0
fi

# ------------------------------------------------------- Weg 1 abschaetzen

if [ "$MODUS" = "--zonen-pruefen" ]; then
  echo "Folgenabschaetzung fuer Weg 1 (443 am Ursprung auf die CF-Netze sperren)."
  echo "Jeder Name, der NICHT hinter der Kante liegt, verliert dadurch seinen Zugang."
  echo
  NAMEN="$(grep -hoE '^\s*server_name [^;]+;' /etc/nginx/sites-enabled/*.conf \
    | sed 's/^\s*server_name //; s/;$//' | tr ' ' '\n' \
    | grep -vE '^\s*$|^www1?\.|^_$|\*' | tr 'A-Z' 'a-z' | sort -u)"
  OHNE=0
  MIT=0
  while read -r name; do
    [ -n "$name" ] || continue
    if hinter_kante "$name"; then
      MIT=$((MIT + 1))
      echo "  Kante   ${name}"
    else
      OHNE=$((OHNE + 1))
      echo "  DIREKT  ${name}"
    fi
  done <<< "$NAMEN"
  echo
  echo "hinter der Kante: ${MIT}, direkt erreichbar: ${OHNE}"
  echo "Weg 1 ist nur vertretbar, wenn die ${OHNE} direkten Namen wegfallen duerfen."
  exit 0
fi

# --------------------------------------------------------------- abnahme

if [ "$MODUS" = "--abnahme" ]; then
  FEHLER=0
  if [ -z "$(scharfe_hosts)" ]; then
    echo "Kein Host scharf -- nichts abzunehmen, #45 bleibt offen." >&2
    echo "Reihenfolge: Cloudflare-Zone auf Authenticated Origin Pulls stellen," >&2
    echo "dann --scharf <host>." >&2
    exit 1
  fi
  while read -r host; do
    [ -n "$host" ] || continue
    echo "== ${host} =="
    DIREKT="$(code_direkt_gefaelscht "$host")"
    echo "  direkt mit gefaelschter CF-Connecting-IP: ${DIREKT} (Soll 403)"
    [ "$DIREKT" = "403" ] || { echo "  FEHLER" >&2; FEHLER=1; }
    KANTE="$(code_ueber_kante "$host")"
    echo "  ueber die Kante:                          ${KANTE} (Soll 200)"
    [ "$KANTE" = "200" ] || { echo "  FEHLER" >&2; FEHLER=1; }
  done < <(scharfe_hosts)
  echo
  echo "Gegenprobe #43 bleibt Pflicht: scripts/nginx-echte-besucher-ip.sh --abnahme"
  exit "$FEHLER"
fi

# ------------------------------------------------------------- einrichten

if [ "$MODUS" = "--einrichten" ]; then
  root_pflicht
  for pfad in "$VHOST" "$CLPDB"; do
    [ -e "$pfad" ] || { echo "Fehlt: $pfad" >&2; exit 2; }
  done
  ZEITSTEMPEL="$(date +%Y%m%d-%H%M%S)"

  # 1. Client-Zertifikat der Kante
  if [ -f "$CA" ]; then
    echo "Zertifikat liegt schon: $CA"
  else
    TMPCA="$(mktemp)"
    curl -fsS --max-time 25 -o "$TMPCA" "$CA_URL"
    IST_CN="$(openssl x509 -in "$TMPCA" -noout -subject | sed 's/.*CN *= *//; s/ *$//')"
    IST_FP="$(openssl x509 -in "$TMPCA" -noout -fingerprint -sha256 | sed 's/.*=//')"
    if [ "$IST_CN" != "$CA_CN" ]; then
      echo "Unerwarteter CN: ${IST_CN} (erwartet ${CA_CN})" >&2; exit 65
    fi
    if [ "$IST_FP" != "$CA_FINGERPRINT" ]; then
      echo "Fingerprint abweichend:" >&2
      echo "  geholt:   ${IST_FP}" >&2
      echo "  erwartet: ${CA_FINGERPRINT}" >&2
      echo "Cloudflare hat das Zertifikat getauscht -- erst pruefen, dann" >&2
      echo "CA_FINGERPRINT im Skript nachziehen." >&2
      exit 65
    fi
    openssl x509 -in "$TMPCA" -noout -checkend 2592000 >/dev/null \
      || { echo "Zertifikat laeuft binnen 30 Tagen ab." >&2; exit 65; }
    mv "$TMPCA" "$CA"
    chmod 644 "$CA"
    echo "Zertifikat geschrieben: $CA"
  fi

  # 2. Host-Liste (leer anlegen) und map
  if [ ! -f "$HOSTLISTE" ]; then
    {
      echo "# Hosts, die nur noch ueber die beglaubigte Cloudflare-Kante"
      echo "# erreichbar sind (#45). Ein Host je Zeile, www1.-Variante wird"
      echo "# automatisch mitgenommen."
      echo "#"
      echo "# NICHT von Hand pflegen -- scripts/nginx-origin-pull-absichern.sh"
      echo "# --scharf <host> / --entschaerfen <host>. Der Eintrag wirkt erst,"
      echo "# wenn in der Cloudflare-Zone des Hosts Authenticated Origin Pulls"
      echo "# eingeschaltet ist, sonst sperrt er die Kante selbst aus."
    } > "$HOSTLISTE"
    chmod 644 "$HOSTLISTE"
    echo "Host-Liste angelegt (leer): $HOSTLISTE"
  fi
  map_schreiben
  echo "map geschrieben: $MAPDATEI"

  # 3. vhost + CloudPanel-Vorlage
  BLOCK_DATEI="$(mktemp)"
  cat > "$BLOCK_DATEI" <<EOF
  ${MARKE}
  #
  # Die Kante legt ihr Client-Zertifikat vor, wenn in der Zone
  # "Authenticated Origin Pulls" eingeschaltet ist. Hier steht "optional"
  # statt "on", weil dieser Block auch Namen bedient, die nicht hinter der Kante liegen
  # (elektrikerportal.com, sanitaerfinden.com) und fremde Projekte
  # (analytics.widimedia.com, www.kasernencheck.de, perigee.widimedia.com).
  #
  # Abgewiesen wird nur, was in /etc/nginx/aop-scharfe-hosts steht --
  # Entscheidung in /etc/nginx/conf.d/aop-scharfe-hosts.conf. Ohne das
  # bliebe CF-Connecting-IP fuer jeden Absender aus einem Cloudflare-Netz
  # faelschbar (docs/echte-besucher-ip.md 5).
  #
  # Steht VOR der https-Umleitung: eine http-Anfrage kann kein Zertifikat
  # vorlegen und hat auf einem scharfen Host nichts zu suchen. /.well-known
  # bleibt offen, sonst stirbt die ACME-Erneuerung.
  ssl_client_certificate ${CA};
  ssl_verify_client optional;
  if (\$aop_abweisen) {
    return 403;
  }
  ${MARKE_ENDE}
EOF

  if grep -q "$MARKE" "$VHOST"; then
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

  python3 - "$CLPDB" "$BLOCK_DATEI" "$SITE" "$MARKE" <<'PY'
import sqlite3, sys

db, blockdatei, site, marke = sys.argv[1:5]
block = open(blockdatei).read()
anker = '  if ($scheme != "https") {\n'

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

  nginx -t
  systemctl reload nginx
  echo
  echo "Eingerichtet, Verhalten unveraendert (Liste leer)."
  echo "Weiter: Cloudflare-Zone auf Authenticated Origin Pulls stellen,"
  echo "dann scripts/nginx-origin-pull-absichern.sh --scharf <host>"
  exit 0
fi

# ---------------------------------------------------- scharf / entschaerfen

if [ "$MODUS" = "--scharf" ] || [ "$MODUS" = "--entschaerfen" ]; then
  root_pflicht
  [ "$#" -gt 0 ] || { echo "Host angeben." >&2; exit 2; }
  grep -q "$MARKE" "$VHOST" || { echo "Erst --einrichten." >&2; exit 2; }

  [ -f "$HOSTLISTE" ] || : > "$HOSTLISTE"
  VORHER="$(mktemp)"
  cp -a "$HOSTLISTE" "$VORHER"

  for host in "$@"; do
    host="$(echo "$host" | tr 'A-Z' 'a-z')"
    if [ "$MODUS" = "--entschaerfen" ]; then
      grep -vxF "$host" "$HOSTLISTE" > "${HOSTLISTE}.neu" || true
      mv "${HOSTLISTE}.neu" "$HOSTLISTE"
      chmod 644 "$HOSTLISTE"
      echo "entschaerft: ${host}"
      continue
    fi
    if scharfe_hosts | grep -qxF "$host"; then
      echo "schon scharf: ${host}"
      continue
    fi
    if ! hinter_kante "$host"; then
      echo "ABBRUCH: ${host} liegt nicht hinter der Cloudflare-Kante." >&2
      echo "  Dort waere die Sperre eine Totalsperre, und real_ip_header ist" >&2
      echo "  ohnehin wirkungslos, weil der Absender nicht in der Netzliste steht." >&2
      cp -a "$VORHER" "$HOSTLISTE"
      rm -f "$VORHER"
      exit 65
    fi
    echo "$host" >> "$HOSTLISTE"
    echo "scharf: ${host}"
  done

  map_schreiben
  if ! nginx -t; then
    cp -a "$VORHER" "$HOSTLISTE"
    map_schreiben
    rm -f "$VORHER"
    echo "nginx -t fehlgeschlagen, Liste zurueckgenommen." >&2
    exit 1
  fi
  systemctl reload nginx

  # Nachkontrolle: jeder scharfe Host MUSS ueber die Kante weiter 200
  # liefern. Ist in der Zone kein Authenticated Origin Pull eingeschaltet,
  # legt die Kante kein Zertifikat vor und wir haetten uns selbst
  # ausgesperrt -- dann zurueck.
  FEHLER=0
  while read -r host; do
    [ -n "$host" ] || continue
    CODE="$(code_ueber_kante "$host")"
    echo "  ${host} ueber die Kante: ${CODE}"
    [ "$CODE" = "200" ] || FEHLER=1
  done < <(scharfe_hosts)

  if [ "$FEHLER" -ne 0 ]; then
    cp -a "$VORHER" "$HOSTLISTE"
    map_schreiben
    nginx -t && systemctl reload nginx
    rm -f "$VORHER"
    echo >&2
    echo "ZURUECKGENOMMEN: ein scharfer Host war ueber die Kante nicht mehr" >&2
    echo "erreichbar. Pruefen: ist in der Zone 'Authenticated Origin Pulls'" >&2
    echo "(SSL/TLS -> Origin Server) wirklich eingeschaltet, und steht die" >&2
    echo "Zone auf Full/Full (strict)? Bei 'Flexible' holt die Kante ueber" >&2
    echo "http und kann kein Zertifikat vorlegen." >&2
    exit 1
  fi

  rm -f "$VORHER"
  echo
  echo "Jetzt: scripts/nginx-origin-pull-absichern.sh --abnahme"
  exit 0
fi

# ---------------------------------------------------------------- zurueck

if [ "$MODUS" = "--zurueck" ]; then
  root_pflicht
  ZEITSTEMPEL="$(date +%Y%m%d-%H%M%S)"
  if grep -q "$MARKE" "$VHOST"; then
    cp -a "$VHOST" "${VHOST}.bak.${ZEITSTEMPEL}"
    python3 - "$VHOST" "$MARKE" "$MARKE_ENDE" <<'PY'
import sys

ziel, start, ende = sys.argv[1:4]
zeilen = open(ziel).read().splitlines(keepends=True)
raus, drin = [], False
for zeile in zeilen:
    if start in zeile:
        drin = True
        continue
    if ende in zeile:
        drin = False
        continue
    if not drin:
        raus.append(zeile)
open(ziel, 'w').write(''.join(raus))
PY
    echo "vhost zurueckgebaut (Sicherung: ${VHOST}.bak.${ZEITSTEMPEL})."
  fi
  python3 - "$CLPDB" "$SITE" "$MARKE" "$MARKE_ENDE" <<'PY'
import sqlite3, sys

db, site, start, ende = sys.argv[1:5]
con = sqlite3.connect(db)
zeile = con.execute(
    'select id, vhost_template from site where domain_name = ?', (site,)
).fetchone()
if zeile is None:
    sys.exit(f'Keine Site {site} in {db}')
id_, vorlage = zeile
if start not in vorlage:
    print('CloudPanel-Vorlage war nicht gepatcht.')
    sys.exit(0)
raus, drin = [], False
for z in vorlage.splitlines(keepends=True):
    if start in z:
        drin = True
        continue
    if ende in z:
        drin = False
        continue
    if not drin:
        raus.append(z)
con.execute('update site set vhost_template = ? where id = ?', (''.join(raus), id_))
con.commit()
print('CloudPanel-Vorlage zurueckgebaut.')
PY
  rm -f "$MAPDATEI"
  nginx -t
  systemctl reload nginx
  echo "Zurueckgebaut. ${HOSTLISTE} und ${CA} bleiben liegen."
  exit 0
fi
