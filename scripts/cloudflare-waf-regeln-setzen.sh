#!/usr/bin/env bash
# Setzt die vier Cloudflare-Regeln gegen Scraper je Portal-Zone und prueft sie
# gegen docs/bot-traffic.md Abschnitt 2 (#24, aus #17).
#
# Das Skript ersetzt die Klickarbeit im Dashboard, nicht den Cloudflare-Zugang
# selbst: es braucht einen API-Token mit
#
#   Zone / Zone: Read
#   Zone / Firewall Services: Edit      (Custom Rules und Rate Limiting Rules)
#   Zone / Bot Management: Read         (nur fuer die Bestandsaufnahme)
#
# Den gibt es im Projekt nicht (#18) — der Turnstile-Token aus
# scripts/cloudflare-token-ablegen.sh ist auf "Account / Turnstile: Edit"
# geschnitten und traegt hier NICHT. Wer keinen zweiten Token anlegen will,
# traegt die vier Regeln im Dashboard nach docs/bot-traffic.md Abschnitt 2 ein
# und ueberspringt dieses Skript; die Ausdruecke unten sind dieselben.
#
# Token-Reihenfolge: CF_API_TOKEN aus der Umgebung, sonst
# CLOUDFLARE_API_TOKEN_SUN_WAF aus /root/sun-zugang.txt am Ursprung, sonst
# Abfrage am Terminal (verdeckt, kein Shell-Verlauf).
#
# Aufruf:
#   scripts/cloudflare-waf-regeln-setzen.sh --kanten-pruefen     # ohne Token: liegt die Zone ueberhaupt bei Cloudflare?
#   scripts/cloudflare-waf-regeln-setzen.sh --bestandsaufnahme   # vorher: nur lesen
#   scripts/cloudflare-waf-regeln-setzen.sh --pruefen            # Soll-Ist, aendert nichts
#   scripts/cloudflare-waf-regeln-setzen.sh --abnahme            # ohne Token: IndexNow-Schluesseldateien als Bingbot
#   scripts/cloudflare-waf-regeln-setzen.sh                      # setzen
#   scripts/cloudflare-waf-regeln-setzen.sh --zone=elektrikerportal.com
#   scripts/cloudflare-waf-regeln-setzen.sh --token-ablegen       # Token hinterlegen
#
# Reihenfolge ist der Kern der Sache: die Ausnahme (Regel 1) steht VOR der
# Sperre. Das Skript schreibt die drei Custom Rules deshalb immer als Block an
# den Anfang der Zonen-Regelliste und haengt fremde Regeln dahinter; fremde
# Regeln bleiben erhalten, werden aber gemeldet.
#
# Exit-Codes:
#   0  gesetzt bzw. Pruefung bestanden
# 64  unbekannte Option
# 65  kein oder untauglicher Token
# 69  jq fehlt
# 70  Cloudflare hat einen Aufruf abgelehnt
# 71  Pruefung ergab Abweichungen (--pruefen, --kanten-pruefen, --abnahme)

set -euo pipefail

API="https://api.cloudflare.com/client/v4"
KENNUNG='SUN #24'

# Dieselbe Portalliste wie scripts/turnstile-widgets-anlegen.sh. Bewusst
# aufgezaehlt und nicht aus /zones geholt: eine Regel darf nie in einer fremden
# Zone des Kontos landen.
ZONEN_VORGABE='fahrschulefinder.de elektrikerportal.com sanitaerfinden.com
sanitaerfinder.com malerfinder.de fliesenleger.io kfzwerkstatt.io
findegutachter.de bodenlegerfinden.com tierarztportal.com
energieberaterportal.net firmenfreund.net firmenfreund.de geruestbauer.gmbh
metallbauer.io mjet.net schluesseldienstportal.com speditionportal.com'

# ---------------------------------------------------------------------------
# Die vier Regeln. Wortgleich mit docs/bot-traffic.md Abschnitt 2 — wird hier
# etwas geaendert, gehoert es dort hin, in config/antispam.php `bot_traffic`
# und umgekehrt, sonst laufen Sperre und Statistik-Erkennung auseinander.
# ---------------------------------------------------------------------------

