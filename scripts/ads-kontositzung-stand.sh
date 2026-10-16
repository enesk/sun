#!/usr/bin/env bash
# Stand der AdSense-Kontositzung (Ticket #53, pilot-protokoll.md §6.1-§6.4)
# und naechster Handgriff.
#
# Warum es dieses Skript gibt: an #53 haengt Handarbeit in zwei fremden
# Oberflaechen (AdSense und Search Console, dazu zwei Konten) und ein
# Zeitgatter — die Feldwerte und der Report sind erst drei volle Pilottage
# nach dem Start ablesbar. Jeder Anlauf wiederholt denselben Satz lesender
# Befehle gegen Repo und Produktion, nur um herauszufinden, welcher der vier
# Punkte noch offen ist. Das ist Werkzeugarbeit — hier in einem Aufruf, in der
# Reihenfolge des Protokolls (§6.1 -> §6.2 -> §6.3 -> §6.4).
#
# Welche Umwege ohne Konto geprueft und verworfen sind, steht in §6.5 des
# Protokolls: §6.2 und §6.4 sind ueber die AdSense Management API lesbar,
# sobald Ticket #55 (API-Freischaltung, Dienstkonto in beiden Konten) steht.
# Ob dieser Zugang steht, prueft Abschnitt §6.0 ueber
# `artisan ads:adsense-konto` (lesend; Exit 70 = API im Cloud-Projekt
# gesperrt, 71 = Konto fehlt dem Dienstkonto, 0 = alle Konten lesbar).
#
# Das Skript aendert NICHTS: kein Schalter, keine Datenbank, kein Deploy. Es
# liest Repo-Dateien, ruft lesende artisan-Befehle auf der Produktion und
# holt /ads.txt per curl von aussen.
#
# Aufruf:
#   scripts/ads-kontositzung-stand.sh           # Stand im Klartext
#   scripts/ads-kontositzung-stand.sh --kurz    # nur der naechste Handgriff
#   scripts/ads-kontositzung-stand.sh --ohne-prod   # nur die Repo-Zeilen
#
# Einstellbar: SUN_SSH_HOST (Vorgabe sun), APP_DIR, APP_USER, PHP_BIN.
#
# Exit-Codes:
#   0   alle vier Punkte erledigt
#   70  mindestens ein Punkt offen (der erste steht als NAECHSTER HANDGRIFF da)
#   65  Produktion nicht erreichbar

set -euo pipefail

SSH_HOST="${SUN_SSH_HOST:-sun}"
APP_DIR="${APP_DIR:-/home/sanitaerfinden/htdocs/sanitaerfinden.dev}"
APP_USER="${APP_USER:-sanitaerfinden}"
PHP_BIN="${PHP_BIN:-/usr/bin/php8.4}"
HIER="$(cd "$(dirname "$0")/.." && pwd)"

# Pilotstart laut pilot-protokoll.md §1; drei volle Tage spaeter beginnt das
# Report-Fenster (§6.1, §6.2).
PILOT_START_UTC="2026-10-16T12:40:00Z"
PILOT_START_EPOCH=1792154400   # dasselbe als Sekunden seit 1970 (UTC)
REPORT_AB_EPOCH=$((PILOT_START_EPOCH + 3 * 86400))

PROTOKOLL="docs/messungen/werbeplaetze-golive/pilot-protokoll.md"
ADSLOTS="docs/ad-slots.md"
PILOT_TENANT=27
GETEILTER_SLOT=3828015317

KURZ=0
OHNE_PROD=0
while [ $# -gt 0 ]; do
  case "$1" in
    --kurz) KURZ=1; shift ;;
    --ohne-prod) OHNE_PROD=1; shift ;;
    -h|--help) sed -n '2,27p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done

remote() { ssh -o BatchMode=yes -o ConnectTimeout=20 "$SSH_HOST" "$@"; }
als_app() { remote "su -s /bin/bash ${APP_USER} -c 'HOME=/home/${APP_USER}; cd ${APP_DIR} && $1'" 2>/dev/null | grep -v '^PHP Deprecated' || true; }

OFFEN=()
zeile() { # zeile <ok|offen|unklar> <abschnitt> <text> [handgriff]
  local z="$1" a="$2" t="$3" h="${4:-}"
  case "$z" in
    ok)     [ "$KURZ" -eq 1 ] || printf '  [x] %-6s %s\n' "$a" "$t" ;;
    offen)  [ "$KURZ" -eq 1 ] || printf '  [ ] %-6s %s\n' "$a" "$t"
            OFFEN+=("${a} — ${t}${h:+
        Handgriff: ${h}}") ;;
    unklar) [ "$KURZ" -eq 1 ] || printf '  [?] %-6s %s\n' "$a" "$t" ;;
  esac
}

