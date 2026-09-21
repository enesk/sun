<?php

declare(strict_types=1);

namespace App\Guide\Orchestration;

use App\Guide\Jobs\DispatchDueTopicsJob;
use App\Guide\Models\Central\GuideRunState;
use App\Guide\Models\TenantGuideSetting;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tageslauf des Ratgebersystems ueber alle Tenants (#13), Einstieg
 * `php artisan guide:daily`.
 *
 * Stufen:
 *  - dispatch: je aktivem Tenant (tenant_guide_settings.is_active) ein
 *    DispatchDueTopicsJob auf guide-dispatch, der die faelligen Themen
 *    gestaffelt ueber das Laufzeitfenster einreiht;
 *  - watchdog: RunWatchdog je aktivem Tenant;
 *  - report: Tagesbericht (DailyReportBuilder), siehe GuideDaily.
 *
 * Jeder Tenant laeuft in seinem eigenen try/catch: ein Fehler eines Portals
 * wird protokolliert und haelt die uebrigen nicht auf.
 *
 * Ist der Tageslauf global pausiert (GuideRunState, #33), ueberspringen
 * dispatch und watchdog jedes Portal.
 */
final class DailyOrchestrator
{
    public const STAGE_DISPATCH = 'dispatch';

    public const STAGE_WATCHDOG = 'watchdog';

    public const STAGE_REPORT = 'report';

    public const STAGES = [self::STAGE_DISPATCH, self::STAGE_WATCHDOG, self::STAGE_REPORT];

    public const OUTCOME_QUEUED = 'queued';

    public const OUTCOME_DONE = 'done';

    public const OUTCOME_SKIPPED = 'skipped';

    public const OUTCOME_FAILED = 'failed';

    public function __construct(
        private readonly RunWatchdog $watchdog,
    ) {}

    /**
     * Laufzeitfenster eines Tages in guide.timezone; leere Werte = Vorgabe
     * aus config. Ein Ende vor dem Beginn reicht in den Folgetag.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function windowFor(Carbon $day, ?string $start = null, ?string $end = null): array
    {
        $timezone = (string) config('guide.timezone', 'Europe/Berlin');
        $date = $day->copy()->setTimezone($timezone)->toDateString();

        $from = Carbon::parse($date.' '.self::time($start, (string) config('guide.run_window_start', '02:00')), $timezone);
        $until = Carbon::parse($date.' '.self::time($end, (string) config('guide.run_window_end', '07:00')), $timezone);

        if ($until->lessThanOrEqualTo($from)) {
            $until->addDay();
        }

        return [$from, $until];
    }

    /**
     * @param  bool  $sync  DispatchDueTopicsJob im selben Prozess statt auf guide-dispatch
     * @param  bool  $ignoreWindow  sofort einreihen statt ueber das Fenster zu staffeln
     * @return array<int, array{tenant_id: int, name: string, outcome: string, message: string}>
     */
    public function dispatch(Carbon $day, ?Tenant $only = null, bool $sync = false, bool $ignoreWindow = false): array
    {
        return $this->eachTenant($only, function (Tenant $tenant) use ($day, $sync, $ignoreWindow): array {
            $job = new DispatchDueTopicsJob((int) $tenant->getKey(), $day->toDateString(), $ignoreWindow);

            if ($sync) {
                dispatch_sync($job);

                return [self::OUTCOME_DONE, __('Tageslauf eingereiht.')];
            }

            dispatch($job);

            return [self::OUTCOME_QUEUED, __('DispatchDueTopicsJob eingereiht.')];
        });
    }

    /**
     * @return array<int, array{tenant_id: int, name: string, outcome: string, message: string}>
     */
    public function watchdog(?Tenant $only = null): array
    {
        return $this->eachTenant($only, function (Tenant $tenant): array {
            $result = $tenant->run(fn (): array => $this->watchdog->check((int) $tenant->getKey()));

            return [self::OUTCOME_DONE, __(':restarted neu angesetzt, :failed aufgegeben, :waiting wartend.', [
                'restarted' => $result['restarted'],
                'failed' => $result['failed'],
                'waiting' => $result['waiting'],
            ])];
        });
    }

    /**
     * @param  callable(Tenant): array{0: string, 1: string}  $step
     * @return array<int, array{tenant_id: int, name: string, outcome: string, message: string}>
     */
    private function eachTenant(?Tenant $only, callable $step): array
    {
        $tenants = $only !== null ? collect([$only]) : Tenant::query()->orderBy('id')->get();
        $results = [];
        $paused = GuideRunState::isPaused();

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            $result = ['tenant_id' => (int) $tenant->getKey(), 'name' => (string) ($tenant->name ?? $tenant->domain ?? $tenant->getKey())];

            if ($paused) {
                $results[] = [...$result, 'outcome' => self::OUTCOME_SKIPPED, 'message' => __('Tageslauf global pausiert.')];

                continue;
            }

            try {
                $active = (bool) $tenant->run(static fn (): bool => (bool) TenantGuideSetting::current()->is_active);

                if (! $active) {
                    $results[] = [...$result, 'outcome' => self::OUTCOME_SKIPPED, 'message' => __('Ratgebersystem für dieses Portal nicht aktiv.')];

                    continue;
                }

                [$outcome, $message] = $step($tenant);
                $results[] = [...$result, 'outcome' => $outcome, 'message' => $message];
            } catch (Throwable $exception) {
                report($exception);
                Log::error('Guide-Tageslauf fuer Tenant fehlgeschlagen.', ['tenant_id' => $result['tenant_id'], 'error' => $exception->getMessage()]);

                $results[] = [...$result, 'outcome' => self::OUTCOME_FAILED, 'message' => mb_substr($exception->getMessage(), 0, 300)];
            }
        }

        return $results;
    }

    private static function time(?string $value, string $fallback): string
    {
        $value = trim((string) $value);

        return $value !== '' ? substr($value, 0, 5) : $fallback;
    }
}
