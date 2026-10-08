<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\VerificationOutcome;
use App\Turnstile\Support\BotProtectionAccess;
use App\Turnstile\Support\TurnstileStats;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Kennzahlen des Bot-Schutzes (#9): Pruefungen, bestanden, blockiert und
 * Fehler der letzten 24 Stunden und 7 Tage, dazu der Anteil blockierter
 * Versuche je Formular.
 *
 * Summiert ueber alle Portale ({@see TurnstileStats}), 60 Sekunden gecacht.
 *
 * Liegt der Anteil der Fehler-Outcomes in der letzten Stunde ueber 30 %, steht
 * als erste Kachel ein roter Hinweis: dann antwortet Siteverify nicht
 * verlaesslich, und bei fail_mode=open laufen Anfragen ungeprueft durch
 * (Alarm SUN-TS-011). Die Mail dazu schickt der Listener
 * {@see \App\Listeners\Turnstile\AlertOnSiteverifyUnreachable} binnen
 * Minuten (#19), die Quotenaufsicht bleibt bei `turnstile:monitor` (#12).
 *
 * Danach, und nur solange ueberhaupt Fehler anliegen, eine Kachel je
 * betroffenem Portal mit dessen Fehlerquote der letzten Stunde (#19): die
 * netzweite Summe verwaescht einen Ausfall, der nur eine Widget-Gruppe oder
 * ein einzelnes Portal mit eigenem Secret trifft.
 */
class BotProtectionStatsWidget extends BaseWidget
{
    protected static ?int $sort = 20;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return BotProtectionAccess::allowed();
    }

    protected function getHeading(): ?string
    {
        return __('Bot-Schutz');
    }

    protected function getDescription(): ?string
    {
        $stats = $this->stats();

        $beschreibung = __('Letzte 24 Stunden, :portals Portale', ['portals' => $stats['portals']]);

        if ($stats['unreadable'] !== []) {
            $beschreibung .= ' · '.__('ohne Log: :portals', ['portals' => implode(', ', $stats['unreadable'])]);
        }

        return $beschreibung;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $stats = $this->stats();
        $tag = $stats['windows']['24h'];
        $woche = $stats['windows']['7d'];
        $stunde = $stats['windows']['1h'];

        $kacheln = [];

        if (TurnstileStats::hasErrorAlert($stats)) {
            $kacheln[] = Stat::make(
                __('Fehlerquote letzte Stunde'),
                TurnstileStats::percent($stunde, VerificationOutcome::Error->value)
            )
                ->description(__('Über 30 % Fehler: Siteverify antwortet nicht verlässlich. Prüfungen laufen bei fail_mode=open ungeprüft durch.'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger');
        }

        foreach ($this->errorPortals($stats) as $portal) {
            $kacheln[] = Stat::make(
                __('Fehler :portal', ['portal' => $portal['tenant']]),
                TurnstileStats::formatShare($portal['share']).' %'
            )
                ->description(__(':errors von :total Prüfungen in der letzten Stunde', [
                    'errors' => $portal['errors'],
                    'total' => $portal['total'],
                ]))
                ->descriptionIcon('heroicon-m-signal-slash')
                ->color($portal['share'] > TurnstileStats::ERROR_ALERT_SHARE ? 'danger' : 'warning');
        }

        $kacheln[] = Stat::make(__('Prüfungen'), (string) $tag['total'])
            ->description(__('7 Tage: :count', ['count' => $woche['total']]))
            ->icon('heroicon-m-shield-check');

        $kacheln[] = Stat::make(
            __('Bestanden'),
            (string) $tag[VerificationOutcome::Passed->value]
        )
            ->description(TurnstileStats::percent($tag, VerificationOutcome::Passed->value).' '.__('der Prüfungen'))
            ->color('success');

        $kacheln[] = Stat::make(
            __('Blockiert'),
            (string) $tag[VerificationOutcome::Failed->value]
        )
            ->description(TurnstileStats::percent($tag, VerificationOutcome::Failed->value).' '.__('der Prüfungen'))
            ->color($tag[VerificationOutcome::Failed->value] > 0 ? 'danger' : 'gray');

        $kacheln[] = Stat::make(
            __('Fehler'),
            (string) $tag[VerificationOutcome::Error->value]
        )
            ->description(TurnstileStats::percent($tag, VerificationOutcome::Error->value).' '.__('der Prüfungen'))
            ->color($tag[VerificationOutcome::Error->value] > 0 ? 'warning' : 'gray');

        foreach (TurnstileAction::cases() as $action) {
            $bucket = $stats['actions'][$action->value]['24h'] ?? [];

            $kacheln[] = Stat::make(
                __('Blockiert: :action', ['action' => $action->label()]),
                TurnstileStats::percent($bucket, VerificationOutcome::Failed->value)
            )
                ->description(__(':failed von :total Prüfungen', [
                    'failed' => (int) ($bucket[VerificationOutcome::Failed->value] ?? 0),
                    'total' => (int) ($bucket['total'] ?? 0),
                ]))
                ->color(TurnstileStats::share($bucket, VerificationOutcome::Failed->value) > 0.5 ? 'danger' : 'gray');
        }

        return $kacheln;
    }

    /**
     * Hoechstens drei Portale, sonst schiebt eine netzweite Stoerung die
     * eigentlichen Kennzahlen aus dem Bild. Der Rest steht im Verifikations-Log.
     *
     * @param  array<string, mixed>  $stats
     * @return list<array{tenant_id: int, tenant: string, total: int, errors: int, share: float}>
     */
    private function errorPortals(array $stats): array
    {
        return array_slice(TurnstileStats::errorPortals($stats), 0, 3);
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        return TurnstileStats::network();
    }
}