cd "$HIER"
JETZT=$(date -u +%s)

if [ "$KURZ" -eq 0 ]; then
  echo "Stand der AdSense-Kontositzung — $(date '+%d.%m.%Y %H:%M')"
  echo "Ablauf: ${PROTOKOLL} §6   Bestandsaufnahme der sieben: freigabe-sieben-portale-2026-10-16.md"
  echo
fi

# ------------------------------------------------------------ Zeitgatter
if [ "$JETZT" -lt "$REPORT_AB_EPOCH" ]; then
  REST=$(( (REPORT_AB_EPOCH - JETZT + 3599) / 3600 ))
  ZEITGATTER=zu
  [ "$KURZ" -eq 1 ] || printf 'Zeitgatter: Pilot laeuft seit %s — Report-Fenster (§6.1, §6.2) oeffnet in rund %d Stunden.\n\n' "$PILOT_START_UTC" "$REST"
else
  ZEITGATTER=offen
  [ "$KURZ" -eq 1 ] || printf 'Zeitgatter: drei volle Pilottage sind um — §6.1 und §6.2 sind ablesbar.\n\n'
fi

# ---------------------------------------------- §6.0: API-Zugang (Ticket #55)
# Steht der Zugang, sind §6.2 und §6.4 ohne Oberflaeche ablesbar. Der Befehl
# aendert nichts; sein Exit-Code sagt, welcher der zwei Handgriffe fehlt.
[ "$KURZ" -eq 1 ] || echo "§6.0 — Zugang der AdSense Management API (#55)"
if [ "$OHNE_PROD" -eq 1 ]; then
  zeile unklar "§6.0" "Produktion auf Wunsch uebersprungen (--ohne-prod)"
else
  if ! remote true 2>/dev/null; then
    echo "Produktion (${SSH_HOST}) nicht erreichbar." >&2
    exit 65
  fi
  API_CODE=$(als_app "${PHP_BIN} artisan ads:adsense-konto >/dev/null 2>&1; echo EXIT\$?" | sed -n 's/.*EXIT\([0-9]*\).*/\1/p' | tail -1)
  case "${API_CODE:-}" in
    0)  zeile ok "§6.0" "Dienstkonto liest alle im Netz benutzten AdSense-Konten — §6.2/§6.4 per API" ;;
    70) zeile offen "§6.0" "AdSense Management API im Cloud-Projekt 321830197826 nicht freigeschaltet" \
          "Ohne Konsole machbar (#58): Token auf Scope cloud-platform holen, dann POST serviceusage.googleapis.com/v1/projects/321830197826/services/adsense.googleapis.com:enable mit Rumpf {} — siehe pilot-protokoll.md §6.5. Gegenprobe: artisan ads:adsense-konto." ;;
    71) zeile offen "§6.0" "Dienstkonto-Weg endet hier — Exit 71 bleibt bis #61 geliefert ist" \
          "Nicht mehr versuchen, das Dienstkonto als AdSense-Nutzer einzutragen: die Doku der Management API sagt \"AdSense doesn't support Service Accounts, instead you must use the Installed Application flow\" (Stand 16.01.2026), und eine Einladung muss aus einem Google-Konto angenommen werden — eine …iam.gserviceaccount.com-Adresse hat kein Postfach, die Einladung bliebe Pending. Weiter geht es nur ueber #61 (Refresh-Token eines echten Nutzers im AdSenseClient); bis dahin §6.2/§6.4 in der Oberflaeche ablesen. Belegt in pilot-protokoll.md §6.5 (#58)." ;;
    65) zeile unklar "§6.0" "kein lesbarer Dienstkonto-Schluessel auf der Produktion (GOOGLE_SERVICE_ACCOUNT_JSON)" ;;
    1)  zeile unklar "§6.0" "ads:adsense-konto bricht ab (Befehl dort unbekannt oder Netzfehler) — Stand der Produktion aelter als #55?" ;;
    *)  zeile unklar "§6.0" "ads:adsense-konto endet mit Exit ${API_CODE:-?} — von Hand nachsehen" ;;
  esac
fi

[ "$KURZ" -eq 1 ] || echo

