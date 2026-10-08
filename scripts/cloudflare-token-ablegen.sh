#!/usr/bin/env bash
# Legt den Cloudflare-API-Token fuer SUN am Ursprung ab und weist vorher nach,
# dass er Turnstile bedienen darf (#18, Vorbedingung von #14 und #17).
#
# Warum es dieses Skript gibt: den Token kann nur eine Person mit
# Dashboard-Anmeldung erzeugen (My Profile -> API Tokens -> Create Token ->
# Custom, Berechtigung "Account / Turnstile: Edit", auf das SUN-Konto begrenzt).
# Alles danach ist Werkzeugarbeit. Damit aus dem Handgriff ein einziges Einfuegen
# wird — und damit spaetere Laeufe von scripts/turnstile-widgets-anlegen.sh ohne
# erneute Eingabe auskommen — nimmt dieses Skript den Rest ab:
#
#   1. fragt den Token verdeckt ab (kein Shell-Verlauf, kein Prozessargument),
#   2. prueft ihn VOR der Ablage gegen Cloudflare: gueltig, Konto bekannt, und
#      die Turnstile-Endpunkte antworten — ein falsch geschnittener Token landet
#      also gar nicht erst am Ursprung,
#   3. schreibt CLOUDFLARE_API_TOKEN_SUN_TURNSTILE und CLOUDFLARE_ACCOUNT_ID_SUN
#      nach /root/sun-zugang.txt (ersetzt vorhandene Zeilen, haengt nicht an),
#      chmod 600 — dieselbe Ablage wie /root/minber-zugang.txt,
#   4. wiederholt danach die Pruefung aus der Ablage heraus.
#
# Der Token gehoert bewusst NICHT ins Repository und NICHT in eine .env: er darf
# Widgets anlegen und loeschen, die Anwendung braucht ihn nie.
#
# Aufruf:
#   scripts/cloudflare-token-ablegen.sh            # ablegen
#   scripts/cloudflare-token-ablegen.sh --pruefen  # nur pruefen, aendert nichts
#
# Ohne Tastatureingabe, etwa aus einem Passwortspeicher:
#   pbpaste | scripts/cloudflare-token-ablegen.sh
#
# Mit Konto-ID, wenn der Token sie nicht selbst verraet (der Normalfall, siehe
# unten) — sie steht in der Dashboard-URL dash.cloudflare.com/<konto-id> oder
# unter Account Home -> rechte Spalte "Account ID":
#   CF_ACCOUNT_ID=<32 Hexzeichen> scripts/cloudflare-token-ablegen.sh
#
# Warum: ein Token mit ausschliesslich "Account / Turnstile: Edit" darf die
# Turnstile-Endpunkte seines Kontos bedienen, das Konto aber nicht auflisten —
# GET /accounts braucht zusaetzlich "Account / Account Settings: Read". Ohne
# CF_ACCOUNT_ID sieht das Skript dann 0 Konten, obwohl der Token richtig
# geschnitten ist. Es nimmt die ID deshalb in dieser Reihenfolge: CF_ACCOUNT_ID,
# sonst CLOUDFLARE_ACCOUNT_ID_SUN aus der Ablage, sonst die Kontoliste.
#
# Einstellbar: SUN_SSH_HOST (Vorgabe sun), ZUGANGSDATEI, CF_API_TOKEN,
# CF_ACCOUNT_ID.
#
# Exit-Codes:
#   0  Token abgelegt und Gegenprobe bestanden (bzw. --pruefen bestanden)
#   1  kein Token eingegeben / keiner hinterlegt
#   2  Token traegt nicht, falscher Umfang, oder Ablage fehlgeschlagen
#   3  Konto-ID unbekannt — mit CF_ACCOUNT_ID erneut starten

set -euo pipefail

API="https://api.cloudflare.com/client/v4"
SSH_HOST="${SUN_SSH_HOST:-sun}"
ZUGANGSDATEI="${ZUGANGSDATEI:-/root/sun-zugang.txt}"
SCHLUESSEL_TOKEN="CLOUDFLARE_API_TOKEN_SUN_TURNSTILE"
SCHLUESSEL_KONTO="CLOUDFLARE_ACCOUNT_ID_SUN"

NUR_PRUEFEN=0
while [ $# -gt 0 ]; do
  case "$1" in
    --pruefen) NUR_PRUEFEN=1; shift ;;
    -h|--help) sed -n '2,50p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done

command -v jq >/dev/null || { echo "jq fehlt (brew install jq)." >&2; exit 2; }

