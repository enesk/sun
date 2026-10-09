#!/usr/bin/env bash
# Findet und entfernt die Cloudflare-Weiterleitungen, die ein am Ursprung
# lebendes SUN-Portal an der Kante verdecken (#37, Vorbedingung von #35).
#
# Stand 08.10.2026 betrifft das zwei Zonen:
#   firmenfreund.net    301 (pfadtreu) auf die fremde Alt-Installation
#                       https://solar-finden.de/...
#   tierarztportal.com  302 (jeder Pfad auf die Wurzel) auf die statische Seite
#                       https://pfotencheck.tierarztportal.com/
# Beide Portale liefern am Ursprung 200. Der Befund und die Dashboard-Variante
# stehen in docs/messungen/weiterleitungen-entfernen-anleitung.md.
#
# Eine Weiterleitung kann an drei Stellen stehen; das Skript sieht alle drei
# nach, in dieser Reihenfolge:
#   1. Rules -> Redirect Rules   (Phase http_request_dynamic_redirect)
#   2. Rules -> Page Rules       (alte Oberflaeche, Aktion forwarding_url)
#   3. Account -> Bulk Redirects (Listen der Art "redirect")
#
# Was als "fremd" gilt und entfernt wird: jede Weiterleitung, deren Ziel-Host
# NICHT der Apex der Zone selbst ist. Damit bleibt das richtige www -> Apex
# (Ziel-Host = Zone) immer stehen, waehrend solar-finden.de und auch die
# Unterdomain pfotencheck.tierarztportal.com erkannt werden. Alles andere wird
# nur gemeldet, nie angefasst.
#
# Token: Zone / Zone: Read  +  Zone / Config Rules: Edit  (Redirect Rules)
#        Zone / Zone: Edit                                 (Page Rules)
#        Account / Account Rulesets: Edit                  (Bulk Redirects)
# Den Turnstile-Token aus /root/sun-zugang.txt traegt das NICHT (er sieht keine
# Zone, gemessen am 08.10.2026) — siehe docs/messungen/cloudflare-token-anleitung.md.
#
# Token-Reihenfolge: CF_API_TOKEN aus der Umgebung, sonst
# CLOUDFLARE_API_TOKEN_SUN_REDIRECT, sonst CLOUDFLARE_API_TOKEN_SUN_WAF aus
# /root/sun-zugang.txt am Ursprung, sonst Abfrage am Terminal (verdeckt).
#
# Aufruf:
#   scripts/cloudflare-weiterleitungen-entfernen.sh                 # nur nachsehen (Vorgabe)
#   scripts/cloudflare-weiterleitungen-entfernen.sh --entfernen     # gefundene fremde Weiterleitungen loeschen
#   scripts/cloudflare-weiterleitungen-entfernen.sh --kante         # ohne Token: was antwortet die Kante heute?
#   scripts/cloudflare-weiterleitungen-entfernen.sh --zone=firmenfreund.net
#
# Nach dem Entfernen die beiden Proben aus #37 fahren:
#   scripts/cloudflare-waf-regeln-setzen.sh --abnahme
#   php artisan guide:golive:check   (am Ursprung)
#
# Exit-Codes:
#   0  nichts Fremdes gefunden bzw. alles entfernt
#  64  unbekannte Option
#  65  kein oder untauglicher Token
#  69  jq fehlt
#  70  Cloudflare hat einen Aufruf abgelehnt
#  71  fremde Weiterleitung gefunden (--pruefen) bzw. Kante leitet noch weg (--kante)

set -euo pipefail

API="https://api.cloudflare.com/client/v4"

# Bewusst aufgezaehlt und nicht aus /zones geholt: hier wird geloescht, das
# darf nie eine fremde Zone des Kontos treffen.
ZONEN_VORGABE='firmenfreund.net tierarztportal.com'

MODUS=pruefen
ZONEN=''
while [ $# -gt 0 ]; do
  case "$1" in
    --pruefen) MODUS=pruefen; shift ;;
    --entfernen) MODUS=entfernen; shift ;;
    --kante) MODUS=kante; shift ;;
    --zone=*) ZONEN="${ZONEN} ${1#--zone=}"; shift ;;
    -h|--help) sed -n '2,50p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done
