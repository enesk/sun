<?php

declare(strict_types=1);

namespace App\Livewire\Central;

use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestAttachment;
use App\ProfileRequests\Models\RequestChange;
use App\ProfileRequests\Models\RequestEvent;
use App\ProfileRequests\Support\FieldRenderer;
use App\ProfileRequests\Support\WordDiff;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Diff einer Anfrage im Central-Panel (SUN-RQ, Ticket #11).
 *
 * Je Zeile: Feldname, aktueller Wert, neuer Wert, ein Haken "übernehmen".
 * Eingesetzt in der Detailansicht der Inbox (#7); die Komponente kennt nur
 * die Anfrage-ID und holt sich alles selbst, damit sie auch in einem Modal
 * oder in einer Mail-Vorschau stehen kann.
 *
 * Der Haken steht in `request_changes.accepted` und nicht nur im
 * Livewire-Zustand: die Uebernahme (#7/#9) liest diese Spalte, und eine
 * Entscheidung, die ein Seitenwechsel verliert, ist keine.
 *
 * Vorbelegung des Hakens:
 *  * an — der Normalfall, auch bei Feldern ausserhalb der Whitelist (die
 *    bekommen den Hinweis "manuelle Freigabe", aber keinen stillen
 *    Vorbehalt);
 *  * aus — wenn der Diff ein KI-Vorschlag mit `ai_confidence` unter 0,7 ist.
 *    Ein unsicherer Vorschlag soll nicht durch Wegklicken uebernommen
 *    werden, sondern durch Hinsehen.
 */
class RequestDiffTable extends Component
{
    /** Schwelle, unter der ein KI-Vorschlag nicht vorbelegt wird. */
    public const AI_CONFIDENCE_THRESHOLD = 0.7;

    #[Locked]
    public int $requestId;

    /** Nur anzeigen, keine Haken und keine Bearbeitung (erledigte Vorgaenge). */
    #[Locked]
    public bool $readOnly = false;

    /** change_id => bool */
    public array $accepted = [];

    /** change_id => Textfassung des neuen Werts, waehrend bearbeitet wird */
    public array $editValues = [];

    /** change_id, der gerade bearbeitet wird; null = keiner */
    public ?int $editing = null;

    /** attachment_id => RequestAttachment::ROLE_* */
    public array $attachmentRoles = [];

    protected ?ProfileRequest $cachedRequest = null;

    /** @var Collection<int, RequestChange>|null */
    protected ?Collection $cachedChanges = null;

    /** @var Collection<int, RequestAttachment>|null */
    protected ?Collection $cachedAttachments = null;

    public function mount(int $requestId, bool $readOnly = false): void
    {
        $this->requestId = $requestId;
        $this->readOnly = $readOnly;

        $this->loadState();
    }

    /**
     * Haken und Bildrollen aus der Datenbank in den Komponentenzustand.
     *
     * Noch nicht entschiedene Zeilen (`accepted` null) werden dabei mit
     * ihrer Vorbelegung **gespeichert**. Das ist Absicht: sonst stuende im
     * Panel ein Haken, den die Uebernahme nicht sieht, und #9 muesste die
     * Vorbelegung ein zweites Mal nachbauen.
     */
    protected function loadState(): void
    {
        $request = $this->request();
        $default = $this->defaultAccepted($request);

        $pending = $request->changes()->whereNull('accepted')->pluck('id')->all();

        if ($pending !== [] && ! $this->readOnly) {
            RequestChange::query()->whereIn('id', $pending)->update(['accepted' => $default]);
        }

        $this->accepted = [];

        foreach ($this->changes() as $change) {
            $this->accepted[$change->id] = in_array($change->id, $pending, true)
                ? $default
                : (bool) $change->accepted;
        }

        $this->attachmentRoles = [];

        foreach ($this->imageAttachments() as $attachment) {
            $this->attachmentRoles[$attachment->id] = (string) ($attachment->image_role ?? RequestAttachment::ROLE_GALLERY);
        }
    }

    /**
     * Vorbelegung des Hakens: aus, wenn die KI den Diff vorgeschlagen hat
     * und sich dabei unsicher war.
     */
    protected function defaultAccepted(ProfileRequest $request): bool
    {
        $confidence = $request->ai_confidence;

        if ($confidence === null) {
            return true;
        }

        return $confidence >= self::AI_CONFIDENCE_THRESHOLD;
    }

    // ------------------------------------------------------------------
    // Aktionen
    // ------------------------------------------------------------------

    public function toggle(int $changeId): void
    {
        if ($this->readOnly) {
            return;
        }

        $change = $this->findChange($changeId);

        if ($change === null) {
            return;
        }

        $value = ! ($this->accepted[$changeId] ?? true);

        $change->accepted = $value;
        $change->save();

        $this->accepted[$changeId] = $value;
    }

    public function startEdit(int $changeId): void
    {
        if ($this->readOnly) {
            return;
        }

        $change = $this->findChange($changeId);

        if ($change === null || ! FieldRenderer::isEditable($change->field)) {
            return;
        }

        $this->editing = $changeId;
        $this->editValues[$changeId] = is_scalar($change->new_value) ? (string) $change->new_value : '';
    }

    public function cancelEdit(): void
    {
        $this->editing = null;
    }

    /**
     * Korrigierten Wert speichern.
     *
     * Schreibt `new_value` und **eine** Protokollzeile
     * (RequestEventType::DIFF_EDITED) mit Feld, vorherigem und neuem Wert
     * sowie dem Bearbeiter — damit im Protokoll steht, dass der uebernommene
     * Wert nicht der eingereichte ist.
     */
    public function saveEdit(int $changeId): void
    {
        if ($this->readOnly) {
            return;
        }

        $change = $this->findChange($changeId);

        if ($change === null || ! FieldRenderer::isEditable($change->field)) {
            return;
        }

        $value = trim((string) ($this->editValues[$changeId] ?? ''));
        $previous = is_scalar($change->new_value) ? (string) $change->new_value : null;

        if ($value === $previous) {
            $this->editing = null;

            return;
        }

        $change->new_value = $value === '' ? null : $value;
        $change->save();

        $user = Auth::user();

        $this->request()->recordEvent(
            RequestEventType::DIFF_EDITED,
            [
                'field' => $change->field,
                'from' => $previous,
                'to' => $change->new_value,
            ],
            $user === null ? RequestEvent::ACTOR_SYSTEM : RequestEvent::ACTOR_USER,
            $user?->getKey() === null ? null : (int) $user->getKey(),
        );

        $this->editing = null;

        Notification::make()
            ->title('Neuer Wert gespeichert')
            ->body(FieldRenderer::label($change->field).' — die Änderung steht im Protokoll.')
            ->success()
            ->send();
    }

    /** Logo oder Galerie fuer einen Bild-Anhang. */
    public function setAttachmentRole(int $attachmentId, string $role): void
    {
        if ($this->readOnly) {
            return;
        }

        if (! in_array($role, [RequestAttachment::ROLE_LOGO, RequestAttachment::ROLE_GALLERY, RequestAttachment::ROLE_IGNORE], true)) {
            return;
        }

        $attachment = $this->imageAttachments()->firstWhere('id', $attachmentId);

        if ($attachment === null) {
            return;
        }

        // Logo gibt es nur einmal — die bisherige Wahl wird Galeriebild.
        if ($role === RequestAttachment::ROLE_LOGO) {
            foreach ($this->imageAttachments() as $other) {
                if ($other->id !== $attachmentId && $other->image_role === RequestAttachment::ROLE_LOGO) {
                    $other->image_role = RequestAttachment::ROLE_GALLERY;
                    $other->save();
                    $this->attachmentRoles[$other->id] = RequestAttachment::ROLE_GALLERY;
                }
            }
        }

        $attachment->image_role = $role;
        $attachment->save();

        $this->attachmentRoles[$attachmentId] = $role;
    }

    // ------------------------------------------------------------------
    // Daten
    // ------------------------------------------------------------------

    public function request(): ProfileRequest
    {
        return $this->cachedRequest ??= ProfileRequest::query()->findOrFail($this->requestId);
    }

    /** @return Collection<int, RequestChange> */
    public function changes(): Collection
    {
        return $this->cachedChanges ??= $this->request()->changes()->orderBy('field')->get();
    }

    /** @return Collection<int, RequestAttachment> */
    public function imageAttachments(): Collection
    {
        return $this->cachedAttachments ??= $this->request()->attachments()->images()->orderBy('id')->get();
    }

    /**
     * Eine Zeile der Tabelle, fertig fuer die Anzeige.
     *
     * Der Wort-Diff laeuft hier und nicht im Blade, damit die Entscheidung
     * "lohnt sich das?" an einer Stelle steht.
     *
     * @return array<string, mixed>
     */
    public function row(RequestChange $change): array
    {
        $field = $change->field;
        $wordDiff = null;

        if (WordDiff::worthwhile($change->old_value, $change->new_value)) {
            $wordDiff = WordDiff::render((string) $change->old_value, (string) $change->new_value);
        }

        return [
            'id' => (int) $change->id,
            'field' => $field,
            'label' => FieldRenderer::label($field),
            'old' => FieldRenderer::describe($field, $change->old_value),
            'new' => FieldRenderer::describe($field, $change->new_value),
            'wordDiff' => $wordDiff,
            'editable' => ! $this->readOnly && FieldRenderer::isEditable($field),
            'multiline' => FieldRenderer::isMultiline($field),
            'needsApproval' => ! FieldRenderer::isWhitelisted($field),
        ];
    }

    /** Hinweisbanner oben: unsicherer KI-Vorschlag. */
    public function isLowConfidenceSuggestion(): bool
    {
        $confidence = $this->request()->ai_confidence;

        return $confidence !== null && $confidence < self::AI_CONFIDENCE_THRESHOLD;
    }

    public function render()
    {
        return view('livewire.central.request-diff-table', [
            'rows' => $this->changes()->map($this->row(...))->all(),
            'attachments' => $this->imageAttachments(),
        ]);
    }

    /** Nur Zeilen dieser Anfrage — eine fremde change_id aus dem Browser zaehlt nicht. */
    protected function findChange(int $changeId): ?RequestChange
    {
        return $this->changes()->firstWhere('id', $changeId);
    }
}
