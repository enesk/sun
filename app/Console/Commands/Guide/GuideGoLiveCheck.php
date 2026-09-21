<?php

declare(strict_types=1);

namespace App\Console\Commands\Guide;

use App\Console\Commands\ContentGoLiveBackup;
use App\Guide\Models\TenantGuideSetting;
use App\Guide\Publishing\IndexNowClient;
use App\Guide\Support\GuideOwners;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * Maschinelle Go-Live-Pruefung des Ratgebersystems (#38 G9,
 * design/guide-dashboard.md §11.4, docs/guide-golive.md).
 *
 * Eine Zeile je Pruefung: Status fest breit (OK/WARN/FAIL, Wort immer
 * ausgeschrieben), Pruefpunkt, Ergebnis. Schluessel erscheinen nur als
 * "gesetzt" oder "fehlt" — nie Wert, Laenge oder Anfang; deshalb gibt der
 * Befehl auch keine Adressen der IndexNow-Schluesseldateien und keine
 * Ausgabe von guide:llm:ping weiter. Exit 1 nur bei FAIL.
 *
 * Kostet einen Aufruf von guide:llm:ping (eine Anfrage, hoechstens eine Suche).
 *
 *   php artisan guide:golive:check
 */
class GuideGoLiveCheck extends Command
{
    private const OK = 'OK';

    private const WARN = 'WARN';

    private const FAIL = 'FAIL';

    protected $signature = 'guide:golive:check';

    protected $description = 'Prueft die Go-Live-Vorbedingungen des Ratgebersystems (Exit 1 bei FAIL)';

    /** @var array<string, int> */
    private array $tally = [self::OK => 0, self::WARN => 0, self::FAIL => 0];

