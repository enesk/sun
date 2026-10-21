<?php

declare(strict_types=1);

namespace App\ProfileRequests\Ingestion;

use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestSource;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestAttachment;
use App\ProfileRequests\Models\RequestEvent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;
use Throwable;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Message;

/**
 * Aus einer Mail des Sammelpostfachs wird eine Anfrage (SUN-RQ, Ticket #5).
 *
 * Der Mapper legt nur den Vorgang an: Absender, Text, Rohmail, Anhaenge und
 * — soweit die Kopfzeilen es hergeben — das Portal. Typ, Betrieb und Diff
 * bleiben offen; die Anfrage startet in RequestStatus::NEW und wartet auf
 * die Klassifikation (#6). `type` steht deshalb zunaechst auf OTHER: die
 * Spalte ist nicht nullable, und "Sonstiges" ist die ehrliche Aussage,
 * solange niemand die Mail gelesen hat.
 *
 * Die Rohmail wird immer abgelegt, auch wenn alle Anhaenge wegfallen: sie
 * ist die Quelle, gegen die sich spaeter jede Entscheidung pruefen laesst.
 * Sie liegt unter storage/app/private/requests/{id}/ und faellt nach
 * `profile-requests.retention_days` der Aufbewahrung zum Opfer, waehrend
 * Anfrage, Diff und Protokoll bleiben.
 */
class MailToRequestMapper
{
    public function __construct(
        private readonly TenantFromRecipient $tenantFromRecipient,
    ) {}

    /**
     * @param  string|null  $recipient  erkannte Portal-Adresse, falls schon bekannt
     */
    public function create(Message $message, ?int $tenantId = null, ?string $recipient = null): ProfileRequest
    {
        if ($tenantId === null && $recipient === null) {
            $erkannt = $this->tenantFromRecipient->resolve($message);
            $tenantId = $erkannt['tenant_id'];
            $recipient = $erkannt['recipient'];
        }

        $request = ProfileRequest::create([
            'tenant_id' => $tenantId,
            'type' => RequestType::OTHER,
            'source' => RequestSource::EMAIL,
            'requester_email' => MailHeaders::firstAddress($message, 'from') ?? '',
            'requester_name' => MailHeaders::senderName($message),
            'message' => $this->plainText($message),
        ]);

        $this->storeRawMail($request, $message);
        $this->storeAttachments($request, $message);

        if ($recipient !== null) {
            $request->recordEvent(RequestEventType::NOTE, [
                'note' => 'Eingang per E-Mail',
                'recipient' => $recipient,
                'subject' => (string) $message->getSubject(),
                'portal_erkannt' => $tenantId !== null,
            ]);
        }

        return $request;
    }

    /**
     * Antwort in einem laufenden Vorgang: kein neuer Vorgang, sondern ein
     * Ereignis mit Text und Anhaengen an der bestehenden Anfrage.
     */
    public function appendReply(ProfileRequest $request, Message $message): void
    {
        $text = $this->plainText($message);

        $this->storeAttachments($request, $message);

        $request->recordEvent(
            RequestEventType::REQUESTER_REPLIED,
            [
                'from' => MailHeaders::firstAddress($message, 'from'),
                'subject' => (string) $message->getSubject(),
                'message' => $text === null ? null : Str::limit($text, 4000),
            ],
            RequestEvent::ACTOR_REQUESTER,
        );
    }

    /**
     * Text der Mail. Nur-HTML-Mails werden zu lesbarem Text gewandelt —
     * der Inhalt geht in die Inbox und in den KI-Aufruf (#6), beide
     * brauchen Text, kein Markup.
     */
    private function plainText(Message $message): ?string
    {
        $text = trim($message->hasTextBody() ? $message->getTextBody() : '');

        if ($text === '' && $message->hasHTMLBody()) {
            $text = trim($this->htmlToText($message->getHTMLBody()));
        }

        if ($text === '') {
            return null;
        }

        // Die Spalte ist ein TEXT; eine Mail mit angehaengtem Verlauf kann
        // darueber hinausgehen. Gekuerzt wird nur die Anzeige — die
        // Rohmail liegt vollstaendig auf der Platte.
        return Str::limit($this->normalizeWhitespace($text), 60000, ' […]');
    }

