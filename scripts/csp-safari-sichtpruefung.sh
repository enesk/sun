#!/usr/bin/env bash
# Stellt den Messaufbau fuer die CSP-Sichtpruefung in Safari her (#29, Rest
# aus #28) und raeumt ihn danach restlos ab.
#
# Warum es dieses Skript gibt: in Chrome ist die CSP vollstaendig gemessen
# (docs/messungen/csp-browser-messung-2026-10-08.md). Offen bleibt allein, ob
# WebKit das Nonce an den geklonten Werbe-Schnipseln aus resources/js/ads.js
# ebenso akzeptiert. Automatisieren laesst sich das lokal nicht — safaridriver
# verlangt "Allow remote automation" in den Safari-Einstellungen, was nur ein
# Mensch am Rechner freigibt. Eine Sichtpruefung mit offenem Web-Inspektor
# reicht aber aus, und alles davor ist Werkzeugarbeit.
#
# Der Aufbau ist nicht beliebig: der Nonce-Pfad (<template data-ad-code> ->
# neuesSkript()) greift nur an den Lazy-Positionen der Komponente
# (App\View\Components\AdSlot::LAZY_POSITIONS: listing_detail_after_description
# und footer_above). Die Themes default/starter liefern beide, sun-v2 nur
# footer_above und das ausschliesslich auf Ratgeber-Seiten mit Inhalten.
# Deshalb:
#   * Mess-Portal ist das Default-Theme-Portal (Tenant 1, sanitaer.test) —
#     dort steht footer_above im Layout, also auf jeder Seite,
#   * den echten AdSense-Code (ca-pub-…) leiht der Mess-Slot sich vom
#     sun-v2-Portal (Tenant 19); die Platzhalter-Slots von Tenant 1 enthalten
#     nur Text und laden nichts nach.
#
# Der Handgriff, der bleibt: Safari oeffnen, die ausgegebene URL laden, mit
# geoeffneter Konsole bis zum Seitenende scrollen, den Gegenprobe-Schnipsel
# (liegt dann in der Zwischenablage) einfuegen, Ergebnis im Protokoll
# nachtragen.
#
# Aufruf:
#   scripts/csp-safari-sichtpruefung.sh              # Aufbau, warten, abraeumen
#   scripts/csp-safari-sichtpruefung.sh --webkit     # Aufbau, in WebKit messen,
#                                                    # abraeumen (ohne Safari)
#   scripts/csp-safari-sichtpruefung.sh --pruefen    # nur Vorbedingungen
#   scripts/csp-safari-sichtpruefung.sh --aufraeumen # Abbau nachholen
#
# --webkit misst dieselben zwei Seiten ohne Handgriff: nicht in Safari, aber
# in dessen Engine. Dafuer liegt ein Playwright-WebKit in einem Temp-Ordner
# ausserhalb des Projekts (WEBKIT_WURZEL, Vorgabe /tmp/webkit-messung):
#   mkdir -p /tmp/webkit-messung && cd /tmp/webkit-messung && npm init -y \
#     && npm i playwright \
#     && PLAYWRIGHT_BROWSERS_PATH=/tmp/webkit-messung/browsers \
#        npx playwright install webkit
# Gemessen wird mit scripts/csp-webkit-messung.mjs, Rohdaten nach AUS.
#
# Veraendert vier Dinge und stellt alle vier selbst zurueck:
#   1. /etc/hosts: "127.0.0.1 sanitaer.test" (nur falls die Zeile fehlt,
#      markiert mit "csp-safari-sichtpruefung" — braucht sudo),
#   2. .env.t29 (Kopie der .env mit CSP_MODE, wird geloescht),
#   3. einen zusaetzlichen ad_slot footer_above bei Tenant 1 (wird geloescht),
#   4. nichts sonst — die bestehenden Slots bleiben, wie sie sind.
# Bricht der Lauf ab, holt --aufraeumen den Abbau aus der Zustandsdatei nach.
#
# Einstellbar: AUS (Rohdaten des --webkit-Laufs,
# docs/messungen/csp-webkit-nonce-<Datum>.json), WEBKIT_WURZEL
# (/tmp/webkit-messung), PORT (8129), MESS_TENANT (1), MESS_DOMAIN (sanitaer.test),
# CODE_TENANT (19, liefert den echten Werbecode), MODUS_CSP (enforce; mit
# report wird nur gemeldet statt geblockt), HOSTS (auto; mit HOSTS=aus bleibt
# /etc/hosts unangetastet, etwa bei dnsmasq oder Valet).
#
# Exit-Codes:
#   0  Aufbau stand und wurde abgeraeumt (bzw. --pruefen/--aufraeumen in Ordnung)
#   1  Vorbedingung fehlt (kein Build, keine .env, Tenant/Domain/Code unbekannt)
#   2  Aufbau fehlgeschlagen (Server antwortet nicht, keine Werbe-Vorlage im HTML)