# ------------------------------------------------------ §6.1: Feldwerte
[ "$KURZ" -eq 1 ] || echo "§6.1 — Feldwerte aus CrUX (Search Console, bodenlegerfinden.com)"
if grep -qE '^\| CLS Feld mobil \| — \| — \|' "$PROTOKOLL"; then
  if [ "$ZEITGATTER" = zu ]; then
    zeile unklar "§6.1" "Feldzeilen in ${PROTOKOLL} §3 leer — noch nicht ablesbar (Zeitgatter)"
  else
    zeile offen "§6.1" "Feldzeilen in ${PROTOKOLL} §3 leer" \
      "Search Console > Core Web Vitals, 75. Perzentile fuer CLS/LCP/INP mobil und Desktop; in §3 die Zeilen CLS Feld mobil, CLS Feld Desktop, INP fuellen und die zwei Haken setzen. CrUX mischt 28 Tage — als Hinweis notieren, nicht als Beweis."
  fi
else
  zeile ok "§6.1" "Feldzeilen in ${PROTOKOLL} §3 gefuellt"
fi

# -------------------------------------------- §6.2: AdSense-Report je Block
[ "$KURZ" -eq 1 ] || echo
[ "$KURZ" -eq 1 ] || echo "§6.2 — AdSense-Report je Anzeigenblock (Viewability-Quote, RPM)"
KONTO_OFFEN=$(grep -c 'offen (Konto)' "$ADSLOTS" || true)
if [ "$KONTO_OFFEN" -gt 0 ]; then
  if [ "$ZEITGATTER" = zu ]; then
    zeile unklar "§6.2" "${KONTO_OFFEN} Zeilen in ${ADSLOTS} §13.2 stehen auf 'offen (Konto)' — noch nicht ablesbar (Zeitgatter)"
  else
    zeile offen "§6.2" "${KONTO_OFFEN} Zeilen in ${ADSLOTS} §13.2 stehen auf 'offen (Konto)'" \
      "AdSense > Berichte > Anzeigenbloecke, Zeitraum ab 16.10.2026: aktive Betrachtungszeit, Viewability-Quote, Klickrate, RPM in die Spalten 'Viewability-Quote (AdSense)' und 'RPM' eintragen. Unter 40 % greift §13.3: Belegung auf paused, nicht loeschen. Steht §6.0 auf [x], geht es ohne Oberflaeche: artisan ads:adsense-konto --report (Dimension AD_UNIT_NAME, Spalten Viewability und RPM)."
  fi
else
  zeile ok "§6.2" "${ADSLOTS} §13.2 hat keine kontogebundene Luecke mehr"
fi

# ------------------------------- §6.3: eigene Anzeigenbloecke je Pilot-Anker
[ "$KURZ" -eq 1 ] || echo
[ "$KURZ" -eq 1 ] || echo "§6.3 — eigene Anzeigenbloecke fuer guide_in_article_1 und search_in_feed_3"
if [ "$OHNE_PROD" -eq 1 ]; then
  zeile unklar "§6.3" "Produktion auf Wunsch uebersprungen (--ohne-prod)"
else
  if ! remote true 2>/dev/null; then
    echo "Produktion (${SSH_HOST}) nicht erreichbar." >&2
    exit 65
  fi
  SLOT_GUIDE=$(als_app "${PHP_BIN} artisan ads:resolve ${PILOT_TENANT} guide_in_article_1 all" | sed -n 's/.*data-ad-slot="\([0-9]*\)".*/\1/p' | head -1)
  SLOT_SEARCH=$(als_app "${PHP_BIN} artisan ads:resolve ${PILOT_TENANT} search_in_feed_3 all" | sed -n 's/.*data-ad-slot="\([0-9]*\)".*/\1/p' | head -1)
  if [ -z "$SLOT_GUIDE" ] || [ -z "$SLOT_SEARCH" ]; then
    zeile unklar "§6.3" "ads:resolve liefert kein Fragment (Belegung aus? Einwilligung?) — von Hand nachsehen"
  elif [ "$SLOT_GUIDE" = "$SLOT_SEARCH" ]; then
    zeile offen "§6.3" "beide Anker laufen auf demselben Slot ${SLOT_GUIDE}${SLOT_GUIDE:+ }— der Report kann sie nicht trennen" \
      "Im Konto zwei Bloecke anlegen (Display, responsive), dann Panel > Werbemittel > je Anker ein eigenes Werbemittel mit eigener Slot-ID (config_json.slot_id). Nachweis: ads:resolve ${PILOT_TENANT} guide_in_article_1 all bzw. ... search_in_feed_3 all zeigt die neue ID. Danach beginnt das Report-Fenster fuer diese zwei Anker neu."
  elif [ "$SLOT_GUIDE" = "$GETEILTER_SLOT" ] || [ "$SLOT_SEARCH" = "$GETEILTER_SLOT" ]; then
    zeile offen "§6.3" "ein Anker haengt noch am geteilten Slot ${GETEILTER_SLOT} (guide ${SLOT_GUIDE}, search ${SLOT_SEARCH})" \
      "Fehlenden Block im Konto anlegen und die Slot-ID am Werbemittel eintragen."
  else
    zeile ok "§6.3" "eigene Slots je Anker (guide ${SLOT_GUIDE}, search ${SLOT_SEARCH})"
  fi
