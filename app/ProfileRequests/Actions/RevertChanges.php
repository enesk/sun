<?php

declare(strict_types=1);

namespace App\ProfileRequests\Actions;

use App\Models\User;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestChange;
use App\ProfileRequests\Models\RequestEvent;
use App\ProfileRequests\Support\FieldRenderer;

/**
 * „Rueckgaengig" fuer eine automatisch uebernommene Aenderung (SUN-RQ #9).
 *
 * Moeglich, weil der Diff beide Seiten haelt: `request_changes.old_value` ist
 * der Wert, der vor der Uebernahme im Profil stand. Die Aktion schreibt genau
 * diese Altwerte zurueck — ueber {@see ApplyChanges::writeValues()}, damit
 * ein Feld nicht an zwei Stellen unterschiedlich behandelt wird.
 *
 * Grenzen, die aus den Daten folgen und nicht verhandelbar sind:
 *
 *  * **Nur automatisch Uebernommenes.** `applied_by = null` kennzeichnet die
 *    Auto-Regel. Was Enes im Panel uebernommen hat, war eine Entscheidung
 *    und wird dort auch wieder von Hand geaendert.
 *  * **Kein Statuswechsel.** `applied` ist ein Endzustand des Automaten
 *    (#3); ein Rueckweg waere ein neuer Uebergang und wuerde den Vorgang
 *    wieder in die offene Inbox heben. Das Rueckdrehen ist deshalb ein
 *    Ereignis im Protokoll, kein Zustand.
 *  * **Keine Bilder.** Ein ersetztes Logo ist weg; der alte Wert im Diff war
 *    nur ein Dateiname.
 */
class RevertChanges
{
    public function __construct(
        private readonly ApplyChanges $applyChanges,
    ) {}

    /**
     * @return array{ok: bool, reverted: list<string>, skipped: array<string, string>, message: string}
     */
    public function __invoke(ProfileRequest $request, ?User $actor = null): array
    {
        if (! $this->isRevertable($request)) {
            return $this->fail('Dieser Vorgang lässt sich nicht automatisch zurückdrehen.');
        }

        $values = $this->oldValues($request);

        if ($values === []) {
            return $this->fail('Es liegen keine Altwerte vor — nichts zurückzudrehen.');
        }

        $result = $this->applyChanges->writeValues($request, $values);

        $actorId = $actor?->getKey() === null ? null : (int) $actor->getKey();

        $request->recordEvent(
            RequestEventType::NOTE,
            [
                'note' => $result['applied'] === []
                    ? 'Rückgängig fehlgeschlagen'
                    : 'Automatische Übernahme zurückgedreht',
                'action' => 'revert_changes',
                'fields' => $result['applied'],
                'skipped' => $result['skipped'],
            ],
            $actor === null ? RequestEvent::ACTOR_SYSTEM : RequestEvent::ACTOR_USER,
            $actorId,
        );

        if ($result['applied'] === []) {
            return [
                'ok' => false,
                'reverted' => [],
                'skipped' => $result['skipped'],
                'message' => 'Kein Feld ließ sich zurückschreiben.',
            ];
        }

        return [
            'ok' => true,
            'reverted' => $result['applied'],
            'skipped' => $result['skipped'],
            'message' => sprintf(
                '%s zurückgesetzt.',
                implode(', ', array_map(FieldRenderer::label(...), $result['applied'])),
            ),
        ];
    }

    /**
     * Zurueckdrehbar ist eine automatisch uebernommene Aenderung mit
     * mindestens einem Altwert im Diff.
     */
    public function isRevertable(ProfileRequest $request): bool
    {
        return $request->type === RequestType::CHANGE
            && $request->status === RequestStatus::APPLIED
            && $request->applied_by === null
            && $request->tenant_id !== null
            && $request->company_id !== null
            && $this->oldValues($request) !== [];
    }

    /**
     * Die Altwerte der uebernommenen Zeilen. `old_value === null` ist ein
     * gueltiger Altwert (das Feld war leer) und wird mitgenommen; eine Zeile
     * ohne Haken wurde nie geschrieben und bleibt draussen.
     *
     * @return array<string, mixed>
     */
    public function oldValues(ProfileRequest $request): array
    {
        $values = [];

        foreach ($request->diffLines() as $change) {
            /** @var RequestChange $change */
            if ($change->accepted !== true) {
                continue;
            }

            $values[(string) $change->field] = $change->old_value;
        }

        return $values;
    }

    /** @return array{ok: bool, reverted: list<string>, skipped: array<string, string>, message: string} */
    private function fail(string $message): array
    {
        return ['ok' => false, 'reverted' => [], 'skipped' => [], 'message' => $message];
    }
}
