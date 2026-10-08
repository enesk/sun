<?php

declare(strict_types=1);

namespace App\Turnstile\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Empfaenger der Bot-Schutz-Mails (#12): Alarme und Tagesbericht.
 *
 * Erste Quelle ist `config('turnstile.alerts.recipients')` — eine Komma-Liste
 * aus der .env, damit der Betrieb den Verteiler ohne Deployment aendern kann.
 *
 * Bleibt sie leer, gehen die Mails an alle nicht gesperrten Administratoren mit
 * dem Recht "update settings": dieselbe Huerde, die auch den Zugang zu den
 * Bot-Schutz-Seiten im Panel entscheidet ({@see BotProtectionAccess}). So
 * erreicht ein frisch aufgesetztes System die richtigen Personen, ohne dass
 * Adressen im Repository stehen.
 */
final class BotProtectionRecipients
{
    /**
     * @return list<string>
     */
    public static function emails(): array
    {
        $konfiguriert = self::fromConfig();

        if ($konfiguriert !== []) {
            return $konfiguriert;
        }

        return User::query()
            ->where('is_blocked', false)
            ->where('is_admin', true)
            ->whereNotNull('email')
            ->get()
            ->filter(static fn (User $user): bool => filled($user->email)
                && $user->hasPermissionTo(BotProtectionAccess::PERMISSION))
            ->map(static fn (User $user): string => (string) $user->email)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function fromConfig(): array
    {
        $roh = config('turnstile.alerts.recipients');

        $liste = is_array($roh)
            ? $roh
            : Str::of((string) $roh)->explode(',')->all();

        return collect($liste)
            ->map(static fn ($adresse): string => trim((string) $adresse))
            ->filter(static fn (string $adresse): bool => filter_var($adresse, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
