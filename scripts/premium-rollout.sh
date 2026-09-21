#!/usr/bin/env bash
# Rollt den Premium-Stand im Wartungsfenster auf sun aus (#29, #35).
# Ziel ist der lokal ausgecheckte Commit; er muss gleich origin/main sein.
#
# Code, Migrationen und Config-Cache gehen nur gemeinsam live: ohne die neuen
# Tabellen (featured_placements, company_events) werfen Stadt- und
# Kategorieseiten 500er, ohne config:cache liefert config('premium…') null.
#
# Handarbeit vorher (docs/premium-golive.md, Abschnitt "Produktions-Rollout"):
# Wartungsfenster ankuendigen, DB-Aenderungen und den Zwischenstand #14/#18
# freigeben, die .env-Abweichungen aus --pruefen abnicken.
#
# Aufrufe:
#   scripts/premium-rollout.sh --pruefen      # nur lesend: Stand, .env-Drift, offene Migrationen
#   scripts/premium-rollout.sh --sichern      # DB-Dump central + tenant_% (ca. 20 min)
#   FREIGABE=$(git rev-parse --short HEAD) scripts/premium-rollout.sh --ausrollen
#   FREIGABE=969a1f2 scripts/premium-rollout.sh --rollback
#   scripts/premium-rollout.sh --billing      # nur lesend: Stand Subscription-Lifecycle (#21)
#
# FREIGABE muss den Ziel-Commit nennen, damit niemand versehentlich ausrollt.

set -euo pipefail

SSH_HOST="${SUN_SSH_HOST:-sun}"
APP_DIR="/home/sanitaerfinden/htdocs/sanitaerfinden.dev"
APP_USER="sanitaerfinden"
PHP="/usr/bin/php8.4"
ZIEL="$(git -C "$(dirname "$0")/.." rev-parse --short HEAD)"
ALT="969a1f2"
BACKUP_DIR="/home/${APP_USER}/backups/vor-premium-$(date +%Y%m%d-%H%M)"

MODUS="${1:-}"

remote() { ssh -o BatchMode=yes "$SSH_HOST" "$@"; }

# Artisan und git laufen nie als root: ein von root geschriebener Config-Cache
# sperrt PHP-FPM aus, und root-eigene Objekte in .git lassen den naechsten Pull
# des App-Users scheitern. Seit 21.09.2026 hat der App-User einen eigenen
# Deploy-Key (nur Lesen) fuer github.com/enesk/sun, root wird dafuer nicht mehr gebraucht.
als_app() {
  remote "su -s /bin/bash ${APP_USER} -c 'HOME=/home/${APP_USER}; cd ${APP_DIR} && $1'"
}

freigabe() {
  [ "${FREIGABE:-}" = "$1" ] || {
    echo "Abbruch: FREIGABE=$1 fehlt. Erst Wartungsfenster und Freigabe klaeren (#29, #35)." >&2
    exit 77
  }
}

pruefen() {
  echo "== Stand =="
  als_app "git fetch -q origin && git rev-parse --short HEAD && git log --oneline -1 origin/main && git status --porcelain --untracked-files=no"
  echo
  echo "== .env gegen bootstrap/cache/config.php (nur Schluesselnamen) =="
  # Baut einen frischen Cache nach /tmp und vergleicht ihn mit dem aktiven.
  # Werte werden nicht ausgegeben.
  remote "su -s /bin/bash ${APP_USER} -c 'HOME=/home/${APP_USER}; cd ${APP_DIR} && APP_CONFIG_CACHE=/tmp/premium-drift-config.php ${PHP} artisan config:cache >/dev/null && ${PHP}; rm -f /tmp/premium-drift-config.php'" <<'PHPCODE'
<?php
function flat(array $a, string $p = ''): array
{
    $o = [];
    foreach ($a as $k => $v) {
        $n = $p === '' ? (string) $k : "{$p}.{$k}";
        if (is_array($v) && $v !== [] && ! array_is_list($v)) {
            $o += flat($v, $n);
            continue;
        }
        $o[$n] = $v;
    }

    return $o;
}
$alt = flat(require 'bootstrap/cache/config.php');
$neu = flat(require '/tmp/premium-drift-config.php');
foreach (array_unique(array_merge(array_keys($alt), array_keys($neu))) as $k) {
    if (! array_key_exists($k, $alt)) {
        echo "neu        {$k}\n";
        continue;
    }
    if (! array_key_exists($k, $neu)) {
        echo "entfaellt  {$k}\n";
        continue;
    }
    if ($alt[$k] !== $neu[$k]) {
        echo "geaendert  {$k}\n";
    }
}
PHPCODE
  echo
  echo "== .env-Schalter fuer Premium =="
  remote "cd ${APP_DIR} && grep -E '^(TENANT_MULTIPLE_SUBSCRIPTIONS_ENABLED|PREMIUM_ENTITLEMENT_CACHE_STORE)=' .env || echo '(keine Zeile)'"
  echo
  echo "== Offene central-Migrationen =="
  als_app "${PHP} artisan migrate:status --pending" || true
  echo
  echo "== Platz fuer Dump =="
  remote "df -h /home | tail -1"
}

