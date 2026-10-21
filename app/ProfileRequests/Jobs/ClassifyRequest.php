<?php

declare(strict_types=1);

namespace App\ProfileRequests\Jobs;

use App\ProfileRequests\Ai\ClassificationResult;
use App\ProfileRequests\Ai\RequestClassifier;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Verification\RequestVerifier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Klassifikation einer E-Mail-Anfrage (SUN-RQ #6).
 *
 * Wird vom Poller (#5) fuer jede neu angelegte Mail-Anfrage eingereiht. Der
 * Ablauf:
 *
 *  1. {@see RequestClassifier} ruft Claude auf — mit Mailtext, Portalliste
 *     und den serverseitig gesuchten Firmenkandidaten.
 *  2. Alles, was zurueckkommt, wird als **Vorschlag** gespeichert: `ai_*` am
 *     Vorgang, der Diff als `request_changes` mit `accepted = null`. Nichts
 *     davon beruehrt ein Profil.
 *  3. Die Anfrage geht auf `classified` — ein Statuswechsel, eine
 *     Protokollzeile.
 *  4. Danach uebernimmt {@see RequestVerifier}: Domain-Match, Magic-Link oder
 *     Handpruefung.
 *
 * `company_id` wird nur uebernommen, wenn die Zuversicht ueber
 * `classification.min_confidence` liegt. Darunter bleibt das Feld leer und
 * die Kandidaten stehen im Protokoll (`ai_suggested`), damit das Panel (#7)
 * sie zur Auswahl anbieten kann.
 *
 * ShouldBeUnique auf die Anfrage-ID: zwei Laeufe fuer dieselbe Mail wuerden
 * den Diff doppelt anlegen. Wiederholung bei Fehlern uebernimmt die Queue
 * (dreimal), danach bleibt die Anfrage in `new` und wartet im Panel — eine
 * Mail ohne Vorschlag ist Handarbeit, kein Datenverlust.
 */
class ClassifyRequest implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    /**
     * @param  bool  $verify  Nach der Klassifikation den Verifikationsweg
     *                        anstossen (Domain-Match, Magic-Link). Der
     *                        Poller (#5) laesst das laufen; der Import des
     *                        Rueckstands (#12, `requests:import-mailbox`)
     *                        nicht — eine Bestaetigungsmail zu einer Mail
     *                        von vor drei Wochen ist fuer den Betrieb
     *                        unverstaendlich.
     */
    public function __construct(
        public readonly int $requestId,
        public readonly bool $verify = true,
    ) {}

    public function uniqueId(): string
    {
        return 'profile-request-classify-'.$this->requestId;
    }

    public function handle(RequestClassifier $classifier, RequestVerifier $verifier): void
    {
        $request = ProfileRequest::find($this->requestId);

        if ($request === null) {
            return;
        }

        if ($request->status !== RequestStatus::NEW) {
            // Schon eingeordnet — etwa von Hand im Panel, waehrend der Job
            // in der Queue lag.
            return;
        }

        $result = $classifier->classify($request);

        $this->store($request, $result);

        if (! $this->verify) {
            // Importierter Rueckstand (#12): der Vorgang bleibt auf
            // `classified` in der Inbox. Verifiziert wird er von Hand im
            // Panel oder gar nicht.
            return;
        }

        // Die Verifikation ist der naechste Schritt und haengt nicht an der
        // Qualitaet des Vorschlags: ein Domain-Match gilt auch dann, wenn die
        // KI nichts verstanden hat.
        $verifier->handle($request);
    }

    /**
     * Vorschlag festschreiben: Spalten, Diff und Protokoll in einer
     * Transaktion auf der Central-Verbindung. Bricht etwas ab, gibt es
     * keinen halben Vorschlag.
     */
    private function store(ProfileRequest $request, ClassificationResult $result): void
    {
        $attributes = [
            'type' => $result->type,
            'ai_confidence' => $result->confidence,
            'ai_summary' => $this->summary($result),
            'ai_reply_draft' => $result->replyDraft,
        ];

        if ($result->tenantId !== null && $request->tenant_id === null) {
            $attributes['tenant_id'] = $result->tenantId;
        }

        if ($result->companyId !== null && $result->isConfident()) {
            $attributes['company_id'] = $result->companyId;
        }

        DB::connection($request->getConnectionName())->transaction(function () use ($request, $result, $attributes): void {
            // Das Protokoll des KI-Laufs steht auch dann, wenn nichts
            // uebernommen wurde — sonst laesst sich spaeter nicht sagen,
            // warum die Zuordnung fehlt.
            $request->recordEvent(RequestEventType::AI_SUGGESTED, [
                'model' => (string) config('profile-requests.claude_model'),
                'type' => $result->type->value,
                'confidence' => $result->confidence,
                'company_id_vorschlag' => $result->companyId,
                'company_id_uebernommen' => $result->companyId !== null && $result->isConfident(),
                'tenant_id_vorschlag' => $result->tenantId,
                'delete_reason' => $result->deleteReason,
                'changes' => array_map(fn (array $change): string => $change['field'], $result->changes),
                // Die Kandidaten bleiben im Protokoll: das Panel zeigt sie
                // zur Auswahl, wenn die Zuversicht nicht gereicht hat.
                'candidates' => $result->candidates,
                'fallback' => $result->fallback,
                'fallback_reason' => $result->fallbackReason,
            ]);

            foreach ($result->changes as $change) {
                $request->changes()->create([
                    'field' => $change['field'],
                    'old_value' => $change['old'],
                    'new_value' => $change['new'],
                    // Vorschlag, nicht Entscheidung.
                    'accepted' => null,
                ]);
            }

            $request->markClassified($attributes);
        });

        if ($result->fallback) {
            Log::info('profile-requests: Anfrage ohne KI-Vorschlag eingeordnet', [
                'request_id' => $request->id,
                'reason' => $result->fallbackReason,
            ]);
        }
    }

    /**
     * Zusammenfassung fuer die Inbox. Beim Loeschwunsch steht der Grund mit
     * drin — danach wird im Panel zuerst gefragt.
     */
    private function summary(ClassificationResult $result): ?string
    {
        $parts = array_filter([
            $result->summary,
            $result->deleteReason === null ? null : 'Grund: '.$result->deleteReason,
        ]);

        return $parts === [] ? null : implode("\n\n", $parts);
    }
}