set -euo pipefail

cd "$(dirname "$0")/.."

PORT="${PORT:-8129}"
MESS_TENANT="${MESS_TENANT:-1}"
MESS_DOMAIN="${MESS_DOMAIN:-sanitaer.test}"
CODE_TENANT="${CODE_TENANT:-19}"
MODUS_CSP="${MODUS_CSP:-enforce}"
HOSTS="${HOSTS:-auto}"
WEBKIT_WURZEL="${WEBKIT_WURZEL:-/tmp/webkit-messung}"
AUS="${AUS:-docs/messungen/csp-webkit-nonce-$(date +%Y-%m-%d).json}"
UMGEBUNG="t29"
ENVDATEI=".env.${UMGEBUNG}"
ARBEIT="/tmp/csp-safari-sichtpruefung"
ZUSTAND="${ARBEIT}/zustand"
HOSTS_MARKE="csp-safari-sichtpruefung"

mkdir -p "$ARBEIT"

MODUS="aufbau"
while [ $# -gt 0 ]; do
  case "$1" in
    --pruefen) MODUS="pruefen"; shift ;;
    --webkit) MODUS="webkit"; shift ;;
    --aufraeumen) MODUS="aufraeumen"; shift ;;
    -h|--help) sed -n '2,70p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done

meldung() { printf '%s\n' "$*"; }
schritt() { printf '\n== %s\n' "$*"; }

zustand_setzen() { # schluessel wert
  touch "$ZUSTAND"
  { grep -v "^$1=" "$ZUSTAND" || true; } > "${ZUSTAND}.neu"
  printf '%s=%s\n' "$1" "$2" >> "${ZUSTAND}.neu"
  mv "${ZUSTAND}.neu" "$ZUSTAND"
}

zustand_lesen() { # schluessel
  [ -f "$ZUSTAND" ] || return 0
  sed -n "s/^$1=//p" "$ZUSTAND" | tail -1
}

# Fuehrt ein PHP-Schnipsel im Tenant-Kontext aus. tinker mit Dateiargument
# haengt, deshalb der require-Umweg mit geschlossener Standardeingabe.
tinker() { # datei
  php artisan tinker --execute="require \"$1\";" < /dev/null
}

