<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ProfileRequests\Actions\AnonymizeClosedRequest;
use App\ProfileRequests\Models\ProfileRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aufbewahrungsfrist geschlossener Vorgaenge (SUN-RQ #15).
 *
 * Nach `profile-requests.anonymize_closed.after_months` (24) fallen die
 * Kontaktfelder eines abgeschlossenen Vorgangs; Typ, Portal, Zeitpunkte,
 * Diff-Feldnamen und Protokoll bleiben. Was genau geht und was bleibt,
 * steht in {@see AnonymizeClosedRequest}.
 *
 * Eigener Lauf und kein Anhaengsel an `requests:purge-raw-mail`: die beiden
 * Fristen sind verschieden lang (90 Tage gegen 24 Monate) und messen von
 * verschiedenen Zeitpunkten — die Rohmail vom Eingang, die Anonymisierung
 * vom Abschluss.
 *
 * Laeuft taeglich (routes/console.php). ZENTRALE Tabelle, ein Lauf fuer alle
 * Portale.
 */
class RequestsAnonymizeClosed extends Command
{
    protected $signature = 'requests:anonymize-closed
        {--dry-run : Nur anzeigen, was anonymisiert wuerde}
        {--limit=500 : Hoechstzahl Vorgaenge je Lauf}';

    protected $description = 'Entfernt den Personenbezug geschlossener Anfragen nach Ablauf der Aufbewahrungsfrist';

    public function handle(AnonymizeClosedRequest $anonymize): int
    {
        $cutoff = AnonymizeClosedRequest::cutoff();
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $this->line(sprintf(
            'Stichtag: Abschluss vor %s (%d Monate).',
            $cutoff->toDateString(),
            AnonymizeClosedRequest::afterMonths(),
        ));

        $faellig = AnonymizeClosedRequest::due($cutoff)->limit($limit)->get();

        if ($faellig->isEmpty()) {
            $this->info('Kein Vorgang fällig.');

            return self::SUCCESS;
        }

        $erledigt = 0;
        $fehler = 0;

        foreach ($faellig as $request) {
            /** @var ProfileRequest $request */
            if ($dryRun) {
                $this->line(sprintf(
                    'REQ-%d (%s, %s, Portal %s, Abschluss %s) würde anonymisiert',
                    $request->id,
                    $request->type?->value ?? '?',
                    $request->status->value,
                    $request->tenant_id ?? '—',
                    ($request->applied_at ?? $request->updated_at)?->toDateString() ?? '?',
                ));

                $erledigt++;

                continue;
            }

            try {
                $ergebnis = $anonymize($request);

                if ($ergebnis['ok']) {
                    $erledigt++;

                    continue;
                }

                // Nicht fällig, obwohl die Abfrage ihn gefunden hat — das ist
                // ein Widerspruch und gehoert gemeldet, nicht verschluckt.
                $fehler++;

                Log::warning('profile-requests: Anonymisierung abgelehnt', [
                    'request_id' => $request->id,
                    'message' => $ergebnis['message'],
                ]);
            } catch (Throwable $e) {
                $fehler++;

                Log::warning('profile-requests: Anonymisierung fehlgeschlagen', [
                    'request_id' => $request->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf(
            '%d Vorgang/Vorgaenge %s, %d Fehler.',
            $erledigt,
            $dryRun ? 'wären fällig' : 'anonymisiert',
            $fehler,
        ));

        return $fehler > 0 ? self::FAILURE : self::SUCCESS;
    }
}