remote() { ssh -o BatchMode=yes -o ConnectTimeout=15 "$SSH_HOST" "$@"; }

cf() {
  curl -sS --max-time 20 "${API}$1" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H 'Content-Type: application/json'
}

fehler_zeilen() {
  printf '%s' "$1" | jq -r '.errors[]? | "  \(.code): \(.message)"' >&2
}

# Setzt KONTO_ID: aus CF_ACCOUNT_ID, sonst aus der Ablage, sonst aus der
# Kontoliste. Die Liste ist bewusst die letzte Quelle und ihr leeres Ergebnis
# kein Fehlschnitt: GET /accounts braucht "Account Settings: Read", das ein
# reiner Turnstile-Token nicht hat (Befund in #38, 08.10.2026).
ermittle_konto() {
  local antwort anzahl

  KONTO_ID="${CF_ACCOUNT_ID:-}"
  [ -n "$KONTO_ID" ] || KONTO_ID="$(hole_konto_aus_ablage)"

  if [ -z "$KONTO_ID" ]; then
    antwort="$(cf '/accounts?per_page=50')"
    if [ "$(printf '%s' "$antwort" | jq -r '.success')" != "true" ]; then
      echo 'Die Kontoliste ist nicht abrufbar.' >&2
      fehler_zeilen "$antwort"
      konto_id_anleitung; return 3
    fi
    anzahl="$(printf '%s' "$antwort" | jq -r '.result | length')"
    if [ "$anzahl" != 1 ]; then
      if [ "$anzahl" = 0 ]; then
        echo 'Der Token sieht kein Konto. Das ist bei einem Token mit nur' >&2
        echo '"Account / Turnstile: Edit" normal und kein Fehlschnitt —' >&2
        echo 'GET /accounts braucht zusaetzlich "Account Settings: Read".' >&2
      else
        echo "Der Token sieht ${anzahl} Konten — CF_ACCOUNT_ID setzen:" >&2
        printf '%s' "$antwort" | jq -r '.result[]? | "  \(.id)  \(.name)"' >&2
      fi
      konto_id_anleitung; return 3
    fi
    KONTO_ID="$(printf '%s' "$antwort" | jq -r '.result[0].id')"
    echo "Konto: $(printf '%s' "$antwort" | jq -r '.result[0].name') (${KONTO_ID})"

    return 0
  fi

  # Die ID wird unten in einen Fernaufruf eingesetzt — ein Wert mit
  # Sonderzeichen wuerde dort Code werden.
  printf '%s' "$KONTO_ID" | grep -qE '^[0-9a-f]{32}$' || {
    echo "Das sieht nicht nach einer Cloudflare-Konto-ID aus (erwartet: 32 Hexzeichen)." >&2
    return 3
  }
  echo "Konto: ${KONTO_ID} (vorgegeben)"
}

konto_id_anleitung() {
  cat >&2 <<'ANLEITUNG'
Konto-ID nachliefern und erneut starten:
  CF_ACCOUNT_ID=<32 Hexzeichen> scripts/cloudflare-token-ablegen.sh
Sie steht in der Dashboard-URL dash.cloudflare.com/<konto-id> oder unter
Account Home -> rechte Spalte "Account ID".
ANLEITUNG
}

# Beweist in drei Schritten, dass der Token taugt, und setzt KONTO_ID.
# Reihenfolge ist Absicht: erst "traegt er ueberhaupt", dann "welches Konto",
# dann "darf er Turnstile" — sonst steht am Ende nur Cloudflares nackter
# "Authentication error" und man sucht den Fehler beim Konto.
pruefe_token() {
  local antwort

  antwort="$(cf '/user/tokens/verify')" || { echo 'Cloudflare nicht erreichbar.' >&2; return 2; }
  if [ "$(printf '%s' "$antwort" | jq -r '.success')" != "true" ]; then
    echo 'Der Token ist ungueltig, abgelaufen oder widerrufen.' >&2
    fehler_zeilen "$antwort"; return 2
  fi
  echo "Token: $(printf '%s' "$antwort" | jq -r '.result.status')"

  ermittle_konto || return $?

  antwort="$(cf "/accounts/${KONTO_ID}/challenges/widgets?per_page=50")"
  if [ "$(printf '%s' "$antwort" | jq -r '.success')" != "true" ]; then
    echo 'Der Token darf Turnstile NICHT bedienen. Fehlende Berechtigung:' >&2
    echo '  Account / Turnstile: Edit (Create Token -> Custom)' >&2
    fehler_zeilen "$antwort"; return 2
  fi
  echo "Turnstile: lesbar, $(printf '%s' "$antwort" | jq -r '.result | length') Widget(s) im Konto"
}