sichern() {
  echo "Starte Dump nach ${BACKUP_DIR} (nohup, ca. 20 min) …"
  # CloudPanel-Backups sind leere gz-Dateien, daher eigener Dump.
  remote "install -d -m 0700 ${BACKUP_DIR} && cd ${APP_DIR} && set -a && . ./.env && set +a && nohup bash -c '
    for db in \$(mariadb -u\"\$DB_USERNAME\" -p\"\$DB_PASSWORD\" -h\"\${DB_HOST:-127.0.0.1}\" -N -e \"SHOW DATABASES\" | grep -E \"^(\${DB_DATABASE}|tenant_.*)\$\"); do
      mariadb-dump -u\"\$DB_USERNAME\" -p\"\$DB_PASSWORD\" -h\"\${DB_HOST:-127.0.0.1}\" --single-transaction --quick --routines --no-tablespaces \"\$db\" | gzip > ${BACKUP_DIR}/\$db.sql.gz
    done
    echo fertig > ${BACKUP_DIR}/FERTIG
  ' > ${BACKUP_DIR}/dump.log 2>&1 &"
  echo "Fertig, wenn ${BACKUP_DIR}/FERTIG existiert und keine .sql.gz 20 Byte gross ist:"
  echo "  ssh ${SSH_HOST} 'ls -la ${BACKUP_DIR}'"
}

ausrollen() {
  freigabe "$ZIEL"
  # Nur committeten und gepushten Stand ausrollen, sonst holt `pull` etwas anderes.
  git -C "$(dirname "$0")/.." fetch -q origin
  [ "$(git -C "$(dirname "$0")/.." rev-parse --short origin/main)" = "$ZIEL" ] || {
    echo "Abbruch: ${ZIEL} ist nicht origin/main. Erst committen und pushen." >&2
    exit 78
  }
  local secret
  secret="$(openssl rand -hex 16)"
  als_app "${PHP} artisan down --secret=${secret}"
  echo "Wartungsmodus aktiv, Vorbeizugang: https://<portal>/${secret}"

  # Migrationen direkt nach dem Pull: bis dahin kommen 500er, auch mit Secret.
  als_app "git pull --ff-only && git rev-parse --short HEAD | grep -q '^${ZIEL}'"
  als_app "${PHP} artisan migrate --force && ${PHP} artisan tenants:migrate --force"
  # PREMIUM_ENTITLEMENT_CACHE_STORE bleibt bewusst ohne Zeile (Default-Store).
  remote "cd ${APP_DIR} && cp -p .env .env.bak-rollout-\$(date +%Y%m%d-%H%M%S) && grep -v '^TENANT_MULTIPLE_SUBSCRIPTIONS_ENABLED=' .env > .env.neu && echo TENANT_MULTIPLE_SUBSCRIPTIONS_ENABLED=true >> .env.neu && cat .env.neu > .env && rm -f .env.neu"
  als_app "composer install --no-dev --optimize-autoloader --no-interaction"
  als_app "npm ci && timeout 900 npm run build"
  # Kein view:cache: bricht an components/city/faq.blade.php ab.
  als_app "${PHP} artisan config:cache && ${PHP} artisan route:cache && ${PHP} artisan view:clear && ${PHP} artisan filament:optimize"
  remote "systemctl reload php8.5-fpm && supervisorctl restart sanitaerfinden-horizon"
  als_app "${PHP} artisan up"
  als_app "${PHP} artisan db:seed --class=PremiumPlansSeeder --force"
  nachkontrolle
}

nachkontrolle() {
  echo
  echo "== Scheduler =="
  als_app "${PHP} artisan schedule:list | grep -E \"premium|stats:aggregate|purge-contacts\"" || echo "FEHLT: Premium-Eintraege im Scheduler"
  echo
  echo "== Laravel-Log (UTC), letzte ERROR-Zeilen =="
  remote "grep -h ' production.ERROR' ${APP_DIR}/storage/logs/laravel*.log 2>/dev/null | tail -20 || true"
  echo
  echo "Stichprobe von Hand: je Portal eine Stadt-, Kategorie- und Profilseite (/{id}-{slug}) sowie /premium."
}

# Nur lesend. Zeigt nach jedem Schritt der Stripe-Matrix (#21), ob Zuordnung,
# Grace Period und Plan stimmen. Nach dem Rollout erneut aufrufen.
billing() {
  echo "== Stand =="
  als_app "git rev-parse --short HEAD"
  echo
  echo "== Central-Migration 000005 =="
  als_app "${PHP} artisan migrate:status | grep company_subscriptions" || echo "FEHLT: 2026_09_17_000005 (Rollout #29 noch nicht erfolgt)"
  echo
  echo "== Scheduler =="
  als_app "${PHP} artisan schedule:list | grep premium:process-expirations" || echo "FEHLT: premium:process-expirations"
  echo
  echo "== Schalter, Abos, Grace Period =="
  remote "su -s /bin/bash ${APP_USER} -c 'HOME=/home/${APP_USER}; cd ${APP_DIR} && ${PHP}'" <<'PHPCODE'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo 'app.tenant_multiple_subscriptions_enabled = ', var_export(config('app.tenant_multiple_subscriptions_enabled'), true), " (Soll: true)\n";
echo 'Stripe-Modus laut Schluessel: ', str_starts_with((string) config('services.stripe.secret_key'), 'sk_live') ? 'LIVE' : 'test/leer', "\n";

if (! Schema::hasTable('company_subscriptions')) {
    echo "company_subscriptions fehlt, keine weiteren Pruefungen.\n";
    exit;
}

$rows = DB::table('company_subscriptions as cs')
    ->join('subscriptions as s', 's.id', '=', 'cs.subscription_id')
    ->orderByDesc('cs.id')->limit(15)
    ->get(['cs.tenant_id', 'cs.company_id', 's.id', 's.status', 's.ends_at', 's.cancelled_at']);
echo "Letzte Zuordnungen (tenant/company -> subscription status ends_at cancelled_at):\n";
foreach ($rows as $r) {
    echo "  {$r->tenant_id}/{$r->company_id} -> #{$r->id} {$r->status} {$r->ends_at} {$r->cancelled_at}\n";
}

foreach (App\Models\Tenant::whereIn('id', DB::table('company_subscriptions')->distinct()->pluck('tenant_id'))->get() as $tenant) {
    $tenant->run(function () use ($tenant) {
        $companies = DB::table('companies')
            ->where(fn ($q) => $q->where('plan_tier', '!=', 'free')->orWhereNotNull('plan_grace_until'))
            ->get(['id', 'plan_tier', 'plan_grace_until']);
        foreach ($companies as $c) {
            echo "  Tenant {$tenant->id} Betrieb {$c->id}: plan_tier={$c->plan_tier} plan_grace_until=", $c->plan_grace_until ?? '-', "\n";
        }
    });
}
PHPCODE
  echo
  echo "== Auto-Downgrade (dry-run) =="
  if als_app "${PHP} artisan list premium >/dev/null 2>&1"; then
    als_app "${PHP} artisan tenants:run premium:process-expirations --option=dry-run=1" || true
  else
    echo "FEHLT: premium:process-expirations (Code-Stand vor 314b18c)"
  fi
}

rollback() {
  freigabe "$ALT"
  als_app "${PHP} artisan down"
  # Neue Tabellen und Spalten bleiben stehen, der alte Code ignoriert sie.
  als_app "git reset --hard ${ALT}"
  als_app "composer install --no-dev --optimize-autoloader --no-interaction && npm ci && timeout 900 npm run build"
  als_app "${PHP} artisan config:cache && ${PHP} artisan route:cache && ${PHP} artisan view:clear"
  remote "systemctl reload php8.5-fpm && supervisorctl restart sanitaerfinden-horizon"
  als_app "${PHP} artisan up"
}

case "$MODUS" in
  --pruefen) pruefen ;;
  --sichern) sichern ;;
  --ausrollen) ausrollen ;;
  --nachkontrolle) nachkontrolle ;;
  --rollback) rollback ;;
  --billing) billing ;;
  *) sed -n '2,19p' "$0"; exit 64 ;;
esac
