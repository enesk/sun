#!/usr/bin/env bash
#
# Zertifikat des Portal-vhost neu ausstellen (#123, #127).
#
# Alle Portale haengen an einem vhost (sanitaerfinden.dev.conf) und damit an
# einem Zertifikat. Der CSR waechst nicht mit: eine neue Portaldomain landet
# zwar im server_name, aber nicht in der SAN-Liste. Dieses Skript stellt das
# Zertifikat mit der vollstaendigen Liste neu aus -- den Namen des heutigen
# Zertifikats plus den als Argument uebergebenen.
#
# Let's Encrypt stellt alles-oder-nichts aus: ein einziger Name, der die
# HTTP-01-Antwort dieses Servers nicht erreicht, laesst den ganzen Lauf
# scheitern. Deshalb wird jeder Name vorher geprobt und bei einem Fehlschlag
# gar nicht erst ausgestellt.
#
# Laeuft als root auf 88.198.64.145 (SSH-Kuerzel `sun`).
#
#   scripts/tls-zertifikat-nachziehen.sh --pruefen
#   scripts/tls-zertifikat-nachziehen.sh --pruefen tierarztportal.com firmenfreund.net
#   scripts/tls-zertifikat-nachziehen.sh tierarztportal.com firmenfreund.net www.firmenfreund.net
#
# --pruefen macht nur die Vorprobe und stellt nichts aus.

set -euo pipefail

SITE="sanitaerfinden.dev"
CERT="/etc/nginx/ssl-certificates/${SITE}.crt"
KEY="/etc/nginx/ssl-certificates/${SITE}.key"
VHOST="/etc/nginx/sites-enabled/${SITE}.conf"
CLPDB="/home/clp/htdocs/app/data/db.sq3"
WEBROOT="/home/sanitaerfinden/htdocs/${SITE}/public"

NUR_PRUEFEN=0
ZUSATZ=()
for arg in "$@"; do
  case "$arg" in
    --pruefen) NUR_PRUEFEN=1 ;;
    -*) echo "Unbekannte Option: $arg" >&2; exit 2 ;;
    *) ZUSATZ+=("$arg") ;;
  esac
done

if [ "$(id -u)" -ne 0 ]; then
  echo "Muss als root laufen." >&2
  exit 2
fi
for pfad in "$CERT" "$VHOST" "$WEBROOT"; do
  [ -e "$pfad" ] || { echo "Fehlt: $pfad" >&2; exit 2; }
done

# 1. Namensliste: heutiges Zertifikat + Argumente, doppelte raus
mapfile -t NAMEN < <(
  {
    openssl x509 -in "$CERT" -noout -ext subjectAltName \
      | tr ',' '\n' | sed -n 's/^ *DNS://p' | tr -d ' '
    printf '%s\n' ${ZUSATZ+"${ZUSATZ[@]}"}
  } | sed '/^$/d' | sort -u
)
echo "Namen insgesamt: ${#NAMEN[@]}"

# 2. Vorprobe: erreicht jeder Name die HTTP-01-Antwort dieses Servers?
PROBE="probe-$(date +%s)-$RANDOM"
CHALLENGE="${WEBROOT}/.well-known/acme-challenge"
mkdir -p "$CHALLENGE"
echo "$PROBE" > "${CHALLENGE}/${PROBE}"
chmod 644 "${CHALLENGE}/${PROBE}"
trap 'rm -f "${CHALLENGE}/${PROBE}"' EXIT

FEHLT=()
for name in "${NAMEN[@]}"; do
  antwort="$(curl -sL --max-time 15 "http://${name}/.well-known/acme-challenge/${PROBE}" || true)"
  if [ "$antwort" = "$PROBE" ]; then
    printf '  ok    %s\n' "$name"
  else
    printf '  FEHLT %s\n' "$name"
    FEHLT+=("$name")
  fi
done

if [ ${#FEHLT[@]} -gt 0 ]; then
  echo
  echo "${#FEHLT[@]} Name(n) erreichen diesen Server nicht -- nichts ausgestellt:"
  printf '  %s\n' "${FEHLT[@]}"
  echo "Ursache pruefen (DNS, Cloudflare-Weiterleitung, server_name im vhost),"
  echo "siehe docs/messungen/produktionsumgebung-anleitung.md, Abschnitt 4.1/4.2."
  exit 1
fi

echo "Alle Namen erreichbar."
[ "$NUR_PRUEFEN" -eq 1 ] && { echo "Nur Vorprobe (--pruefen) -- nichts ausgestellt."; exit 0; }

# 3. Sicherung vor dem Ausstellen
BACKUP="/root/tls-backup-$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP"
cp -a "$CERT" "$BACKUP/" 2>/dev/null || true
cp -a "$KEY" "$BACKUP/" 2>/dev/null || true
cp -a "$VHOST" "$BACKUP/"
cp -a "$CLPDB" "$BACKUP/"
echo "Sicherung: $BACKUP"

# 4. Ausstellen. --domainName ist der CloudPanel-Site-Name, alles uebrige SAN.
SAN="$(printf '%s\n' "${NAMEN[@]}" | grep -vx "$SITE" | paste -sd, -)"
clpctl lets-encrypt:install:certificate \
  --domainName="$SITE" \
  --subjectAlternativeName="$SAN"

# 5. Abnahme
echo
openssl x509 -in "$CERT" -noout -enddate
echo "Namen im neuen Zertifikat: $(openssl x509 -in "$CERT" -noout -ext subjectAltName | tr ',' '\n' | grep -c DNS:)"