    public function handle(IndexNowClient $indexNow): int
    {
        $this->checkKeys();
        $this->checkLlmPing();
        $this->checkIndexNow($indexNow);
        $this->checkHorizon();
        $this->checkBudgets();
        $this->checkOwners();
        $this->checkBackup();

        $fail = $this->tally[self::FAIL];
        $warn = $this->tally[self::WARN];

        $this->newLine();
        $this->line(sprintf('Go-Live-Check: %d FAIL, %d WARN — %s', $fail, $warn, $fail > 0 ? 'nicht bereit.' : 'bereit.'));

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkKeys(): void
    {
        $keys = [
            'Schlüssel Anthropic' => config('guide.anthropic.api_key'),
            'Schlüssel fal.ai' => config('guide.images.fal.api_key'),
            // Der IndexNow-Schluessel wird je Portal aus APP_KEY abgeleitet.
            'Schlüssel IndexNow (APP_KEY)' => config('app.key'),
        ];

        foreach ($keys as $label => $value) {
            filled($value)
                ? $this->result(self::OK, $label, 'gesetzt')
                : $this->result(self::FAIL, $label, 'fehlt');
        }
    }

    private function checkLlmPing(): void
    {
        try {
            // Ausgabe verwerfen: sie gehoert nicht in das Protokoll des Checks.
            $exit = Artisan::call('guide:llm:ping', ['--max-searches' => 1], new BufferedOutput);
        } catch (Throwable) {
            $exit = self::FAILURE;
        }

        $exit === self::SUCCESS
            ? $this->result(self::OK, 'guide:llm:ping', 'ok')
            : $this->result(self::FAIL, 'guide:llm:ping', 'nicht ok — Details mit php artisan guide:llm:ping');
    }

    private function checkIndexNow(IndexNowClient $indexNow): void
    {
        if (! $indexNow->isEnabled()) {
            $this->result(self::FAIL, 'IndexNow', 'aus (GUIDE_INDEXNOW_ENABLED)');

            return;
        }

        $this->result(self::OK, 'IndexNow', 'aktiviert');

        foreach ($this->activeTenants() as $tenant) {
            $label = 'IndexNow '.($tenant->domain ?: $tenant->name);
            $location = $indexNow->keyLocation($tenant);

            if ($location === null) {
                $this->result(self::FAIL, $label, 'keine Domain hinterlegt');

                continue;
            }

            try {
                $status = Http::timeout(10)->get($location)->status();
            } catch (Throwable) {
                $status = null;
            }

            $status === 200
                ? $this->result(self::OK, $label, 'Schlüsseldatei HTTP 200')
                : $this->result(self::FAIL, $label, $status !== null ? "Schlüsseldatei HTTP {$status}" : 'Schlüsseldatei nicht erreichbar');
        }
    }

    private function checkHorizon(): void
    {
        $served = collect((array) config('horizon.defaults', []))
            ->flatMap(static fn (mixed $supervisor): array => is_array($supervisor) ? (array) ($supervisor['queue'] ?? []) : [])
            ->unique()
            ->all();

        $missing = array_values(array_diff(array_values((array) config('guide.queues', [])), $served));

        $missing === []
            ? $this->result(self::OK, 'Horizon-Supervisor', 'alle Queues aus guide.queues versorgt')
            : $this->result(self::FAIL, 'Horizon-Supervisor', 'ohne Supervisor: '.implode(', ', $missing));
    }

    private function checkBudgets(): void
    {
        $budget = (array) config('guide.budget', []);
        $values = [
            'guide.budget.daily_usd_total' => (float) ($budget['daily_usd_total'] ?? 0),
            'guide.budget.daily_usd_per_tenant' => (float) ($budget['daily_usd_per_tenant'] ?? 0),
            'guide.budget.max_usd_per_run' => (float) ($budget['max_usd_per_run'] ?? 0),
        ];

        // Eingetragene Portalbudgets; leer heisst Vorgabe und ist in Ordnung.
        /** @var Tenant $tenant */
        foreach (Tenant::query()->get() as $tenant) {
            try {
                $value = $tenant->run(static fn (): mixed => TenantGuideSetting::query()->value('daily_budget_usd'));
            } catch (Throwable) {
                continue;
            }

            if ($value !== null) {
                $values['Tagesbudget '.($tenant->domain ?: $tenant->name)] = (float) $value;
            }
        }

        $invalid = array_keys(array_filter($values, static fn (float $value): bool => $value <= 0.0));

        $invalid === []
            ? $this->result(self::OK, 'Budgets', 'alle größer 0')
            : $this->result(self::FAIL, 'Budgets', implode(', ', $invalid).' ≤ 0 (0 schaltet die Grenze ab)');
    }

    private function checkOwners(): void
    {
        $count = count(GuideOwners::emails());

        $count >= 2
            ? $this->result(self::OK, 'Inhaber mit Mailadresse', (string) $count)
            : $this->result(self::FAIL, 'Inhaber mit Mailadresse', "{$count} (mindestens 2)");
    }

    private function checkBackup(): void
    {
        $today = CarbonImmutable::today()->toDateString();
        $label = "Backup backups/content/{$today}";

        if (is_dir(ContentGoLiveBackup::directory($today))) {
            $this->result(self::OK, $label, 'vorhanden');

            return;
        }

        $latest = collect(glob(dirname(ContentGoLiveBackup::directory($today)).'/*', GLOB_ONLYDIR) ?: [])
            ->map(static fn (string $path): string => basename($path))
            ->filter(static fn (string $name): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $name) === 1)
            ->sort()
            ->last();

        $this->result(self::FAIL, $label, $latest !== null ? "fehlt (jüngstes: {$latest})" : 'fehlt (php artisan content:golive:backup)');
    }

    /**
     * @return list<Tenant>
     */
    private function activeTenants(): array
    {
        /** @var list<Tenant> */
        return Tenant::query()->get()
            ->filter(static function (Tenant $tenant): bool {
                try {
                    return (bool) $tenant->run(static fn (): bool => (bool) TenantGuideSetting::query()->value('is_active'));
                } catch (Throwable) {
                    return false;
                }
            })
            ->sortBy(static fn (Tenant $tenant): string => (string) ($tenant->domain ?? $tenant->name))
            ->values()
            ->all();
    }

    private function result(string $status, string $check, string $outcome): void
    {
        $this->tally[$status]++;

        $color = match ($status) {
            self::OK => 'green',
            self::WARN => 'yellow',
            default => 'red',
        };

        // mb_strlen statt %-40s: Umlaute im Pruefpunkt verschieben sonst die Spalte.
        $this->line(sprintf('<fg=%s>%s</>  %s%s', $color, str_pad($status, 4), $check.str_repeat(' ', max(1, 41 - mb_strlen($check))), $outcome));
    }
}
