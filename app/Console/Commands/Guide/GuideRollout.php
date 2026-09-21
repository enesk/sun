<?php

declare(strict_types=1);

namespace App\Console\Commands\Guide;

use App\Guide\Models\TenantGuideSetting;
use App\Guide\Support\Usd;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/**
 * Rollout-Schalter des Ratgebersystems (#38 G8, design/guide-dashboard.md
 * §11.4) auf tenant_guide_settings. Ausgabe immer als Tabelle mit der
 * wirksamen Schwelle (TenantGuideSetting::effectiveThreshold()).
 *
 *   php artisan guide:rollout
 *   php artisan guide:rollout --activate=sanitaerfinder.com --threshold=100
 *   php artisan guide:rollout --activate=tierarztportal.com --ymyl
 *   php artisan guide:rollout --deactivate=7
 *   php artisan guide:rollout --activate-all --force
 *
 * --threshold und --ymyl gelten fuer die Portale aus --activate bzw.
 * --activate-all; gespeichert wird der eingetragene Wert.
 */
class GuideRollout extends Command
{
    protected $signature = 'guide:rollout
        {--activate=* : Portal freischalten (Domain, ID oder UUID)}
        {--deactivate=* : Portal herausnehmen (Domain, ID oder UUID)}
        {--threshold= : Freigabeschwelle der freigeschalteten Portale (50-100, 100 = alles in Pruefung)}
        {--ymyl : Freigeschaltete Portale als YMYL markieren (Schwelle mindestens 90)}
        {--activate-all : Alle Portale freischalten}
        {--deactivate-all : Alle Portale herausnehmen}
        {--force : Ohne Rueckfrage (Deploy-Skripte)}';

    protected $description = 'Zeigt und setzt den Rollout-Schalter des Ratgebersystems je Portal';

    public function handle(): int
    {
        $threshold = $this->option('threshold');

        if ($threshold !== null && (! ctype_digit((string) $threshold) || (int) $threshold < 50 || (int) $threshold > 100)) {
            $this->error('--threshold muss eine ganze Zahl von 50 bis 100 sein.');

            return self::FAILURE;
        }

        if ($this->option('activate-all') && $this->option('deactivate-all')) {
            $this->error('--activate-all und --deactivate-all schliessen sich aus.');

            return self::FAILURE;
        }

        $tenants = Tenant::query()->get()->sortBy(fn (Tenant $tenant): string => mb_strtolower((string) ($tenant->domain ?? $tenant->name)))->values();

        // Erst alle Angaben aufloesen: eine unbekannte Domain aendert nichts.
        $activate = $this->resolve((array) $this->option('activate'));
        $deactivate = $this->resolve((array) $this->option('deactivate'));

        if ($activate === null || $deactivate === null) {
            return self::FAILURE;
        }

        if ($this->option('activate-all')) {
            $activate = $tenants->map(fn (Tenant $tenant): int => (int) $tenant->getKey())->all();
        }

        if ($this->option('deactivate-all')) {
            $deactivate = $tenants->map(fn (Tenant $tenant): int => (int) $tenant->getKey())->all();
        }

        if (($threshold !== null || $this->option('ymyl')) && $activate === []) {
            $this->error('--threshold und --ymyl wirken nur zusammen mit --activate oder --activate-all.');

            return self::FAILURE;
        }

        if (($this->option('activate-all') || $this->option('deactivate-all')) && ! $this->option('force')) {
            $question = $this->option('activate-all')
                ? trans_choice('{1} Ein Portal freischalten?|[2,*] :count Portale freischalten?', $tenants->count(), ['count' => $tenants->count()])
                : trans_choice('{1} Ein Portal herausnehmen?|[2,*] :count Portale herausnehmen?', $tenants->count(), ['count' => $tenants->count()]);

            // Vorgabe nein; ohne Terminal (--no-interaction) gilt die Vorgabe.
            $answer = mb_strtolower(trim((string) $this->ask($question.' (ja/nein)', 'nein')));

            if (! in_array($answer, ['ja', 'j'], true)) {
                $this->line('Abgebrochen, nichts geändert.');

                return self::FAILURE;
            }
        }

        $rows = [];
        $changed = 0;
        $failed = false;

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            $id = (int) $tenant->getKey();

            try {
                [$before, $after] = $tenant->run(function () use ($id, $activate, $deactivate, $threshold): array {
                    $setting = TenantGuideSetting::current();
                    $before = $this->state($setting);

                    if (in_array($id, $activate, true)) {
                        $setting->is_active = true;
                        $setting->auto_publish_threshold = $threshold !== null ? (int) $threshold : $setting->auto_publish_threshold;
                        $setting->is_ymyl = $this->option('ymyl') ? true : $setting->is_ymyl;
                    }

                    if (in_array($id, $deactivate, true)) {
                        $setting->is_active = false;
                    }

                    if ($setting->isDirty()) {
                        $setting->save();
                    }

                    return [$before, $this->state($setting)];
                });
            } catch (Throwable $exception) {
                $failed = true;
                $rows[] = ['  '.($tenant->domain ?? $tenant->name), 'nicht lesbar', '', '', mb_substr($exception->getMessage(), 0, 60)];

                continue;
            }

            $isChanged = $before !== $after;
            $changed += (int) $isChanged;

            $rows[] = [
                ($isChanged ? '* ' : '  ').($tenant->domain ?? $tenant->name),
                $after['active'] ? 'ja' : 'nein',
                $after['ymyl'] ? 'ja' : 'nein',
                $this->thresholdLabel($after['threshold'], $after['ymyl']),
                $after['budget'] !== null ? Usd::format($after['budget']) : Usd::format((float) config('guide.budget.daily_usd_per_tenant')).' (Vorgabe)',
            ];
        }

