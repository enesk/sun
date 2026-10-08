<?php

declare(strict_types=1);

namespace App\Turnstile\Monitoring;

use App\Models\Tenant;
use App\Turnstile\Enums\VerificationOutcome;
use App\Turnstile\Support\TurnstileLogConnection;
use App\Turnstile\Support\TurnstileStats;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Tagesbericht des Bot-Schutzes je Portal (#12): Registrierungen, Eintraege,
 * blockierte Pruefungen und neue Quarantaenefaelle (#10) eines Kalendertags.
 *
 * Quellen — bewusst je Zahl genau eine:
 *
 * | Zahl                | Quelle                                              |
 * |---------------------|-----------------------------------------------------|
 * | Registrierungen     | zentrale `users` x `tenant_user` (Konto an dem Tag   |
 * |                     | angelegt und an dieses Portal gehaengt), EINE        |
 * |                     | Abfrage fuer alle Portale                           |
 * | Eintraege           | `companies.created_at` in der Tenant-DB, inklusive   |
 * |                     | seither geloeschter                                 |
 * | Pruefungen/blockiert| `turnstile_verifications` ueber                      |
 * |                     | TurnstileStats::portal() — dieselbe Aggregation wie  |
 * |                     | das Widget (#9), keine zweite Query-Logik            |
 * | Quarantaene         | `suspected_bot_at` an dem Tag gesetzt, Konten        |
 * |                     | zentral, Eintraege je Portal                        |
 *
 * "Registrierungen" sind fertig angelegte Konten, nicht Pruefversuche: eine
 * bestandene Pruefung heisst nur, dass das Formular die Rule passiert hat.
 * Umgekehrt ist "blockiert" immer eine Pruefung, nie ein Konto.
 *
 * Quarantaene ist eine Tageszahl (an dem Tag markiert), kein Bestand — der
 * Bestand gehoert in das Panel, nicht in einen Tagesbericht.
 *
 * Portale, deren Datenbank nicht lesbar ist, fehlen in der Tabelle und stehen
 * namentlich unter `unreadable`; der Bericht geht trotzdem raus.
 */
final class DailyReport
{
    /**
     * @return array{
     *     date: string,
     *     date_label: string,
     *     generated_at: string,
     *     unreadable: list<string>,
     *     portals: list<array<string, mixed>>,
     *     totals: array<string, int|float>
     * }
     */
    public function build(CarbonInterface $tag): array
    {
        $von = $tag->copy()->startOfDay();
        $bis = $tag->copy()->startOfDay()->addDay();

        $registrierungen = $this->accountsPerTenant($von, $bis);
        $quarantaeneKonten = $this->quarantinedAccountsPerTenant($von, $bis);

        $portale = [];
        $unlesbar = [];
        $summen = [
            'portals' => 0,
            'registrations' => 0,
            'listings' => 0,
            'checks' => 0,
            'passed' => 0,
            'blocked' => 0,
            'errors' => 0,
            'skipped' => 0,
            'quarantined_accounts' => 0,
            'quarantined_listings' => 0,
        ];

        foreach (Tenant::query()->orderBy('name')->get() as $tenant) {
            /** @var Tenant $tenant */
            $tenantId = (int) $tenant->getKey();

            try {
                $werte = TurnstileStats::portal($tenant, ['day' => [$von, $bis]]);
                $eintraege = $this->listings($von, $bis);
            } catch (Throwable $exception) {
                $unlesbar[] = (string) $tenant->name;
                Log::info('Turnstile-Tagesbericht: Portal nicht lesbar', [
                    'tenant_id' => $tenantId,
                    'exception' => $exception::class,
                ]);

                continue;
            }

            $bucket = $werte['windows']['day'];

            $zeile = [
                'tenant_id' => $tenantId,
                'name' => (string) $tenant->name,
                'registrations' => (int) ($registrierungen[$tenantId] ?? 0),
                'listings' => (int) $eintraege['created'],
                'checks' => (int) $bucket['total'],
                'passed' => (int) $bucket[VerificationOutcome::Passed->value],
                'blocked' => (int) $bucket[VerificationOutcome::Failed->value],
                'errors' => (int) $bucket[VerificationOutcome::Error->value],
                'skipped' => (int) $bucket[VerificationOutcome::Skipped->value],
                'blocked_share' => TurnstileStats::share($bucket, VerificationOutcome::Failed->value),
                'quarantined_accounts' => (int) ($quarantaeneKonten[$tenantId] ?? 0),
                'quarantined_listings' => (int) $eintraege['quarantined'],
                'actions' => array_map(
                    static fn (array $fenster): array => $fenster['day'],
                    $werte['actions']
                ),
            ];

            $portale[] = $zeile;

            $summen['portals']++;

            foreach (['registrations', 'listings', 'checks', 'passed', 'blocked', 'errors', 'skipped', 'quarantined_accounts', 'quarantined_listings'] as $schluessel) {
                $summen[$schluessel] += (int) $zeile[$schluessel];
            }
        }

        TurnstileLogConnection::forget();

        $summen['blocked_share'] = $summen['checks'] > 0 ? $summen['blocked'] / $summen['checks'] : 0.0;
        $summen['error_share'] = $summen['checks'] > 0 ? $summen['errors'] / $summen['checks'] : 0.0;

        return [
            'date' => $von->format('Y-m-d'),
            'date_label' => $von->format('d.m.Y'),
            'generated_at' => now()->toIso8601String(),
            'unreadable' => $unlesbar,
            'portals' => $portale,
            'totals' => $summen,
        ];
    }

    /**
     * Neue Konten je Portal — eine Abfrage fuer alle Portale, nicht eine je
     * Portal. `tenant_user` haengt das Konto an das Portal, auf dem es
     * entstanden ist (App\Listeners\User\AssignCompanyOwnerRoleOnTenantRegistration);
     * ein spaeter dazugekommenes Portal zaehlt nicht mit, weil der Tag am
     * Anlegen des Kontos haengt.
     *
     * @return array<int, int>
     */
    private function accountsPerTenant(CarbonInterface $von, CarbonInterface $bis): array
    {
        return DB::connection('central')
            ->table('tenant_user')
            ->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->where('users.created_at', '>=', $von)
            ->where('users.created_at', '<', $bis)
            ->groupBy('tenant_user.tenant_id')
            ->selectRaw('tenant_user.tenant_id, count(*) as anzahl')
            ->pluck('anzahl', 'tenant_id')
            ->map(static fn ($anzahl): int => (int) $anzahl)
            ->all();
    }

    /**
     * Konten, die an diesem Tag in Quarantaene gingen (#10), je Portal.
     * Freigegebene zaehlen nicht mit — sie waren keine Bots.
     *
     * @return array<int, int>
     */
    private function quarantinedAccountsPerTenant(CarbonInterface $von, CarbonInterface $bis): array
    {
        if (! Schema::connection('central')->hasColumn('users', 'suspected_bot_at')) {
            return [];
        }

        return DB::connection('central')
            ->table('tenant_user')
            ->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->whereNull('users.suspected_bot_cleared_at')
            ->where('users.suspected_bot_at', '>=', $von)
            ->where('users.suspected_bot_at', '<', $bis)
            ->groupBy('tenant_user.tenant_id')
            ->selectRaw('tenant_user.tenant_id, count(*) as anzahl')
            ->pluck('anzahl', 'tenant_id')
            ->map(static fn ($anzahl): int => (int) $anzahl)
            ->all();
    }

    /**
     * Firmeneintraege des Tages im gerade angehaengten Portal. Geloeschte
     * zaehlen mit (`deleted_at` wird nicht geprueft): ein Eintrag, der am
     * selben Tag entstand und wieder verschwand, ist fuer den Bericht
     * passiert.
     *
     * `companies` hat keinen Index auf `created_at`. Der Bericht laeuft einmal
     * am Tag; ein Scan je Portal ist dafuer vertretbar, eine stuendliche
     * Abfrage waere es nicht.
     *
     * @return array{created: int, quarantined: int}
     */
    private function listings(CarbonInterface $von, CarbonInterface $bis): array
    {
        $verbindung = DB::connection(TurnstileLogConnection::NAME);

        $angelegt = (int) $verbindung->table('companies')
            ->where('created_at', '>=', $von)
            ->where('created_at', '<', $bis)
            ->count();

        $quarantaene = 0;

        if (Schema::connection(TurnstileLogConnection::NAME)->hasColumn('companies', 'suspected_bot_at')) {
            $quarantaene = (int) $verbindung->table('companies')
                ->whereNull('suspected_bot_cleared_at')
                ->where('suspected_bot_at', '>=', $von)
                ->where('suspected_bot_at', '<', $bis)
                ->count();
        }

        return ['created' => $angelegt, 'quarantined' => $quarantaene];
    }
}