BESCHREIBUNG_1="${KENNUNG} Ausnahme: verifizierte Bots, robots/sitemap/llms/ads.txt, IndexNow-Schluessel"
AUSDRUCK_1='(cf.client.bot) or (http.request.uri.path in {"/robots.txt" "/sitemap.xml" "/llms.txt" "/llms-full.txt" "/ads.txt"}) or (http.request.uri.path matches "^/sitemap[^/]*\.xml$") or (http.request.uri.path matches "^/[a-f0-9]{8,128}\.txt$") or (lower(http.user_agent) contains "bingbot") or (lower(http.user_agent) contains "googlebot")'

BESCHREIBUNG_2="${KENNUNG} Sperre: selbstbenannte Datensammler"
AUSDRUCK_2='(lower(http.user_agent) contains "dataquality") or (lower(http.user_agent) contains "leadresearch") or (lower(http.user_agent) contains "lead-research") or (lower(http.user_agent) contains "authorized-probe") or (lower(http.user_agent) contains "webapp-mapper") or (lower(http.user_agent) contains "google-apps-script") or (lower(http.user_agent) contains "beanserver") or (lower(http.user_agent) contains "python-httpx") or (lower(http.user_agent) contains "python-urllib") or (lower(http.user_agent) contains "python-requests") or (lower(http.user_agent) contains "aiohttp") or (lower(http.user_agent) contains "scrapy") or (lower(http.user_agent) contains "go-http-client") or (starts_with(lower(http.user_agent), "axios/")) or (starts_with(lower(http.user_agent), "curl/")) or (starts_with(lower(http.user_agent), "wget/")) or (http.user_agent eq "Mozilla/5.0") or (http.user_agent eq "Mozilla/4.0") or (http.user_agent eq "")'

BESCHREIBUNG_3="${KENNUNG} Scraper-Netze: Alibaba Cloud 47.79.200.0/21"
AUSDRUCK_3='(ip.src in {47.79.200.0/21})'

BESCHREIBUNG_4="${KENNUNG} Seitenabrufe: 120 GET/Minute je IP"
# Kein Pfad-Praefix moeglich: Firmenprofile liegen auf /{companySlug}
# (routes/tenant.php). Ausgenommen sind nur Anhaenge, nicht Seiten. "storage"
# deckt als Praefix auch die Tenant-Platten /storage-<uuid>/ ab, "img" auch
# /images/; die Schriften liefert Vite unter /build aus.
AUSDRUCK_4='(http.request.method eq "GET" and not http.request.uri.path matches "^/(build|storage|img|css|js|favicon)")'
RL_SCHWELLE=120
RL_FENSTER=60
RL_SPERRE=60

MODUS=setzen
ZONEN=''
while [ $# -gt 0 ]; do
  case "$1" in
    --pruefen) MODUS=pruefen; shift ;;
    --bestandsaufnahme) MODUS=bestand; shift ;;
    --zone=*) ZONEN="${ZONEN} ${1#--zone=}"; shift ;;
    --token-ablegen) MODUS=ablegen; shift ;;
    --kanten-pruefen) MODUS=kanten; shift ;;
    --abnahme) MODUS=abnahme; shift ;;
    -h|--help) sed -n '2,41p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done
[ -n "$ZONEN" ] || ZONEN="$ZONEN_VORGABE"

command -v jq >/dev/null || { echo 'jq fehlt (brew install jq).' >&2; exit 69; }

# ---------------------------------------------------------------------------
# Zwei Pruefungen, die ohne Cloudflare-Token auskommen: beide gehoeren vor und
# nach das Setzen der Regeln (docs/bot-traffic.md Abschnitt 2.6).
# ---------------------------------------------------------------------------

