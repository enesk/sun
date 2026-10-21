<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Companies\Deletion\DeleteCompanyAction;
use App\ProfileRequests\AutoRules\ContractGuard;
use App\ProfileRequests\AutoRules\RuleRunner;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Models\ProfileRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Verifizierte Loeschungen ausfuehren, deren Karenzfrist abgelaufen ist
 * (SUN-RQ #9).
 *
 * Auswahl: Typ `delete`, Status `verified`, `auto_applicable = true` und
 * `grace_until` in der Vergangenheit — gesetzt von
 * {@see \App\ProfileRequests\AutoRules\ScheduleVerifiedDeletion}. Ein
 * Stornoklick setzt den Vorgang auf `cancelled` und nimmt ihn damit aus der
 * Auswahl; dieser Befehl prueft das nicht extra nach, der Status reicht.
 *
 * Zwei Sicherungen vor jedem Lauf:
 *
 *  1. **Notschalter** `profile-requests.auto_disabled` — dann laeuft nichts.
 *     Bestehende Fristen laufen damit nicht ab, sie stehen still; nach dem
 *     Zurueckschalten werden sie abgearbeitet.
 *  2. **Vertragslage erneut pruefen** ({@see ContractGuard}). In 48 Stunden
 *     kann ein Premium-Vertrag oder ein offener Lead entstanden sein; dann
 *     wird die Vormerkung zurueckgenommen und der Vorgang bleibt zur
 *     Freigabe im Panel.
 *
 * Laeuft stuendlich (routes/console.php). ZENTRALE Tabelle, ein Lauf fuer
 * alle Portale; die Loeschung selbst wechselt je Vorgang in das Portal
 * ({@see DeleteCompanyAction}).
 */
class RequestsRunGraceDeletions extends Command
{
    protected $signature = 'requests:run-grace-deletions
        {--dry-run : Nur anzeigen, was geloescht wuerde}';

    protected $description = 'Führt verifizierte Löschungen nach Ablauf der Karenzfrist aus (#9)';

    public function __construct(
        private readonly RuleRunner $runner,
        private readonly ContractGuard $guard,
        private readonly DeleteCompanyAction $deleteCompany,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->runner->isDisabled()) {
            $this->warn('Auto-Regeln sind abgeschaltet (profile-requests.auto_disabled) — keine Löschung.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $zaehler = ['geloescht' => 0, 'gestoppt' => 0, 'fehler' => 0];

        foreach ($this->due()->cursor() as $request) {
            /** @var ProfileRequest $request */
            $grund = $this->guard->blockingReason($request);

            if ($grund !== null) {
                $zaehler['gestoppt']++;

                $this->line(sprintf('REQ-%d gestoppt: %s', $request->id, $grund));

                if (! $dryRun) {
                    $request->auto_applicable = false;
                    $request->grace_until = null;
                    $request->save();

                    $request->recordEvent(RequestEventType::NOTE, [
                        'note' => 'Automatische Löschung zurückgenommen — bleibt zur Freigabe',
                        'rule' => 'run_grace_deletions',
                        'grund' => $grund,
                    ]);
                }

                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    'REQ-%d (Portal %s, Betrieb %s) wuerde geloescht — Frist war %s',
                    $request->id,
                    $request->tenant_id ?? '—',
                    $request->company_id ?? '—',
                    $request->grace_until?->format('d.m.Y H:i') ?? '—',
                ));

                $zaehler['geloescht']++;

                continue;
            }

            try {
                $result = $this->deleteCompany->forRequest($request);

                $zaehler['geloescht']++;

                $this->line(sprintf(
                    'REQ-%d: %s',
                    $request->id,
                    $result->ok() ? 'gelöscht' : 'gelöscht mit offenen Schritten',
                ));
            } catch (Throwable $exception) {
                $zaehler['fehler']++;

                Log::error('profile-requests: automatische Löschung fehlgeschlagen', [
                    'request_id' => $request->id,
                    'exception' => $exception->getMessage(),
                ]);

                $request->recordEvent(RequestEventType::NOTE, [
                    'note' => 'Automatische Löschung fehlgeschlagen — bleibt zur Freigabe',
                    'rule' => 'run_grace_deletions',
                    'fehler' => Str::limit($exception->getMessage(), 300),
                ]);
            }
        }

        $this->info(sprintf(
            '%d gelöscht, %d gestoppt, %d Fehler.',
            $zaehler['geloescht'],
            $zaehler['gestoppt'],
            $zaehler['fehler'],
        ));

        return $zaehler['fehler'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ProfileRequest>
     */
    private function due()
    {
        return ProfileRequest::query()
            ->ofType(RequestType::DELETE)
            ->dueForAutoApply()
            ->whereNotNull('grace_until')
            ->whereNotNull('tenant_id')
            ->whereNotNull('company_id')
            ->orderBy('grace_until')
            ->orderBy('id');
    }
}
