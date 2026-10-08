#!/usr/bin/env bash
#
# DNS-Bestand und Gegenprobe fuer den Umzug einer Portal-Zone hinter die
# Cloudflare-Kante (#34, Vorbedingung fuer die Wirkung von #24).
#
# Der Umzug selbst ist Handarbeit in zwei fremden Oberflaechen (Cloudflare
# "Add site", Nameserver bei name.com) und bleibt es — es gibt keinen
# Cloudflare-Token in Reichweite (#18, docs/bot-traffic.md Abschnitt 2).
# Automatisierbar ist der Teil, der dabei schiefgehen kann: Cloudflares
# Zonen-Scan uebernimmt beim Anlegen nicht zwingend jeden Record, und ein
# fehlender MX, SPF- oder DKIM-Eintrag faellt erst auf, wenn Mail nicht mehr
# ankommt. Dieses Skript haelt den Stand VOR dem Umzug fest und vergleicht
# danach dagegen.
#
# Gefragt wird immer am autoritativen Nameserver der Zone, nicht am Resolver:
# oeffentliche Resolver halten alte Werte (am 08.10.2026 lieferten 1.1.1.1 und
# 8.8.8.8 fuer elektrikerportal.com zwei Cloudflare-Adressen, waehrend
# name.com autoritativ 88.198.64.145 nannte).
#
# Aufruf:
#   scripts/zone-dns-umzug.sh --bestand       # vor dem Umzug: Stand festhalten
#   scripts/zone-dns-umzug.sh --vergleichen   # nach dem Umzug: gegen den Stand pruefen
#   scripts/zone-dns-umzug.sh --abnahme       # Vergleich + Kantenprobe in einem Lauf
#   scripts/zone-dns-umzug.sh --zone=elektrikerportal.com --bestand
#   scripts/zone-dns-umzug.sh --vergleichen --stand=docs/messungen/zone-dns-<zone>-<datum>.txt
#
# Bewertung im Vergleich:
#   * MX, TXT (SPF/DMARC/Verifikation), CAA und DKIM-CNAMEs muessen gleich
#     bleiben — jede Abweichung ist ein Fehler (Exit 71).
#   * A/AAAA/CNAME der Web-Namen aendern sich beim Umzug erwartungsgemaess auf
#     Cloudflare-Adressen; das wird nur gemeldet.
#   * NS und SOA wechseln naturgemaess und werden nicht bewertet.
#
# Exit-Codes:
#   0  Bestand geschrieben bzw. Pruefung bestanden
# 64  unbekannte Option
# 66  kein Bestand zum Vergleichen gefunden
# 69  dig fehlt
# 71  Abweichung gefunden

set -euo pipefail

ZONEN_VORGABE='elektrikerportal.com sanitaerfinden.com'
ABLAGE="${ABLAGE:-docs/messungen}"
HEUTE="$(date +%F)"

# Namen, die bei den Portalen ueberhaupt vorkommen koennen. Lieber ein paar zu
# viel abfragen als einen DKIM-Selektor verlieren.
WEB_NAMEN='www mail smtp imap pop webmail autodiscover autoconfig ftp'
TEXT_NAMEN='_dmarc _domainkey default._domainkey google._domainkey
selector1._domainkey selector2._domainkey k1._domainkey s1._domainkey
s2._domainkey mandrill._domainkey dkim._domainkey zoho._domainkey
fm1._domainkey fm2._domainkey fm3._domainkey _acme-challenge
_cf-custom-hostname'

MODUS=''
ZONEN=''
STAND=''
while [ $# -gt 0 ]; do
  case "$1" in
    --bestand) MODUS=bestand; shift ;;
    --vergleichen) MODUS=vergleichen; shift ;;
    --abnahme) MODUS=abnahme; shift ;;
    --zone=*) ZONEN="${ZONEN} ${1#--zone=}"; shift ;;
    --stand=*) STAND="${1#--stand=}"; shift ;;
    -h|--help) sed -n '2,39p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done
[ -n "$MODUS" ] || MODUS=bestand
[ -n "$ZONEN" ] || ZONEN="$ZONEN_VORGABE"

command -v dig >/dev/null || { echo 'dig fehlt (macOS: brew install bind).' >&2; exit 69; }

# Autoritativer Nameserver der Zone. Faellt auf den Resolver zurueck, wenn die
# Delegierung gerade nicht antwortet (Propagation).
autoritativ() {
  local zone="$1" ns
  ns="$(dig +short NS "$zone" 2>/dev/null | sed 's/\.$//' | sort | head -1)"
  [ -n "$ns" ] || ns=''
  printf '%s' "$ns"
}