        $this->table(['Domain', 'Aktiv', 'YMYL', 'Schwelle', 'Tagesbudget'], $rows);

        if ($changed > 0) {
            $this->line(trans_choice('{1} Ein Portal geändert.|[2,*] :count Portale geändert.', $changed, ['count' => $changed]));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Wirksamer Wert; weicht er ab: "eingetragen → wirksam (YMYL)".
     */
    private function thresholdLabel(int $entered, bool $ymyl): string
    {
        $effective = TenantGuideSetting::effectiveThresholdFor($entered, $ymyl);
        $label = $effective !== $entered ? "{$entered} → {$effective} (YMYL)" : (string) $effective;

        return TenantGuideSetting::allowsAutoPublish($effective) ? $label : "{$label} (alles in Prüfung)";
    }

    /**
     * @return array{active: bool, ymyl: bool, threshold: int, budget: ?float}
     */
    private function state(TenantGuideSetting $setting): array
    {
        return [
            'active' => (bool) $setting->is_active,
            'ymyl' => (bool) $setting->is_ymyl,
            'threshold' => (int) ($setting->auto_publish_threshold ?? TenantGuideSetting::DEFAULT_THRESHOLD),
            'budget' => $setting->daily_budget_usd !== null ? (float) $setting->daily_budget_usd : null,
        ];
    }

    /**
     * @param  array<int, string>  $needles
     * @return list<int>|null null bei unbekannter Angabe
     */
    private function resolve(array $needles): ?array
    {
        $ids = [];

        foreach ($needles as $needle) {
            $needle = trim((string) $needle);
            $tenant = ctype_digit($needle)
                ? Tenant::query()->find((int) $needle)
                : Tenant::query()->where('domain', $needle)->orWhere('uuid', $needle)->first();

            if ($tenant === null) {
                $this->error("Kein Portal mit der Domain {$needle} gefunden.");

                return null;
            }

            $ids[] = (int) $tenant->getKey();
        }

        return $ids;
    }
}
