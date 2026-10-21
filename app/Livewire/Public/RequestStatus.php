<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\ProfileRequests\Enums\RequestEventType;
// Alias: der Enum heisst wie diese Komponente.
use App\ProfileRequests\Enums\RequestStatus as Status;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestEvent;
use App\ProfileRequests\Support\FieldRenderer;
use App\ProfileRequests\Support\MaskedEmail;
use App\Support\Tenancy\TenantMailBranding;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Oeffentliche Statusseite eines Vorgangs (SUN-RQ #10).
 *
 * Erreichbar allein ueber die signierte Adresse aus den Mails
 * ({@see \App\ProfileRequests\Support\RequestLinks::status()}) und aus dem
 * Dialog am Profil (#4). Sie nimmt die Rueckfrage „wie weit sind Sie?" weg,
 * die heute den groessten Teil des Mailverkehrs zu einem Vorgang ausmacht
 * (docs/profile-requests.md 2.4), und sie ist der Stornoweg fuer eine
 * laufende Loeschung: der Token der Bestaetigungsmail ist mit der
 * Verifikation entwertet, diese Adresse laeuft nicht ab.
 *
 * **Was hier nicht steht:** KI-Vorschlaege, Notizen, interne Ereignisse,
 * Mailprotokoll, Zuversichtswerte, Namen von Bearbeitern. Der Zeitstrahl
 * zeigt nur die Schritte, die den Betrieb betreffen
 * ({@see self::VISIBLE_EVENTS}), und von den eingereichten Werten nur die,
 * die der Betrieb selbst geschickt hat. Die Adresse des Anfragenden steht
 * maskiert da: die Seite ist fuer jeden offen, der den Link hat.
 *
 * Die Knoepfe aendern den Zustand ueber den Automaten des Models, also mit
 * Protokollzeile. Sie haengen am Eigenschaftswert `requestId`, den Livewire
 * mit einer Pruefsumme sichert — ein fremder Vorgang laesst sich damit nicht
 * unterschieben, obwohl `POST /livewire/update` selbst keine Signatur
 * mitbringt.
 *
 * @property-read ProfileRequest $profileRequest
 */
class RequestStatus extends Component
{
    /**
     * Ereignisse, die auf dem Zeitstrahl erscheinen. Alles andere
     * (ai_suggested, note, mail_sent, diff_edited, attachment_rejected,
     * discarded) ist interne Arbeit und bleibt im Panel.
     */
    private const VISIBLE_EVENTS = [
        RequestEventType::CREATED->value => 'Anfrage eingegangen',
        RequestEventType::CLASSIFIED->value => 'In Prüfung',
        RequestEventType::VERIFICATION_REQUESTED->value => 'Bestätigung angefordert',
        RequestEventType::VERIFIED->value => 'Von Ihnen bestätigt',
        RequestEventType::REQUESTER_REPLIED->value => 'Ihre Nachricht ist eingegangen',
        RequestEventType::APPLIED->value => 'Erledigt',
        RequestEventType::REJECTED->value => 'Abgelehnt',
        RequestEventType::CANCELLED->value => 'Zurückgezogen',
        RequestEventType::EXPIRED->value => 'Abgelaufen',
    ];

    public int $requestId;

    /** Rueckmeldung nach einem Klick; ueberlebt keinen Seitenwechsel. */
    public ?string $notice = null;

    public function mount(int $request): void
    {
        $this->requestId = $request;

        // Lieber 404 als eine leere Seite mit gueltiger Signatur.
        abort_if(ProfileRequest::query()->whereKey($request)->doesntExist(), 404);
    }

    #[Computed]
    public function profileRequest(): ProfileRequest
    {
        return ProfileRequest::query()
            ->with(['changes', 'events'])
            ->findOrFail($this->requestId);
    }

    // ------------------------------------------------------------------
    // Aktionen
    // ------------------------------------------------------------------

    /** „Anfrage zurueckziehen" — gilt fuer jeden offenen Vorgang. */
    public function withdraw(): void
    {
        if (! $this->canWithdraw()) {
            return;
        }

        $this->profileRequest->cancel(
            'Vom Anfragenden über die Statusseite zurückgezogen',
            RequestEvent::ACTOR_REQUESTER,
        );

        unset($this->profileRequest);
        $this->notice = 'Die Anfrage ist zurückgezogen. Am Eintrag ändert sich nichts.';
    }

    /** „Loeschung stornieren" — nur innerhalb der Karenzfrist. */
    public function cancelDeletion(): void
    {
        if (! $this->canCancelDeletion()) {
            return;
        }

        $this->profileRequest->cancel(
            'Löschung vom Betrieb über die Statusseite storniert',
            RequestEvent::ACTOR_REQUESTER,
        );

        unset($this->profileRequest);
        $this->notice = 'Die Löschung ist gestoppt. Ihr Eintrag bleibt bestehen.';
    }

    // ------------------------------------------------------------------
    // Abgeleitetes fuer die View
    // ------------------------------------------------------------------

    public function canWithdraw(): bool
    {
        return $this->profileRequest->isOpen() && ! $this->canCancelDeletion();
    }

    /**
     * Eine bestaetigte Loeschung, deren Karenzfrist noch laeuft — der einzige
     * Zustand, in dem „stornieren" etwas anderes heisst als „zurueckziehen".
     */
    public function canCancelDeletion(): bool
    {
        $request = $this->profileRequest;

        return $request->type === RequestType::DELETE
            && $request->status === Status::VERIFIED
            && $request->grace_until !== null
            && $request->grace_until->isFuture();
    }

    /** Kopfzeile: wo der Vorgang steht, in einem Satz. */
    public function headline(): string
    {
        $request = $this->profileRequest;

        return match ($request->status) {
            Status::NEW, Status::CLASSIFIED => 'Ihre Anfrage liegt bei uns',
            Status::AWAITING_VERIFICATION => 'Wir warten auf die Bestätigung',
            Status::VERIFIED => $this->canCancelDeletion()
                ? 'Die Löschung ist vorgemerkt'
                : 'Bestätigt — wir sind dran',
            Status::APPLIED => $request->type === RequestType::DELETE
                ? 'Der Eintrag ist gelöscht'
                : 'Erledigt',
            Status::REJECTED => 'Wir setzen die Anfrage nicht um',
            Status::CANCELLED => 'Die Anfrage ist zurückgezogen',
            Status::EXPIRED => 'Der Vorgang ist abgelaufen',
        };
    }

    /** Erklaerung darunter: was als Naechstes passiert und wer etwas tun muss. */
    public function explanation(): string
    {
        $request = $this->profileRequest;

        if ($this->canCancelDeletion()) {
            return 'Wir löschen den Eintrag am '
                .$request->grace_until->timezone('Europe/Berlin')->format('d.m.Y \u\m H:i')
                .' Uhr. Bis dahin können Sie die Löschung hier stoppen.';
        }

        return match ($request->status) {
            Status::NEW, Status::CLASSIFIED => 'Wir sehen uns die Anfrage an. Sie müssen nichts weiter tun — sobald etwas passiert, schreiben wir Ihnen.',
            Status::AWAITING_VERIFICATION => 'Zur Sicherheit haben wir eine Bestätigung an die im Eintrag hinterlegte E-Mail-Adresse geschickt. Erst ein Klick auf den Link darin gibt die Anfrage frei. Bitte schauen Sie auch im Spam-Ordner nach.',
            Status::VERIFIED => 'Die Anfrage ist bestätigt und in Bearbeitung. Sobald sie erledigt ist, schreiben wir Ihnen.',
            Status::APPLIED => $request->type === RequestType::DELETE
                ? 'Der Eintrag ist bei uns gelöscht. Bei Suchmaschinen kann die alte Seite noch einige Tage auftauchen, bis sie die Adresse erneut abrufen.'
                : 'Ihre Angaben stehen im Eintrag. Bei Suchmaschinen kann der alte Stand noch einige Tage sichtbar sein.',
            Status::REJECTED => trim((string) $request->rejected_reason) !== ''
                ? (string) $request->rejected_reason
                : 'Die Anfrage wurde nicht umgesetzt. Antworten Sie auf unsere E-Mail, wenn Sie das anders sehen.',
            Status::CANCELLED => 'Am Eintrag wurde nichts geändert und nichts gelöscht. Sie können jederzeit eine neue Anfrage stellen.',
            Status::EXPIRED => 'Die Bestätigung ist ausgeblieben, deshalb haben wir den Vorgang geschlossen. Stellen Sie die Anfrage bitte auf der Profilseite neu.',
        };
    }

    /**
     * Zeitstrahl: nur Ereignisse, die den Betrieb betreffen, ohne Nutzlast.
     *
     * @return list<array{label: string, at: \Illuminate\Support\Carbon|null}>
     */
    public function timeline(): array
    {
        $steps = [];

        foreach ($this->profileRequest->events as $event) {
            $key = $event->event instanceof RequestEventType ? $event->event->value : (string) $event->event;
            $label = self::VISIBLE_EVENTS[$key] ?? null;

            if ($label === null) {
                continue;
            }

            $steps[] = ['label' => $label, 'at' => $event->created_at];
        }

        return $steps;
    }

    /**
     * Die eingereichten Felder — Beschriftung und der gewuenschte neue Wert,
     * aufbereitet wie im Panel (#11), aber ohne alten Wert und ohne
     * Wort-Diff: hier liest der Betrieb nach, was er geschickt hat.
     *
     * @return list<array{label: string, value: array<string, mixed>}>
     */
    public function submittedFields(): array
    {
        $fields = [];

        foreach ($this->profileRequest->diffLines() as $change) {
            $fields[] = [
                'label' => FieldRenderer::label($change->field),
                'value' => FieldRenderer::describe($change->field, $change->new_value),
            ];
        }

        return $fields;
    }

    public function requesterEmail(): string
    {
        return MaskedEmail::mask($this->profileRequest->requester_email);
    }

    public function render(): View
    {
        $request = $this->profileRequest;
        $branding = TenantMailBranding::for($request->tenant);

        return view('livewire.public.request-status')
            ->layout('profile-requests.page', [
                'title' => $this->headline(),
                'portalName' => $branding->portalName(),
                'portalUrl' => $branding->url('/'),
                'brandColor' => $branding->brandColor(),
            ]);
    }
}
