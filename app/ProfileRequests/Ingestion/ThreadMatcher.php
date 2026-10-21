<?php

declare(strict_types=1);

namespace App\ProfileRequests\Ingestion;

use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestMailLog;
use Webklex\PHPIMAP\Message;

/**
 * Gehoert eine eingegangene Mail zu einem laufenden Vorgang? (SUN-RQ, #5)
 *
 * Drei Wege, in dieser Reihenfolge:
 *
 * 1. In-Reply-To/References gegen die Message-IDs unserer ausgehenden Mails
 *    (#10). Die setzen ihre ID als <req-{id}-{zufall}@domain>, siehe config
 *    `profile-requests.ingestion.outgoing_message_id_pattern`. Das ist der
 *    verlaesslichste Weg: an der Message-ID aendert kein Mailprogramm etwas.
 * 2. Dieselben Kopfzeilen gegen das Eingangsprotokoll — so haengt auch die
 *    zweite Mail eines Betriebs an derselben Anfrage, wenn er auf seine
 *    eigene antwortet.
 * 3. Die Betreffsmarke [REQ-{id}]. Faellt zuletzt, weil sie beim
 *    Weiterleiten verlorengehen oder von Hand erfunden werden kann.
 *
 * Gefunden wird eine Anfrage nur, wenn es sie wirklich gibt; eine erfundene
 * Nummer im Betreff fuehrt zu einer neuen Anfrage, nicht zu einem Fehler.
 */
class ThreadMatcher
{
    public function match(Message $message): ?ProfileRequest
    {
        $referenzen = MailHeaders::referencedMessageIds($message);

        foreach ($referenzen as $messageId) {
            $request = $this->fromOutgoingMessageId($messageId);

            if ($request !== null) {
                return $request;
            }
        }

        foreach ($referenzen as $messageId) {
            $requestId = RequestMailLog::requestIdForMessageId($messageId);

            if ($requestId !== null) {
                $request = ProfileRequest::find($requestId);

                if ($request !== null) {
                    return $request;
                }
            }
        }

        return $this->fromSubjectTag((string) $message->getSubject());
    }

    private function fromOutgoingMessageId(string $messageId): ?ProfileRequest
    {
        $muster = (string) config('profile-requests.ingestion.outgoing_message_id_pattern');

        if ($muster === '' || preg_match($muster, $messageId, $treffer) !== 1) {
            return null;
        }

        return ProfileRequest::find((int) $treffer[1]);
    }

    private function fromSubjectTag(string $subject): ?ProfileRequest
    {
        $muster = (string) config('profile-requests.ingestion.subject_tag_pattern');

        if ($muster === '' || preg_match($muster, $subject, $treffer) !== 1) {
            return null;
        }

        return ProfileRequest::find((int) $treffer[1]);
    }
}
