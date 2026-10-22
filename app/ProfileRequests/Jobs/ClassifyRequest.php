<?php

declare(strict_types=1);

namespace App\ProfileRequests\Jobs;

use App\ProfileRequests\Ai\ClassificationResult;
use App\ProfileRequests\Ai\RequestClassifier;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\RequestType;
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

    /**
     * Felder, deren Wert eine Struktur sein muss (Liste von Zeilen). Ein
     * Satz steht dort nie für eine übernehmbare Angabe — `ApplyChanges`
     * lehnt ihn ab.
     */
    private const STRUKTURFELDER = ['opening_hours', 'services'];

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
     * @param  bool  $erneut  Auf Anforderung aus dem Panel: noch einmal
     *                        einlesen, obwohl der Vorgang nicht mehr in
     *                        `new` steht. Nötig für alles, was ohne
     *                        KI-Lauf in die Inbox kam — der Altbestand
     *                        (#16) liegt auf `classified`, und
     *                        `CLASSIFIED -> CLASSIFIED` lässt der Automat
     *                        nicht zu. Der Status bleibt dann unberührt,
     *                        aktualisiert werden nur die Vorschlagsfelder.
     */
    public function __construct(
        public readonly int $requestId,
        public readonly bool $verify = true,
        public readonly bool $erneut = false,
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

        if (! $this->erneut && $request->status !== RequestStatus::NEW) {
            // Schon eingeordnet — etwa von Hand im Panel, waehrend der Job
            // in der Queue lag.
            return;
        }

        if ($this->erneut && $request->status->isFinal()) {
            // Ein abgeschlossener Vorgang bekommt keinen neuen Vorschlag:
            // er ist entschieden, und ein Diff daran wuerde im Panel eine
            // Handlungsmoeglichkeit vorspiegeln, die es nicht mehr gibt.
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

        if ($this->erneut) {
            // Beim erneuten Lauf schlaegt Handarbeit den Vorschlag: eine
            // schon gesetzte Zuordnung bleibt stehen.
            if ($request->company_id !== null) {
                unset($attributes['company_id']);
            }

            // Eine Uebernahme bleibt eine Uebernahme. `external_ref` zeigt
            // bei ihr auf `claim_requests.id`, und ApproveClaim findet den
            // Antrag nur ueber `type === CLAIM` — deutet die KI den Vorgang
            // um, ist die Bruecke zum Altantrag gerissen.
            if ($request->type === RequestType::CLAIM) {
                unset($attributes['type']);
            }
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
                // Beim erneuten Lauf (#23) kann die Zeile schon stehen —
                // `request_changes` ist je Vorgang und Feld eindeutig. Eine
                // bereits entschiedene Zeile (`accepted` gesetzt) bleibt,
                // wie sie ist: das war eine Entscheidung, kein Vorschlag.
                $vorhanden = $request->changes()->where('field', $change['field'])->first();

                if ($vorhanden !== null) {
                    // Eine entschiedene Zeile bleibt, wie sie ist — mit einer
                    // Ausnahme: taugt ihr Wert fuer dieses Feld gar nicht,
                    // ist das Haken auf etwas gesetzt, das `ApplyChanges`
                    // ablehnen wuerde. Das ist keine Entscheidung, sondern
                    // eine Falle (gemessen an Vorgang #171: Oeffnungszeiten
                    // als Satz, angehakt, nicht uebernehmbar). Dann ersetzt
                    // der neue Vorschlag den Wert und das Haken faellt
                    // zurueck auf „unentschieden".
                    $unbrauchbar = $vorhanden->accepted !== null
                        && in_array($change['field'], self::STRUKTURFELDER, true)
                        && ! is_array($vorhanden->new_value);

                    if ($vorhanden->accepted === null || $unbrauchbar) {
                        $vorhanden->update([
                            'old_value' => $change['old'],
                            'new_value' => $change['new'],
                            'accepted' => $unbrauchbar ? null : $vorhanden->accepted,
                        ]);
                    }

                    if ($unbrauchbar) {
                        $request->recordEvent(RequestEventType::NOTE, [
                            'note' => "Feld „{$change['field']}“ trug einen Freitext statt der erwarteten Struktur "
                                .'und war so nicht übernehmbar. Der neue Vorschlag hat ihn ersetzt, das Häkchen '
                                .'ist zurückgesetzt — bitte noch einmal prüfen.',
                        ]);
                    }

                    continue;
                }

                $request->changes()->create([
                    'field' => $change['field'],
                    'old_value' => $change['old'],
                    'new_value' => $change['new'],
                    // Vorschlag, nicht Entscheidung.
                    'accepted' => null,
                ]);
            }

            if ($request->status === RequestStatus::NEW) {
                $request->markClassified($attributes);

                return;
            }

            // Erneuter Lauf an einem Vorgang, der schon eingeordnet ist:
            // Der Automat kennt keinen Übergang auf denselben Zustand, und
            // es gibt auch keinen Grund dafür. Nur die Vorschlagsfelder
            // werden frisch geschrieben — die Statusspalte bleibt außen vor,
            // sonst schlägt der Wächter am Model zu.
            $request->forceFill($attributes)->save();
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