pruefe_vorbedingungen() {
  local fehlt=0

  if [ -f public/build/manifest.json ]; then
    meldung "  Build vorhanden (public/build/manifest.json)."
  else
    meldung "  FEHLT: public/build/ — erst 'npm run build' laufen lassen." >&2
    fehlt=1
  fi

  if [ -f public/hot ]; then
    meldung "  WARNUNG: public/hot existiert — der Vite-Dev-Server laeuft und" >&2
    meldung "           liefert andere Skriptquellen als die Produktion." >&2
  fi

  if [ -f .env ]; then
    meldung "  .env vorhanden."
  else
    meldung "  FEHLT: .env — ohne Datenbankzugang kein Portal." >&2
    fehlt=1
  fi

  cat > "${ARBEIT}/pruefen.php" <<PHP
<?php
\$mess = \App\Models\Tenant::find(${MESS_TENANT});
\$code = \App\Models\Tenant::find(${CODE_TENANT});
if (! \$mess || ! \$code) { echo "TENANT_FEHLT\n"; return; }
echo "DOMAIN=".(\$mess->domain ?? '-')."\n";
tenancy()->initialize(\$code);
\$werbecode = (string) \App\Models\Portal\AdSlot::query()
    ->where('code', 'like', '%googlesyndication%')
    ->value('code');
echo "CODE_LAENGE=".strlen(\$werbecode)."\n";
tenancy()->end();
tenancy()->initialize(\$mess);
echo "SLOTS=".\App\Models\Portal\AdSlot::count()."\n";
echo "LAZY_VORHANDEN=".\App\Models\Portal\AdSlot::where('position', 'footer_above')->count()."\n";
\$firma = \App\Models\Portal\Company::query()->whereNotNull('slug')->orderByDesc('id')->first();
echo "FIRMA=".(\$firma ? \$firma->id.'-'.\$firma->slug : '-')."\n";
PHP
  local ausgabe
  ausgabe="$(tinker "${ARBEIT}/pruefen.php" 2>&1)" || true
  printf '%s\n' "$ausgabe" > "${ARBEIT}/pruefen.out"

  if printf '%s' "$ausgabe" | grep -q 'TENANT_FEHLT'; then
    meldung "  FEHLT: Tenant ${MESS_TENANT} oder ${CODE_TENANT} gibt es in dieser Datenbank nicht." >&2
    return 1
  fi

  local domain codelaenge firma lazy
  domain="$(printf '%s' "$ausgabe" | sed -n 's/^DOMAIN=//p' | tail -1)"
  codelaenge="$(printf '%s' "$ausgabe" | sed -n 's/^CODE_LAENGE=//p' | tail -1)"
  firma="$(printf '%s' "$ausgabe" | sed -n 's/^FIRMA=//p' | tail -1)"
  lazy="$(printf '%s' "$ausgabe" | sed -n 's/^LAZY_VORHANDEN=//p' | tail -1)"

  meldung "  Mess-Portal: Tenant ${MESS_TENANT} auf ${domain:-?}, Firmenseite /${firma}."
  meldung "  Werbecode von Tenant ${CODE_TENANT}: ${codelaenge:-0} Zeichen."

  if [ "$domain" != "$MESS_DOMAIN" ]; then
    meldung "  FEHLT: Tenant ${MESS_TENANT} haengt an '${domain}', erwartet '${MESS_DOMAIN}'." >&2
    fehlt=1
  fi
  if [ "${codelaenge:-0}" = "0" ]; then
    meldung "  FEHLT: Tenant ${CODE_TENANT} hat keinen AdSense-Code — ohne echten" >&2
    meldung "         Schnipsel laedt nichts nach und die Pruefung beweist nichts." >&2
    fehlt=1
  fi
  if [ "${lazy:-0}" != "0" ]; then
    meldung "  WARNUNG: Tenant ${MESS_TENANT} hat schon einen footer_above-Slot." >&2
    meldung "           Der Aufbau fasst ihn nicht an und legt keinen zweiten an." >&2
  fi

  if [ "$MODUS" = "webkit" ]; then
    if [ -d "${WEBKIT_WURZEL}/node_modules/playwright" ] && [ -d "${WEBKIT_WURZEL}/browsers" ]; then
      meldung "  Playwright-WebKit in ${WEBKIT_WURZEL}."
    else
      meldung "  FEHLT: Playwright-WebKit in ${WEBKIT_WURZEL} — Einrichtung siehe --help." >&2
      fehlt=1
    fi
  fi

  if grep -qE "^[^#]*[[:space:]]${MESS_DOMAIN}([[:space:]]|$)" /etc/hosts 2>/dev/null; then
    meldung "  /etc/hosts kennt ${MESS_DOMAIN}."
  else
    meldung "  /etc/hosts kennt ${MESS_DOMAIN} noch nicht — der Aufbau legt die Zeile per sudo an."
  fi

  return "$fehlt"
}

