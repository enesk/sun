<?php

declare(strict_types=1);

namespace App\Guide\Llm;

use App\Guide\Events\ProviderAlarmRaised;
use App\Guide\Llm\Exceptions\ContentBudgetExceededException;
use App\Guide\Models\Central\LlmUsageLog;
use App\Guide\Models\Central\ProviderState;
use Illuminate\Support\Facades\Log;

/**
 * Prueft vor jedem kostenpflichtigen Provider-Aufruf die Grenzen aus
 * config('content.budget') (#6).
 *
 * Vier Grenzen, von grob nach fein:
 *   1. Tagesbudget ueber alle Provider und Mandanten
 *   2. Tagesanteil des einzelnen Providers (budget.provider_share)
 *   3. Tagesbudget je Mandant
 *   4. Reissleine je Artikel
 *
 * Bei Ueberschreitung wird der Provider auf ProviderState::STATUS_PAUSED
 * gesetzt und eine ContentBudgetExceededException geworfen. Die Pause endet um 00:00
 * (circuit_open_until steht auf Mitternacht); ein weiterer Aufruf danach gibt
 * den Provider beim ersten check() wieder frei, ohne dass ein eigener Job
 * dafuer laufen muss.
 *
 * Grundlage ist immer die Summe aus llm_usage_logs — der Zaehler in
 * provider_states ist nur Anzeige fuer den Quellen-Monitor (#20).
 */
class ContentBudgetGuard
{
    public function __construct(
        private readonly ?\Closure $clock = null,
    ) {}

    /**
     * @param  string|null  $referenceType  z.B. 'guide_topic_runs'
     *
     * @throws ContentBudgetExceededException
     */
    public function check(
        string $provider,
        ?int $tenantId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): void {
        $state = $this->stateFor($provider);

        $budget = (array) config('content.budget', []);
        $day = $this->today();

        $dailyLimit = (float) ($budget['daily_usd'] ?? 0.0);
        $spentTotal = LlmUsageLog::costForDay($day);

        $this->assertBelow(
            ContentBudgetExceededException::SCOPE_DAILY,
            $provider,
            $spentTotal,
            $dailyLimit,
            $state,
        );

        $share = (float) ($budget['provider_share'][$provider] ?? 1.0);
        $this->assertBelow(
            ContentBudgetExceededException::SCOPE_PROVIDER,
            $provider,
            LlmUsageLog::costForDay($day, $provider),
            $dailyLimit * $share,
            $state,
        );

        if ($tenantId !== null) {
            $this->assertBelow(
                ContentBudgetExceededException::SCOPE_TENANT,
                $provider,
                LlmUsageLog::costForDay($day, null, $tenantId),
                (float) ($budget['daily_usd_per_tenant'] ?? 0.0),
                $state,
                $tenantId,
                // Ein einzelner Mandant am Limit pausiert den Provider nicht
                // fuer alle anderen.
                pauses: false,
            );
        }

        if ($referenceType !== null && $referenceId !== null) {
            $this->assertBelow(
                ContentBudgetExceededException::SCOPE_ARTICLE,
                $provider,
                LlmUsageLog::costForReference($referenceType, $referenceId),
                (float) ($budget['max_usd_per_article'] ?? 0.0),
                $state,
                $tenantId,
                pauses: false,
            );
        }
    }

    /**
     * Schreibt den Tageszaehler des Providers fort. Wird vom LlmClient nach jedem
     * Aufruf gerufen, auch nach einem gescheiterten
     * — verbrauchte Tokens fallen auch dann an.
     */
    public function record(string $provider, float $costUsd, int $requests = 1): void
    {
        $state = $this->stateFor($provider);

        $state->forceFill([
            'requests_today' => $state->requests_today + $requests,
            'cost_today_usd' => round($state->cost_today_usd + $costUsd, 4),
            'counters_date' => $this->today(),
        ])->save();
    }

