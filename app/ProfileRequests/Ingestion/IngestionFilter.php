<?php

declare(strict_types=1);

namespace App\ProfileRequests\Ingestion;

use Illuminate\Support\Str;
use Webklex\PHPIMAP\Message;

/**
 * Was gar nicht erst zur Anfrage wird (SUN-RQ, Ticket #5).
 *
 * Automatische Antworten, Newsletter und bekannte Absender ohne Menschen
 * dahinter. Die Mail bleibt im Postfach nicht liegen — sie wandert wie jede
 * andere nach 'Verarbeitet' und bekommt eine Zeile im Eingangsprotokoll mit
 * `outcome = discarded` und dem Grund.
 *
 * Streng nur bei Eindeutigem: eine Abwesenheitsnotiz traegt nach RFC 3834
 * Auto-Submitted, ein Newsletter traegt List-Id. Wer nur "Re:" schreibt und
 * noreply im Absender hat, wird verworfen, weil dorthin keine Rueckfrage
 * moeglich ist — der Vorgang waere ohnehin tot.
 */
class IngestionFilter
{
    /** Grund fuer das Verwerfen, oder null wenn die Mail durchgeht. */
    public function discardReason(Message $message): ?string
    {
        if ($this->isAutoReply($message)) {
            return 'auto_reply';
        }

        if ($this->isNewsletter($message)) {
            return 'newsletter';
        }

        $absender = MailHeaders::firstAddress($message, 'from');

        if ($absender === null) {
            return 'no_sender';
        }

        if ($this->isBlockedSender($absender)) {
            return 'blocked_sender';
        }

        if ($this->isNoReplyAddress($absender)) {
            return 'no_reply_sender';
        }

        return null;
    }

    private function isAutoReply(Message $message): bool
    {
        foreach ((array) config('profile-requests.ingestion.auto_reply_headers', []) as $header) {
            $wert = strtolower(MailHeaders::text($message, (string) $header));

            if ($wert === '') {
                continue;
            }

            // RFC 3834: "no" ist die Kennzeichnung einer normalen Mail.
            if ((string) $header === 'auto-submitted' && $wert === 'no') {
                continue;
            }

            return true;
        }

        $precedence = strtolower(MailHeaders::text($message, 'precedence'));

        if ($precedence !== '' && in_array($precedence, (array) config('profile-requests.ingestion.bulk_precedences', []), true)) {
            return true;
        }

        // Leerer Return-Path: Zustellbericht oder Bounce.
        $returnPath = trim(MailHeaders::text($message, 'return_path'));

        return $returnPath === '<>' || $returnPath === '';
    }

    private function isNewsletter(Message $message): bool
    {
        foreach ((array) config('profile-requests.ingestion.newsletter_headers', []) as $header) {
            if (MailHeaders::text($message, (string) $header) !== '') {
                return true;
            }
        }

        return false;
    }

    private function isBlockedSender(string $absender): bool
    {
        foreach ($this->blockList() as $eintrag) {
            if ($eintrag === '') {
                continue;
            }

            if (str_starts_with($eintrag, '@') && str_ends_with($absender, $eintrag)) {
                return true;
            }

            if (str_ends_with($eintrag, '@') && str_starts_with($absender, $eintrag)) {
                return true;
            }

            if ($absender === $eintrag) {
                return true;
            }
        }

        return false;
    }

    private function isNoReplyAddress(string $absender): bool
    {
        $lokal = Str::before($absender, '@');
        $lokal = (string) preg_replace('/[^a-z]/', '', strtolower($lokal));

        foreach ((array) config('profile-requests.ingestion.blocked_local_parts', []) as $teil) {
            $teil = (string) preg_replace('/[^a-z]/', '', strtolower((string) $teil));

            if ($teil !== '' && $lokal === $teil) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function blockList(): array
    {
        $liste = (array) config('profile-requests.ingestion.blocked_senders', []);

        $ausEnv = (string) config('profile-requests.ingestion.blocked_senders_env', '');

        if ($ausEnv !== '') {
            $liste = array_merge($liste, explode(',', $ausEnv));
        }

        return array_values(array_filter(array_map(
            static fn ($eintrag): string => strtolower(trim((string) $eintrag)),
            $liste,
        )));
    }
}