# Eine Abfrage, normiert auf "name<TAB>typ<TAB>wert". Name ohne Zone und ohne
# Punkt am Ende, Apex als "@". TTL fliegt raus: sie darf sich aendern.
abfrage() {
  local ns="$1" zone="$2" name="$3" typ="$4" voll
  if [ "$name" = '@' ]; then voll="$zone"; else voll="${name}.${zone}"; fi
  local server=()
  [ -z "$ns" ] || server=("@${ns}")
  dig +noall +answer +time=3 +tries=1 "${server[@]}" "$voll" "$typ" 2>/dev/null \
    | awk -v zone="$zone" -v typ="$typ" '
        $4 == typ {
          name = tolower($1); sub(/\.$/, "", name)
          if (name == zone) { name = "@" } else { sub("\\." zone "$", "", name) }
          wert = ""
          for (i = 5; i <= NF; i++) { wert = wert (i > 5 ? " " : "") $i }
          print name "\t" typ "\t" wert
        }' \
    | sort -u
}

# Alle interessanten Records einer Zone.
bestand_zone() {
  local zone="$1" ns name
  ns="$(autoritativ "$zone")"
  for typ in NS A AAAA MX TXT CAA; do
    abfrage "$ns" "$zone" '@' "$typ"
  done
  # Der Platzhalter-Record wird einzeln abgefragt: in der Namensliste wuerde
  # ihn die Shell zu Dateinamen aufloesen. Beide Zonen haben ihn (08.10.2026,
  # "*" A 88.198.64.145) — daher antwortet dort jeder Name, auch mail/ftp. Beim
  # Umzug ist das der Knackpunkt: ein Platzhalter laesst sich nur im
  # Enterprise-Tarif proxyen, er bleibt also grau.
  for typ in A AAAA CNAME; do
    abfrage "$ns" "$zone" '*' "$typ"
  done
  for name in $WEB_NAMEN; do
    for typ in A AAAA CNAME; do
      abfrage "$ns" "$zone" "$name" "$typ"
    done
  done
  for name in $TEXT_NAMEN; do
    for typ in TXT CNAME; do
      abfrage "$ns" "$zone" "$name" "$typ"
    done
  done
}

# Record kritisch? Dann darf er sich beim Umzug nicht aendern.
kritisch() {
  local name="$1" typ="$2"
  case "$typ" in
    MX|TXT|CAA|SRV) return 0 ;;
    CNAME) case "$name" in *_domainkey*|_dmarc|mail|smtp|autodiscover|autoconfig) return 0 ;; esac ;;
  esac
  return 1
}

standdatei() {
  local zone="$1"
  printf '%s/zone-dns-%s-%s.txt' "$ABLAGE" "$zone" "$HEUTE"
}

# Neueste abgelegte Aufnahme einer Zone.
letzter_stand() {
  local zone="$1"
  ls -1 "${ABLAGE}"/zone-dns-"${zone}"-*.txt 2>/dev/null | sort | tail -1
}

schreibe_bestand() {
  local zone datei ns
  for zone in $ZONEN; do
    ns="$(autoritativ "$zone")"
    datei="$(standdatei "$zone")"
    mkdir -p "$ABLAGE"
    {
      printf '# DNS-Bestand %s, aufgenommen %s am autoritativen Nameserver %s\n' \
        "$zone" "$HEUTE" "${ns:-(Resolver)}"
      printf '# Erzeugt von scripts/zone-dns-umzug.sh --bestand (#34).\n'
      printf '# Spalten: name<TAB>typ<TAB>wert. Apex ist "@", TTL absichtlich nicht erfasst.\n'
      bestand_zone "$zone"
    } > "$datei"
    echo "== ${zone} -> ${datei}"
    grep -v '^#' "$datei" | sed 's/^/  /'
    echo
  done
  cat <<'HINWEIS'
Diese Dateien sind die Vorlage fuer den Zonen-Scan in Cloudflare: nach "Add
site" jeden kritischen Record (MX, TXT/SPF/DMARC, DKIM-CNAME, CAA) in der
Cloudflare-Liste wiederfinden, fehlende nachtragen, Mail-Records grau lassen.
Danach:

  scripts/zone-dns-umzug.sh --abnahme
HINWEIS
}

