#!/usr/bin/env bash
# Legt die drei Turnstile-Widgets der SUN-Portale an und prueft ihre
# Einstellungen gegen docs/turnstile.md, Abschnitt 8 (#14).
#
# Das Skript ersetzt die Klickarbeit im Cloudflare-Dashboard, nicht den
# Cloudflare-Zugang selbst: es braucht einen API-Token mit der Berechtigung
# "Account / Turnstile: Edit". Den gibt es im Projekt nicht (geprueft in .env,
# .env.example, config/, docs/ und der Produktions-.env) und er ist der letzte
# Menschenschritt (#18) — wer keinen Token anlegen will, macht die drei Widgets
# im Dashboard nach denselben Angaben und ueberspringt dieses Skript.
#
# Den Token holt sich das Skript in dieser Reihenfolge: CF_API_TOKEN aus der
# Umgebung, sonst CLOUDFLARE_API_TOKEN_SUN_TURNSTILE aus /root/sun-zugang.txt am
# Ursprung (dort ablegen mit scripts/cloudflare-token-ablegen.sh), sonst Abfrage
# am Terminal.
#
# Aufruf (Token wird abgefragt, landet nicht in der Shell-Historie):
#   scripts/turnstile-widgets-anlegen.sh
#
# Nur pruefen, ob vorhandene Widgets zu Abschnitt 8 passen (aendert nichts):
#   scripts/turnstile-widgets-anlegen.sh --pruefen
#
# Ohne Token, Widgets von Hand im Dashboard anlegen (docs/turnstile-golive.md
# §1.1 Weg B) — druckt die drei Widget-Definitionen zum Abtippen und redet mit
# nichts, weder mit Cloudflare noch mit dem Ursprung:
#   scripts/turnstile-widgets-anlegen.sh --weg-b
#
# CF_ACCOUNT_ID ist optional: hat der Token genau ein Konto, holt das Skript die
# Account-ID selbst. Nur bei mehreren Konten muss sie gesetzt werden.
#
# Ausgabe sind Sitekey und Secret je Gruppe — direkt in den Passwort-Manager
# ("SUN / Cloudflare Turnstile / Gruppe A|B|C", Felder sitekey und secret),
# nie in eine Datei im Repo.

set -euo pipefail

API="https://api.cloudflare.com/client/v4"

# Gruppen genau wie docs/turnstile.md Abschnitt 8. www.-Varianten und die fuenf
# firmenfreund.de-Subdomains stehen bewusst NICHT drin: Subdomains sind laut
# Cloudflare mit abgedeckt, und jeder Eintrag zaehlt gegen das Limit von 10.
NAME_A='SUN Welle 1'
HOSTS_A='fahrschulefinder.de elektrikerportal.com sanitaerfinden.com'
NAME_B='SUN Welle 2'
HOSTS_B='sanitaerfinder.com malerfinder.de fliesenleger.io kfzwerkstatt.io findegutachter.de bodenlegerfinden.com tierarztportal.com energieberaterportal.net firmenfreund.net firmenfreund.de'
NAME_C='SUN Welle 3'
HOSTS_C='geruestbauer.gmbh metallbauer.io mjet.net schluesseldienstportal.com speditionportal.com'

NUR_PRUEFEN=0
WEG_B=0
while [ $# -gt 0 ]; do
  case "$1" in
    --pruefen) NUR_PRUEFEN=1; shift ;;
    --weg-b) WEG_B=1; shift ;;
    -h|--help) sed -n '2,31p' "$0"; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done

# Weg B kommt vor jeder Pruefung auf jq und vor der Tokensuche: er ist genau
# fuer den Fall da, dass kein Token existiert (#38), und darf deshalb an keiner
# Vorbedingung scheitern, die nur Weg A braucht.
if [ "$WEG_B" = 1 ]; then
  gruppe_ausgeben() {
    local gruppe="$1" name="$2" hosts="$3"
    echo
    echo "== Gruppe ${gruppe} — Dashboard › Turnstile › Add widget =="
    echo "  Widget name:   ${name}"
    echo "  Widget Mode:   Managed"
    echo "  Pre-clearance: no clearance level"
    echo "  Hostnames ($(printf '%s\n' $hosts | wc -w | tr -d ' ')), je Zeile einer:"
    printf '    %s\n' $hosts
  }

  cat <<'KOPF'
Weg B — drei Widgets von Hand im Cloudflare-Dashboard (docs/turnstile-golive.md §1.1).
Die Namen muessen genau stimmen: unter ihnen findet --pruefen die Widgets spaeter
wieder, statt Dubletten anzulegen.
KOPF
  gruppe_ausgeben A "$NAME_A" "$HOSTS_A"
  gruppe_ausgeben B "$NAME_B" "$HOSTS_B"
  gruppe_ausgeben C "$NAME_C" "$HOSTS_C"
  cat <<'FUSS'

Danach, bei jedem Widget:
  * "Allow a domain to be added automatically" AUS (die API kennt den Schalter
    nicht, deshalb steht er auch bei Weg A hier).
  * Sitekey und Secret in den Passwort-Manager:
    "SUN / Cloudflare Turnstile / Gruppe A|B|C", Hostname-Liste als Notiz.

