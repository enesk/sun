#!/usr/bin/env bash
# Nenner der Viewability-Quote auf der Produktion: Stand und erste Quote
# (Ticket #52 aus #47, docs/ad-slots-golive.md §6.3, docs/ad-slots.md §13.2/§13.3).
#
# Warum es dieses Skript gibt: an der Quote haengen vier Dinge, die nacheinander
# stimmen muessen, und drei davon sind schnell wieder falsch — eine Migration,
# ein gebautes Bundle, eintreffende Beacons und ein Fenster, das vollstaendig
# NACH dem Deploy liegt. Faellt eines aus, steht im Widget ein Gedankenstrich,
# und man sieht ihm nicht an, welches. Jeder Anlauf wiederholte bisher
# denselben Satz lesender Befehle gegen die Produktion. Hier in einem Aufruf,
# in der Reihenfolge der Checkliste.
#
# Das Zeitgatter ist der Kern, und es gatert in die unerwartete Richtung: Tage
# vor dem Deploy bringen Zeilen mit Sichtkontakten, aber deliveries = 0. Ueber
# das Fenster summiert landen sie im Zaehler und nicht im Nenner, das
# Verhaeltnis steigt ueber 1, und AdImpressionStats::quote() kappt bei 100 %.
# Eine zu frueh abgelesene Quote ist also ZU HOCH, meist glatte 100 % — und
# genau das loest unter §13.3 den netzweiten Rollout aus. Belastbar ist sie
# erst, wenn das gewaehlte Fenster vollstaendig hinter dem Deploy liegt; das
# Skript rechnet den Tag aus und verweigert bis dahin die Freigabe.
#
# Das Skript aendert NICHTS an Code, Schaltern oder Belegungen. Es liest, und
# es ruft `ads:aggregate-impressions` — der verdichtet nur abgeschlossene
# Stunden aus dem Puffer ins Tagesaggregat und ist derselbe Lauf, den der
# Scheduler stuendlich macht.
#
# Aufruf:
#   scripts/ads-viewability-quote.sh            # Stand + Quote, Fenster 7 Tage
#   scripts/ads-viewability-quote.sh --tage=30  # Fenster 30 Tage
#   scripts/ads-viewability-quote.sh --ab=2026-10-17  # Fenster ab diesem Tag
#   scripts/ads-viewability-quote.sh --kurz     # nur der naechste Handgriff
#   scripts/ads-viewability-quote.sh --stunde   # saubere Zwischenablesung
#
# --ab= setzt den Fensteranfang statt einer der beiden Breiten (Ticket #66).
# Damit ist der erste ganze Tag hinter dem Deploy schon am Folgetag ablesbar,
# statt auf ein volles 7-Tage-Fenster zu warten. Das Zeitgatter prueft dann
# dieses Datum; die Grundmenge ist klein, also §13.3 nur mit Vermerk anwenden.
#
# --stunde liest die LAUFENDE Stunde direkt aus `ad_impression_buffer`. Das ist
# die einzige Quote, die vor dem Reifedatum nicht verfaelscht ist — sofern die
# Stunde vollstaendig nach dem Deploy begonnen hat, hat jede Zeile darin einen
# Nenner. Sie ist eine Stichprobe und keine Entscheidungsgrundlage fuer §13.3
# (kleine Grundmenge, eine Tageszeit), taugt aber als Beleg, dass die Kette
# Browser -> Beacon -> Puffer wirklich zaehlt.
#
# Einstellbar: SUN_SSH_HOST (Vorgabe sun), APP_DIR, APP_USER, PHP_BIN,
#              REMOTE_TIMEOUT (Vorgabe 180 s je artisan-Aufruf).
#
# Exit-Codes:
#   0   Quote ablesbar — ein ganzes Fenster liegt nach dem Deploy
#   70  Nenner laeuft, Fenster noch nicht reif (Datum steht in der Ausgabe)
#   71  Vorbedingung fehlt (Migration, Bundle oder Beacons)
#   65  Produktion nicht erreichbar