[ -n "$ZONEN" ] || ZONEN="$ZONEN_VORGABE"

# ---------------------------------------------------------------------------
# Probe ohne Token: was antwortet die Kante? Ohne -L, denn eine Weiterleitung
# ist hier der Befund und nicht der Weg zum Ziel (#35).
# ---------------------------------------------------------------------------
kante_pruefen() {
  local abweichung=0 name koepfe code ziel
  printf '%-24s %-6s %s\n' 'Zone' 'Kante' 'Ziel'
  for name in $ZONEN; do
    koepfe="$(curl -sS -m 20 -o /dev/null -D - "https://${name}/robots.txt" 2>/dev/null || true)"
    code="$(printf '%s' "$koepfe" | awk 'tolower($1) ~ /^http/ { print $2; exit }')"
    ziel="$(printf '%s' "$koepfe" | awk 'tolower($1) == "location:" { print $2; exit }' | tr -d '\r')"
    printf '%-24s %-6s %s\n' "$name" "${code:-000}" "${ziel:-—}"
    [ "${code:-000}" = 200 ] || abweichung=1
  done
  echo
  if [ "$abweichung" = 0 ]; then
    echo 'Keine Weiterleitung mehr an der Kante. Jetzt die Abnahme fahren:'
    echo '  scripts/cloudflare-waf-regeln-setzen.sh --abnahme'
    return 0
  fi
  echo 'Die Kante leitet noch weg. Mit Token entfernen:' >&2
  echo '  scripts/cloudflare-weiterleitungen-entfernen.sh --entfernen' >&2
  echo 'Ohne Token: docs/messungen/weiterleitungen-entfernen-anleitung.md' >&2
  return 71
}

if [ "$MODUS" = 'kante' ]; then
  kante_pruefen
  exit $?
fi

command -v jq >/dev/null || { echo 'jq fehlt (brew install jq).' >&2; exit 69; }

ZUGANGSDATEI="${ZUGANGSDATEI:-/root/sun-zugang.txt}"

if [ -z "${CF_API_TOKEN:-}" ]; then
  for schluessel in CLOUDFLARE_API_TOKEN_SUN_REDIRECT CLOUDFLARE_API_TOKEN_SUN_WAF; do
    CF_API_TOKEN="$(ssh -o BatchMode=yes -o ConnectTimeout=15 "${SUN_SSH_HOST:-sun}" \
      "grep -m1 '^${schluessel}=' ${ZUGANGSDATEI} 2>/dev/null | cut -d= -f2-" 2>/dev/null || true)"
    [ -n "$CF_API_TOKEN" ] || continue
    echo "Token ${schluessel} aus ${ZUGANGSDATEI} (Ursprung)."
    break
  done
fi

if [ -z "${CF_API_TOKEN:-}" ] && [ -t 0 ]; then
  printf 'CF_API_TOKEN (Zone: Read + Config Rules: Edit): '
  read -rs CF_API_TOKEN; echo
fi
[ -n "${CF_API_TOKEN:-}" ] || {
  echo "Kein Token: weder CF_API_TOKEN gesetzt noch ein Schluessel in ${ZUGANGSDATEI}." >&2
  echo 'Anlegen: docs/messungen/cloudflare-token-anleitung.md (#18)' >&2
  exit 65
}

if [ -z "${CF_ACCOUNT_ID:-}" ]; then
  CF_ACCOUNT_ID="$(ssh -o BatchMode=yes -o ConnectTimeout=15 "${SUN_SSH_HOST:-sun}" \
    "grep -m1 '^CLOUDFLARE_ACCOUNT_ID_SUN=' ${ZUGANGSDATEI} 2>/dev/null | cut -d= -f2-" 2>/dev/null || true)"
fi

cf() {
  local methode="$1" pfad="$2"; shift 2
  curl -sS --max-time 30 -X "$methode" "${API}${pfad}" \
    -H "Authorization: Bearer ${CF_API_TOKEN}" \
    -H 'Content-Type: application/json' "$@"
}

fehler_zeilen() { printf '%s' "$1" | jq -r '.errors[]? | "  \(.code): \(.message)"' >&2; }

erfolgreich() { [ "$(printf '%s' "$1" | jq -r '.success')" = 'true' ]; }

