<?php

declare(strict_types=1);

namespace App\ProfileRequests\Ingestion;

/**
 * Was der Poller (#5) mit einer Mail gemacht hat — der `outcome` einer Zeile
 * in `request_mail_log`.
 */
enum MailOutcome: string
{
    /**
     * Zeile ist reserviert, die Verarbeitung laeuft. Wird vor allem anderen
     * geschrieben, damit der Unique-Index auf der Message-ID greift, bevor
     * eine Anfrage entsteht — bleibt dieser Wert stehen, ist ein Lauf
     * mittendrin abgebrochen, und die Mail wird trotzdem nicht ein zweites
     * Mal verarbeitet.
     */
    case PROCESSING = 'processing';

    /** Neue Anfrage angelegt. */
    case CREATED = 'created';

    /** Antwort in einem bestehenden Thread, als Ereignis angehaengt. */
    case REPLIED = 'replied';

    /** Auto-Antwort, Newsletter oder gesperrter Absender — nichts angelegt. */
    case DISCARDED = 'discarded';
}
