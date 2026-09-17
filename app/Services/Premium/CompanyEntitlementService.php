<?php

declare(strict_types=1);

namespace App\Services\Premium;

use App\Enums\PlanTier;
use App\Enums\PremiumFeature;
use App\Models\Portal\Company;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Einzige Freischaltquelle des Premium-Moduls (#4).
 *
 * Nur dieser Service liest companies.plan_tier, plan_ends_at und
 * plan_grace_until. Der Plan-Zustand wird je Request memoisiert und
 * zusaetzlich premium.entitlements.cache_ttl Sekunden im Cache gehalten;
 * CompanyPlanChanged leert beides. Die effektive Stufe wird bei jedem Aufruf
 * aus dem Zustand und der aktuellen Zeit berechnet, damit ein Ablauf nicht
 * vom Cache verdeckt wird.
 *
 * Als scoped registriert: der Memo-Speicher lebt nur einen Request/Job lang.
 */
class CompanyEntitlementService
{
    /**
     * @var array<string, array{tier: string, ends_at: ?int, grace_until: ?int}>
     */
    private array $memo = [];

    public function can(?Company $company, PremiumFeature $feature): bool
    {
        if ($company === null) {
            return false;
        }

        return $this->effectiveTier($company)->hasFeature($feature);
    }

    /**
     * Limit des Features fuer den Betrieb; null bedeutet unbegrenzt.
     * Ohne Freischaltung ist das Limit 0.
     */
    public function limit(Company $company, PremiumFeature $feature): ?int
    {
        if (! $this->can($company, $feature)) {
            return 0;
        }

        $key = config("premium.feature_limits.{$feature->value}");

        if (! is_string($key)) {
            return null;
        }

        return $this->effectiveTier($company)->limit($key);
    }

    /**
     * Gebuchte Stufe, solange plan_ends_at oder plan_grace_until nicht
     * ueberschritten ist; danach Free. Ohne plan_ends_at laeuft der Plan
     * unbefristet (z.B. manuell gesetzt).
     */
    public function effectiveTier(Company $company): PlanTier
    {
        $state = $this->state($company);
        $tier = PlanTier::tryFrom($state['tier']) ?? PlanTier::Free;

        if ($tier === PlanTier::Free || $state['ends_at'] === null) {
            return $tier;
        }

        $now = Carbon::now()->getTimestamp();

        if ($state['ends_at'] >= $now) {
            return $tier;
        }

        if ($state['grace_until'] !== null && $state['grace_until'] >= $now) {
            return $tier;
        }

        return PlanTier::Free;
    }

    /**
     * SQL-Ausdruck (1/0), ob der Betrieb in $table das Feature hat — fuer
     * Sortierungen in Listen, ohne jeden Betrieb einzeln zu laden. Gleiche
     * Regeln wie effectiveTier(): ein befristeter Plan gilt bis plan_ends_at
     * bzw. plan_grace_until.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    public function featureSql(PremiumFeature $feature, string $table = 'companies'): array
    {
        $tiers = array_values(array_filter(
            PlanTier::cases(),
            fn (PlanTier $tier): bool => $tier->hasFeature($feature),
        ));

        if (in_array(PlanTier::Free, $tiers, true)) {
            return ['1', []];
        }

        if ($tiers === []) {
            return ['0', []];
        }

        $now = Carbon::now();
        $placeholders = implode(', ', array_fill(0, count($tiers), '?'));

        return [
            "CASE WHEN {$table}.plan_tier IN ({$placeholders})"
            ." AND ({$table}.plan_ends_at IS NULL OR {$table}.plan_ends_at >= ? OR {$table}.plan_grace_until >= ?)"
            .' THEN 1 ELSE 0 END',
            [...array_map(fn (PlanTier $tier): string => $tier->value, $tiers), $now->toDateTimeString(), $now->toDateTimeString()],
        ];
    }

    public function isInGracePeriod(Company $company): bool
    {
        $state = $this->state($company);
        $now = Carbon::now()->getTimestamp();

        return $state['ends_at'] !== null
            && $state['ends_at'] < $now
            && $state['grace_until'] !== null
            && $state['grace_until'] >= $now;
    }

    public function forget(int $companyId, ?string $tenantId = null): void
    {
        $key = $this->cacheKey($companyId, $tenantId);

        unset($this->memo[$key]);
        $this->cache()->forget($key);
    }

    /**
     * @return array{tier: string, ends_at: ?int, grace_until: ?int}
     */
    private function state(Company $company): array
    {
        $key = $this->cacheKey((int) $company->getKey());

        return $this->memo[$key] ??= $this->cache()->remember(
            $key,
            (int) config('premium.entitlements.cache_ttl', 300),
            fn (): array => $this->readState($company),
        );
    }

    /**
     * @return array{tier: string, ends_at: ?int, grace_until: ?int}
     */
    private function readState(Company $company): array
    {
        $columns = ['plan_tier', 'plan_ends_at', 'plan_grace_until'];

        // Teilweise geladene Modelle (select ohne Plan-Felder) nachladen
        if (array_diff($columns, array_keys($company->getAttributes())) !== []) {
            $company = Company::query()->select(['id', ...$columns])->find($company->getKey()) ?? $company;
        }

        $tier = $company->getAttribute('plan_tier');

        return [
            'tier' => $tier instanceof PlanTier ? $tier->value : PlanTier::Free->value,
            'ends_at' => $company->getAttribute('plan_ends_at')?->getTimestamp(),
            'grace_until' => $company->getAttribute('plan_grace_until')?->getTimestamp(),
        ];
    }

    private function cacheKey(int $companyId, ?string $tenantId = null): string
    {
        $tenantId ??= tenant() ? (string) tenant()->getTenantKey() : 'central';

        return "premium:entitlements:{$tenantId}:{$companyId}";
    }

    private function cache(): Repository
    {
        return Cache::store(config('premium.entitlements.cache_store'));
    }
}
