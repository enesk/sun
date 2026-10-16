<?php

declare(strict_types=1);

namespace App\Ads\Support;

use App\Models\User;

/**
 * Wer darf die Werbe-Seiten im Admin-Panel sehen und die Vorschau eines
 * Werbemittels aufrufen (Ticket #10)?
 *
 * Dieselbe Huerde wie beim Bot-Schutz ({@see \App\Turnstile\Support\BotProtectionAccess}):
 * Betreiber des Netzes, also Administratoren mit dem Recht "update settings".
 * Das ist hier keine Formsache — wer `ad_units` anlegen darf, traegt bei
 * `provider = html` fremdes Markup ein, das (abgeschottet) im Portal laeuft
 * (docs/ad-slots.md 5.2, Regel 5).
 */
final class AdsAdminAccess
{
    public const PERMISSION = 'update settings';

    public static function allowed(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->isAdmin()
            && $user->hasPermissionTo(self::PERMISSION);
    }
}
