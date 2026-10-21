<?php

declare(strict_types=1);

namespace App\ProfileRequests\Mail;

use App\Models\Tenant;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Support\FieldRenderer;
use App\ProfileRequests\Support\RequestLinks;
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
 * Gemeinsame Basis der sieben Mails an Betriebe (SUN-RQ #10).
 *
 * Alle sieben teilen vier Dinge, und jedes davon bricht den Vorgang, wenn es
 * in einer Mail fehlt:
 *
 *  1. **`[REQ-{id}]` im Betreff.** Der Poller (#5) liest die Marke aus dem
 *     Betreff einer Antwort (`profile-requests.ingestion.subject_tag_pattern`)
 *     und haengt sie an den Vorgang, statt eine neue Anfrage anzulegen.
 *  2. **Message-ID `<req-{id}-{zufall}@domain>`.** Der zweite, robustere Weg
 *     zur Zuordnung (`outgoing_message_id_pattern`): In-Reply-To ueberlebt
 *     auch einen verstuemmelten Betreff.
 *  3. **Reply-To auf das Sammelpostfach.** Antwortet der Betrieb, muss die
 *     Mail dort landen, wo der Poller liest — nicht beim Portalbetreiber.
 *     Ohne diese Angabe setzt {@see \App\Listeners\Mail\SetPortalSender} die
 *     Kontaktadresse des Portals ein, und die Antwort versandet.
 *  4. **Branding des Portals.** Name und Markenfarbe kommen aus dem Tenant,
 *     nie aus `config('app.name')`: die Mails laufen ueber die Queue und
 *     sehen dort kein Portal.
 *
 * Die Unterklasse liefert Betreffzeile, View und ihre eigenen View-Daten.
 * Vererbung statt Trait, weil `envelope()`, `headers()` und `content()`
 * vollstaendig geteilt sind und eine Unterklasse keine davon anfassen soll.
 *
 * Methodennamen bewusst nicht `subject()`/`view()`: beides sind Baumethoden
 * von Illuminate\Mail\Mailable.
 */
abstract class RequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProfileRequest $request,
        public ?Tenant $tenant,
    ) {}

    /** Betreff ohne Vorgangsmarke und ohne Portalname — die haengt die Basis an. */
    abstract protected function subjectLine(): string;

    abstract protected function viewName(): string;

    /**
     * View-Daten der Unterklasse.
     *
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '%s [REQ-%d] — %s',
                $this->subjectLine(),
                $this->request->id,
                $this->branding()->portalName(),
            ),
            replyTo: $this->mailboxReplyTo(),
        );
    }

    public function headers(): Headers
    {
        $domain = parse_url($this->branding()->url('/'), PHP_URL_HOST);

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
            view: $this->viewName(),
            with: $this->payload() + [
                // Ohne Tenant-Kontext liest das Layout das Branding hieraus.
                'mailTenant' => $this->tenant,
                'profileRequest' => $this->request,
                'portalName' => $this->branding()->portalName(),
                'statusUrl' => RequestLinks::status($this->request, $this->tenant),
            ],
        );
    }

    protected function branding(): TenantMailBranding
    {
        return TenantMailBranding::for($this->tenant);
    }

    /**
     * Das zentrale Sammelpostfach. Leer, solange es nicht eingerichtet ist —
     * dann greift die Antwortadresse des Portals aus SetPortalSender, und
     * die Antwort landet wenigstens bei einem Menschen.
     *
     * @return list<string>
     */
    protected function mailboxReplyTo(): array
    {
        $mailbox = (string) config('profile-requests.mailbox.address', '');

        return filter_var($mailbox, FILTER_VALIDATE_EMAIL) !== false ? [$mailbox] : [];
    }

    /**
     * Der Diff in Worten: Feldname und der neue Wert als Text.
     *
     * Bewusst keine Tabelle — Oeffnungszeiten und Leistungen sind
     * strukturiert, und eine JSON-Tabelle in einer Mail liest niemand. Die
     * Beschriftungen kommen aus {@see FieldRenderer}, damit Mail und Panel
     * (#11) dieselben Feldnamen zeigen.
     *
     * @return list<string>
     */
    protected function changeLines(): array
    {
        $lines = [];

        foreach ($this->request->diffLines() as $change) {
            $label = FieldRenderer::label($change->field);
            $new = $change->new_value;

            $lines[] = is_scalar($new) && (string) $new !== ''
                ? $label.': '.Str::limit((string) $new, 120)
                : $label.' (neue Angabe)';
        }

        return $lines;
    }
}
