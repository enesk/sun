#!/usr/bin/env bash
# Erhebt die Vergleichswerte der Vorwoche fuer Stufe 1 des Bot-Schutz-Rollouts
# (docs/turnstile-golive.md §3, Akzeptanzkriterium 2 von #36).
#
# Warum als Werkzeug: 'turnstile:report --json' gibt es erst ab dem Deploy, die
# Vorwoche muss also aus der Datenbank kommen — und sie muss erhoben sein, BEVOR
# Gruppe A scharf geht, sonst ist "Bot-Registrierungen gestoppt" spaeter gegen
# nichts zu halten.
#
# Gezaehlt wird je Portal und Tag:
#   * neue 'users' zentral, dem Portal ueber die Tabelle 'tenant_user' zugeordnet
#   * neue 'companies' in der Portal-Datenbank
#   * davon jeweils die, die 'antispam:scan' als Bot-Verdacht markiert hat
#     (suspected_bot_at), als Hinweis auf den Bot-Anteil der Vorwoche
# Soft-gelöschte Zeilen zaehlen mit: eine Bot-Registrierung, die in der
# Bestandsbereinigung (#23) entfernt wird, hat trotzdem stattgefunden.
#
# Das Skript ist streng lesend: nur SELECTs, keine Schreibbefehle, kein Artisan.
#
# Aufruf:
#   scripts/turnstile-vorwoche.sh                 # sieben Tage bis gestern
#   scripts/turnstile-vorwoche.sh --tage=14       # anderer Zeitraum
#   scripts/turnstile-vorwoche.sh --nur-gruppe-a  # nur die drei Portale der Stufe 1
#
# Ergebnis: docs/messungen/turnstile-golive/vorwoche-<datum>.json (Beleg) und auf
# der Konsole die Markdown-Tabelle fuer §3 zum Eintragen.
#
# Einstellbar: SUN_SSH_HOST (Vorgabe sun), APP_DIR.
#
# Exit-Codes: 0 erhoben, 64 falscher Aufruf, 65 Produktion nicht erreichbar.

set -euo pipefail

SSH_HOST="${SUN_SSH_HOST:-sun}"
APP_DIR="${APP_DIR:-/home/sanitaerfinden/htdocs/sanitaerfinden.dev}"
HIER="$(cd "$(dirname "$0")/.." && pwd)"
TAGE=7
NUR_A=0
GRUPPE_A="fahrschulefinder.de elektrikerportal.com sanitaerfinden.com"

while [ $# -gt 0 ]; do
  case "$1" in
    --tage=*) TAGE="${1#*=}"; shift ;;
    --nur-gruppe-a) NUR_A=1; shift ;;
    -h|--help) sed -n '2,31p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; exit 64 ;;
  esac
done

case "$TAGE" in (*[!0-9]*|'') echo "--tage braucht eine Zahl." >&2; exit 64 ;; esac

remote() { ssh -o BatchMode=yes -o ConnectTimeout=20 "$SSH_HOST" "$@"; }

if ! remote true 2>/dev/null; then
  echo "Produktion nicht erreichbar (ssh ${SSH_HOST})." >&2
  exit 65
fi