# Liegt die Zone ueberhaupt bei Cloudflare? Eine Regel wirkt nur, wenn der
# Verkehr durch die Kante laeuft — steht die Zone bei einem fremden DNS-Anbieter
# und zeigt direkt auf den Ursprung, bleibt die WAF ohne Wirkung. Erkennungs-
# merkmal sind die Nameserver und der Antwortkopf cf-ray.
#
# Gefragt wird nicht ueber den lokalen Resolver, sondern am autoritativen
# Nameserver der Zone: ein zwischenspeichernder Resolver (Router, Firmen-DNS)
# haelt nach einem Umzug noch die alte Ursprungsadresse und curl laeuft dann an
# der Kante vorbei — cf-ray fehlt, obwohl die Zone proxied ist. Genau so wurde
# elektrikerportal.com am 08.10.2026 zweimal falsch als "ohne Kante" gemessen.
autoritative_adresse() {
  local name="$1" zone ns
  # Zone = letzte zwei Labels; die Portalliste enthaelt nur Apex-Domains.
  zone="$(printf '%s' "$name" | awk -F. '{ if (NF < 2) print $0; else print $(NF-1) "." $NF }')"
  ns="$(dig +short NS "$zone" 2>/dev/null | sed 's/\.$//' | sort | head -1)"
  if [ -n "$ns" ]; then
    dig +short +time=3 +tries=1 A "$name" "@${ns}" 2>/dev/null | grep -E '^[0-9.]+$' | head -1
    return 0
  fi
  dig +short A "$name" 2>/dev/null | grep -E '^[0-9.]+$' | head -1
}

kanten_pruefen() {
  command -v dig >/dev/null || { echo 'dig fehlt (macOS: brew install bind).' >&2; return 69; }
  local abweichung=0 veraltet='' name ns kante adresse lokal koepfe
  printf '%-30s %-11s %-7s %-16s %s\n' 'Zone' 'DNS' 'cf-ray' 'A (autoritativ)' 'Nameserver'
  for name in $ZONEN; do
    ns="$(dig +short NS "$name" | sed 's/\.$//' | sort | paste -sd, -)"
    case "$ns" in
      *cloudflare.com*) kante='Cloudflare' ;;
      '')               kante='kein NS' ;;
      *)                kante='FREMD' ;;
    esac
    adresse="$(autoritative_adresse "$name")"
    lokal="$(dig +short A "$name" 2>/dev/null | grep -E '^[0-9.]+$' | head -1)"
    # -k, weil das eine Ursprungszertifikat nur die gepflegten SAN-Namen
    # traegt; hier zaehlt der Antwortkopf, nicht die Kette.
    if [ -n "$adresse" ]; then
      koepfe="$(curl -sSk -m 15 -o /dev/null -D - --resolve "${name}:443:${adresse}" "https://${name}/robots.txt" 2>/dev/null)"
    else
      koepfe="$(curl -sS -m 15 -o /dev/null -D - "https://${name}/robots.txt" 2>/dev/null)"
    fi
    if printf '%s' "$koepfe" | grep -qi '^cf-ray:'; then
      printf '%-30s %-11s %-7s %-16s %s\n' "$name" "$kante" 'ja' "${adresse:-?}" "$ns"
      # Gegenprobe ueber den eigenen Resolver. Eine andere Adresse allein sagt
      # nichts (Cloudflare antwortet je Abfrage mit einer anderen Anycast-IP) —
      # gemeldet wird nur, wenn der eigene Weg an der Kante vorbeilaeuft.
      if [ -n "$lokal" ] && ! curl -sSk -m 15 -o /dev/null -D - "https://${name}/robots.txt" 2>/dev/null | grep -qi '^cf-ray:'; then
        veraltet="${veraltet} ${name}(lokal ${lokal})"
      fi
    else
      printf '%-30s %-11s %-7s %-16s %s\n' "$name" "$kante" 'NEIN' "${adresse:-?}" "$ns"
      abweichung=1
    fi
  done
  echo
  if [ -n "$veraltet" ]; then
    echo "Hinweis: hinter der Kante, aber der eigene Resolver fuehrt am"
    echo "Ursprung vorbei:${veraltet}"
    echo 'Gemessen wurde am autoritativen Nameserver. Der eigene Resolver haelt'
    echo 'noch die alte Adresse (Router-Cache) — jede Proben-Messung von hier'
    echo 'aus ohne --resolve trifft den Ursprung, nicht Cloudflare.'
    echo
  fi
  if [ "$abweichung" = 0 ]; then
    echo 'Alle Zonen laufen ueber die Cloudflare-Kante.'
    return 0
  fi
  cat <<'HINWEIS'
Mindestens eine Zone laeuft NICHT ueber Cloudflare. Dort sind die vier Regeln
ohne Wirkung, egal ob sie im Konto stehen: der Verkehr erreicht den Ursprung
ohne Kante. Erst die Zone in Cloudflare aufnehmen (Nameserver beim Registrar
umstellen, Proxy je A-Record an), dann setzen.
HINWEIS
  return 71
}

