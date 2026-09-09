<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\TenantContentSetting;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Database\TenantCollection;
use Throwable;

/**
 * Rollout-Schalter der Content-Pipeline (#26).
 *
 * Der gestaffelte Go-Live schaltet Portale einzeln frei. Massgeblich ist
 * `tenant_content_settings.is_active` in der Tenant-Datenbank — eine
 * Zeile je Portal, Vorgabe `false`. Diese Klasse ist die einzige Stelle,
 * die den Schalter liest und schreibt, damit Orchestrator, Konsole und
 * Panel nicht drei Auslegungen von "aktiv" haben.
 *
 * Die Lesefunktion ist bewusst fehlertolerant: laeuft die Migration auf
 * einem Portal noch nicht oder ist die Tenant-Datenbank gerade nicht
 * erreichbar, gilt das Portal als inaktiv. Ein Rollout, der aus Versehen
 * zu wenig macht, ist harmloser als einer, der zu viel veroeffentlicht.
 */
final class TenantRollout
{
    public function isActive(Tenant $tenant): bool
    {
        return $this->rolloutState($tenant)['active'];
    }

    /**
     * Die freigeschalteten Portale — die Menge, auf der Orchestrator,
     * Wachhund und die kostenpflichtigen Konsolenbefehle arbeiten.
     */
    public function activeTenants(): TenantCollection
    {
        /** @var TenantCollection $tenants */
        $tenants = Tenant::all()
            ->filter(fn (Tenant $tenant): bool => $this->isActive($tenant))
            ->values();

        return $tenants;
    }

    /**
     * Die Portale, die an einem bestimmten Tag schon freigeschaltet waren
     * (#102). Ein Portal, das erst spaeter freigeschaltet wurde, gehoert
     * nicht in den Tagesbericht dieses Tages; ohne `activated_at` zaehlt
     * das Portal ab dem ersten Tag mit.
     */
    public function activeTenantsOn(DateTimeInterface $date): TenantCollection
    {
        $end = CarbonImmutable::instance($date)->endOfDay();

        /** @var TenantCollection $tenants */
        $tenants = Tenant::all()
            ->filter(function (Tenant $tenant) use ($end): bool {
                $state = $this->rolloutState($tenant);

                if (! $state['active']) {
                    return false;
                }

                return $state['activated_at'] === null
                    || $state['activated_at']->lessThanOrEqualTo($end);
            })
            ->values();

        return $tenants;
    }

    public function activate(Tenant $tenant): bool
    {
        return $this->write($tenant, true);
    }

    public function deactivate(Tenant $tenant): bool
    {
        return $this->write($tenant, false);
    }

    /**
     * Auto-Live-Schwelle eines Portals setzen (#26). Der Go-Live gibt sie
     * je Rollout-Welle vor; ohne diesen Weg muesste sie fuer jedes Portal
     * einzeln im Panel gepflegt werden. YMYL-Portale bekommen nie weniger
     * als content.quality.ymyl_auto_approve_score.
     */
    public function setAutoPublishThreshold(Tenant $tenant, int $threshold): bool
    {
        $threshold = max(50, min(100, $threshold));
        $ymylFloor = (int) config('content.quality.ymyl_auto_approve_score', 90);

        try {
            $tenant->run(static function () use ($threshold, $ymylFloor): void {
                $settings = TenantContentSetting::current();

                // Ein YMYL-Portal faellt nie unter die strengere Schwelle,
                // auch wenn die Rollout-Welle eine niedrigere vorgibt.
                $settings->forceFill([
                    'auto_publish_threshold' => $settings->is_ymyl ? max($threshold, $ymylFloor) : $threshold,
                ])->save();
            });

            return true;
        } catch (Throwable $exception) {
            Log::error('Auto-Live-Schwelle nicht schreibbar.', [
                'tenant_id' => (int) $tenant->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Zustand aller Portale fuer Konsole und Abnahme-Checkliste.
     *
     * @return array<int, array{tenant: Tenant, active: bool, articles_per_day: int, threshold: int, ymyl: bool, gsc_property: ?string}>
     */
    public function status(): array
    {
        $rows = [];

        /** @var Tenant $tenant */
        foreach (Tenant::all() as $tenant) {
            $settings = $this->settingsOf($tenant);

            $rows[] = [
                'tenant' => $tenant,
                'active' => (bool) ($settings?->is_active ?? false),
                'articles_per_day' => (int) ($settings?->articles_per_day ?? 0),
                'threshold' => (int) ($settings?->auto_publish_threshold ?? 0),
                'ymyl' => (bool) ($settings?->is_ymyl ?? false),
                'gsc_property' => $settings?->gsc_property,
            ];
        }

        return $rows;
    }

    /**
     * Schalterstand eines Portals. Fehlt die Tabelle oder ist die
     * Tenant-Datenbank nicht erreichbar, gilt das Portal als inaktiv.
     *
     * @return array{active: bool, activated_at: ?CarbonImmutable}
     */
    private function rolloutState(Tenant $tenant): array
    {
        try {
            /** @var array{active: bool, activated_at: ?string} $state */
            $state = $tenant->run(static function (): array {
                $settings = TenantContentSetting::query()->first();
                $activatedAt = $settings?->activated_at;

                return [
                    'active' => (bool) ($settings?->is_active ?? false),
                    'activated_at' => $activatedAt !== null
                        ? CarbonImmutable::parse($activatedAt)->toIso8601String()
                        : null,
                ];
            });
        } catch (Throwable $exception) {
            Log::warning('Rollout-Schalter nicht lesbar, Portal gilt als inaktiv.', [
                'tenant_id' => (int) $tenant->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return ['active' => false, 'activated_at' => null];
        }

        return [
            'active' => $state['active'],
            'activated_at' => $state['activated_at'] !== null
                ? CarbonImmutable::parse($state['activated_at'])
                : null,
        ];
    }

    private function settingsOf(Tenant $tenant): ?TenantContentSetting
    {
        try {
            return $tenant->run(static fn (): ?TenantContentSetting => TenantContentSetting::query()->first());
        } catch (Throwable $exception) {
            Log::warning('Einstellungen des Portals nicht lesbar.', [
                'tenant_id' => (int) $tenant->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function write(Tenant $tenant, bool $active): bool
    {
        try {
            $tenant->run(static function () use ($active): void {
                TenantContentSetting::current()->forceFill([
                    'is_active' => $active,
                    'activated_at' => $active ? now() : null,
                ])->save();
            });

            Log::info($active ? 'Portal fuer die Content-Pipeline freigeschaltet.' : 'Portal aus der Content-Pipeline genommen.', [
                'tenant_id' => (int) $tenant->getKey(),
                'domain' => (string) $tenant->domain,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::error('Rollout-Schalter nicht schreibbar.', [
                'tenant_id' => (int) $tenant->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
