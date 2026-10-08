<?php

declare(strict_types=1);

namespace App\Turnstile\Monitoring;

use App\Models\Tenant;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\VerificationOutcome;
use App\Turnstile\Mail\BotProtectionAlert;
use App\Turnstile\Models\TenantTurnstileSetting;
use App\Turnstile\Support\BotProtectionRecipients;
use App\Turnstile\Support\TurnstileLogConnection;
use App\Turnstile\Support\TurnstileStats;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Stuendliche Ueberwachung des Verifikations-Logs je Portal (#12).
 *
 * Vier Alarme, alle aus derselben Stundenauswertung
 * ({@see TurnstileStats::portal()}, dieselbe Aggregation wie das Widget):
 *
 *  1. error_share      — Anteil `error` ueber der Schwelle (Siteverify stoert
 *                        oder das Secret ist falsch)
 *  2. error_count      — mehr als N Fehler in der Stunde; bei fail_mode=open
 *                        lief das Formular in dieser Zeit ohne Turnstile
 *  3. block_share_low  — viele Registrierungspruefungen, fast keine
 *                        abgewiesen: Hinweis auf eine Umgehung des Formulars
 *  4. block_share_high — fast alles abgewiesen: kaputtes oder falsch
 *                        konfiguriertes Widget, echte Nutzer kommen nicht durch
 *
 * Gelesen wird die zurueckliegende Stunde ab dem Lauf, entdoppelt wird je
 * Portal, Alarmtyp und Kalenderstunde (Cache-Key
 * `turnstile:alert:<tenant>:<typ>:<YmdH>`) — hoechstens eine Mail je Stunde und
 * Portal und Typ, auch wenn der Lauf doppelt kommt.
 *
 * Laeuft ZENTRAL, nicht je Portal: die Verbindung `tenant` wird nur auf die
 * Log-Tabelle gerichtet ({@see TurnstileLogConnection}), Tenancy bleibt aus.
 * Darum zeigt auch der Cache auf den zentralen Store und ein Portal kann den
 * Alarm eines anderen nicht verdecken.
 */
final class VerificationMonitor
{
    /**
     * @return array{
     *     checked_at: string,
     *     hour: string,
     *     portals: int,
     *     unreadable: list<string>,
     *     alerts: list<VerificationAlert>,
     *     sent: list<VerificationAlert>,
     *     recipients: list<string>
     * }
     */
    public function run(?CarbonInterface $jetzt = null, bool $versenden = true): array
    {
        $jetzt ??= now();
        $von = $jetzt->copy()->subHour();
        $stunde = $jetzt->format('YmdH');

        $alarme = [];
        $verschickt = [];
        $unlesbar = [];
        $portale = 0;

        /** @var \Illuminate\Database\Eloquent\Collection<int, TenantTurnstileSetting> $einstellungen */
        $einstellungen = TenantTurnstileSetting::query()->get()->keyBy('tenant_id');

        foreach (Tenant::query()->orderBy('name')->get() as $tenant) {
            /** @var Tenant $tenant */
            try {
                $werte = TurnstileStats::portal($tenant, ['1h' => [$von, $jetzt]]);
            } catch (Throwable $exception) {
                $unlesbar[] = (string) $tenant->name;
                Log::info('Turnstile-Monitor: Portal nicht lesbar', [
                    'tenant_id' => (int) $tenant->getKey(),
                    'exception' => $exception::class,
                ]);

                continue;
            }

            $portale++;

            $failMode = (string) ($einstellungen->get((int) $tenant->getKey())?->fail_mode
                ?: config('turnstile.fail_mode', 'open'));

            foreach ($this->alertsFor($tenant, $werte, $failMode) as $alarm) {
                $alarme[] = $alarm;
            }
        }

        TurnstileLogConnection::forget();

        $empfaenger = BotProtectionRecipients::emails();

        foreach ($alarme as $alarm) {
            if (! $versenden || ! $this->claim($alarm, $stunde)) {
                continue;
            }

            if ($empfaenger === []) {
                Log::warning('Turnstile-Alarm ohne Empfaenger', $alarm->toArray());

                continue;
            }

            Mail::to($empfaenger)->send(new BotProtectionAlert($alarm));
            Log::warning('Turnstile-Alarm verschickt', $alarm->toArray());

            $verschickt[] = $alarm;
        }

        return [
            'checked_at' => $jetzt->toIso8601String(),
            'hour' => $stunde,
            'portals' => $portale,
            'unreadable' => $unlesbar,
            'alerts' => $alarme,
            'sent' => $verschickt,
            'recipients' => $empfaenger,
        ];
    }

    /**
     * Schwellen aus config/turnstile.php auf die Stundenwerte eines Portals
     * anwenden. Ein Portal kann mehrere Alarme auf einmal haben (viele Fehler
     * UND kaum Blockaden); jeder laeuft fuer sich.
     *
     * @param  array{windows: array<string, array<string, int>>, actions: array<string, array<string, array<string, int>>>}  $werte
     * @return list<VerificationAlert>
     */
    private function alertsFor(Tenant $tenant, array $werte, string $failMode): array
    {
        $bucket = $werte['windows']['1h'];
        $registrierung = $werte['actions'][TurnstileAction::Registration->value]['1h'];

        $gesamt = (int) $bucket['total'];
        $fehler = (int) $bucket[VerificationOutcome::Error->value];
        $fehlerAnteil = TurnstileStats::share($bucket, VerificationOutcome::Error->value);

        $name = (string) $tenant->name;
        $tenantId = (int) $tenant->getKey();
        $alarme = [];

        $offen = $failMode !== 'closed';
        $folgeFehler = $offen
            ? __('Der Fail-Mode dieses Portals ist "open": Anfragen laufen in dieser Zeit ohne Turnstile durch. Honeypot, Mindest-Ausfüllzeit und Rate-Limits greifen weiter.')
            : __('Der Fail-Mode dieses Portals ist "closed": Registrierung und Firmeneintragung sind in dieser Zeit blockiert.');

        $anteilSchwelle = (float) config('turnstile.alerts.error_share.threshold', 0.3);
        $mindestPruefungen = (int) config('turnstile.alerts.error_share.min_checks', 10);

        if ($gesamt >= $mindestPruefungen && $fehlerAnteil > $anteilSchwelle) {
            $alarme[] = new VerificationAlert(
                type: VerificationAlert::TYPE_ERROR_SHARE,
                tenantId: $tenantId,
                tenantName: $name,
                headline: __('Fehlerquote :percent in der letzten Stunde', [
                    'percent' => $this->prozent($fehlerAnteil),
                ]),
                message: __('Von :total Prüfungen endeten :errors mit einem Fehler (:percent, Schwelle :threshold). Siteverify antwortet nicht verlässlich — oder das Secret des Portals ist falsch.', [
                    'total' => $gesamt,
                    'errors' => $fehler,
                    'percent' => $this->prozent($fehlerAnteil),
                    'threshold' => $this->prozent($anteilSchwelle),
                ]),
                consequence: $folgeFehler,
                numbers: [
                    __('Prüfungen') => $gesamt,
                    __('Fehler') => $fehler,
                    __('Fehlerquote') => $this->prozent($fehlerAnteil),
                    __('Fail-Mode') => $failMode,
                ],
            );
        }

        $fehlerSchwelle = (int) config('turnstile.alerts.error_count.threshold', 20);

        if ($fehler > $fehlerSchwelle) {
            $alarme[] = new VerificationAlert(
                type: VerificationAlert::TYPE_ERROR_COUNT,
                tenantId: $tenantId,
                tenantName: $name,
                headline: __(':errors Fehler in der letzten Stunde', ['errors' => $fehler]),
                message: __(':errors von :total Prüfungen konnten nicht entschieden werden (Schwelle :threshold).', [
                    'errors' => $fehler,
                    'total' => $gesamt,
                    'threshold' => $fehlerSchwelle,
                ]),
                consequence: $folgeFehler,
                numbers: [
                    __('Prüfungen') => $gesamt,
                    __('Fehler') => $fehler,
                    __('Fehlerquote') => $this->prozent($fehlerAnteil),
                    __('Fail-Mode') => $failMode,
                ],
            );
        }

        $mindestRegistrierungen = (int) config('turnstile.alerts.block_share.min_registrations', 50);
        $registrierungen = (int) $registrierung['total'];
        $blockiert = (int) $registrierung[VerificationOutcome::Failed->value];
        $blockQuote = TurnstileStats::share($registrierung, VerificationOutcome::Failed->value);

        if ($registrierungen <= $mindestRegistrierungen) {
            return $alarme;
        }

        $untergrenze = (float) config('turnstile.alerts.block_share.low', 0.01);
        $obergrenze = (float) config('turnstile.alerts.block_share.high', 0.95);

        $zahlen = [
            __('Registrierungsprüfungen') => $registrierungen,
            __('Abgewiesen') => $blockiert,
            __('Blockierungsquote') => $this->prozent($blockQuote),
        ];

        if ($blockQuote < $untergrenze) {
            $alarme[] = new VerificationAlert(
                type: VerificationAlert::TYPE_BLOCK_SHARE_LOW,
                tenantId: $tenantId,
                tenantName: $name,
                headline: __('Blockierungsquote nur :percent bei :count Registrierungen', [
                    'percent' => $this->prozent($blockQuote),
                    'count' => $registrierungen,
                ]),
                message: __(':count Registrierungsprüfungen in einer Stunde, davon nur :blocked abgewiesen (:percent, Untergrenze :threshold). Das sieht nach einer Umgehung des Formulars aus — ein Weg, der die Rule nicht durchläuft.', [
                    'count' => $registrierungen,
                    'blocked' => $blockiert,
                    'percent' => $this->prozent($blockQuote),
                    'threshold' => $this->prozent($untergrenze),
                ]),
                consequence: __('Prüfen, ob alle Registrierwege (auch API, OAuth und Checkout) über TurnstileRule laufen und ob die Pflicht für dieses Portal eingeschaltet ist.'),
                numbers: $zahlen,
            );
        }

        if ($blockQuote > $obergrenze) {
            $alarme[] = new VerificationAlert(
                type: VerificationAlert::TYPE_BLOCK_SHARE_HIGH,
                tenantId: $tenantId,
                tenantName: $name,
                headline: __('Blockierungsquote :percent bei :count Registrierungen', [
                    'percent' => $this->prozent($blockQuote),
                    'count' => $registrierungen,
                ]),
                message: __(':blocked von :count Registrierungsprüfungen wurden abgewiesen (:percent, Obergrenze :threshold). Entweder läuft eine Bot-Welle — oder das Widget ist kaputt und echte Nutzer kommen nicht durch (falscher Sitekey, Hostname nicht im Widget, Action-Mismatch).', [
                    'blocked' => $blockiert,
                    'count' => $registrierungen,
                    'percent' => $this->prozent($blockQuote),
                    'threshold' => $this->prozent($obergrenze),
                ]),
                consequence: __('Das Formular weist derzeit fast jeden ab. Fehlercodes im Verifikations-Log prüfen; im Zweifel die Pflicht für dieses Portal abschalten, bis die Ursache steht.'),
                numbers: $zahlen,
            );
        }

        return $alarme;
    }

    /**
     * Entdopplung: true nur beim ersten Alarm dieser Art in dieser Stunde.
     * Cache::add ist atomar, zwei gleichzeitige Laeufe schicken also nicht
     * beide.
     */
    private function claim(VerificationAlert $alarm, string $stunde): bool
    {
        return $this->cache()->add($alarm->cacheKey($stunde), true, 3600);
    }

    private function cache(): Repository
    {
        $store = config('turnstile.alerts.cache_store');

        return $store === null || $store === '' ? Cache::store() : Cache::store((string) $store);
    }

    private function prozent(float $anteil): string
    {
        return number_format($anteil * 100, 1, ',', '.').' %';
    }
}