    private function htmlToText(string $html): string
    {
        try {
            $converter = new HtmlConverter([
                'strip_tags' => true,
                'remove_nodes' => 'script style head title',
                'hard_break' => true,
                'suppress_errors' => true,
            ]);

            return $converter->convert($html);
        } catch (Throwable) {
            // Lieber ein roher, entschaerfter Text als gar keiner.
            return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = (string) preg_replace("/\r\n?/", "\n", $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    private function storeRawMail(ProfileRequest $request, Message $message): void
    {
        $roh = ($message->getHeader()?->raw ?? '')."\r\n\r\n".$message->getRawBody();
        $pfad = RequestAttachment::directoryFor((int) $request->id).'/original.eml';

        Storage::disk(RequestAttachment::DISK)->put($pfad, $roh);

        $request->attachments()->create([
            'path' => $pfad,
            'mime' => 'message/rfc822',
            'size' => strlen($roh),
            'is_raw_mail' => true,
            'purge_after' => now()->addDays((int) config('profile-requests.retention_days', 90)),
        ]);

        $request->forceFill(['raw_email_path' => $pfad])->save();
    }

    private function storeAttachments(ProfileRequest $request, Message $message): void
    {
        $maxBytes = (int) config('profile-requests.max_attachment_mb', 10) * 1024 * 1024;
        /** @var list<string> $erlaubt */
        $erlaubt = (array) config('profile-requests.ingestion.allowed_attachment_mimes', []);

        $index = 0;

        foreach ($message->getAttachments() as $attachment) {
            /** @var Attachment $attachment */
            $index++;

            $name = $this->safeFilename((string) ($attachment->name ?? ''), $index, $attachment);
            $mime = strtolower((string) ($attachment->getMimeType() ?? ''));
            $inhalt = (string) $attachment->content;
            $size = strlen($inhalt);

            if ($size > $maxBytes) {
                $request->recordEvent(RequestEventType::ATTACHMENT_REJECTED, [
                    'filename' => $name,
                    'mime' => $mime,
                    'size' => $size,
                    'reason' => 'zu_gross',
                    'max_bytes' => $maxBytes,
                ]);

                continue;
            }

            if (! in_array($mime, $erlaubt, true)) {
                $request->recordEvent(RequestEventType::ATTACHMENT_REJECTED, [
                    'filename' => $name,
                    'mime' => $mime,
                    'size' => $size,
                    'reason' => 'typ_nicht_erlaubt',
                ]);

                continue;
            }

            $pfad = RequestAttachment::directoryFor((int) $request->id)."/{$index}-{$name}";

            Storage::disk(RequestAttachment::DISK)->put($pfad, $inhalt);

            $request->attachments()->create([
                'path' => $pfad,
                'mime' => $mime,
                'size' => $size,
                'is_raw_mail' => false,
                'purge_after' => now()->addDays((int) config('profile-requests.retention_days', 90)),
            ]);
        }
    }

    /**
     * Dateiname ohne Pfadanteile und Sonderzeichen — er landet auf der
     * Platte, und ein "../" im Namen einer fremden Mail darf dort nichts
     * ausrichten.
     */
    private function safeFilename(string $name, int $index, Attachment $attachment): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $endung = strtolower((string) ($attachment->getExtension() ?: 'bin'));

        $basis = Str::of(pathinfo($name, PATHINFO_FILENAME))
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-')
            ->limit(80, '')
            ->toString();

        if ($basis === '') {
            $basis = "anhang-{$index}";
        }

        $vorhandeneEndung = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        return $basis.'.'.($vorhandeneEndung !== '' ? preg_replace('/[^a-z0-9]/', '', $vorhandeneEndung) : $endung);
    }
}