fi

# ------------------------------------- §6.4: die sieben Portale ohne Altbestand
[ "$KURZ" -eq 1 ] || echo
[ "$KURZ" -eq 1 ] || echo "§6.4 — Site-Status und Schalter der sieben Portale ohne Altbestand"
SIEBEN="30 elektrikerportal.com
49 sanitaerfinder.com
51 firmenfreund.net
54 klempner.firmenfreund.de
55 energieberaterportal.net
57 speditionportal.com
58 schluesseldienstportal.com"

if [ "$OHNE_PROD" -eq 1 ]; then
  zeile unklar "§6.4" "Produktion auf Wunsch uebersprungen (--ohne-prod)"
else
  CHECK=$(als_app "${PHP_BIN} artisan ads:golive:check --ohne-alt")
  AUS=0
  AN=0
  while read -r id dom; do
    [ -n "$id" ] || continue
    ROW=$(printf '%s\n' "$CHECK" | grep -E "^\| *${id} \|" || true)
    FREI=$(printf '%s\n' "$ROW" | awk -F'|' '{gsub(/ /,"",$5); print $5}')
    CODE=$(curl -s -o /dev/null -m 15 -w '%{http_code}' "https://${dom}/ads.txt" || echo '---')
    case "$FREI" in
      nein) AUS=$((AUS + 1)); [ "$KURZ" -eq 1 ] || printf '      %-3s %-28s ads_enabled=nein  /ads.txt %s\n' "$id" "$dom" "$CODE" ;;
      ja)   AN=$((AN + 1));   [ "$KURZ" -eq 1 ] || printf '      %-3s %-28s ads_enabled=JA    /ads.txt %s\n' "$id" "$dom" "$CODE" ;;
      *)    [ "$KURZ" -eq 1 ] || printf '      %-3s %-28s Stand unklar      /ads.txt %s\n' "$id" "$dom" "$CODE" ;;
    esac
  done <<< "$SIEBEN"

  if [ "$AUS" -gt 0 ]; then
    zeile offen "§6.4" "${AUS} der sieben stehen noch fail-closed auf ads_enabled = nein (${AN} freigegeben)" \
      "Je Domain AdSense > Sites > Status pruefen — 30 im ZWEITEN Konto ca-pub-1901800422608466. 'Bereit' => Schalter im Panel auf ja, danach ads:golive:check und bash scripts/ads-txt-sweep.sh (genau eine Google-Zeile). Status fehlt oder 'Erforderliche Massnahme' => aus lassen, Domain im Konto hinzufuegen. Bei 49 genuegt der Schalter, die Publisher-ID steht seit 16.10.2026 drin. Steht §6.0 auf [x], liefert artisan ads:adsense-konto den state je Domain (accounts.sites.list) fuer beide Konten."
  else
    zeile ok "§6.4" "alle sieben entschieden und freigegeben"
  fi
fi

if [ "${#OFFEN[@]}" -eq 0 ]; then
  [ "$KURZ" -eq 1 ] || { echo; echo "Alle vier Punkte der Kontositzung sind erledigt."; }
  exit 0
fi

echo
echo "NAECHSTER HANDGRIFF"
printf '  %s\n' "${OFFEN[0]}"
if [ "${#OFFEN[@]}" -gt 1 ] && [ "$KURZ" -eq 0 ]; then
  echo
  echo "Danach noch offen:"
  for i in $(seq 1 $(( ${#OFFEN[@]} - 1 ))); do printf '  %s\n' "${OFFEN[$i]}"; done
fi
exit 70
