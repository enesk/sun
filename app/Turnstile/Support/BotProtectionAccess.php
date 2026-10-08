<?php

declare(strict_types=1);

namespace App\Turnstile\Support;

use App\Models\User;

/**
 * Wer darf die Turnstile-Seiten im Admin-Panel sehen (#9)?
 *
 * Eine Stelle fuer Einstellungsseite, Sicherheitspruefungen und
 * Kennzahlen-Widget: Betreiber des Netzes, also Administratoren mit dem Recht
 * "update settings" — dieselbe Huerde wie bei den uebrigen Einstellungsseiten
 * des Panels.
 *
 * Redaktionsrollen kommen damit nicht heran: die Ratgeber-Rollen owner/editor
 * (guide_role) tragen dieses Recht nicht und haben ueberdies keinen Zugang zum
 * Admin-Panel (App\Models\User::canAccessPanel()).
 */
final class BotProtectionAccess
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
