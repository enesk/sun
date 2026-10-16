#!/usr/bin/env bash
# Eigentum nach einem Deploy richten — ohne die Produktion lahmzulegen (#59).
#
# WARUM ES DIESES SKRIPT GIBT
# ---------------------------
# Auf der Produktion zieht `git pull` als root (nur root hat den
# GitHub-Schluessel, docs/ad-slots-golive.md §2). Danach gehoeren die neuen
# Dateien root und muessen dem Anwendungsbenutzer zurueckgegeben werden. Der
# naheliegende Befehl dafuer ist falsch:
#
#     chown -R sanitaerfinden:sanitaerfinden .      # NIEMALS
#
# Er laeuft durch `storage/` — und dort liegt der **Datei-Cache je Tenant**:
# 29 Tenant-Verzeichnisse mit je 256 Hash-Ordnern voller Kleinstdateien, auf
# einem RAID1 aus zwei rotierenden Platten. Jede Datei einzeln angefasst sind
# tausende Metadaten-Schreibvorgaenge pro Sekunde; am 16.10.2026 hat genau
# dieser Lauf die Maschine auf Load 53, iowait 85 % und **TTFB 49 Sekunden**
# gebracht, ueber 17 Minuten lang. Gebraucht wurde dabei nichts: die
# Tenant-Storages gehoerten dem richtigen Benutzer, root-eigen waren sieben
# frische Code-Dateien.
#
# Dieses Skript fasst deshalb nur an, was wirklich root gehoert, und laesst
# `storage/`, `node_modules/` und `.git/` ganz aussen vor.
#
# AUFRUF (auf der Produktion, als root)
#
#     scripts/deploy-eigentuemer-richten.sh --pruefen   # nur zeigen
#     scripts/deploy-eigentuemer-richten.sh --los       # richten
#
# Einstellbar: APP_DIR, APP_USER, ZEITGRENZE (Sekunden, Vorgabe 120).
#
# ZWEITE REGEL, GLEICHER VORFALL: lange Befehle auf diesem Server **immer** in
# `timeout` (dieses Skript tut es selbst). Eine abgerissene SSH-Sitzung beendet
# den Befehl nicht — er laeuft als Waise weiter und ist nur noch ueber
# `pgrep -af chown` zu finden. Derselbe Fehler wie am 15.09.2026 mit einem `du`
# ueber /home.
#
# Exit-Codes: 0 fertig oder nichts zu tun, 64 falscher Aufruf,
#             70 Zeitgrenze erreicht (dann steht der Rest im Protokoll)

set -euo pipefail

APP_DIR="${APP_DIR:-/home/sanitaerfinden/htdocs/sanitaerfinden.dev}"
APP_USER="${APP_USER:-sanitaerfinden}"
ZEITGRENZE="${ZEITGRENZE:-120}"

# Nie anfassen: storage (Tenant-Datei-Cache, PHP legt das selbst an),
# node_modules (hunderttausende Dateien, gehoert dem Build), .git (root zieht,
# root liest).
AUSNAHMEN=(-path "./storage/*" -o -path "./node_modules/*" -o -path "./.git/*")

LOS=0
case "${1:-}" in
    --pruefen) LOS=0 ;;
    --los) LOS=1 ;;
    *) sed -n '2,40p' "$0" | sed 's/^# \{0,1\}//'; exit 64 ;;
esac

cd "$APP_DIR"

echo "Verzeichnis: $APP_DIR"
echo "Ziel-Eigentuemer: ${APP_USER}:${APP_USER}"
echo "Ausgenommen: storage/, node_modules/, .git/"
echo

# -xdev: nicht ueber Einhaengepunkte hinaus. Die Ausnahmen werden mit -prune
# abgeschnitten, damit `find` dort nicht einmal absteigt — bei storage/ ist
# genau das Absteigen das Teure.
treffer() {
    find . -xdev \( "${AUSNAHMEN[@]}" \) -prune -o -user root -print0
}

ANZAHL="$(treffer | tr -dc '\0' | wc -c | tr -d ' ')"

echo "Root-eigene Dateien ausserhalb der Ausnahmen: ${ANZAHL}"

if [ "$ANZAHL" = "0" ]; then
    echo "Nichts zu tun."
    exit 0
fi

treffer | xargs -0 -r ls -ld | head -20
[ "$ANZAHL" -gt 20 ] && echo "… und $((ANZAHL - 20)) weitere"

if [ "$LOS" -eq 0 ]; then
    echo
    echo "Trockenlauf — nichts geaendert. Mit --los richten."
    exit 0
fi

echo
echo "Richten (Zeitgrenze ${ZEITGRENZE}s) …"

# `timeout` um den ganzen Lauf: ein Abriss der Sitzung darf keinen
# weiterlaufenden Waisen hinterlassen.
if ! timeout "$ZEITGRENZE" bash -c 'find . -xdev \( -path "./storage/*" -o -path "./node_modules/*" -o -path "./.git/*" \) -prune -o -user root -print0 | xargs -0 -r chown '"${APP_USER}:${APP_USER}"; then
    echo "FEHLER: Zeitgrenze von ${ZEITGRENZE}s erreicht — Lauf abgebrochen." >&2
    echo "        Nachsehen: pgrep -af chown   (es darf nichts uebrig sein)" >&2
    exit 70
fi

VERBLEIBEND="$(treffer | tr -dc '\0' | wc -c | tr -d ' ')"
echo "Fertig. Root-eigen verbleibend: ${VERBLEIBEND}"

# Gegenprobe in storage/ — aber NUR flach.
#
# Die erste Fassung dieses Skripts hatte hier `find storage -user root -print
# -quit` ohne Tiefenbegrenzung und ist damit in dieselbe Falle gelaufen, die es
# verhindern soll: ohne Treffer scannt der Lauf alle 29 Tenant-Caches, und beim
# Versuch am 16.10.2026 stand er nach 160 Sekunden in IO-Wartestellung (Status
# D, Load von 13 auf 27). Erst `kill -9` hat ihn beendet.
#
# Deshalb `-maxdepth 2`: das deckt `storage/*` und `storage/tenant<uuid>` ab —
# also die Faelle, die ein Artisan-Lauf als root tatsaechlich erzeugt (Logs,
# frisch angelegte Tenant-Verzeichnisse) — und steigt nie in die
# Hash-Ordner des Datei-Caches ab. Wer tiefer sehen will, nimmt gezielt einen
# Pfad und fuehrt den Lauf in `timeout`.
STORAGE="$(timeout 20 find storage -xdev -maxdepth 2 -user root -print -quit 2>/dev/null || true)"

if [ -n "$STORAGE" ]; then
    echo
    echo "HINWEIS: in storage/ liegen root-eigene Dateien, z. B.:"
    echo "  $STORAGE"
    echo "  Das ist ein eigener Befund (meist ein Artisan-Lauf als root)."
    echo "  Einzeln richten, niemals 'chown -R storage' — siehe Kopf dieses Skripts."
fi