Sechs Werte auf die Produktion, ohne Cloudflare-API:
  scripts/turnstile-schluessel-eintragen.sh
Gegenprobe (zaehlt 18 Hostnames, redet nur mit Siteverify):
  php artisan turnstile:keys:check --siteverify
FUSS
  exit 0
fi

command -v jq >/dev/null || { echo "jq fehlt (brew install jq)." >&2; exit 69; }

# Liegt der Token am Ursprung (scripts/cloudflare-token-ablegen.sh, #18), kommt
# er von dort — sonst waere jeder Lauf wieder eine Eingabe von Hand und das
# Skript damit nur fuer Enes bedienbar.
if [ -z "${CF_API_TOKEN:-}" ]; then
  CF_API_TOKEN="$(ssh -o BatchMode=yes -o ConnectTimeout=15 "${SUN_SSH_HOST:-sun}" \
    "grep -m1 '^CLOUDFLARE_API_TOKEN_SUN_TURNSTILE=' /root/sun-zugang.txt 2>/dev/null | cut -d= -f2-" 2>/dev/null || true)"
  [ -z "$CF_API_TOKEN" ] || echo 'Token aus /root/sun-zugang.txt (Ursprung).'
fi

# Die Konto-ID kommt aus derselben Ablage: ein Token mit nur
# "Account / Turnstile: Edit" darf die Widget-Endpunkte bedienen, das Konto aber
# nicht auflisten (GET /accounts braucht "Account Settings: Read"), sonst sieht
# ermittle_konto 0 Konten und bricht ab (Befund in #38, 08.10.2026).
if [ -z "${CF_ACCOUNT_ID:-}" ]; then
  CF_ACCOUNT_ID="$(ssh -o BatchMode=yes -o ConnectTimeout=15 "${SUN_SSH_HOST:-sun}" \
    "grep -m1 '^CLOUDFLARE_ACCOUNT_ID_SUN=' /root/sun-zugang.txt 2>/dev/null | cut -d= -f2-" 2>/dev/null || true)"
  if [ -n "$CF_ACCOUNT_ID" ]; then
    printf '%s' "$CF_ACCOUNT_ID" | grep -qE '^[0-9a-f]{32}$' || {
      echo 'CLOUDFLARE_ACCOUNT_ID_SUN in /root/sun-zugang.txt ist keine Konto-ID (erwartet: 32 Hexzeichen).' >&2
      exit 65
    }
    echo "Konto-ID aus /root/sun-zugang.txt: ${CF_ACCOUNT_ID}"
  fi
fi

if [ -z "${CF_API_TOKEN:-}" ] && [ -t 0 ]; then
  printf 'CF_API_TOKEN (Berechtigung "Account / Turnstile: Edit"): '
  read -rs CF_API_TOKEN; echo
fi
[ -n "${CF_API_TOKEN:-}" ] || {
  echo 'Kein Token: weder CF_API_TOKEN gesetzt noch einer in /root/sun-zugang.txt.' >&2
  echo 'Einmalig ablegen: scripts/cloudflare-token-ablegen.sh  (#18)' >&2
  exit 65
}

cf() {
  local methode="$1" pfad="$2"; shift 2
  curl -sS -X "$methode" "${API}${pfad}" \
    -H "Authorization: Bearer ${CF_API_TOKEN}" \
    -H 'Content-Type: application/json' "$@"
}

# Bricht ab, wenn Cloudflare success=false meldet — sonst laeuft das Skript mit
# leeren Feldern weiter und schreibt Unsinn in die Ausgabe.
pruefe_antwort() {
  local antwort="$1" was="$2"
  if [ "$(printf '%s' "$antwort" | jq -r '.success')" != "true" ]; then
    echo "${was} fehlgeschlagen:" >&2
    printf '%s' "$antwort" | jq -r '.errors[]? | "  \(.code): \(.message)"' >&2
    exit 70
  fi
}

# Ein Token ohne Turnstile-Berechtigung antwortet auf die Widget-Endpunkte mit
# einem nackten "Authentication error" (Code 10000) — ohne diesen Hinweis sucht
# man den Fehler beim Konto statt bei der Berechtigung.
pruefe_token() {
  local antwort; antwort="$(cf GET '/user/tokens/verify')"
  if [ "$(printf '%s' "$antwort" | jq -r '.success')" != "true" ]; then
    echo 'Der Token ist ungueltig, abgelaufen oder widerrufen.' >&2
    printf '%s' "$antwort" | jq -r '.errors[]? | "  \(.code): \(.message)"' >&2
    exit 65
  fi
}