pruefe_token() {
  local antwort; antwort="$(cf GET '/user/tokens/verify')"
  erfolgreich "$antwort" || {
    echo 'Der Token ist ungueltig, abgelaufen oder widerrufen.' >&2
    fehler_zeilen "$antwort"
    exit 65
  }
}

zone_id() {
  local name="$1" antwort
  antwort="$(cf GET "/zones?name=${name}&per_page=1")"
  erfolgreich "$antwort" || { fehler_zeilen "$antwort"; return 1; }
  printf '%s' "$antwort" | jq -r '.result[0].id // empty'
}

# Zieht den Host aus allem, was ein Weiterleitungsziel sein kann: einer festen
# URL, einem Page-Rule-Muster mit $1 oder einem concat(...)-Ausdruck einer
# Redirect Rule. Leer, wenn kein Host darin steht (z.B. ein relativer Pfad —
# der bleibt in der Zone und ist damit nie fremd).
ziel_host() {
  printf '%s' "$1" | grep -oiE 'https?://[^"/)[:space:]]+' | head -1 | sed -E 's#^[a-zA-Z]+://##' | tr 'A-Z' 'a-z'
}

# Fremd = Ziel-Host vorhanden und nicht der Apex der Zone. Das laesst
# www -> Apex stehen und erfasst auch Unterdomains der eigenen Zone
# (pfotencheck.tierarztportal.com).
ist_fremd() {
  local ziel="$1" zone="$2" host
  host="$(ziel_host "$ziel")"
  [ -n "$host" ] || return 1
  [ "$host" != "$zone" ]
}

GEFUNDEN=0
ENTFERNT=0

# --- 1. Redirect Rules (Single Redirect) -----------------------------------
redirect_rules() {
  local zone="$1" id="$2" antwort rs_id regeln anzahl i regel ziel beschr
  antwort="$(cf GET "/zones/${id}/rulesets/phases/http_request_dynamic_redirect/entrypoint")"
  if ! erfolgreich "$antwort"; then
    # Kein Einstiegs-Ruleset in dieser Zone ist der Normalfall, kein Fehler.
    if printf '%s' "$antwort" | jq -e '.errors[]? | select(.code == 10000 or .code == 9109)' >/dev/null 2>&1; then
      echo '  Redirect Rules: nicht lesbar (Berechtigung "Zone / Config Rules" fehlt).'
      fehler_zeilen "$antwort"
      return 0
    fi
    echo '  Redirect Rules: keine (kein Einstiegs-Ruleset).'
    return 0
  fi
  rs_id="$(printf '%s' "$antwort" | jq -r '.result.id // empty')"
  regeln="$(printf '%s' "$antwort" | jq -c '.result.rules // []')"
  anzahl="$(printf '%s' "$regeln" | jq -r 'length')"
  [ "$anzahl" -gt 0 ] || { echo '  Redirect Rules: keine.'; return 0; }

  i=0
  while [ "$i" -lt "$anzahl" ]; do
    regel="$(printf '%s' "$regeln" | jq -c ".[${i}]")"
    i=$((i + 1))
    [ "$(printf '%s' "$regel" | jq -r '.action')" = 'redirect' ] || continue
    ziel="$(printf '%s' "$regel" | jq -r '
      [.action_parameters.from_value.target_url.value?,
       .action_parameters.from_value.target_url.expression?] | map(select(. != null)) | join(" ")')"
    beschr="$(printf '%s' "$regel" | jq -r '.description // .expression // ""')"
    if ist_fremd "$ziel" "$zone"; then
      GEFUNDEN=$((GEFUNDEN + 1))
      echo "  Redirect Rule FREMD: $(ziel_host "$ziel")  [${beschr}]"
      [ "$MODUS" = 'entfernen' ] || continue
      local loesch; loesch="$(cf DELETE "/zones/${id}/rulesets/${rs_id}/rules/$(printf '%s' "$regel" | jq -r '.id')")"
      erfolgreich "$loesch" || { echo '  Loeschen der Redirect Rule fehlgeschlagen:' >&2; fehler_zeilen "$loesch"; exit 70; }
      echo '    entfernt.'
      ENTFERNT=$((ENTFERNT + 1))
    else
      echo "  Redirect Rule bleibt: $(ziel_host "$ziel")  [${beschr}]"
    fi
  done
}

