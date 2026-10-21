<?php

declare(strict_types=1);

namespace App\ProfileRequests\Mail;

use App\Models\Tenant;
use App\ProfileRequests\Models\ProfileRequest;
use App\Support\Tenancy\TenantMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Freie Antwort an den Anfragenden, von Hand aus dem Panel verschickt
 * (SUN-RQ #7, Aktion „Antwort senden").
 *
 * Der Text kommt vollstaendig aus dem Panel — als Entwurf schlaegt die KI
 * (#6, `ai_reply_draft`) etwas vor, abgeschickt wird aber nur, was Enes
 * gelesen und freigegeben hat.
 *
 * Betreff und Message-ID tragen die Vorgangsnummer in der Form, die der
 * Poller (#5) wiedererkennt — `[REQ-{id}]` bzw. `<req-{id}-{zufall}@domain>`.
 * Die Antwort des Betriebs landet damit als Ereignis am Vorgang und nicht
 * als neue Anfrage. `Reply-To` zeigt auf das Sammelpostfach, denn nur das
 * wird abgefragt; die Absenderadresse der Anwendung liest niemand.
 */
class RequestReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProfileRequest $request,
        public ?Tenant $tenant,
        public string $betreff,
        public string $nachricht,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = (string) config('profile-requests.mailbox.address');

        return new Envelope(
            subject: $this->betreff,
            replyTo: $replyTo === '' ? [] : [$replyTo],
        );
    }

    public function headers(): Headers
    {
        $domain = parse_url(TenantMailBranding::for($this->tenant)->url('/'), PHP_URL_HOST);

        return new Headers(
            messageId: sprintf(
                'req-%d-%s@%s',
                $this->request->id,
                Str::lower(Str::random(12)),
                is_string($domain) && $domain !== '' ? $domain : 'localhost',
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.profile-requests.reply',
            with: [
                'mailTenant' => $this->tenant,
                'profileRequest' => $this->request,
                'nachricht' => $this->nachricht,
            ],
        );
    }

    /**
     * Betreff mit Vorgangsmarke. Die Marke steht am Ende, damit der Mensch
     * zuerst den Inhalt liest; der Poller findet sie an jeder Stelle
     * (`ingestion.subject_tag_pattern`).
     */
    public static function betreffMitMarke(ProfileRequest $request, string $betreff): string
    {
        $betreff = trim($betreff);
        $marke = "[REQ-{$request->id}]";

        if ($betreff === '') {
            $betreff = 'Ihre Anfrage zu Ihrem Eintrag';
        }

        return str_contains($betreff, $marke) ? $betreff : "{$betreff} {$marke}";
    }
}