hole_aus_ablage() {
  remote "grep -m1 '^${SCHLUESSEL_TOKEN}=' ${ZUGANGSDATEI} 2>/dev/null | cut -d= -f2-" 2>/dev/null || true
}

hole_konto_aus_ablage() {
  remote "grep -m1 '^${SCHLUESSEL_KONTO}=' ${ZUGANGSDATEI} 2>/dev/null | cut -d= -f2-" 2>/dev/null || true
}

if [ "$NUR_PRUEFEN" = 1 ]; then
  TOKEN="${CF_API_TOKEN:-$(hole_aus_ablage)}"
  if [ -z "$TOKEN" ]; then
    echo "Kein Token hinterlegt (${SSH_HOST}:${ZUGANGSDATEI}) — #18 ist noch nicht ausgefuehrt." >&2
    exit 1
  fi
  pruefe_token || exit $?
  echo 'Pruefung bestanden. Weiter mit: scripts/turnstile-widgets-anlegen.sh'
  exit 0
fi

if [ -n "${CF_API_TOKEN:-}" ]; then
  TOKEN="$CF_API_TOKEN"
elif [ -t 0 ]; then
  printf 'Cloudflare-API-Token (Berechtigung "Account / Turnstile: Edit"): '
  read -rs TOKEN; echo
else
  read -r TOKEN || TOKEN=''
fi
TOKEN="$(printf '%s' "${TOKEN:-}" | tr -d '[:space:]')"
[ -n "$TOKEN" ] || { echo 'Kein Token eingegeben.' >&2; exit 1; }

# Zeichenpruefung, nicht Kosmetik: der Token wird unten in einen Fernaufruf
# eingesetzt, und ein Wert mit Anfuehrungszeichen wuerde dort Code werden.
printf '%s' "$TOKEN" | grep -qE '^[A-Za-z0-9_.-]{25,}$' || {
  echo 'Das sieht nicht nach einem Cloudflare-API-Token aus (erwartet: mindestens 25 Zeichen A-Z a-z 0-9 _ . -).' >&2
  exit 2
}

pruefe_token || exit $?

# Der Token geht als stdin-Strom hinueber, nie als ssh-Argument — sonst steht er
# in der Prozessliste des Servers.
{
  printf 'TOKEN=%s\nKONTO=%s\nDATEI=%s\nSCHLUESSEL_TOKEN=%s\nSCHLUESSEL_KONTO=%s\n' \
    "$TOKEN" "$KONTO_ID" "$ZUGANGSDATEI" "$SCHLUESSEL_TOKEN" "$SCHLUESSEL_KONTO"
  cat <<'FERN'
set -eu
umask 077
if [ ! -f "$DATEI" ]; then
  printf '# Cloudflare-Zugang fuer SUN (#18). Nicht ins Repository.\n# Angelegt: %s\n' \
    "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" > "$DATEI"
fi
TMP="$(mktemp)"
grep -v -E "^(${SCHLUESSEL_TOKEN}|${SCHLUESSEL_KONTO})=" "$DATEI" > "$TMP" || true
printf '%s=%s\n%s=%s\n' "$SCHLUESSEL_TOKEN" "$TOKEN" "$SCHLUESSEL_KONTO" "$KONTO" >> "$TMP"
cat "$TMP" > "$DATEI"
rm -f "$TMP"
chmod 600 "$DATEI"
echo "Abgelegt: $DATEI (chmod 600)"
FERN
} | remote 'bash -s'

echo
echo 'Gegenprobe aus der Ablage:'
TOKEN="$(hole_aus_ablage)"
[ -n "$TOKEN" ] || { echo 'Der Token steht nicht in der Ablage.' >&2; exit 2; }
pruefe_token || exit $?

cat <<'HINWEIS'

Fertig. Damit laufen die Folgeschritte ohne weitere Eingabe:

  scripts/turnstile-widgets-anlegen.sh --pruefen   # Soll-Ist der drei Widgets
  scripts/turnstile-widgets-anlegen.sh             # anlegen (#14)

Handarbeit bleibt nur: je Widget "Allow a domain to be added automatically"
ausschalten (die API kennt den Schalter nicht) und die drei Eintraege
"SUN / Cloudflare Turnstile / Gruppe A|B|C" im Passwort-Manager.
HINWEIS