set -euo pipefail

SSH_HOST="${SUN_SSH_HOST:-sun}"
APP_DIR="${APP_DIR:-/home/sanitaerfinden/htdocs/sanitaerfinden.dev}"
APP_USER="${APP_USER:-sanitaerfinden}"
PHP_BIN="${PHP_BIN:-/usr/bin/php8.4}"
REMOTE_TIMEOUT="${REMOTE_TIMEOUT:-180}"

# Deploy des Nenners auf der Produktion (Assets gebaut, ab hier melden die
# Browser `event=delivered`). Quelle: docs/ad-slots-golive.md §6.3.
NENNER_AB_UTC="2026-10-16T13:25:00Z"
NENNER_AB_TAG="2026-10-16"

TAGE=7
AB=""
KURZ=0
STUNDE=0
while [ $# -gt 0 ]; do
  case "$1" in
    --tage=*) TAGE="${1#*=}"; shift ;;
    --ab=*) AB="${1#*=}"; shift ;;
    --kurz) KURZ=1; shift ;;
    --stunde) STUNDE=1; shift ;;
    -h|--help) sed -n '2,44p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done

remote() { ssh -o BatchMode=yes -o ConnectTimeout=20 "$SSH_HOST" "$@"; }
# `timeout` steht remote vor jedem artisan-Aufruf: `ads:impressionen-abnahme`
# lief am 16.10. als Waise auf der Produktion weiter, nachdem die Sitzung weg
# war, und musste in einer Lastdiagnose beendet werden (#59).
als_app() { remote "su -s /bin/bash ${APP_USER} -c 'HOME=/home/${APP_USER}; cd ${APP_DIR} && timeout ${REMOTE_TIMEOUT} $1'" 2>/dev/null | grep -v '^PHP Deprecated' || true; }

zeile() { # zeile <ok|offen|unklar> <text>
  [ "$KURZ" -eq 1 ] && return 0
  case "$1" in
    ok)     printf '  [x] %s\n' "$2" ;;
    offen)  printf '  [ ] %s\n' "$2" ;;
    unklar) printf '  [?] %s\n' "$2" ;;
  esac
}

if ! remote true 2>/dev/null; then
  echo "Produktion (${SSH_HOST}) nicht erreichbar." >&2
  exit 65
fi

[ "$KURZ" -eq 1 ] || {
  echo "Nenner der Viewability-Quote — Stand $(date -u '+%d.%m.%Y %H:%M UTC')"
  echo "Checkliste: docs/ad-slots-golive.md §6.3   Regel: docs/ad-slots.md §13.3"
  echo
}

FEHLT=0

# -------------------------------------------------- 1. Migrationen (Central)
MIG=$(als_app "${PHP_BIN} artisan migrate:status" | grep -c 'add_deliveries_to_ad_impression_tables .*Ran' || true)
IDX=$(als_app "${PHP_BIN} artisan migrate:status" | grep -c 'add_covering_indexes_to_ad_impression_daily_table .*Ran' || true)
if [ "$MIG" -ge 1 ] && [ "$IDX" -ge 1 ]; then
  zeile ok "Migrationen 000009 (#44) und 000010 (#47) sind auf der Central-DB gelaufen"
else
  zeile offen "Migration fehlt — ${PHP_BIN} artisan migrate --force (000010 setzt 000009 voraus)"
  FEHLT=1
fi

# ------------------------------------------------------ 2. gebautes Bundle
# ads.js liegt nicht in einem eigenen Entry, sondern im gemeinsamen Chunk, den
# sich resources/js/app.js und das sun-v2-Bundle teilen — darum ueber alle
# gebauten Dateien suchen und nicht ueber die manifest-Eintraege.
BUNDLE=$(remote "grep -l 'event=delivered\\|delivered' ${APP_DIR}/public/build/assets/*.js 2>/dev/null | head -1" || true)
if [ -n "$BUNDLE" ]; then
  zeile ok "gebautes Bundle kennt die Meldung delivered ($(basename "$BUNDLE"))"