aufraeumen() {
  schritt "Abbau"

  local pid
  pid="$(zustand_lesen SERVER_PID)"
  if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then
    kill "$pid" 2>/dev/null || true
    meldung "  Server ${pid} beendet."
  fi

  local slot
  slot="$(zustand_lesen MESS_SLOT)"
  if [ -n "$slot" ]; then
    cat > "${ARBEIT}/slot-weg.php" <<PHP
<?php
tenancy()->initialize(\App\Models\Tenant::find(${MESS_TENANT}));
\App\Models\Portal\AdSlot::whereKey('${slot}')->delete();
echo "SLOTS_JETZT=".\App\Models\Portal\AdSlot::count()."\n";
PHP
    tinker "${ARBEIT}/slot-weg.php" 2>&1 | sed -n 's/^/  /p'
    zustand_setzen MESS_SLOT ""
  fi

  if [ -f "$ENVDATEI" ]; then
    rm -f "$ENVDATEI"
    meldung "  ${ENVDATEI} geloescht."
  fi

  if [ -n "$(zustand_lesen HOSTS_ANGELEGT)" ]; then
    if sudo sed -i '' "/${HOSTS_MARKE}/d" /etc/hosts 2>/dev/null; then
      meldung "  /etc/hosts-Zeile entfernt."
      zustand_setzen HOSTS_ANGELEGT ""
    else
      meldung "  /etc/hosts-Zeile bitte von Hand entfernen (markiert mit '${HOSTS_MARKE}')." >&2
    fi
  fi

  php artisan config:clear > /dev/null 2>&1 || true
  meldung "  Fertig."
}

case "$MODUS" in
  pruefen)
    schritt "Vorbedingungen"
    pruefe_vorbedingungen || exit 1
    meldung ""
    meldung "Alles bereit. Aufbau starten: scripts/csp-safari-sichtpruefung.sh"
    exit 0
    ;;
  aufraeumen)
    aufraeumen
    exit 0
    ;;
esac

schritt "Vorbedingungen"
pruefe_vorbedingungen || exit 1

FIRMA="$(sed -n 's/^FIRMA=//p' "${ARBEIT}/pruefen.out" | tail -1)"

trap 'aufraeumen' EXIT

schritt "Namensaufloesung"
if [ "$HOSTS" = "aus" ]; then
  meldung "  HOSTS=aus — /etc/hosts bleibt unberuehrt; ${MESS_DOMAIN} muss"
  meldung "  anderswo auf 127.0.0.1 zeigen."
elif grep -qE "^[^#]*[[:space:]]${MESS_DOMAIN}([[:space:]]|$)" /etc/hosts 2>/dev/null; then
  meldung "  ${MESS_DOMAIN} steht schon in /etc/hosts — unveraendert."
else
  meldung "  Lege '127.0.0.1 ${MESS_DOMAIN}' in /etc/hosts an (sudo)."
  printf '127.0.0.1 %s # %s\n' "$MESS_DOMAIN" "$HOSTS_MARKE" | sudo tee -a /etc/hosts > /dev/null
  zustand_setzen HOSTS_ANGELEGT 1
fi

schritt "Mess-Umgebung ${ENVDATEI}"
cp .env "$ENVDATEI"
for zeile in "APP_ENV=${UMGEBUNG}" 'APP_DEBUG=false' "CSP_MODE=${MODUS_CSP}"; do
  schluessel="${zeile%%=*}"
  if grep -q "^${schluessel}=" "$ENVDATEI"; then
    sed -i '' "s|^${schluessel}=.*|${zeile}|" "$ENVDATEI"
  else
    printf '%s\n' "$zeile" >> "$ENVDATEI"
  fi
