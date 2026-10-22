<?php

declare(strict_types=1);

namespace App\ProfileRequests\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Empfänger der internen Mails des Anfragen-Moduls (SUN-RQ).
 *
 * Drei Mails gehen nicht an Betriebe, sondern an uns: der Tagesdigest (#9),
 * der Alarm des Pollers (#5) und die Sofortmeldung über einen Löschwunsch
 * oder eine Übernahme (#23). Alle drei beantworten die Empfängerfrage
 * gleich — eine konfigurierte Liste, sonst alle nicht gesperrten
 * Administratoren —, und sie stand bisher zweimal im Code. Hier steht sie
 * einmal.
 *
 * Ein gesperrtes Konto bekommt nichts: wer sich nicht anmelden kann, kann
 * den Vorgang auch nicht bearbeiten.
 */
class InternalRecipients
{
    /**
     * @param  string|null  $configured  Komma-Liste aus der Konfiguration;
     *                                   leer heißt „alle Administratoren".
     * @return list<string>
     */
    public static function from(?string $configured): array
    {
        $liste = collect(Str::of((string) $configured)->explode(','))
            ->map(static fn ($adresse): string => trim((string) $adresse))
            ->filter(static fn (string $adresse): bool => $adresse !== '')
            ->values()
            ->all();

        if ($liste !== []) {
            return $liste;
        }

        return User::query()
            ->where('is_admin', true)
            ->where('is_blocked', false)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
