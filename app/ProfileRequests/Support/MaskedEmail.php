<?php

declare(strict_types=1);

namespace App\ProfileRequests\Support;

use Illuminate\Support\Str;

/**
 * Maskierte Anzeige einer E-Mail-Adresse (SUN-RQ #4).
 *
 * Der Dialog muss zeigen, **wohin** die Bestaetigungsmail geht — sonst
 * wartet der Betrieb auf eine Mail, die in einem Postfach liegt, das er nicht
 * im Blick hat. Die ganze Adresse darf dabei nicht erscheinen: die
 * Profilseite ist oeffentlich und waere sonst ein Adressverzeichnis fuer
 * Sammler.
 *
 * Gezeigt werden die ersten zwei Zeichen des lokalen Teils und die Domain
 *
 * vollstaendig (`ma***@firma.de`) — die Domain steht ohnehin als Website am
 * Profil, und erst sie macht die Angabe fuer den Empfaenger brauchbar.
 */
class MaskedEmail
{
    public static function mask(?string $email): string
    {
        $email = trim((string) $email);

        if ($email === '' || ! Str::contains($email, '@')) {
            return '';
        }

        [$local, $domain] = explode('@', $email, 2);

        $visible = Str::substr($local, 0, min(2, max(1, Str::length($local) - 1)));

        return $visible.'***@'.$domain;
    }
}