done
meldung "  CSP_MODE=${MODUS_CSP}, APP_DEBUG=false."
php artisan config:clear > /dev/null 2>&1 || true

schritt "Lazy-Werbeplatz mit echtem Code anlegen"
cat > "${ARBEIT}/slot-an.php" <<PHP
<?php
tenancy()->initialize(\App\Models\Tenant::find(${CODE_TENANT}));
\$code = (string) \App\Models\Portal\AdSlot::query()
    ->where('code', 'like', '%googlesyndication%')
    ->value('code');
tenancy()->end();
tenancy()->initialize(\App\Models\Tenant::find(${MESS_TENANT}));
if (\App\Models\Portal\AdSlot::where('position', 'footer_above')->exists()) {
    echo "VORHANDEN=1\n";
    return;
}
\$slot = \App\Models\Portal\AdSlot::create([
    'name' => 'Sichtpruefung Safari (#29)',
    'position' => 'footer_above',
    'code' => \$code,
    'is_active' => true,
    'sort_order' => 99,
]);
echo "SLOT=".\$slot->id."\n";
PHP
SLOT_AUSGABE="$(tinker "${ARBEIT}/slot-an.php" 2>&1)"
SLOT="$(printf '%s' "$SLOT_AUSGABE" | sed -n 's/^SLOT=//p' | tail -1)"
if [ -n "$SLOT" ]; then
  zustand_setzen MESS_SLOT "$SLOT"
  meldung "  footer_above angelegt (${SLOT}), wird am Ende geloescht."
else
  meldung "  footer_above war schon da — unveraendert uebernommen."
fi

schritt "Server"
APP_ENV="$UMGEBUNG" PHP_CLI_SERVER_WORKERS=10 \
  php artisan serve --host=127.0.0.1 --port="$PORT" > "${ARBEIT}/serve.log" 2>&1 &
zustand_setzen SERVER_PID "$!"
for _ in $(seq 1 30); do
  if curl -s -o /dev/null -m 3 -H "Host: ${MESS_DOMAIN}" "http://127.0.0.1:${PORT}/"; then break; fi
  sleep 1
done
KOPF="$(curl -s -D - -o "${ARBEIT}/start.html" -m 20 -H "Host: ${MESS_DOMAIN}" "http://127.0.0.1:${PORT}/" || true)"
if ! printf '%s' "$KOPF" | grep -qi '^HTTP/1.1 200'; then
  meldung "  Portal antwortet nicht mit 200 (siehe ${ARBEIT}/serve.log)." >&2
  printf '%s\n' "$KOPF" | head -5 >&2
  exit 2
fi
if printf '%s' "$KOPF" | grep -qi '^content-security-policy'; then
  meldung "  CSP-Header gesetzt (${MODUS_CSP})."
else
  meldung "  WARNUNG: kein CSP-Header — CSP_MODE pruefen." >&2
fi

# Ohne Werbe-Vorlage im HTML waere die Sichtpruefung wertlos: dann setzt
# ads.js nichts ein und die Konsole bleibt auch ohne gueltiges Nonce still.
VORLAGEN="$({ grep -o 'data-ad-code' "${ARBEIT}/start.html" || true; } | wc -l | tr -d ' ')"
WERBECODE="$({ grep -o 'googlesyndication' "${ARBEIT}/start.html" || true; } | wc -l | tr -d ' ')"
if [ "${VORLAGEN:-0}" = "0" ] || [ "${WERBECODE:-0}" = "0" ]; then
  meldung "  Startseite liefert keine template[data-ad-code] mit AdSense-Code" >&2
  meldung "  (${VORLAGEN} Vorlagen, ${WERBECODE} Code-Treffer) — die Pruefung" >&2
  meldung "  wuerde nichts beweisen." >&2
  exit 2