# Abnahme nach dem Setzen: die IndexNow-Schluesseldatei muss mit Bingbot-Kennung
# weiterhin 200 liefern, sonst faellt die Meldung an Bing aus
# (docs/guide-golive.md Abschnitt 1.4). Die Adressen werden am Ursprung
# berechnet (Schluessel haengt an APP_KEY) und stehen nirgends im Repo.
schluesseladressen() {
  local wurzel="${SUN_APP_DIR:-/home/sanitaerfinden/htdocs/sanitaerfinden.dev}"
  local php="${SUN_PHP:-/usr/bin/php8.4}" nutzlast b64
  # Mehrzeilige Nutzlast base64-kodiert uebergeben: ein Anfuehrungszeichen im
  # Code zerlegt sonst das Quoting von ssh + su.
  nutzlast="$(cat <<FERN
<?php
require '${wurzel}/vendor/autoload.php';
\$app = require '${wurzel}/bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$client = \$app->make(App\Guide\Publishing\IndexNowClient::class);
foreach (App\Models\Tenant::query()->get() as \$tenant) {
    \$ort = \$client->keyLocation(\$tenant);
    \$domain = trim((string) \$tenant->domain);
    if (\$domain === '' || \$ort === null) { continue; }
    echo \$domain."\t".\$ort."\n";
}
FERN
)"
  b64="$(printf '%s' "$nutzlast" | base64 | tr -d '\n')"
  ssh -o BatchMode=yes -o ConnectTimeout=15 "${SUN_SSH_HOST:-sun}" \
    "umask 077; D=\$(mktemp /tmp/sun-keyloc-XXXXXX.php); echo ${b64} | base64 -d > \$D; chmod 644 \$D; su -s /bin/bash ${SUN_APP_USER:-sanitaerfinden} -c '${php} '\$D; rm -f \$D"
}

abnahme() {
  local abweichung=0 zeile domain adresse code ziel passt name
  local adressen; adressen="$(schluesseladressen)" || {
    echo 'Die Schluesseladressen liessen sich am Ursprung nicht ermitteln (ssh/Codestand pruefen).' >&2
    return 71
  }
  [ -n "$adressen" ] || { echo 'Keine Schluesseladressen erhalten.' >&2; return 71; }

  printf '%-32s %-6s %s\n' 'Domain' 'Status' 'Bemerkung'
  while IFS="$(printf '\t')" read -r domain adresse; do
    [ -n "${adresse:-}" ] || continue
    # Nur Domains, die zu den bearbeiteten Zonen gehoeren (Zone selbst oder
    # Unterdomain davon).
    passt=0
    for name in $ZONEN; do
      case "$domain" in "$name"|*".$name") passt=1 ;; esac
    done
    [ "$passt" = 1 ] || continue

    # Ohne -L: Bing liest die Datei nur unter der gemeldeten Adresse. Eine
    # Weiterleitung ist deshalb ein Befund, auch wenn das Ziel 200 liefert
    # (#35: tierarztportal.com -> pfotencheck.tierarztportal.com).
    code="$(curl -sS -m 20 -o /dev/null -w '%{http_code}' -A 'Bingbot' "$adresse" 2>/dev/null || echo 000)"
    ziel="$(curl -sS -m 20 -o /dev/null -w '%{redirect_url}' -A 'Bingbot' "$adresse" 2>/dev/null || true)"
    if [ "$code" = 200 ]; then
      printf '%-32s %-6s %s\n' "$domain" "$code" ''
    elif [ -n "${ziel:-}" ]; then
      # Nur den Zielhost melden: das Ziel enthaelt den Schluessel im Pfad.
      printf '%-32s %-6s %s\n' "$domain" "$code" "Weiterleitung auf $(printf '%s' "$ziel" | sed -E 's#^[a-z]+://([^/]+).*#\1#') — IndexNow faellt aus"
      abweichung=1
    else
      printf '%-32s %-6s %s\n' "$domain" "$code" 'ERWARTET WAR 200 — IndexNow faellt aus'
      abweichung=1
    fi
  done <<EOF
