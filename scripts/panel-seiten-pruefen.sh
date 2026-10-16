#!/usr/bin/env bash
# Panel-Seiten nach einem Deploy per HTTP pruefen (#64).
#
# WARUM ES DIESES SKRIPT GIBT
# ---------------------------
# Am 16.10.2026 war `/admin/werbeplaetze` auf der Produktion **404**, obwohl
# `php artisan route:list` die Route anzeigte. Ursache: ein Filament-
# Komponenten-Cache vom 08.10. (`bootstrap/cache/filament/panels/*.php`).
# Liegt dieser Cache, liest Filament nur die Liste darin und durchsucht die
# Verzeichnisse nicht mehr — eine danach dazugekommene Panel-Seite ist nicht
# registriert. `route:list` baut die Routen dagegen frisch auf und zeigt sie.
#
# Daraus die Regel: **nach einem Deploy jede Panel-Seite per HTTP aufrufen**,
# nicht per `route:list`. Eine Seite ohne Sitzung antwortet mit
# `302 -> /login`; ein **404 heisst "nicht registriert"**.
#
# Behebung eines Treffers: `php artisan filament:optimize` auf der Produktion
# (baut Komponenten- und Icon-Cache neu).
#
# AUFRUF
#
#     scripts/panel-seiten-pruefen.sh                      # gegen die Produktion
#     BASIS=https://sun.test scripts/panel-seiten-pruefen.sh
#
# Einstellbar: BASIS (Vorgabe https://sanitaerfinden.com), ZEITGRENZE (20 s je Abruf).
#
# NICHT GEPRUEFT: das Panel `dashboard`. Seine Seiten liegen hinter
# `/dashboard/{tenant}/…`, und Filament loest den Tenant aus der Sitzung auf —
# ohne Anmeldung antwortet dort **jede** Adresse mit 404, auch eine
# vollstaendig registrierte. Ein anonymer Abruf kann dort nichts belegen; fuer
# dieses Panel gilt die Pruefung im Browser oder ueber den Komponenten-Cache
# (`php artisan ads:golive:check` meldet einen veralteten Cache).
#
# Exit-Codes: 0 alles registriert, 1 mindestens ein 404, 65 kein curl

set -uo pipefail

BASIS="${BASIS:-https://sanitaerfinden.com}"
ZEITGRENZE="${ZEITGRENZE:-20}"

command -v curl >/dev/null || { echo "curl fehlt" >&2; exit 65; }

# Panel admin (ohne Tenant-Praefix) — Seiten und Resource-Listen.
SEITEN=(
    admin
    admin/werbeplaetze
    admin/werbung
    admin/werbemittel
    admin/placements
    admin/ad-slot-distribution
    admin/bot-protection
    admin/verdaechtige-datensaetze
    admin/general-settings
    admin/tenancy-settings
    admin/invoice-settings
    admin/referral-settings
    admin/open-graph-image-settings
    admin/tenant-import-page
    admin/users
    admin/tenants
    admin/sicherheitspruefungen
    # Panel content (ohne Tenant-Praefix)
    content
    content/verlauf
    content/leistung
    content/themen/import
    content/einstellungen
)

FEHLER=0
GEPRUEFT=0

printf 'Basis: %s\n\n' "$BASIS"
printf '%-42s %-6s %s\n' 'Pfad' 'Status' 'Urteil'
printf '%-42s %-6s %s\n' '------------------------------------------' '------' '------'

for pfad in "${SEITEN[@]}"; do
    code="$(curl -sk -m "$ZEITGRENZE" -o /dev/null -w '%{http_code}' "${BASIS}/${pfad}" || echo 000)"
    GEPRUEFT=$((GEPRUEFT + 1))

    case "$code" in
        302|200) urteil='registriert' ;;
        404)     urteil='FEHLT — nicht registriert (Komponenten-Cache?)'; FEHLER=$((FEHLER + 1)) ;;
        000)     urteil='keine Antwort (Zeitgrenze?)'; FEHLER=$((FEHLER + 1)) ;;
        *)       urteil='unerwartet'; FEHLER=$((FEHLER + 1)) ;;
    esac

    printf '%-42s %-6s %s\n' "/${pfad}" "$code" "$urteil"
done

echo
if [ "$FEHLER" -eq 0 ]; then
    echo "${GEPRUEFT} Seiten geprueft, alle registriert."
    exit 0
fi

echo "${GEPRUEFT} Seiten geprueft, ${FEHLER} mit Befund." >&2
echo "Bei 404: auf der Produktion 'php artisan filament:optimize' und erneut pruefen." >&2
exit 1