# Holt die Account-ID selbst, solange der Token genau ein Konto sieht. Damit
# bleibt als Menschenschritt nur noch das Anlegen des Tokens (#18).
ermittle_konto() {
  [ -z "${CF_ACCOUNT_ID:-}" ] || return 0

  local antwort anzahl; antwort="$(cf GET '/accounts?per_page=50')"
  pruefe_antwort "$antwort" 'Kontoliste'
  anzahl="$(printf '%s' "$antwort" | jq -r '.result | length')"

  if [ "$anzahl" != 1 ]; then
    echo "Der Token sieht ${anzahl} Konten — CF_ACCOUNT_ID setzen:" >&2
    printf '%s' "$antwort" | jq -r '.result[]? | "  \(.id)  \(.name)"' >&2
    exit 65
  fi

  CF_ACCOUNT_ID="$(printf '%s' "$antwort" | jq -r '.result[0].id')"
  echo "Konto: $(printf '%s' "$antwort" | jq -r '.result[0].name') (${CF_ACCOUNT_ID})"
}

liste() {
  local antwort; antwort="$(cf GET "/accounts/${CF_ACCOUNT_ID}/challenges/widgets?per_page=50")"
  pruefe_antwort "$antwort" 'Widget-Liste'
  printf '%s' "$antwort"
}

domains_json() {
  printf '%s\n' $1 | jq -R . | jq -s -c .
}

# Vergleicht ein vorhandenes Widget mit der Vorgabe und meldet jede Abweichung.
pruefe_widget() {
  local widget="$1" soll_hosts="$2" abweichung=0
  local sitekey mode clearance
  sitekey="$(printf '%s' "$widget" | jq -r '.sitekey')"
  mode="$(printf '%s' "$widget" | jq -r '.mode')"
  clearance="$(printf '%s' "$widget" | jq -r '.clearance_level // "no_clearance"')"

  [ "$mode" = 'managed' ] || { echo "  ! Modus ist '${mode}', erwartet 'managed'"; abweichung=1; }
  [ "$clearance" = 'no_clearance' ] || { echo "  ! Pre-Clearance ist '${clearance}', erwartet 'no_clearance'"; abweichung=1; }

  local ist soll
  ist="$(printf '%s' "$widget" | jq -r '.domains[]' | sort | tr '\n' ' ')"
  soll="$(printf '%s\n' $soll_hosts | sort | tr '\n' ' ')"
  if [ "$ist" != "$soll" ]; then
    echo "  ! Hostnames weichen ab"
    echo "    ist : ${ist}"
    echo "    soll: ${soll}"
    abweichung=1
  fi

  echo "  Sitekey: ${sitekey}"
  echo "  Secret:  $(cf GET "/accounts/${CF_ACCOUNT_ID}/challenges/widgets/${sitekey}" | jq -r '.result.secret // "(nur im Dashboard sichtbar)"')"
  # Kein "&&" am Zeilenende: unter set -e wuerde ein abweichendes Widget den
  # Lauf abbrechen, statt die naechste Gruppe noch zu pruefen.
  if [ "$abweichung" = 0 ]; then
    echo "  Einstellungen passen zu docs/turnstile.md Abschnitt 8."
  fi
}

verarbeite_gruppe() {
  local gruppe="$1" name="$2" hosts="$3" alle="$4"
  echo
  echo "== Gruppe ${gruppe} — \"${name}\" =="

  local vorhanden
  vorhanden="$(printf '%s' "$alle" | jq -c --arg n "$name" '.result[]? | select(.name == $n)')"

  if [ -n "$vorhanden" ]; then
    echo "  Widget existiert bereits — nichts angelegt."
    pruefe_widget "$vorhanden" "$hosts"
    return
  fi

  if [ "$NUR_PRUEFEN" = 1 ]; then
    echo "  ! Widget fehlt. Ohne --pruefen anlegen."
    return
  fi

  local antwort
  antwort="$(cf POST "/accounts/${CF_ACCOUNT_ID}/challenges/widgets" -d "$(jq -n \
    --arg name "$name" --argjson domains "$(domains_json "$hosts")" \
    '{name: $name, mode: "managed", domains: $domains, clearance_level: "no_clearance", offlabel: false}')")"
  pruefe_antwort "$antwort" "Anlegen von \"${name}\""
  echo "  Angelegt."
  pruefe_widget "$(printf '%s' "$antwort" | jq -c '.result')" "$hosts"
}

pruefe_token
ermittle_konto

if ! ALLE="$(liste)"; then
  echo 'Fehlt dem Token die Berechtigung "Account / Turnstile: Edit"? Siehe #18.' >&2
  exit 70
fi
verarbeite_gruppe A "$NAME_A" "$HOSTS_A" "$ALLE"
verarbeite_gruppe B "$NAME_B" "$HOSTS_B" "$ALLE"
verarbeite_gruppe C "$NAME_C" "$HOSTS_C" "$ALLE"

cat <<'HINWEIS'

Zwei Dinge bleiben Handarbeit im Dashboard:

1. "Allow a domain to be added automatically" ausschalten — die API kennt den
   Schalter nicht. Bleibt er an, wandern fremde Hostnames ins Widget und der
   Hostname-Check (docs/turnstile.md Abschnitt 3) verliert seinen Sinn.
2. Sitekey und Secret je Gruppe in den Passwort-Manager:
   "SUN / Cloudflare Turnstile / Gruppe A|B|C", Felder sitekey und secret,
   Hostname-Liste als Notiz. Nichts davon ins Repo.

Danach die Schluessel auf Produktion eintragen:
  scripts/turnstile-schluessel-eintragen.sh
HINWEIS