$adressen
EOF

  echo
  [ "$abweichung" = 0 ] || {
    echo 'Mindestens eine Schluesseldatei ist unter der gemeldeten Adresse nicht' >&2
    echo 'erreichbar. Bei 403/404 Regel 1 (Ausnahme) pruefen, insbesondere das' >&2
    echo 'Muster ^/[a-f0-9]{8,128}\.txt$; bei 301/302 die Weiterleitung der Zone' >&2
    echo 'bei Cloudflare entfernen (Single Redirect, Page Rule oder Bulk Redirect).' >&2
    return 71
  }
  echo 'Alle Schluesseldateien liefern mit Bingbot-Kennung 200, ohne Weiterleitung.'
  echo 'Gegenprobe ueber alle Portale am Ursprung: php artisan guide:golive:check'
}

if [ "$MODUS" = 'kanten' ]; then
  kanten_pruefen
  exit $?
fi

if [ "$MODUS" = 'abnahme' ]; then
  abnahme
  exit $?
fi

ZUGANGSDATEI="${ZUGANGSDATEI:-/root/sun-zugang.txt}"
SCHLUESSEL='CLOUDFLARE_API_TOKEN_SUN_WAF'

if [ "$MODUS" = 'ablegen' ] && [ -z "${CF_API_TOKEN:-}" ] && [ -t 0 ]; then
  printf 'Cloudflare-API-Token (Zone: Read + Firewall Services: Edit): '
  read -rs CF_API_TOKEN; echo
fi

if [ -z "${CF_API_TOKEN:-}" ]; then
  CF_API_TOKEN="$(ssh -o BatchMode=yes -o ConnectTimeout=15 "${SUN_SSH_HOST:-sun}" \
    "grep -m1 '^${SCHLUESSEL}=' ${ZUGANGSDATEI} 2>/dev/null | cut -d= -f2-" 2>/dev/null || true)"
  [ -z "$CF_API_TOKEN" ] || echo "Token aus ${ZUGANGSDATEI} (Ursprung)."
fi


if [ -z "${CF_API_TOKEN:-}" ] && [ -t 0 ]; then
  printf 'CF_API_TOKEN (Zone: Read + Firewall Services: Edit): '
  read -rs CF_API_TOKEN; echo
fi
[ -n "${CF_API_TOKEN:-}" ] || {
  echo "Kein Token: weder CF_API_TOKEN gesetzt noch ${SCHLUESSEL} in ${ZUGANGSDATEI}." >&2
  echo 'Einmalig ablegen: scripts/cloudflare-waf-regeln-setzen.sh --token-ablegen  (#18)' >&2
  exit 65
}

cf() {
  local methode="$1" pfad="$2"; shift 2
  curl -sS --max-time 30 -X "$methode" "${API}${pfad}" \
    -H "Authorization: Bearer ${CF_API_TOKEN}" \
    -H 'Content-Type: application/json' "$@"
}

fehler_zeilen() { printf '%s' "$1" | jq -r '.errors[]? | "  \(.code): \(.message)"' >&2; }

pruefe_antwort() {
  local antwort="$1" was="$2"
  if [ "$(printf '%s' "$antwort" | jq -r '.success')" != "true" ]; then
    echo "${was} fehlgeschlagen:" >&2
    fehler_zeilen "$antwort"
    exit 70
  fi
}

pruefe_token() {
  local antwort; antwort="$(cf GET '/user/tokens/verify')"
  if [ "$(printf '%s' "$antwort" | jq -r '.success')" != "true" ]; then
    echo 'Der Token ist ungueltig, abgelaufen oder widerrufen.' >&2
    fehler_zeilen "$antwort"
    exit 65
  fi
}

# Liefert die Zone-ID zu einem Namen, oder leer, wenn der Token sie nicht sieht.
zone_id() {
  local name="$1" antwort
  antwort="$(cf GET "/zones?name=${name}&per_page=1")"
  [ "$(printf '%s' "$antwort" | jq -r '.success')" = 'true' ] || { fehler_zeilen "$antwort"; return 1; }
  printf '%s' "$antwort" | jq -r '.result[0].id // empty'
}

