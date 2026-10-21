<?php

declare(strict_types=1);

namespace App\ProfileRequests\Enums;

/**
 * Ereignisarten im Protokoll einer Anfrage (SUN-RQ, Ticket #3).
 *
 * Die ersten sechs Werte sind die Statuswechsel des Automaten
 * (docs/profile-requests.md 8) — genau einer je Uebergang. Die restlichen
 * halten fest, was ohne Statuswechsel passiert ist (KI-Lauf, verworfener
 * Anhang, versendete Mail) und werden von #5, #6 und #10 geschrieben.
 */
enum RequestEventType: string
{
    case CLASSIFIED = 'classified';
    case VERIFICATION_REQUESTED = 'verification_requested';
    case VERIFIED = 'verified';
    case APPLIED = 'applied';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    case CREATED = 'created';
    case AI_SUGGESTED = 'ai_suggested';
    case ATTACHMENT_REJECTED = 'attachment_rejected';
    case REQUESTER_REPLIED = 'requester_replied';
    case DISCARDED = 'discarded';
    case DIFF_EDITED = 'diff_edited';
    case MAIL_SENT = 'mail_sent';
    case NOTE = 'note';

    public function label(): string
    {
        return match ($this) {
            self::CLASSIFIED => __('Eingeordnet'),
            self::VERIFICATION_REQUESTED => __('Bestätigungslink versendet'),
            self::VERIFIED => __('Bestätigt'),
            self::APPLIED => __('Übernommen'),
            self::REJECTED => __('Abgelehnt'),
            self::EXPIRED => __('Abgelaufen'),
            self::CANCELLED => __('Storniert'),
            self::CREATED => __('Eingegangen'),
            self::AI_SUGGESTED => __('KI-Vorschlag'),
            self::ATTACHMENT_REJECTED => __('Anhang verworfen'),
            self::REQUESTER_REPLIED => __('Antwort des Anfragenden'),
            self::DISCARDED => __('Mail verworfen'),
            self::DIFF_EDITED => __('Diff im Panel bearbeitet'),
            self::MAIL_SENT => __('E-Mail versendet'),
            self::NOTE => __('Notiz'),
        };
    }

    /**
     * Ereignis, das zu einem Zielzustand gehoert — die Zuordnung ist
     * eindeutig, damit ein Statuswechsel nicht zwei Namen bekommt.
     */
    public static function forStatus(RequestStatus $status): self
    {
        return match ($status) {
            RequestStatus::NEW => self::CREATED,
            RequestStatus::CLASSIFIED => self::CLASSIFIED,
            RequestStatus::AWAITING_VERIFICATION => self::VERIFICATION_REQUESTED,
            RequestStatus::VERIFIED => self::VERIFIED,
            RequestStatus::APPLIED => self::APPLIED,
            RequestStatus::REJECTED => self::REJECTED,
            RequestStatus::EXPIRED => self::EXPIRED,
            RequestStatus::CANCELLED => self::CANCELLED,
        };
    }
}
