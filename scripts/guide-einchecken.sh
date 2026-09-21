#!/usr/bin/env bash
# Checkt das Ratgebersystem in vier Commits ein (#41, Voraussetzung B1 fuer #39).
# Ausgangspunkt ist main auf origin/main mit den offenen Aenderungen im
# Arbeitsverzeichnis. Der Schnitt richtet sich nach dem Vorschlag im Ticket:
#
#   1  Rueckbau alte Pipeline (#19/#23/#34/#35): alle Loeschungen, composer.*
#   2  Ratgebersystem: neue Dateien, geaenderter Code/Config/Migrationen/Routen
#   3  Portal-Themes und Content-Panel: geaenderte Views, CSS, app/Filament
#   4  Doku: docs/, design/, dieses Skript
#
# Die Commits 1-4 sind nur zusammen lauffaehig. Sie werden gemeinsam gepusht,
# dazwischen wird nicht deployt.
#
# Aufrufe:
#   scripts/guide-einchecken.sh --pruefen      # nur lesend: Schnitt, Secret-Scan, Pint --test
#   FREIGABE=$(git rev-parse --short HEAD) scripts/guide-einchecken.sh --committen
#   FREIGABE=$(git rev-parse --short HEAD) scripts/guide-einchecken.sh --pushen
#
# FREIGABE muss den aktuellen HEAD nennen, damit niemand versehentlich committet
# oder pusht. Vor der Migration in #39 ein Backup ziehen (docs/guide-golive.md §1).

set -euo pipefail

cd "$(dirname "$0")/.."

MODUS="${1:---pruefen}"
PHP_BIN="${PHP_BINARY:-php}"
BASIS="653a080"

# Pint nur auf eigene, neue Pfade anwenden, nie --dirty (fremde Arbeit).
PINT_PFADE=(
  app/Guide
  app/Console/Commands/Guide
  app/Http/Controllers/GuideController.php
  app/Http/Controllers/GuidePreviewController.php
  app/Support/IntendedUrl.php
  app/View/Components/Guide
  config/guide.php
  config/guide_lint.php
  database/seeders/GuidePromptTemplateSeeder.php
  database/seeders/GuideSourceListSeeder.php
  database/seeders/TenantGuideSettingSeeder.php
)
for datei in database/migrations/2026_09_2[23]_*.php database/migrations/tenant/2026_09_2[23]_*.php; do
  PINT_PFADE+=("$datei")