# Bestandsaufnahme vor dem Setzen (Ticket #24: "vorher festhalten"). Die
# gemessenen 33 % maschineller Verkehr passen nur zu "Bot Fight Mode aus" —
# steht hier etwas anderes, gehoert das ins Ticket, bevor gesetzt wird.
bestand_zone() {
  local id="$1" antwort
  antwort="$(cf GET "/zones/${id}/bot_management")"
  if [ "$(printf '%s' "$antwort" | jq -r '.success')" != 'true' ]; then
    echo '  Bot Management nicht lesbar (Berechtigung "Zone / Bot Management: Read" fehlt).'
    fehler_zeilen "$antwort"
    return 0
  fi
  printf '%s' "$antwort" | jq -r '.result | "  Bot Fight Mode:       \(.fight_mode // false)\n  Super Bot Fight Mode: definitely_automated=\(.sbfm_definitely_automated // "-"), likely_automated=\(.sbfm_likely_automated // "-"), verified_bots=\(.sbfm_verified_bots // "-")\n  JS-Erkennung:         \(.enable_js // false)"'
}

ruleset_holen() {
  local id="$1" phase="$2" antwort
  antwort="$(cf GET "/zones/${id}/rulesets/phases/${phase}/entrypoint")"
  if [ "$(printf '%s' "$antwort" | jq -r '.success')" = 'true' ]; then
    printf '%s' "$antwort" | jq -c '.result'
    return 0
  fi
  # Noch kein Einstiegs-Ruleset in dieser Zone: leere Liste, wird unten erzeugt.
  if printf '%s' "$antwort" | jq -e '.errors[]? | select(.code == 1000 or .code == 10000)' >/dev/null 2>&1; then
    fehler_zeilen "$antwort"
    return 1
  fi
  printf '{"id":"","rules":[]}'
}

# Schreibt die Regelliste einer Phase. Gibt es noch kein Einstiegs-Ruleset,
# wird es angelegt; sonst wird es ersetzt.
ruleset_schreiben() {
  local id="$1" phase="$2" regeln="$3" vorhandene_id="$4" antwort rumpf
  if [ -n "$vorhandene_id" ]; then
    rumpf="$(jq -n --argjson rules "$regeln" '{rules: $rules}')"
    antwort="$(cf PUT "/zones/${id}/rulesets/phases/${phase}/entrypoint" -d "$rumpf")"
  else
    rumpf="$(jq -n --arg phase "$phase" --argjson rules "$regeln" \
      '{name: "default", kind: "zone", phase: $phase, rules: $rules}')"
    antwort="$(cf POST "/zones/${id}/rulesets" -d "$rumpf")"
  fi
  pruefe_antwort "$antwort" "Schreiben der Phase ${phase}"
}

# Die drei Custom Rules in der vorgeschriebenen Reihenfolge.
soll_custom_rules() {
  jq -n \
    --arg b1 "$BESCHREIBUNG_1" --arg a1 "$AUSDRUCK_1" \
    --arg b2 "$BESCHREIBUNG_2" --arg a2 "$AUSDRUCK_2" \
    --arg b3 "$BESCHREIBUNG_3" --arg a3 "$AUSDRUCK_3" \
    '[
      {
        description: $b1, expression: $a1, enabled: true, action: "skip",
        action_parameters: {
          ruleset: "current",
          phases: ["http_ratelimit", "http_request_sbfm"],
          products: ["bic", "hot", "rateLimit", "securityLevel", "uaBlock", "zoneLockdown"]
        },
        logging: { enabled: true }
      },
      { description: $b2, expression: $a2, enabled: true, action: "block" },
      { description: $b3, expression: $a3, enabled: true, action: "managed_challenge" }
    ]'
}

soll_ratelimit_rules() {
  jq -n \
    --arg b "$BESCHREIBUNG_4" --arg a "$AUSDRUCK_4" \
    --argjson schwelle "$RL_SCHWELLE" --argjson fenster "$RL_FENSTER" --argjson sperre "$RL_SPERRE" \
    '[{
      description: $b, expression: $a, enabled: true, action: "managed_challenge",
      ratelimit: {
        characteristics: ["ip.src", "cf.colo.id"],
        period: $fenster,
        requests_per_period: $schwelle,
        mitigation_timeout: $sperre
      }
    }]'
}