# --- 2. Page Rules ----------------------------------------------------------
page_rules() {
  local zone="$1" id="$2" antwort anzahl i regel ziel muster
  antwort="$(cf GET "/zones/${id}/pagerules")"
  if ! erfolgreich "$antwort"; then
    echo '  Page Rules: nicht lesbar (Berechtigung "Zone / Zone: Edit" fehlt).'
    fehler_zeilen "$antwort"
    return 0
  fi
  anzahl="$(printf '%s' "$antwort" | jq -r '.result | length')"
  [ "$anzahl" -gt 0 ] || { echo '  Page Rules: keine.'; return 0; }

  i=0
  while [ "$i" -lt "$anzahl" ]; do
    regel="$(printf '%s' "$antwort" | jq -c ".result[${i}]")"
    i=$((i + 1))
    ziel="$(printf '%s' "$regel" | jq -r '[.actions[]? | select(.id == "forwarding_url") | .value.url] | join(" ")')"
    [ -n "$ziel" ] || continue
    muster="$(printf '%s' "$regel" | jq -r '[.targets[]?.constraint.value] | join(" ")')"
    if ist_fremd "$ziel" "$zone"; then
      GEFUNDEN=$((GEFUNDEN + 1))
      echo "  Page Rule FREMD: ${muster} -> $(ziel_host "$ziel")"
      [ "$MODUS" = 'entfernen' ] || continue
      local loesch; loesch="$(cf DELETE "/zones/${id}/pagerules/$(printf '%s' "$regel" | jq -r '.id')")"
      erfolgreich "$loesch" || { echo '  Loeschen der Page Rule fehlgeschlagen:' >&2; fehler_zeilen "$loesch"; exit 70; }
      echo '    entfernt.'
      ENTFERNT=$((ENTFERNT + 1))
    else
      echo "  Page Rule bleibt: ${muster} -> $(ziel_host "$ziel")"
    fi
  done
}

# --- 3. Bulk Redirects (Konto-Ebene) ---------------------------------------
# Die Listen haengen am Konto, nicht an der Zone. Entfernt wird nur der
# einzelne Eintrag, dessen Quelle in einer der bearbeiteten Zonen liegt — die
# Liste selbst und ihre Regel bleiben stehen (sie koennen fremde Zonen des
# Kontos bedienen).
bulk_redirects() {
  local antwort listen listen_anzahl i liste liste_id liste_name
  local eintraege eintrag_anzahl j eintrag quelle ziel host passt name
  [ -n "${CF_ACCOUNT_ID:-}" ] || { echo 'Bulk Redirects: uebersprungen (keine Konto-ID, CF_ACCOUNT_ID setzen).'; return 0; }

  antwort="$(cf GET "/accounts/${CF_ACCOUNT_ID}/rules/lists")"
  if ! erfolgreich "$antwort"; then
    echo 'Bulk Redirects: nicht lesbar (Berechtigung "Account / Account Filter Lists" fehlt).'
    fehler_zeilen "$antwort"
    return 0
  fi
  listen="$(printf '%s' "$antwort" | jq -c '[.result[]? | select(.kind == "redirect")]')"
  listen_anzahl="$(printf '%s' "$listen" | jq -r 'length')"
  [ "$listen_anzahl" -gt 0 ] || { echo 'Bulk Redirects: keine Liste der Art "redirect".'; return 0; }

  i=0
  while [ "$i" -lt "$listen_anzahl" ]; do
    liste="$(printf '%s' "$listen" | jq -c ".[${i}]")"
    i=$((i + 1))
    liste_id="$(printf '%s' "$liste" | jq -r '.id')"
    liste_name="$(printf '%s' "$liste" | jq -r '.name')"
    eintraege="$(cf GET "/accounts/${CF_ACCOUNT_ID}/rules/lists/${liste_id}/items?per_page=500")"
    erfolgreich "$eintraege" || { echo "Bulk-Liste ${liste_name}: Eintraege nicht lesbar."; fehler_zeilen "$eintraege"; continue; }
    eintrag_anzahl="$(printf '%s' "$eintraege" | jq -r '.result | length')"
    j=0
    while [ "$j" -lt "$eintrag_anzahl" ]; do
      eintrag="$(printf '%s' "$eintraege" | jq -c ".result[${j}]")"
      j=$((j + 1))
      quelle="$(printf '%s' "$eintrag" | jq -r '.redirect.source_url // ""')"
      ziel="$(printf '%s' "$eintrag" | jq -r '.redirect.target_url // ""')"
      [ -n "$quelle" ] || continue
      host="$(printf '%s' "$quelle" | sed -E 's#^[a-zA-Z]+://##' | cut -d/ -f1 | tr 'A-Z' 'a-z')"
      passt=0
      for name in $ZONEN; do
        case "$host" in "$name"|*".$name") passt=1 ;; esac
      done
      [ "$passt" = 1 ] || continue
      GEFUNDEN=$((GEFUNDEN + 1))
      echo "  Bulk Redirect FREMD: ${liste_name}: ${host} -> $(ziel_host "$ziel")"
      [ "$MODUS" = 'entfernen' ] || continue
      local rumpf loesch
      rumpf="$(jq -n --arg id "$(printf '%s' "$eintrag" | jq -r '.id')" '{items: [{id: $id}]}')"
      loesch="$(cf DELETE "/accounts/${CF_ACCOUNT_ID}/rules/lists/${liste_id}/items" -d "$rumpf")"
      erfolgreich "$loesch" || { echo '  Loeschen des Bulk-Eintrags fehlgeschlagen:' >&2; fehler_zeilen "$loesch"; exit 70; }
      echo '    entfernt.'
      ENTFERNT=$((ENTFERNT + 1))
    done
  done
}