else
  zeile offen "kein gebautes Bundle mit der Meldung delivered — npm run build auf der Produktion"
  FEHLT=1
fi

# ------------------------------------------------- 3. Beacons kommen an
# Zwei Quellen, weil der Job nur ABGESCHLOSSENE Stunden verdichtet: direkt nach
# dem Deploy steht der Nenner noch vollstaendig im Stundenpuffer und im
# Tagesaggregat auf Null. Beides als "Beacons kommen an" zu werten ist richtig
# — ohne den Puffer meldet das Skript in der ersten Stunde faelschlich Stille.
als_app "${PHP_BIN} artisan ads:aggregate-impressions" >/dev/null || true
# Base64, weil ein einzelnes Anfuehrungszeichen das Quoting von
# ssh -> su -c '…' zerlegt (gilt fuer jede PHP-Nutzlast auf diesem Weg).
PUFFER_B64=$(printf '%s' 'echo (int) DB::table("ad_impression_buffer")->sum("deliveries");' | base64 | tr -d '\n')
PUFFER=$(als_app "${PHP_BIN} artisan tinker --execute=\"\$(echo ${PUFFER_B64} | base64 -d)\"" | tr -dc '0-9')
PUFFER="${PUFFER:-0}"
if [ -n "$AB" ]; then
  FENSTER_ARG="--ab=${AB}"
else
  FENSTER_ARG="--tage=${TAGE}"
fi
ZAHLEN=$(als_app "${PHP_BIN} artisan ads:impressionen-abnahme ${FENSTER_ARG}")
AUSGELIEFERT=$(printf '%s\n' "$ZAHLEN" | sed -n 's/^Ausgeliefert: \([0-9]*\).*/\1/p' | head -1)
AUSGELIEFERT="${AUSGELIEFERT:-0}"
if [ "$AUSGELIEFERT" -gt 0 ]; then
  zeile ok "Nenner laeuft: ${AUSGELIEFERT} ausgelieferte Fragmente im Fenster (Puffer der laufenden Stunde: ${PUFFER})"
elif [ "$PUFFER" -gt 0 ]; then
  zeile ok "Nenner laeuft: ${PUFFER} ausgelieferte Fragmente im Stundenpuffer, noch nicht verdichtet"
else
  zeile offen "kein ausgeliefertes Fragment — Beacons pruefen (POST /_ads/impressions)"
  FEHLT=1
fi

# ------------------------------------------------------------ 4. Zeitgatter
# Das Fenster beginnt TAGE-1 Tage vor heute. Reif ist es, wenn dieser Anfang
# nicht mehr vor dem Deploytag liegt.
HEUTE=$(date -u +%Y-%m-%d)
if [ -n "$AB" ]; then
  # Mit --ab= steht der Anfang fest. Reif ist er ab dem Folgetag: der
  # angegebene Tag muss als GANZER Tag vorbei sein, sonst waere es wieder eine
  # angefangene Grundmenge.
  FENSTER_AB="$AB"
  FENSTER_NAME="Fenster ab ${AB}"
  REIF_AB=$(date -u -d "${NENNER_AB_TAG} +1 days" +%Y-%m-%d 2>/dev/null \
    || date -u -j -v+1d -f %Y-%m-%d "${NENNER_AB_TAG}" +%Y-%m-%d)
else
  FENSTER_AB=$(date -u -d "${HEUTE} -$((TAGE - 1)) days" +%Y-%m-%d 2>/dev/null \
    || date -u -j -v-$((TAGE - 1))d -f %Y-%m-%d "${HEUTE}" +%Y-%m-%d)
  FENSTER_NAME="Fenster ${TAGE} Tage"
  REIF_AB=$(date -u -d "${NENNER_AB_TAG} +${TAGE} days" +%Y-%m-%d 2>/dev/null \
    || date -u -j -v+${TAGE}d -f %Y-%m-%d "${NENNER_AB_TAG}" +%Y-%m-%d)