# Vergleicht Ist und Soll einer Phase. Gibt 0 bei Gleichstand, sonst 1 und
# meldet jede Abweichung einzeln.
vergleiche() {
  local ist="$1" soll="$2" abweichung=0 anzahl i
  anzahl="$(printf '%s' "$soll" | jq -r 'length')"

  # Unsere Regeln muessen die ERSTEN sein, in genau dieser Reihenfolge.
  i=0
  while [ "$i" -lt "$anzahl" ]; do
    local s_beschr s_ausdruck s_aktion i_beschr i_ausdruck i_aktion
    s_beschr="$(printf '%s' "$soll" | jq -r ".[${i}].description")"
    s_ausdruck="$(printf '%s' "$soll" | jq -r ".[${i}].expression")"
    s_aktion="$(printf '%s' "$soll" | jq -r ".[${i}].action")"
    i_beschr="$(printf '%s' "$ist" | jq -r ".[${i}].description // \"(keine Regel)\"")"
    i_ausdruck="$(printf '%s' "$ist" | jq -r ".[${i}].expression // \"\"")"
    i_aktion="$(printf '%s' "$ist" | jq -r ".[${i}].action // \"\"")"

    if [ "$i_beschr" != "$s_beschr" ]; then
      echo "  ! Platz $((i + 1)): \"${i_beschr}\", erwartet \"${s_beschr}\""
      abweichung=1
    elif [ "$i_ausdruck" != "$s_ausdruck" ] || [ "$i_aktion" != "$s_aktion" ]; then
      echo "  ! Platz $((i + 1)) \"${s_beschr}\" weicht ab (Aktion ist '${i_aktion}', erwartet '${s_aktion}')"
      abweichung=1
    else
      echo "  ok Platz $((i + 1)): ${s_beschr} [${s_aktion}]"
    fi
    i=$((i + 1))
  done

  local fremd
  fremd="$(printf '%s' "$ist" | jq -r --arg k "$KENNUNG" '.[] | select((.description // "") | startswith($k) | not) | .description // "(ohne Beschreibung)"')"
  if [ -n "$fremd" ]; then
    echo '  Fremde Regeln in dieser Zone (bleiben erhalten, stehen hinter unseren):'
    printf '%s\n' "$fremd" | sed 's/^/    - /'
  fi

  return "$abweichung"
}

# Haengt fremde Regeln hinter die Soll-Regeln. Unsere alten Regeln (Kennung in
# der Beschreibung) fallen dabei weg, damit ein zweiter Lauf keine Dubletten
# erzeugt.
zusammenfuehren() {
  local ist="$1" soll="$2"
  jq -n --argjson ist "$ist" --argjson soll "$soll" --arg k "$KENNUNG" \
    '$soll + [$ist[] | select((.description // "") | startswith($k) | not)
              | del(.id, .version, .last_updated, .ref)]'
}

phase_verarbeiten() {
  local id="$1" phase="$2" soll="$3" titel="$4" ruleset ist rs_id neu
  echo "  -- ${titel}"
  ruleset="$(ruleset_holen "$id" "$phase")" || return 1
  rs_id="$(printf '%s' "$ruleset" | jq -r '.id // ""')"
  ist="$(printf '%s' "$ruleset" | jq -c '.rules // []')"

  if vergleiche "$ist" "$soll"; then
    [ "$MODUS" = 'pruefen' ] && return 0
    echo '  Nichts zu tun.'
    return 0
  fi

  if [ "$MODUS" = 'pruefen' ]; then
    return 1
  fi

  neu="$(zusammenfuehren "$ist" "$soll")"
  ruleset_schreiben "$id" "$phase" "$neu" "$rs_id"
  echo "  Gesetzt: $(printf '%s' "$neu" | jq -r 'length') Regel(n) in ${phase}."
}

