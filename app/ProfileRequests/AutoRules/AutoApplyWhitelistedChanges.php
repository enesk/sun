<?php

declare(strict_types=1);

namespace App\ProfileRequests\AutoRules;

use App\ProfileRequests\Actions\ApplyChanges;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestSource;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Models\ProfileRequest;

/**
 * Verifizierte Aenderung an Whitelist-Feldern ohne Enes uebernehmen
 * (SUN-RQ #9; Whitelist in `profile-requests.auto_apply_fields`).
 *
 * Alle Bedingungen muessen zusammen erfuellt sein:
 *
 *  * Typ `change`, Status `verified`, und der Verifikationsweg laesst
 *    Automatik zu ({@see \App\ProfileRequests\Enums\VerificationMethod::allowsAutoApply()}
 *    — handgepruefte Vorgaenge nie, dort hat Enes schon entschieden).
 *  * Portal und Betrieb sind zugeordnet.
 *  * **Jedes** Feld des Diffs steht auf der Whitelist. Liegt ein Feld
 *    ausserhalb — Firmenname, Adresse, Kategorie, Inhaber —, bleibt die
 *    **ganze** Anfrage zur Freigabe. Kein teilweises Uebernehmen: wer den
 *    Namen aendern will, aendert meist auch die Telefonnummer mit, und ein
 *    halb uebernommener Vorgang verschleiert, was noch offen ist.
 *  * Die Quelle ist `form` (strukturierter Diff aus #4) **oder** der
 *    KI-Vorschlag ist mindestens `auto.min_ai_confidence` (0,9) sicher.
 *  * Der Betrieb ist nicht als „manuell pruefen" markiert.
 *
 * Ausgefuehrt wird ueber {@see ApplyChanges} — denselben Weg wie das Panel,
 * damit es nur eine schreibende Stelle gibt. Die Aktion schreibt aber nur
 * angehakte Zeilen; deshalb werden hier vorher alle Zeilen auf
 * `accepted = true` gesetzt (im Protokoll als `diff_edited` mit Akteur
 * `system`). Der Vorgang geht danach mit `applied_by = null` auf `applied`,
 * die Bestaetigungsmail an den Betrieb versendet ApplyChanges.
 */
class AutoApplyWhitelistedChanges implements AutoRule
{
    public function __construct(
        private readonly ApplyChanges $applyChanges,
        private readonly ContractGuard $guard,
    ) {}

    public function key(): string
    {
        return 'auto_apply_whitelisted_changes';
    }

    public function appliesTo(ProfileRequest $request): bool
    {
        return $this->reasonAgainst($request) === null;
    }

    /**
     * Warum die Regel nicht greift — null heisst: sie greift. Die Gruende
     * sind einzeln benannt, weil genau sie im Protokoll stehen, wenn ein
     * Vorgang zur Freigabe liegenbleibt.
     */
    public function reasonAgainst(ProfileRequest $request): ?string
    {
        if ($request->type !== RequestType::CHANGE) {
            return 'kein Änderungswunsch';
        }

        if ($request->status !== RequestStatus::VERIFIED || ! $request->isVerified()) {
            return 'nicht bestätigt';
        }

        if ($request->verification_method !== null && ! $request->verification_method->allowsAutoApply()) {
            return 'von Hand geprüft';
        }

        if ($request->tenant_id === null || $request->company_id === null) {
            return 'kein Betrieb zugeordnet';
        }

        if ($this->guard->isMarkedForManualReview((int) $request->tenant_id, (int) $request->company_id)) {
            return 'Betrieb als „manuell prüfen" markiert';
        }

        $felder = $request->diffFields();

        if ($felder === []) {
            return 'kein Diff';
        }

        if (! $request->hasOnlyAutoApplicableChanges()) {
            $ausserhalb = array_values(array_diff($felder, (array) config('profile-requests.auto_apply_fields', [])));

            return 'Feld außerhalb der Whitelist: '.implode(', ', $ausserhalb);
        }

        if ($request->source === RequestSource::FORM) {
            return null;
        }

        $schwelle = (float) config('profile-requests.auto.min_ai_confidence', 0.9);

        if (($request->ai_confidence ?? 0.0) >= $schwelle) {
            return null;
        }

        return sprintf('KI-Zuversicht %.2f unter %.2f', $request->ai_confidence ?? 0.0, $schwelle);
    }

    public function run(ProfileRequest $request): bool
    {
        $grund = $this->reasonAgainst($request);

        if ($grund !== null) {
            return false;
        }

        $this->acceptAllLines($request);

        $ergebnis = ($this->applyChanges)($request, null);

        $request->recordEvent(RequestEventType::NOTE, [
            'note' => $ergebnis['ok'] ? 'Automatisch übernommen' : 'Automatische Übernahme fehlgeschlagen',
            'rule' => $this->key(),
            'fields' => $ergebnis['applied'],
            'skipped' => $ergebnis['skipped'],
            'meldung' => $ergebnis['message'],
        ]);

        return (bool) $ergebnis['ok'];
    }

    /**
     * Alle Diff-Zeilen anhaken. Ohne Haken schreibt ApplyChanges nichts;
     * hier ist die Entscheidung aber schon mit der Whitelist gefallen.
     */
    private function acceptAllLines(ProfileRequest $request): void
    {
        $request->changes()->update(['accepted' => true]);
        $request->unsetRelation('changes');

        $request->recordEvent(RequestEventType::DIFF_EDITED, [
            'note' => 'Alle Zeilen automatisch angehakt (Whitelist)',
            'rule' => $this->key(),
            'fields' => $request->diffFields(),
        ]);
    }
}
