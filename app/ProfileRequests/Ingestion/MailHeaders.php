<?php

declare(strict_types=1);

namespace App\ProfileRequests\Ingestion;

use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Message;

/**
 * Lesezugriff auf die Kopfzeilen einer Mail (SUN-RQ, Ticket #5).
 *
 * Eine Stelle, weil webklex je nach Kopfzeile mal {@see Address}-Objekte,
 * mal Strings und mal ein Array davon liefert — jeder Aufrufer, der das
 * selbst auseinandernimmt, macht es ein bisschen anders falsch.
 */
final class MailHeaders
{
    /** Message-ID ohne spitze Klammern, oder null. */
    public static function messageId(Message $message): ?string
    {
        $id = trim(self::text($message, 'message_id'));

        if ($id === '') {
            return null;
        }

        return trim($id, '<>');
    }

    /**
     * Rohwerte einer Kopfzeile als Strings — fuer alles, was keine Adresse
     * ist (Auto-Submitted, Precedence, List-Id, References).
     *
     * @return list<string>
     */
    public static function values(Message $message, string $header): array
    {
        $attribute = $message->getHeader()?->get($header);

        if ($attribute === null) {
            return [];
        }

        $werte = [];

        foreach ($attribute->all() as $wert) {
            $text = self::stringify($wert);

            if ($text !== '') {
                $werte[] = $text;
            }
        }

        return $werte;
    }

    /** Alle Werte einer Kopfzeile zu einem String — fuer Mustersuchen. */
    public static function text(Message $message, string $header): string
    {
        return implode(' ', self::values($message, $header));
    }

    /**
     * E-Mail-Adressen einer Kopfzeile, in Kleinschreibung und ohne Dubletten.
     *
     * @return list<string>
     */
    public static function addresses(Message $message, string $header): array
    {
        $attribute = $message->getHeader()?->get($header);

        if ($attribute === null) {
            return [];
        }

        $adressen = [];

        foreach ($attribute->all() as $wert) {
            if ($wert instanceof Address) {
                $adressen[] = strtolower(trim((string) $wert->mail));

                continue;
            }

            foreach (self::parseAddressList(self::stringify($wert)) as $adresse) {
                $adressen[] = $adresse;
            }
        }

        return array_values(array_unique(array_filter(
            $adressen,
            static fn (string $adresse): bool => str_contains($adresse, '@'),
        )));
    }

    /** Erste Adresse einer Kopfzeile oder null. */
    public static function firstAddress(Message $message, string $header): ?string
    {
        return self::addresses($message, $header)[0] ?? null;
    }

    /** Anzeigename des Absenders, soweit vorhanden. */
    public static function senderName(Message $message): ?string
    {
        $attribute = $message->getHeader()?->get('from');

        foreach ($attribute?->all() ?? [] as $wert) {
            if ($wert instanceof Address && filled($wert->personal)) {
                return trim((string) $wert->personal);
            }
        }

        return null;
    }

    /**
     * Message-IDs aus In-Reply-To und References, aelteste zuerst.
     *
     * @return list<string>
     */
    public static function referencedMessageIds(Message $message): array
    {
        $roh = self::text($message, 'in_reply_to').' '.self::text($message, 'references');

        preg_match_all('/<([^<>\s]+@[^<>\s]+)>/', $roh, $treffer);

        /** @var list<string> $ids */
        $ids = array_values(array_unique($treffer[1] ?? []));

        return $ids;
    }

    /**
     * Adressen aus einer Kopfzeile im Klartext ("Name <a@b.de>, c@d.de").
     *
     * @return list<string>
     */
    private static function parseAddressList(string $raw): array
    {
        preg_match_all('/[\w.+\-]+@[\w\-]+(?:\.[\w\-]+)+/', $raw, $treffer);

        return array_map('strtolower', $treffer[0] ?? []);
    }

    private static function stringify(mixed $wert): string
    {
        if ($wert instanceof Address) {
            return trim((string) $wert->mail);
        }

        if (is_array($wert)) {
            return trim(implode(' ', array_map(self::stringify(...), $wert)));
        }

        if (is_object($wert) && method_exists($wert, '__toString')) {
            return trim((string) $wert);
        }

        return is_scalar($wert) ? trim((string) $wert) : '';
    }
}