vergleiche_zone() {
  local zone="$1" alt neu abweichung=0 zeile name typ wert
  alt="${STAND:-$(letzter_stand "$zone")}"
  [ -n "$alt" ] && [ -f "$alt" ] || {
    echo "  Kein Bestand gefunden (erst 'scripts/zone-dns-umzug.sh --bestand' laufen lassen)." >&2
    return 66
  }
  echo "  Bestand: ${alt}"

  neu="$(mktemp)"
  bestand_zone "$zone" > "$neu"

  # Verloren: steht im Bestand, fehlt jetzt.
  while IFS="$(printf '\t')" read -r name typ wert; do
    case "$typ" in NS|SOA) continue ;; esac
    [ -n "${name:-}" ] || continue
    if grep -qxF "$(printf '%s\t%s\t%s' "$name" "$typ" "$wert")" "$neu"; then
      continue
    fi
    if kritisch "$name" "$typ"; then
      echo "  ! FEHLT: ${name} ${typ} ${wert}"
      abweichung=1
    else
      echo "  ~ geaendert/weg (Web-Record, beim Umzug erwartet): ${name} ${typ} ${wert}"
    fi
  done < <(grep -v '^#' "$alt")

  # Neu dazugekommen: nur melden.
  while IFS="$(printf '\t')" read -r name typ wert; do
    case "$typ" in NS|SOA) continue ;; esac
    [ -n "${name:-}" ] || continue
    grep -v '^#' "$alt" | grep -qxF "$(printf '%s\t%s\t%s' "$name" "$typ" "$wert")" \
      || echo "  + neu: ${name} ${typ} ${wert}"
  done < "$neu"

  # Mail-Namen hinter dem Proxy: Cloudflare leitet nur HTTP(S)-Ports weiter.
  # Zeigt mail/smtp/imap/... nach dem Umzug nicht mehr auf die Adresse aus dem
  # Bestand, steckt der Name hinter der orangen Wolke und SMTP/IMAP/FTP sind
  # dort tot. Kein Fehler (bei den Portalen laeuft Mail ueber den MX eines
  # fremden Anbieters), aber ein Hinweis, der nicht untergehen darf.
  local ursprung mailname aktuell
  ursprung="$(grep -v '^#' "$alt" | awk -F'\t' '$1 == "@" && $2 == "A" { print $3; exit }')"
  if [ -n "$ursprung" ]; then
    for mailname in mail smtp imap pop webmail autodiscover autoconfig ftp; do
      aktuell="$(awk -F'\t' -v n="$mailname" '$1 == n && $2 == "A" { print $3 }' "$neu" | sort | paste -sd, -)"
      [ -n "$aktuell" ] || continue
      [ "$aktuell" != "$ursprung" ] || continue
      echo "  i ${mailname} liegt hinter dem Proxy (${aktuell} statt ${ursprung}) — SMTP/IMAP/FTP ueber diesen Namen antwortet nicht mehr"
    done
  fi
  rm -f "$neu"

  return "$abweichung"
}

vergleichen() {
  local zone abweichung=0 rc
  for zone in $ZONEN; do
    echo "== ${zone} =="
    rc=0; vergleiche_zone "$zone" || rc=$?
    [ "$rc" != 66 ] || return 66
    [ "$rc" = 0 ] || abweichung=1
    echo
  done
  if [ "$abweichung" = 0 ]; then
    echo 'Kein kritischer Record verloren.'
    return 0
  fi
  cat <<'HINWEIS' >&2
Mindestens ein kritischer Record fehlt nach dem Umzug. Diese Eintraege im
Cloudflare-DNS der Zone von Hand nachtragen (Mail-Records grau, nicht
proxied), dann erneut pruefen. Solange ein MX, SPF- oder DKIM-Eintrag fehlt,
geht Mail verloren oder landet im Spam.
HINWEIS
  return 71
}

case "$MODUS" in
  bestand) schreibe_bestand ;;
  vergleichen) vergleichen ;;
  abnahme)
    rc=0
    vergleichen || rc=$?
    [ "$rc" != 66 ] || exit 66
    echo
    echo '== Kantenprobe =='
    kantenargumente=''
    for zone in $ZONEN; do kantenargumente="${kantenargumente} --zone=${zone}"; done
    # shellcheck disable=SC2086
    "$(dirname "$0")/cloudflare-waf-regeln-setzen.sh" --kanten-pruefen $kantenargumente || rc=71
    echo
    if [ "$rc" = 0 ]; then
      cat <<'HINWEIS'
Umzug abgenommen. Weiter mit den vier WAF-Regeln auf diesen Zonen
(docs/bot-traffic.md Abschnitt 2, braucht den WAF-Token):

  scripts/cloudflare-waf-regeln-setzen.sh --zone=elektrikerportal.com --zone=sanitaerfinden.com
  scripts/cloudflare-waf-regeln-setzen.sh --zone=elektrikerportal.com --zone=sanitaerfinden.com --pruefen
  scripts/cloudflare-waf-regeln-setzen.sh --zone=elektrikerportal.com --zone=sanitaerfinden.com --abnahme

Erst danach ist die Nachmessung aus #24 aussagekraeftig:

  php artisan antispam:bot-traffic --tenant=elektrikerportal.com --days=7
HINWEIS
    else
      echo 'Abnahme nicht bestanden — siehe oben.' >&2
    fi
    exit "$rc"
    ;;
esac