done

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# Ordnet jeden offenen Pfad genau einem Commit zu. Art: D geloescht, M geaendert, N neu.
klasse() {
  local art="$1" pfad="$2"
  case "$pfad" in
    composer.json|composer.lock) echo 1; return ;;
    docs/*|design/*|resources/content/fallback/README.md|scripts/guide-einchecken.sh) echo 4; return ;;
  esac
  if [ "$art" = D ]; then echo 1; return; fi
  if [ "$art" = M ]; then
    case "$pfad" in
      app/Filament/*|resources/views/*|resources/css/*|tailwind.content.config.cjs) echo 3; return ;;
    esac
  fi
  echo 2
}

schnitt() {
  : >"$TMP/1"; : >"$TMP/2"; : >"$TMP/3"; : >"$TMP/4"
  { git ls-files --deleted; git diff --cached --name-only --diff-filter=D; } | sort -u >"$TMP/D"
  git ls-files --modified | sort -u | comm -23 - "$TMP/D" >"$TMP/M"
  git ls-files --others --exclude-standard | sort -u >"$TMP/N"
  local art pfad
  for art in D M N; do
    while IFS= read -r pfad; do
      [ -n "$pfad" ] || continue
      echo "$pfad" >>"$TMP/$(klasse "$art" "$pfad")"
    done <"$TMP/$art"
  done
}

TITEL_1="chore(content): alte Content-Pipeline zurueckbauen (#19, #23, #34, #35)"
TITEL_2="feat(guide): themengetriebenes Ratgebersystem (#1, #3-#13, #20, #36-#38, #40)"
TITEL_3="feat(guide): Ratgeber in Portal-Themes und Content-Panel einbinden (#14-#18, #22, #28-#33)"
TITEL_4="docs(guide): Ratgeber-System, Go-Live, Prompts, Security-Review und Design (#2, #4, #21)"

pruefe_ausgangslage() {
  git fetch --quiet origin main
  local kopf ursprung
  kopf="$(git rev-parse --short HEAD)"
  ursprung="$(git rev-parse --short origin/main)"
  echo "HEAD ${kopf}, origin/main ${ursprung}, erwartet ${BASIS}"
  if [ "$kopf" != "$BASIS" ] || [ "$ursprung" != "$BASIS" ]; then
    echo "Abbruch: HEAD oder origin/main weicht von ${BASIS} ab. Schnitt neu pruefen." >&2
    exit 1
  fi
  local vorgemerkt
  vorgemerkt="$(git diff --cached --name-status | grep -v '^D' || true)"
  if [ -n "$vorgemerkt" ]; then
    echo "Abbruch: Im Index liegen Aenderungen ausser Loeschungen:" >&2
    echo "$vorgemerkt" >&2
    exit 1
  fi
}

secret_scan() {
  cat "$TMP/M" "$TMP/N" | sort -u >"$TMP/scan"
  # Liste als Array uebergeben: zsh/bash-Wortaufteilung einer Variablen ist unzuverlaessig.
  local dateien=()
  while IFS= read -r pfad; do dateien+=("$pfad"); done <"$TMP/scan"
  "$PHP_BIN" scripts/secret-scan.php
  "$PHP_BIN" scripts/secret-scan.php "${dateien[@]}"
}

pruefen() {
  pruefe_ausgangslage
  schnitt
  local n
  for n in 1 2 3 4; do
    local titel="TITEL_${n}"
    echo
    echo "== Commit ${n}: ${!titel} ($(wc -l <"$TMP/$n" | tr -d ' ') Pfade)"
    sed 's/^/   /' "$TMP/$n"
  done
  echo
  echo "== Secret-Scan"
  secret_scan
  echo
  echo "== Pint --test (nur eigene Pfade)"
  vendor/bin/pint --test "${PINT_PFADE[@]}" || echo "Pint wuerde formatieren; --committen fuehrt es vor Commit 2 aus."
}

committen() {
  pruefe_ausgangslage
  vendor/bin/pint "${PINT_PFADE[@]}"
  schnitt
  secret_scan
  local n
  for n in 1 2 3 4; do
    local titel="TITEL_${n}"
    if [ ! -s "$TMP/$n" ]; then
      echo "Commit ${n} leer, uebersprungen."
      continue
    fi
    # Bereits vorgemerkte Loeschungen (weder im Index noch auf der Platte)
    # kennt `git add` nicht mehr als Pfad und bricht sonst mit "pathspec" ab.
    while IFS= read -r pfad; do
      [ -e "$pfad" ] || git ls-files --error-unmatch -- "$pfad" >/dev/null 2>&1 && printf '%s\0' "$pfad"
    done <"$TMP/$n" | xargs -0 -r git add -A --
    # Der Pre-Commit-Hook scannt die vorgemerkten Dateien erneut.
    git commit --quiet -m "${!titel}"
    echo "Commit ${n}: $(git log -1 --format='%h %s')"
  done
  echo
  echo "== git status (muss leer sein)"
  git status --short
  echo
  echo "Weiter mit: FREIGABE=$(git rev-parse --short HEAD) scripts/guide-einchecken.sh --pushen"
}

pushen() {
  git fetch --quiet origin main
  local vorsprung
  vorsprung="$(git rev-list --count origin/main..HEAD)"
  if [ "$(git rev-parse --short origin/main)" != "$BASIS" ] || [ "$vorsprung" -lt 1 ] || [ "$vorsprung" -gt 4 ]; then
    echo "Abbruch: erwartet origin/main=${BASIS} und 1-4 neue Commits, gefunden ${vorsprung}." >&2
    exit 1
  fi
  git log --oneline origin/main..HEAD
  git push origin main
  echo "Gepusht. Weiter in #39 nach docs/guide-golive.md §1 – vor der Migration Backup ziehen."
}

freigabe_pruefen() {
  local kopf
  kopf="$(git rev-parse --short HEAD)"
  if [ "${FREIGABE:-}" != "$kopf" ]; then
    echo "Abbruch: FREIGABE=${FREIGABE:-<leer>} passt nicht zu HEAD ${kopf}." >&2
    exit 1
  fi
}

case "$MODUS" in
  --pruefen) pruefen ;;
  --committen) freigabe_pruefen; committen ;;
  --pushen) freigabe_pruefen; pushen ;;
  *)
    echo "Aufruf: $0 --pruefen | --committen | --pushen" >&2
    exit 2
    ;;
esac