    /**
     * Wieviel USD der Provider heute noch ausgeben darf — der kleinere Wert aus
     * Gesamtbudget und Provider-Anteil.
     */
    public function remainingFor(string $provider): float
    {
        $budget = (array) config('content.budget', []);
        $daily = (float) ($budget['daily_usd'] ?? 0.0);
        $share = (float) ($budget['provider_share'][$provider] ?? 1.0);
        $day = $this->today();

        return max(0.0, min(
            $daily - LlmUsageLog::costForDay($day),
            $daily * $share - LlmUsageLog::costForDay($day, $provider),
        ));
    }

    /**
     * Zustandszeile des Providers, mit Tagesrollover und automatischer Freigabe
     * einer abgelaufenen Budgetpause.
     */
    public function stateFor(string $provider): ProviderState
    {
        $state = ProviderState::forProvider($provider);
        $today = $this->today();

        $isNewDay = $state->counters_date === null || $state->counters_date->startOfDay()->lt($today);

        if ($isNewDay) {
            $meta = $state->meta_json ?? [];
            unset($meta['budget_paused_at'], $meta['budget_scope'], $meta['budget_spent_usd']);

            $state->forceFill([
                'requests_today' => 0,
                'cost_today_usd' => 0,
                'counters_date' => $today,
                'status' => $state->isPaused() ? ProviderState::STATUS_OK : $state->status,
                'circuit_open_until' => $state->isPaused() ? null : $state->circuit_open_until,
                'meta_json' => $meta,
            ])->save();
        }

        return $state;
    }

    /**
     * Setzt den Provider fuer den Rest des Tages aus.
     */
    public function pause(ProviderState $state, ContentBudgetExceededException $exception): void
    {
        if ($state->isPaused()) {
            return;
        }

        $meta = $state->meta_json ?? [];
        $meta['budget_paused_at'] = $this->now()->toIso8601String();
        $meta['budget_scope'] = $exception->scope;
        $meta['budget_spent_usd'] = round($exception->spentUsd, 4);

        $state->forceFill([
            'status' => ProviderState::STATUS_PAUSED,
            'last_error' => \Illuminate\Support\Str::limit($exception->getMessage(), 500, ''),
            // Reset um 00:00.
            'circuit_open_until' => $this->now()->addDay()->startOfDay(),
            'meta_json' => $meta,
        ])->save();

        Log::warning('Provider wegen Budget pausiert.', [
            'provider' => $state->provider,
            'scope' => $exception->scope,
            'spent_usd' => $exception->spentUsd,
            'limit_usd' => $exception->limitUsd,
        ]);

        ProviderAlarmRaised::dispatch(
            $state->provider,
            0,
            $exception->getMessage(),
            $exception->tenantId,
        );
    }

    /**
     * @throws ContentBudgetExceededException
     */
    private function assertBelow(
        string $scope,
        string $provider,
        float $spent,
        float $limit,
        ProviderState $state,
        ?int $tenantId = null,
        bool $pauses = true,
    ): void {
        if ($limit <= 0.0) {
            return;
        }

        if ($spent < $limit) {
            $this->warnIfClose($scope, $provider, $spent, $limit);

            return;
        }

        $exception = ContentBudgetExceededException::forScope($scope, $provider, $spent, $limit, $tenantId);

        if ($pauses) {
            $this->pause($state, $exception);
        }

        throw $exception;
    }

    private function warnIfClose(string $scope, string $provider, float $spent, float $limit): void
    {
        $threshold = (float) config('content.budget.warn_threshold', 0.8);

        if ($threshold <= 0.0 || $spent < $limit * $threshold) {
            return;
        }

        Log::warning('Budgetgrenze fast erreicht.', [
            'scope' => $scope,
            'provider' => $provider,
            'spent_usd' => round($spent, 4),
            'limit_usd' => round($limit, 4),
        ]);
    }

    private function now(): \Illuminate\Support\Carbon
    {
        return $this->clock !== null ? ($this->clock)() : now();
    }

    private function today(): \Illuminate\Support\Carbon
    {
        return $this->now()->copy()->startOfDay();
    }
}
