<?php

declare(strict_types=1);

namespace App\ProfileRequests\Mail;

use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Models\ProfileRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sofortmeldung über einen eiligen Vorgang (SUN-RQ #23).
 *
 * Vor der Inbox ging bei jedem Änderungsvorschlag und jedem
 * Übernahmeantrag eine Mail an Enes. Die hing fest verdrahtet in den
 * Altbausteinen und fiel mit ihnen aus dem Hauptweg; Ersatz war allein der
 * Tagesdigest um 08:00.
 *
 * Entscheidung von Enes (22.10.2026): Digest bleibt, dazu eine Sofortmail
 * **nur** für Löschwünsche und Übernahmen. Die Eile hängt an der Sache und
 * nicht an der Menge — eine geänderte Telefonnummer kann bis morgen früh
 * warten, ein Löschwunsch hat eine Karenzfrist und eine Übernahme
 * entscheidet, wer den Eintrag führt.
 *
 * Interne Mail wie der Digest: keine Unterklasse von {@see RequestMail},
 * also kein `[REQ-…]` im Betreff, keine Antwortadresse auf das
 * Sammelpostfach, kein Portal-Branding — und Du-Form.
 */
class NewRequestAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProfileRequest $request,
        public string $portal,
        public string $betrieb,
        public string $panelUrl,
    ) {}

    public function envelope(): Envelope
    {
        $art = $this->request->type === RequestType::DELETE
            ? 'Löschwunsch'
            : 'Übernahmeantrag';

        return new Envelope(
            subject: sprintf('%s: %s (%s)', $art, $this->betrieb, $this->portal),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.profile-requests.new-request-alert',
            with: [
                'request' => $this->request,
                'portal' => $this->portal,
                'betrieb' => $this->betrieb,
                'panelUrl' => $this->panelUrl,
                'istLoeschung' => $this->request->type === RequestType::DELETE,
            ],
        );
    }
}