fi

if [ "$FENSTER_AB" \> "$NENNER_AB_TAG" ]; then
  ZEITGATTER=offen
  zeile ok "${FENSTER_NAME} (ab ${FENSTER_AB}) liegt vollstaendig nach dem Deploy ${NENNER_AB_UTC}"
  [ -n "$AB" ] && [ ! "$FENSTER_AB" \< "$HEUTE" ] && zeile unklar "ab ${AB} ist der laufende Tag — erst morgen ist er ein ganzer Tag"
else
  ZEITGATTER=zu
  zeile unklar "${FENSTER_NAME} (ab ${FENSTER_AB}) enthaelt noch Tage ohne Nenner — belastbar ab ${REIF_AB}"
fi

[ "$KURZ" -eq 1 ] || {
  echo
  printf '%s\n' "$ZAHLEN"
}

# ------------------------------- Zwischenablesung aus der laufenden Stunde
if [ "$STUNDE" -eq 1 ]; then
  STUNDE_PHP='
$z = DB::table("ad_impression_buffer")
    ->selectRaw("slot_key, MIN(hour) h, SUM(impressions) i, SUM(deliveries) d")
    ->groupBy("slot_key")->orderByDesc("d")->get();
if ($z->isEmpty()) { echo "Stundenpuffer leer.\n"; return; }
printf("Laufende Stunde ab %s UTC — nur Zeilen MIT Nenner sind aussagekraeftig.\n\n", $z->first()->h);
printf("%-34s %9s %12s %8s\n", "Anker", "sichtbar", "ausgeliefert", "Quote");
$si = 0; $sd = 0;
foreach ($z as $r) {
    $si += $r->i; $sd += $r->d;
    printf("%-34s %9d %12d %8s\n", $r->slot_key, $r->i, $r->d, $r->d > 0 ? round($r->i / $r->d * 100) . " %" : "—");
}
printf("%-34s %9d %12d %8s\n", "SUMME", $si, $sd, $sd > 0 ? round($si / $sd * 100) . " %" : "—");
'
  STUNDE_B64=$(printf '%s' "$STUNDE_PHP" | base64 | tr -d '\n')
  echo
  echo "Zwischenablesung aus ad_impression_buffer (laufende Stunde):"
  als_app "${PHP_BIN} artisan tinker --execute=\"\$(echo ${STUNDE_B64} | base64 -d)\""
  cat <<'EOF'

Quote ueber 100 % heisst: die Stunde hat vor dem Deploy des Nenners begonnen.
Dann zaehlen Sichtkontakte ohne zugehoeriges delivered mit — die naechste volle
Stunde lesen, nicht diese.
EOF
fi

echo
if [ "$FEHLT" -eq 1 ]; then
  echo "NAECHSTER HANDGRIFF: die offene Vorbedingung oben erledigen (docs/ad-slots-golive.md §6.3)."
  exit 71
fi
if [ "$ZEITGATTER" = zu ]; then
  cat <<EOF
NAECHSTER HANDGRIFF: ab ${REIF_AB} denselben Aufruf wiederholen — oder frueher
mit --ab=<erster ganzer Tag nach dem Deploy> (#66), dann aber mit Vermerk auf
die kleine Grundmenge. Danach die Spalte
"Sichtbar" je Anker in docs/ad-slots.md §13.2 eintragen, die Ausgabe nach
docs/messungen/werbeplaetze-golive/ legen und §13.3 anwenden
(unter 40 % => Belegung auf paused, ueber 70 % => applies_to_all_tenants).
Bis dahin ist jede abgelesene Quote ZU HOCH (gekappt auf 100 %): das Fenster
enthaelt Tage, an denen nur Sichtkontakte gezaehlt wurden — die stehen im
Zaehler, ihr Nenner fehlt.
EOF
  exit 70
fi
echo "Quote ist ablesbar — Spalte \"Sichtbar\" nach docs/ad-slots.md §13.2 uebertragen und §13.3 anwenden."
exit 0