# Legt den Token am Ursprung ab — dieselbe Datei und dieselbe Form wie
# scripts/cloudflare-token-ablegen.sh (#18), nur unter eigenem Schluessel: der
# Turnstile-Token darf keine WAF-Regeln aendern und umgekehrt.
token_ablegen() {
  printf '%s' "$CF_API_TOKEN" | grep -qE '^[A-Za-z0-9_.-]{25,}$' || {
    echo 'Das sieht nicht nach einem Cloudflare-API-Token aus.' >&2
    exit 65
  }
  # Der Token geht als stdin-Strom hinueber, nie als ssh-Argument — sonst steht
  # er in der Prozessliste des Servers.
  {
    printf 'TOKEN=%s\nDATEI=%s\nSCHLUESSEL=%s\n' "$CF_API_TOKEN" "$ZUGANGSDATEI" "$SCHLUESSEL"
    cat <<'FERN'
set -eu
umask 077
[ -f "$DATEI" ] || printf '# Cloudflare-Zugang fuer SUN. Nicht ins Repository.\n' > "$DATEI"
TMP="$(mktemp)"
grep -v -E "^${SCHLUESSEL}=" "$DATEI" > "$TMP" || true
printf '%s=%s\n' "$SCHLUESSEL" "$TOKEN" >> "$TMP"
cat "$TMP" > "$DATEI"
rm -f "$TMP"
chmod 600 "$DATEI"
echo "Abgelegt: $DATEI (chmod 600)"
FERN
  } | ssh -o BatchMode=yes -o ConnectTimeout=15 "${SUN_SSH_HOST:-sun}" 'bash -s'
}

pruefe_token

if [ "$MODUS" = 'ablegen' ]; then
  token_ablegen
  echo 'Weiter mit: scripts/cloudflare-waf-regeln-setzen.sh --bestandsaufnahme'
  exit 0
fi

ABWEICHUNGEN=0
FEHLENDE_ZONEN=''

for name in $ZONEN; do
  echo
  echo "== ${name} =="
  if ! id="$(zone_id "$name")" || [ -z "$id" ]; then
    echo '  Zone im Konto nicht gefunden oder fuer den Token nicht lesbar — uebersprungen.'
    FEHLENDE_ZONEN="${FEHLENDE_ZONEN} ${name}"
    continue
  fi
  echo "  Zone-ID: ${id}"

  bestand_zone "$id"
  [ "$MODUS" = 'bestand' ] && continue

  phase_verarbeiten "$id" 'http_request_firewall_custom' "$(soll_custom_rules)" 'Custom Rules (Regeln 1-3)' || ABWEICHUNGEN=1
  phase_verarbeiten "$id" 'http_ratelimit' "$(soll_ratelimit_rules)" "Rate Limiting (Regel 4: ${RL_SCHWELLE}/${RL_FENSTER}s)" || ABWEICHUNGEN=1
done

echo
[ -z "$FEHLENDE_ZONEN" ] || echo "Nicht bearbeitet:${FEHLENDE_ZONEN}"

if [ "$MODUS" = 'bestand' ]; then
  cat <<'HINWEIS'
Bestandsaufnahme fertig. Diese Werte gehoeren als Kommentar ins Ticket #24,
bevor etwas gesetzt wird. Was die API nicht hergibt und im Dashboard
nachzusehen ist: Security -> Events, letzte 24 h, Filter
"Bot Detection = not verified bot" — tauchen die User-Agents aus
docs/bot-traffic.md Abschnitt 1 dort mit "allowed" auf?
HINWEIS
  exit 0
fi

if [ "$MODUS" = 'pruefen' ]; then
  [ "$ABWEICHUNGEN" = 0 ] || { echo 'Abweichungen gefunden. Ohne --pruefen setzen.'; exit 71; }
  echo 'Alle Zonen entsprechen docs/bot-traffic.md Abschnitt 2.'
  exit 0
fi

cat <<'HINWEIS'
Nachher pruefen (docs/bot-traffic.md Abschnitt 2.6):

1. IndexNow-Schluesseldatei muss weiterhin 200 liefern — sonst faellt die
   Meldung an Bing aus (docs/guide-golive.md Abschnitt 1.4). Das prueft das
   Skript selbst, ohne Token: es holt die Adressen am Ursprung und ruft jede
   mit Bingbot-Kennung ab.

     scripts/cloudflare-waf-regeln-setzen.sh --abnahme

   Gegenprobe am Ursprung ueber alle aktiven Portale:

     php artisan guide:golive:check

2. Nach einigen Tagen nachmessen. Die Bot-Quote muss fallen, die Zahl der
   Ereignisse echter Besucher darf nicht fallen:

     php artisan antispam:bot-traffic --tenant=elektrikerportal.com --days=7

3. Wird in Cloudflare etwas ergaenzt, gehoert derselbe Eintrag in
   config/antispam.php `bot_traffic` und in docs/bot-traffic.md Abschnitt 2.
HINWEIS
