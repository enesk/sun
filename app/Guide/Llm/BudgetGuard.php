<?php

declare(strict_types=1);

namespace App\Guide\Llm;

use App\Guide\Llm\Exceptions\BudgetExceededException;
use App\Guide\Llm\Exceptions\ContentBudgetExceededException;
use App\Guide\Models\Central\GuideAlert;
use App\Guide\Models\Central\LlmUsageLog;
use App\Guide\Models\TenantGuideSetting;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prueft vor jedem Aufruf des Ratgebersystems die Grenzen aus
 * config('guide.budget') (#5, docs/guide-system.md §6 und §8).
 *
 *   1. daily_usd_total        ueber alle Tenants
 *   2. daily_usd_per_tenant   je Tenant; der Panelwert
 *                             tenant_guide_settings.daily_budget_usd geht vor (#38 G5)
 *   3. max_usd_per_run        je Lauf (Reissleine)
 *   4. content.budget         aeussere Notbremse der alten Pipeline bis #19
 *
 * Gezaehlt wird nur llm_usage_logs mit operation 'guide.%'. Der Tag laeuft von
 * 00:00 bis 00:00 Europe/Berlin (guide.timezone); der Reset braucht keinen
 * Job, weil jede Pruefung nur den laufenden Berliner Tag summiert. Ein Wert
 * von 0 schaltet die jeweilige Pruefung ab, wie in der alten Pipeline.
 *
 * Bei Ueberschreitung entsteht ein guide_alert (je Tag und Bereich einer,
 * weitere Treffer zaehlen occurrences hoch) und eine BudgetExceededException.
 * provider_states bleibt unberuehrt: das Guide-Budget pausiert nicht die alte
 * Pipeline.
 */
class BudgetGuard
{
    public const OPERATION_PREFIX = 'guide.';

    public function __construct(
        private readonly ContentBudgetGuard $contentBudget,
        private readonly ?\Closure $clock = null,
    ) {}

    /**
     * @throws BudgetExceededException
     */
    public function check(LlmCallContext $context): void
    {
        $budget = (array) config('guide.budget', []);

        $this->assertBelow(
            BudgetExceededException::SCOPE_TOTAL,
            $this->spentToday(),
            (float) ($budget['daily_usd_total'] ?? 0.0),
            $context,
        );

        if ($context->tenantId !== null) {
            $this->assertBelow(
                BudgetExceededException::SCOPE_TENANT,
                $this->spentToday($context->tenantId),
                $this->tenantLimit($context->tenantId),
                $context,
            );
        }

        if ($context->runId !== null && $context->tenantId !== null) {
            $this->assertBelow(
                BudgetExceededException::SCOPE_RUN,
                $this->spentForRun($context->tenantId, $context->runId),
                (float) ($budget['max_usd_per_run'] ?? 0.0),
                $context,
            );
        }

        try {
            $this->contentBudget->check(LlmClient::PROVIDER);
        } catch (ContentBudgetExceededException $exception) {
            $this->exceeded(BudgetExceededException::SCOPE_NETWORK, $exception->spentUsd, $exception->limitUsd, $context);
        }
    }

    /**
     * Anzeigezaehler in provider_states (Quellen-Monitor) fortschreiben.
     */
    public function record(float $costUsd): void
    {
        $this->contentBudget->record(LlmClient::PROVIDER, $costUsd);
    }

    /**
     * Heutiger Verbrauch des Ratgebersystems, optional je Tenant.
     */
    public function spentToday(?int $tenantId = null): float
    {
        [$from, $until] = $this->dayBounds();

        return (float) $this->guideLogs()
            ->whereBetween('created_at', [$from, $until])
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->sum('cost_usd');
    }

    /**
     * Tagesbudget eines Portals: Panelwert (tenant_guide_settings
     * .daily_budget_usd), sonst guide.budget.daily_usd_per_tenant. Ist die
     * Tenant-DB nicht lesbar, gilt die Vorgabe.
     */
    public function tenantLimit(int $tenantId): float
    {
        $default = (float) config('guide.budget.daily_usd_per_tenant', 0.0);

        try {
            $read = static fn (): ?float => ($value = TenantGuideSetting::query()->value('daily_budget_usd')) !== null ? (float) $value : null;

            if (tenancy()->initialized && (int) tenant()?->getKey() === $tenantId) {
                $panel = $read();
            } else {
                $tenant = Tenant::query()->find($tenantId);
                $panel = $tenant?->run($read);
            }
        } catch (Throwable $exception) {
            Log::warning('Portalbudget nicht lesbar, Vorgabe aus der Konfiguration gilt.', [
                'tenant_id' => $tenantId,
                'exception' => $exception->getMessage(),
            ]);

            return $default;
        }

        return $panel ?? $default;
    }

    public function spentForRun(int $tenantId, int $runId): float
    {
        return (float) $this->guideLogs()
            ->where('tenant_id', $tenantId)
            ->where('reference_type', LlmCallContext::REFERENCE_RUN)
            ->where('reference_id', $runId)
            ->sum('cost_usd');
    }

    /**
     * Beginn und Ende des laufenden Berliner Tages in der Zeitzone der
     * Anwendung, in der created_at gespeichert wird.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function dayBounds(): array
    {
        $now = $this->now()->setTimezone($this->timezone());
        $appTimezone = (string) config('app.timezone', 'UTC');

        return [
            $now->startOfDay()->setTimezone($appTimezone),
            $now->endOfDay()->setTimezone($appTimezone),
        ];
    }

    /**
     * @throws BudgetExceededException
     */
    private function assertBelow(string $scope, float $spent, float $limit, LlmCallContext $context): void
    {
        if ($limit <= 0.0) {
            return;
        }

        if ($spent >= $limit) {
            $this->exceeded($scope, $spent, $limit, $context);
        }

        $threshold = (float) config('guide.budget.warn_threshold', 0.8);

        if ($threshold > 0.0 && $spent >= $limit * $threshold && $scope !== BudgetExceededException::SCOPE_RUN) {
            $this->warn($scope, $spent, $limit, $context);
        }
    }

    /**
     * @throws BudgetExceededException
     */
    private function exceeded(string $scope, float $spent, float $limit, LlmCallContext $context): never
    {
        $exception = BudgetExceededException::forScope($scope, $spent, $limit, $context->tenantId, $context->runId);

        Log::warning('Guide-Budget erreicht.', [
            'scope' => $scope,
            'tenant_id' => $context->tenantId,
            'run_id' => $context->runId,
            'spent_usd' => round($spent, 4),
            'limit_usd' => round($limit, 4),
        ]);

        $this->alert(
            GuideAlert::KEY_BUDGET_EXCEEDED,
            GuideAlert::LEVEL_CRITICAL,
            $scope,
            $exception->getMessage(),
            $spent,
            $limit,
            $context,
        );

        throw $exception;
    }

    private function warn(string $scope, float $spent, float $limit, LlmCallContext $context): void
    {
        $message = sprintf(
            'Guide-Budget zu %d %% verbraucht (%.2f von %.2f USD, Bereich %s).',
            (int) round($spent / $limit * 100),
            $spent,
            $limit,
            $scope,
        );

        $this->alert(GuideAlert::KEY_BUDGET_WARNING, GuideAlert::LEVEL_WARNING, $scope, $message, $spent, $limit, $context);
    }

    private function alert(
        string $key,
        string $level,
        string $scope,
        string $message,
        float $spent,
        float $limit,
        LlmCallContext $context,
    ): void {
        $day = $this->now()->setTimezone($this->timezone())->toDateString();

        // Tenantbezug nur dort, wo das Budget dem Tenant gehoert; die
        // Gesamtgrenze ist ein netzwerkweiter Alarm.
        $tenantId = $scope === BudgetExceededException::SCOPE_TENANT || $scope === BudgetExceededException::SCOPE_RUN
            ? $context->tenantId
            : null;

        $dedupe = implode(':', array_filter([
            $key,
            $scope,
            $tenantId,
            $scope === BudgetExceededException::SCOPE_RUN ? $context->runId : null,
            $day,
        ], fn ($part) => $part !== null));

        GuideAlert::raise($dedupe, $key, $level, $message, [
            'tenant_id' => $tenantId,
            'guide_topic_id' => $tenantId === null ? null : $context->topicId,
            'guide_topic_run_id' => $scope === BudgetExceededException::SCOPE_RUN ? $context->runId : null,
            'for_date' => $day,
            'context_json' => [
                'scope' => $scope,
                'spent_usd' => round($spent, 4),
                'limit_usd' => round($limit, 4),
            ],
        ]);
    }

    private function guideLogs(): Builder
    {
        return LlmUsageLog::query()->where('operation', 'like', self::OPERATION_PREFIX.'%');
    }

    private function now(): CarbonImmutable
    {
        return $this->clock !== null ? CarbonImmutable::instance(($this->clock)()) : CarbonImmutable::now();
    }

    private function timezone(): string
    {
        return (string) config('guide.timezone', 'Europe/Berlin');
    }
}