# Nur melden, nicht aendern: steht die Zone auf Full (strict) und traegt das
# Ursprungszertifikat den Namen nicht, antwortet Cloudflare nach dem Entfernen
# 526 statt die Seite auszuliefern (Anleitung, Abschnitt 1).
ssl_modus() {
  local id="$1" antwort modus
  antwort="$(cf GET "/zones/${id}/settings/ssl")"
  erfolgreich "$antwort" || { echo '  SSL-Modus: nicht lesbar.'; return 0; }
  modus="$(printf '%s' "$antwort" | jq -r '.result.value // "?"')"
  echo "  SSL-Modus: ${modus}"
  [ "$modus" != 'strict' ] || cat <<'HINWEIS'
    ACHTUNG: Full (strict). Das Ursprungszertifikat traegt firmenfreund.net,
    www.firmenfreund.net und tierarztportal.com nicht (#37) — nach dem
    Entfernen antwortet die Kante 526. Zone vorher auf "Full" stellen, Namen
    am Ursprung nachziehen, dann zurueck auf Full (strict).
HINWEIS
}

pruefe_token

for zone in $ZONEN; do
  echo "== ${zone}"
  id="$(zone_id "$zone" || true)"
  if [ -z "${id:-}" ]; then
    echo '  Der Token sieht diese Zone nicht (fehlende Zonen-Berechtigung oder fremdes Konto).' >&2
    echo '  Ein auf "Account / Turnstile: Edit" geschnittener Token traegt hier nicht (#18).' >&2
    exit 65
  fi
  ssl_modus "$id"
  redirect_rules "$zone" "$id"
  page_rules "$zone" "$id"
done

bulk_redirects

echo
if [ "$GEFUNDEN" = 0 ]; then
  echo 'Keine fremde Weiterleitung gefunden. Kante gegenpruefen:'
  echo '  scripts/cloudflare-weiterleitungen-entfernen.sh --kante'
  exit 0
fi

if [ "$MODUS" != 'entfernen' ]; then
  echo "${GEFUNDEN} fremde Weiterleitung(en) gefunden, nichts geaendert." >&2
  echo 'Entfernen: scripts/cloudflare-weiterleitungen-entfernen.sh --entfernen' >&2
  exit 71
fi

echo "${ENTFERNT} von ${GEFUNDEN} Weiterleitung(en) entfernt. Jetzt die Proben aus #37:"
echo '  scripts/cloudflare-weiterleitungen-entfernen.sh --kante'
echo '  scripts/cloudflare-waf-regeln-setzen.sh --abnahme'
echo '  ssh sun ... php artisan guide:golive:check'