# Das Abfrageskript geht als stdin-Strom hinueber: ueber argv stuenden die
# Zugangsdaten in der Prozessliste der Produktion.
abfrage() {
  printf 'tage=%s\n' "$TAGE"
  printf 'appdir=%s\n' "$APP_DIR"
  cat <<'SQL'
set -euo pipefail
cd "$appdir"
wert() { grep -m1 "^$1=" .env | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
U="$(wert DB_USERNAME)"; H="$(wert DB_DATABASE)"
MYSQL_PWD="$(wert DB_PASSWORD)"; export MYSQL_PWD
q() { mariadb -u "$U" -N -B ${2:+"$2"} -e "$1"; }

# Zeitraum: 'tage' volle Tage bis einschliesslich gestern.
von=$(date -d "-${tage} day" +%F)
bis=$(date -d "-1 day" +%F)
echo "ZEITRAUM	$von	$bis"

# Registrierungen je Portal und Tag, zentral ueber tenant_user zugeordnet.
# Die Marke kommt per sed davor: mariadb -B trennt die Spalten schon mit Tab,
# und ein concat() liesse sich nicht gruppieren (Fehler 1056).
q "select coalesce(t.domain, t.name) portal, date(u.created_at) tag,
      count(*) anzahl, sum(u.suspected_bot_at is not null) bot
    from users u
    join tenant_user tu on tu.user_id = u.id
    join tenants t on t.id = tu.tenant_id
    where date(u.created_at) between '$von' and '$bis'
    group by 1, 2
    order by 1, 2" "$H" | tr -d '\r' | sed 's/^/USERS\t/'

# Eintraege je Portal und Tag, je Portal-Datenbank.
for db in $(q "show databases like 'tenant_%'"); do
  uuid=${db#tenant_}
  t=$(q "select count(*) from information_schema.tables where table_schema='$db' and table_name='companies'")
  [ "$t" = "1" ] || continue
  dom=$(q "select coalesce(domain, name) from tenants where uuid='$uuid'" "$H")
  [ -n "$dom" ] || dom="$db"
  q "select date(created_at) tag, count(*) anzahl, sum(suspected_bot_at is not null) bot
      from \`$db\`.companies
      where date(created_at) between '$von' and '$bis'
      group by 1
      order by 1" | tr -d '\r' | sed "s|^|COMPANIES\t${dom}\t|"
done
unset MYSQL_PWD
SQL
}

ROH="$(abfrage | remote "bash -s")"
STAND="$(date +%F)"
ZIEL="${HIER}/docs/messungen/turnstile-golive/vorwoche-${STAND}.json"

# Der Auswerter liegt in einer Datei und nicht auf stdin: stdin traegt die
# Messwerte. 'python3 -I' laedt nichts aus dem Verzeichnis der Datei.
AUSWERTER="$(mktemp -t turnstile-vorwoche)"
trap 'rm -f "$AUSWERTER"' EXIT
cat > "$AUSWERTER" <<'PYCODE'
import json, sys, collections

ziel, tage, nur_a, gruppe_a = sys.argv[1], int(sys.argv[2]), sys.argv[3] == '1', sys.argv[4].split()
zeitraum = {}
portale = collections.defaultdict(lambda: {'registrierungen': {}, 'eintraege': {},
                                           'registrierungen_botverdacht': 0,
                                           'eintraege_botverdacht': 0})

for zeile in sys.stdin.read().splitlines():
    teile = zeile.split('\t')
    if teile[0] == 'ZEITRAUM' and len(teile) >= 3:
        zeitraum = {'von': teile[1], 'bis': teile[2], 'tage': tage}
    elif teile[0] in ('USERS', 'COMPANIES') and len(teile) >= 5:
        _, portal, tag, anzahl, bot = teile[:5]
        schluessel = 'registrierungen' if teile[0] == 'USERS' else 'eintraege'
        portale[portal][schluessel][tag] = int(anzahl)
        portale[portal][f'{schluessel}_botverdacht'] += int(bot or 0)

ergebnis = {'stand': ziel.rsplit('vorwoche-', 1)[1][:10], 'zeitraum': zeitraum, 'portale': {}}
for portal in sorted(portale):
    if nur_a and portal not in gruppe_a:
        continue
    p = portale[portal]
    r, e = sum(p['registrierungen'].values()), sum(p['eintraege'].values())
    ergebnis['portale'][portal] = {
        'registrierungen_gesamt': r,
        'registrierungen_schnitt_pro_tag': round(r / tage, 2),
        'registrierungen_botverdacht': p['registrierungen_botverdacht'],
        'registrierungen_je_tag': p['registrierungen'],
        'eintraege_gesamt': e,
        'eintraege_schnitt_pro_tag': round(e / tage, 2),
        'eintraege_botverdacht': p['eintraege_botverdacht'],
        'eintraege_je_tag': p['eintraege'],
    }

with open(ziel, 'w', encoding='utf-8') as f:
    json.dump(ergebnis, f, ensure_ascii=False, indent=2, sort_keys=False)
    f.write('\n')

z = ergebnis['zeitraum']
print(f"Zeitraum: {z.get('von','?')} bis {z.get('bis','?')} ({tage} Tage), "
      f"{len(ergebnis['portale'])} Portale")
print()
print('Tabelle fuer docs/turnstile-golive.md §3 (Gruppe A):')
print()
print('| Portal | Registrierungen Vorwoche (Ø/Tag) | Einträge Vorwoche (Ø/Tag) |')
print('|---|---|---|')
for portal in gruppe_a:
    p = ergebnis['portale'].get(portal)
    if p is None:
        print(f'| {portal} | 0 | 0 |')
        continue
    print(f"| {portal} | {p['registrierungen_gesamt']} ({p['registrierungen_schnitt_pro_tag']}) "
          f"| {p['eintraege_gesamt']} ({p['eintraege_schnitt_pro_tag']}) |")
print()
print('Alle Portale (Registrierungen / Einträge gesamt, davon Botverdacht):')
for portal, p in ergebnis['portale'].items():
    print(f"  {portal:30s} {p['registrierungen_gesamt']:4d} / {p['eintraege_gesamt']:5d}"
          f"   Botverdacht {p['registrierungen_botverdacht']} / {p['eintraege_botverdacht']}")
PYCODE

printf '%s\n' "$ROH" | python3 -I "$AUSWERTER" "$ZIEL" "$TAGE" "$NUR_A" "$GRUPPE_A"

echo
echo "Beleg: ${ZIEL#"$HIER"/}"