fi
meldung "  Startseite liefert ${VORLAGEN} Werbe-Vorlage(n) zum Einsetzen."

GEGENPROBE="${ARBEIT}/gegenprobe.js"
cat > "$GEGENPROBE" <<'JS'
// In der Safari-Konsole einfuegen, NACHDEM bis zum Seitenende gescrollt wurde.
JSON.stringify({
  adsbygoogleGeladen: !!(window.adsbygoogle && window.adsbygoogle.loaded),
  anzeigenRahmen: document.querySelectorAll('ins.adsbygoogle iframe').length,
  eingesetzteAnzeigen: document.querySelectorAll('ins.adsbygoogle[data-adsbygoogle-status]').length,
  offeneVorlagen: document.querySelectorAll('template[data-ad-code]').length,
  werbeSkripte: [...document.querySelectorAll('script[src*="googlesyndication"]')]
    .map(s => (s.nonce || s.getAttribute('nonce')) ? 'mit Nonce' : 'OHNE NONCE'),
  livewire: typeof window.Livewire,
  alpine: typeof window.Alpine,
}, null, 2)
JS
command -v pbcopy > /dev/null && pbcopy < "$GEGENPROBE" || true

if [ "$MODUS" = "webkit" ]; then
  schritt "Messlauf in WebKit"
  cat > "${ARBEIT}/seiten.json" <<JSON
[
  { "name": "Startseite (footer_above)", "url": "http://${MESS_DOMAIN}:${PORT}/" },
  { "name": "Firmenseite (footer_above + listing_detail)", "url": "http://${MESS_DOMAIN}:${PORT}/${FIRMA}" }
]
JSON
  mkdir -p "$(dirname "$AUS")"
  # Erst die Messung selbst gegenpruefen: eine Seite mit gewollten
  # Verstoessen muss Treffer liefern, sonst beweist "0 Verstoesse" nichts.
  node scripts/csp-webkit-messung.mjs --selbsttest --warten=3000 \
    --playwright="$WEBKIT_WURZEL" > /dev/null
  node scripts/csp-webkit-messung.mjs \
    --seiten="${ARBEIT}/seiten.json" \
    --aus="$AUS" \
    --playwright="$WEBKIT_WURZEL"
  meldung ""
  meldung "  Rohdaten: ${AUS}"
  meldung "  Ergebnis in docs/messungen/csp-browser-messung-2026-10-08.md,"
  meldung "  Abschnitt 7 nachtragen."
  exit 0
fi

cat <<ENDE

== Aufbau steht. Jetzt der Handgriff in Safari

  1. Safari -> Einstellungen -> Erweitert -> "Funktionen fuer Webentwickler"
     einschalten, falls noch aus. (Remote Automation braucht es hierfuer nicht.)
  2. Diese Seiten laden, Web-Inspektor offen (Wahl+Cmd+C), jeweils bis zum
     Seitenende scrollen — der Werbeplatz liegt unter dem Falz:

       http://${MESS_DOMAIN}:${PORT}/
       http://${MESS_DOMAIN}:${PORT}/${FIRMA}

  3. Konsole: keine Meldung mit "Content Security Policy" / "Refused to".
  4. Gegenprobe einfuegen — liegt in der Zwischenablage, sonst
     ${GEGENPROBE}
     Erwartet: adsbygoogleGeladen true, offeneVorlagen 0, werbeSkripte nur
     "mit Nonce", anzeigenRahmen >= 1. (Auf .test liefert AdSense keine echten
     Werbemittel; der Rahmen entsteht trotzdem.)
  5. Ergebnis in docs/messungen/csp-browser-messung-2026-10-08.md nachtragen,
     Abschnitt 7.

ENDE

read -r -p "Fertig? Enter raeumt Mess-Slot, ${ENVDATEI}, /etc/hosts und Server ab. " _ || true
